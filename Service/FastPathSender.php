<?php

declare(strict_types=1);

namespace MauticPlugin\MauticAspectFileBundle\Service;

use Mautic\LeadBundle\Entity\Lead;
use MauticPlugin\MauticAspectFileBundle\Entity\Schema;
use Psr\Log\LoggerInterface;
use SoapClient;
use SoapFault;

/**
 * Service to send individual lead data to FastPath SOAP service
 */
class FastPathSender
{
    private LoggerInterface $logger;
    private FieldMapper $fieldMapper;
    private FileGenerator $fileGenerator;

    public function __construct(
        LoggerInterface $logger,
        FieldMapper $fieldMapper,
        FileGenerator $fileGenerator
    ) {
        $this->logger = $logger;
        $this->fieldMapper = $fieldMapper;
        $this->fileGenerator = $fileGenerator;
    }

    /**
     * Send lead data to FastPath SOAP service
     *
     * @param Lead $lead
     * @param Schema $schema
     * @param array $config Configuration from campaign action
     *   - wsdl_url: WSDL endpoint URL
     *   - fast_list: FastList name
     *   - function_type: Function type (int)
     *   - timeout: Connection timeout in seconds
     *   - custom_field_1: Custom field 1 value (optional)
     *   - custom_field_2: Custom field 2 value (optional)
     *   - custom_field_3: Custom field 3 value (optional)
     *   - response_uri: Response URI (optional)
     *
     * @return array{success: bool, error?: string, response?: mixed}
     */
    public function send(Lead $lead, Schema $schema, array $config): array
    {
        $wsdlUrl = $config['wsdl_url'] ?? 'http://bpctaasp1alme.bp.local:8000/FastPathService?wsdl';
        $fastList = $config['fast_list'] ?? '';
        $functionType = (int) ($config['function_type'] ?? 1);
        $timeout = (int) ($config['timeout'] ?? 30);

        $leadId = $lead->getId();

        $this->logger->info('FastPath: Preparing to send lead', [
            'lead_id' => $leadId,
            'schema_id' => $schema->getId(),
            'schema_name' => $schema->getName(),
            'wsdl_url' => $wsdlUrl,
            'fast_list' => $fastList,
            'function_type' => $functionType,
        ]);

        try {
            // Map lead data to schema
            $leadData = $this->fieldMapper->mapLeadToSchema($lead, $schema);

            // Generate fixed-width formatted line
            $recordLine = $this->fileGenerator->generateLine($schema, $leadData);

            $this->logger->info('FastPath: Generated record line', [
                'lead_id' => $leadId,
                'record_length' => strlen($recordLine),
                'record_preview' => substr($recordLine, 0, 100),
            ]);

            // Create SOAP client
            $soapOptions = [
                'trace' => 1,
                'exceptions' => true,
                'connection_timeout' => $timeout,
                'cache_wsdl' => \WSDL_CACHE_NONE, // Disable cache for development
                'soap_version' => \SOAP_1_1,
            ];

            $client = new SoapClient($wsdlUrl, $soapOptions);

            // Build FeedRecordMsg structure
            $feedRecordMsg = [
                'MessageId' => $this->generateMessageId($lead),
                'FunctionType' => $functionType,
                'FastList' => $fastList,
                'Record' => $recordLine,
                'ResponseURI' => $config['response_uri'] ?? null,
                'CustomField1' => $config['custom_field_1'] ?? null,
                'CustomField2' => $config['custom_field_2'] ?? null,
                'CustomField3' => $config['custom_field_3'] ?? null,
            ];

            $this->logger->info('FastPath: Sending SOAP request', [
                'lead_id' => $leadId,
                'message_id' => $feedRecordMsg['MessageId'],
                'function_type' => $feedRecordMsg['FunctionType'],
                'fast_list' => $feedRecordMsg['FastList'],
            ]);

            // Call SOAP method
            $response = $client->FeedRecord(['feedrecord' => $feedRecordMsg]);

            // Log SOAP request/response for debugging
            $this->logger->debug('FastPath: SOAP Request', [
                'lead_id' => $leadId,
                'request' => $client->__getLastRequest(),
            ]);

            $this->logger->debug('FastPath: SOAP Response', [
                'lead_id' => $leadId,
                'response' => $client->__getLastResponse(),
            ]);

            $this->logger->info('FastPath: Successfully sent lead', [
                'lead_id' => $leadId,
                'message_id' => $feedRecordMsg['MessageId'],
            ]);

            return [
                'success' => true,
                'response' => $response,
                'message_id' => $feedRecordMsg['MessageId'],
            ];
        } catch (SoapFault $e) {
            $errorMessage = sprintf(
                'SOAP Fault: [%s] %s',
                $e->faultcode ?? 'Unknown',
                $e->faultstring ?? $e->getMessage()
            );

            $this->logger->error('FastPath: SOAP error', [
                'lead_id' => $leadId,
                'error' => $errorMessage,
                'fault_code' => $e->faultcode ?? null,
                'fault_string' => $e->faultstring ?? null,
            ]);

            return [
                'success' => false,
                'error' => $errorMessage,
            ];
        } catch (\Exception $e) {
            $this->logger->error('FastPath: Unexpected error', [
                'lead_id' => $leadId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Generate unique message ID for tracking
     */
    private function generateMessageId(Lead $lead): string
    {
        return sprintf(
            'MAUTIC_%d_%s',
            $lead->getId(),
            date('YmdHis')
        );
    }
}
