<?php

declare(strict_types=1);

namespace MauticPlugin\MauticAspectFileBundle\Service;

use Doctrine\ORM\EntityManagerInterface;
use Mautic\LeadBundle\Entity\Lead;
use MauticPlugin\MauticAspectFileBundle\Entity\FastPathLog;
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
    private EntityManagerInterface $entityManager;

    public function __construct(
        LoggerInterface $logger,
        FieldMapper $fieldMapper,
        FileGenerator $fileGenerator,
        EntityManagerInterface $entityManager
    ) {
        $this->logger = $logger;
        $this->fieldMapper = $fieldMapper;
        $this->fileGenerator = $fileGenerator;
        $this->entityManager = $entityManager;
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
     *   - campaign_id: Campaign ID (optional)
     *   - event_id: Event ID (optional)
     *
     * @return array{success: bool, error?: string, response?: mixed, log_id?: int}
     */
    public function send(Lead $lead, Schema $schema, array $config): array
    {
        $wsdlUrl = $config['wsdl_url'] ?? 'http://bpctaasp1alme.bp.local:8000/FastPathService?wsdl';
        $fastList = $config['fast_list'] ?? '';
        $functionType = (int) ($config['function_type'] ?? 1);
        $timeout = (int) ($config['timeout'] ?? 30);

        $leadId = $lead->getId();
        $startTime = microtime(true);

        // Initialize log entry
        $logEntry = new FastPathLog();
        $logEntry->setLead($lead);
        $logEntry->setSchema($schema);
        $logEntry->setWsdlUrl($wsdlUrl);
        $logEntry->setFastList($fastList);
        $logEntry->setFunctionType($functionType);
        $logEntry->setCampaignId($config['campaign_id'] ?? null);
        $logEntry->setEventId($config['event_id'] ?? null);

        // Store custom fields
        $customFields = [
            'custom_field_1' => $config['custom_field_1'] ?? null,
            'custom_field_2' => $config['custom_field_2'] ?? null,
            'custom_field_3' => $config['custom_field_3'] ?? null,
            'response_uri' => $config['response_uri'] ?? null,
        ];
        $logEntry->setCustomFieldsFromArray(array_filter($customFields));

        $this->logger->info('FastPath: Preparing to send lead', [
            'lead_id' => $leadId,
            'schema_id' => $schema->getId(),
            'schema_name' => $schema->getName(),
            'wsdl_url' => $wsdlUrl,
            'fast_list' => $fastList,
            'function_type' => $functionType,
        ]);

        $recordLine = null;
        $messageId = null;
        $client = null;

        try {
            // Map lead data to schema
            $leadData = $this->fieldMapper->mapLeadToSchema($lead, $schema);

            // Generate fixed-width formatted line
            $recordLine = $this->fileGenerator->generateLine($schema, $leadData);
            $logEntry->setRecordLine($recordLine);

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
            $messageId = $this->generateMessageId($lead);
            $logEntry->setMessageId($messageId);

            $feedRecordMsg = [
                'MessageId' => $messageId,
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

            // Calculate duration
            $durationMs = (int) ((microtime(true) - $startTime) * 1000);
            $logEntry->setDurationMs($durationMs);

            // Store SOAP request/response
            $logEntry->setRequestPayload($client->__getLastRequest());
            $logEntry->setResponsePayload($client->__getLastResponse());
            $logEntry->setStatus(FastPathLog::STATUS_SUCCESS);

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
                'message_id' => $messageId,
                'duration_ms' => $durationMs,
            ]);

            // Persist log entry
            $this->saveLogEntry($logEntry);

            return [
                'success' => true,
                'response' => $response,
                'message_id' => $messageId,
                'log_id' => $logEntry->getId(),
            ];
        } catch (SoapFault $e) {
            $durationMs = (int) ((microtime(true) - $startTime) * 1000);

            $errorMessage = sprintf(
                'SOAP Fault: [%s] %s',
                $e->faultcode ?? 'Unknown',
                $e->faultstring ?? $e->getMessage()
            );

            // Update log entry with error info
            $logEntry->setStatus(FastPathLog::STATUS_FAILED);
            $logEntry->setErrorMessage($errorMessage);
            $logEntry->setFaultCode($e->faultcode ?? null);
            $logEntry->setDurationMs($durationMs);
            $logEntry->setMessageId($messageId ?? $this->generateMessageId($lead));

            // Try to get SOAP request/response if available
            if ($client) {
                try {
                    $logEntry->setRequestPayload($client->__getLastRequest());
                    $logEntry->setResponsePayload($client->__getLastResponse());
                } catch (\Exception $ex) {
                    // Ignore if not available
                }
            }

            $this->logger->error('FastPath: SOAP error', [
                'lead_id' => $leadId,
                'error' => $errorMessage,
                'fault_code' => $e->faultcode ?? null,
                'fault_string' => $e->faultstring ?? null,
                'duration_ms' => $durationMs,
            ]);

            // Persist log entry
            $this->saveLogEntry($logEntry);

            return [
                'success' => false,
                'error' => $errorMessage,
                'log_id' => $logEntry->getId(),
            ];
        } catch (\Exception $e) {
            $durationMs = (int) ((microtime(true) - $startTime) * 1000);

            // Update log entry with error info
            $logEntry->setStatus(FastPathLog::STATUS_FAILED);
            $logEntry->setErrorMessage($e->getMessage());
            $logEntry->setDurationMs($durationMs);
            $logEntry->setMessageId($messageId ?? $this->generateMessageId($lead));

            // Try to get SOAP request/response if available
            if ($client) {
                try {
                    $logEntry->setRequestPayload($client->__getLastRequest());
                    $logEntry->setResponsePayload($client->__getLastResponse());
                } catch (\Exception $ex) {
                    // Ignore if not available
                }
            }

            $this->logger->error('FastPath: Unexpected error', [
                'lead_id' => $leadId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'duration_ms' => $durationMs,
            ]);

            // Persist log entry
            $this->saveLogEntry($logEntry);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'log_id' => $logEntry->getId(),
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

    /**
     * Save log entry to database
     */
    private function saveLogEntry(FastPathLog $logEntry): void
    {
        try {
            $this->entityManager->persist($logEntry);
            $this->entityManager->flush();

            $this->logger->debug('FastPath: Log entry saved', [
                'log_id' => $logEntry->getId(),
                'status' => $logEntry->getStatus(),
            ]);
        } catch (\Exception $e) {
            $this->logger->error('FastPath: Failed to save log entry', [
                'error' => $e->getMessage(),
            ]);
        }
    }
}
