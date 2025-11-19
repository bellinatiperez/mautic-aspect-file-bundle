<?php

declare(strict_types=1);

namespace MauticPlugin\MauticAspectFileBundle\EventListener;

use Doctrine\ORM\EntityManagerInterface;
use Mautic\CampaignBundle\CampaignEvents;
use Mautic\CampaignBundle\Event\CampaignBuilderEvent;
use Mautic\CampaignBundle\Event\PendingEvent;
use MauticPlugin\MauticAspectFileBundle\Entity\Schema;
use MauticPlugin\MauticAspectFileBundle\Form\Type\AspectFileActionType;
use MauticPlugin\MauticAspectFileBundle\Form\Type\FastPathActionType;
use MauticPlugin\MauticAspectFileBundle\Model\AspectFileModel;
use MauticPlugin\MauticAspectFileBundle\Service\FastPathSender;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Campaign Event Subscriber for AspectFile actions
 */
class CampaignSubscriber implements EventSubscriberInterface
{
    private AspectFileModel $aspectFileModel;
    private LoggerInterface $logger;
    private EntityManagerInterface $entityManager;
    private FastPathSender $fastPathSender;

    public function __construct(
        AspectFileModel $aspectFileModel,
        LoggerInterface $logger,
        EntityManagerInterface $entityManager,
        FastPathSender $fastPathSender
    ) {
        $this->aspectFileModel = $aspectFileModel;
        $this->logger = $logger;
        $this->entityManager = $entityManager;
        $this->fastPathSender = $fastPathSender;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            CampaignEvents::CAMPAIGN_ON_BUILD => ['onCampaignBuild', 0],
            'mautic.aspectfile.on_campaign_trigger_action' => ['onCampaignTriggerAction', 0],
            'mautic.fastpath.on_campaign_trigger_action' => ['onFastPathTriggerAction', 0],
        ];
    }

    /**
     * Register AspectFile actions to campaigns
     */
    public function onCampaignBuild(CampaignBuilderEvent $event): void
    {
        // Register Generate Aspect File action (batch processing)
        $event->addAction(
            'aspectfile.generate',
            [
                'label' => 'Generate Aspect File',
                'description' => 'Queue lead for fixed-width file generation',
                'batchEventName' => 'mautic.aspectfile.on_campaign_trigger_action',
                'formType' => AspectFileActionType::class,
                'channel' => 'aspectfile',
                'channelIdField' => 'schema_id',
            ]
        );

        // Register Send to FastPath action (individual processing)
        $event->addAction(
            'fastpath.send_individual',
            [
                'label' => 'Send to FastPath (Individual)',
                'description' => 'Send lead data individually to FastPath SOAP service',
                'eventName' => 'mautic.fastpath.on_campaign_trigger_action',
                'formType' => FastPathActionType::class,
                'channel' => 'fastpath',
                'channelIdField' => 'schema_id',
            ]
        );
    }

    /**
     * Execute AspectFile action when triggered in campaign (batch processing)
     */
    public function onCampaignTriggerAction(PendingEvent $event): void
    {
        $config = $event->getEvent()->getProperties();

        $this->logger->info('AspectFile: Campaign action triggered', [
            'campaign_id' => $event->getEvent()->getCampaign()->getId(),
            'campaign_name' => $event->getEvent()->getCampaign()->getName(),
        ]);

        // Get configuration
        $schemaId = (int) ($config['schema_id'] ?? 0);
        $bucketName = $config['bucket_name'] ?? '';
        $fileNameTemplate = $config['file_name_template'] ?? '';
        $destinationType = $config['destination_type'] ?? 'S3';
        $networkPath = $config['network_path'] ?? null;

        // Validate required fields based on destination type
        if (!$schemaId) {
            $this->logger->error('AspectFile: Missing schema_id', ['schema_id' => $schemaId]);
            $event->failAll('Missing schema_id configuration');
            return;
        }

        if ($destinationType === 'S3' && !$bucketName) {
            $this->logger->error('AspectFile: Missing bucket_name for S3 destination', [
                'destination_type' => $destinationType,
            ]);
            $event->failAll('Missing bucket_name for S3 destination');
            return;
        }

        if ($destinationType === 'NETWORK' && !$networkPath) {
            $this->logger->error('AspectFile: Missing network_path for NETWORK destination', [
                'destination_type' => $destinationType,
            ]);
            $event->failAll('Missing network_path for NETWORK destination');
            return;
        }

        // Get schema entity from database
        $schema = $this->entityManager->getRepository(Schema::class)->find($schemaId);

        if (!$schema) {
            $this->logger->error('AspectFile: Schema not found', [
                'schema_id' => $schemaId,
            ]);

            // Use passWithError instead of fail to prevent infinite retries for config errors
            // Schema not found is a configuration error that won't resolve by retrying
            $event->passAllWithError("Schema not found: {$schemaId}");

            return;
        }

        // Process each log entry (each contact)
        $logs = $event->getPending();
        $this->logger->info('AspectFile: Processing logs', ['count' => $logs->count()]);

        foreach ($logs as $log) {
            $lead = $log->getLead();

            $this->logger->info('AspectFile: Processing lead', [
                'lead_id' => $lead->getId(),
                'log_id' => $log->getId(),
            ]);

            // Queue lead for batch processing
            $result = $this->aspectFileModel->queueLead(
                $lead,
                $event->getEvent()->getCampaign()->getId(),
                $event->getEvent()->getId(),
                $schema,
                $bucketName,
                $fileNameTemplate,
                $destinationType,
                $networkPath
            );

            if ($result['success']) {
                $this->logger->info('AspectFile: Lead queued successfully', [
                    'lead_id' => $lead->getId(),
                    'batch_id' => $result['batch_id'] ?? null,
                ]);

                $log->setIsScheduled(false);
                $log->setDateTriggered(new \DateTime());
                $event->pass($log);
            } else {
                $this->logger->error('AspectFile: Failed to queue lead', [
                    'lead_id' => $lead->getId(),
                    'error' => $result['error'] ?? 'Unknown error',
                ]);

                $event->fail($log, $result['error'] ?? 'Failed to queue lead');
            }
        }
    }

    /**
     * Execute FastPath action when triggered in campaign (individual processing)
     */
    public function onFastPathTriggerAction(PendingEvent $event): void
    {
        $config = $event->getEvent()->getProperties();

        $this->logger->info('FastPath: Campaign action triggered', [
            'campaign_id' => $event->getEvent()->getCampaign()->getId(),
            'campaign_name' => $event->getEvent()->getCampaign()->getName(),
        ]);

        // Get configuration
        $schemaId = (int) ($config['schema_id'] ?? 0);
        $wsdlUrl = $config['wsdl_url'] ?? 'http://bpctaasp1alme.bp.local:8000/FastPathService?wsdl';
        $fastList = $config['fast_list'] ?? '';
        $functionType = (int) ($config['function_type'] ?? 1);

        // Validate required fields
        if (!$schemaId) {
            $this->logger->error('FastPath: Missing schema_id', ['schema_id' => $schemaId]);
            $event->failAll('Missing schema_id configuration');
            return;
        }

        if (!$fastList) {
            $this->logger->error('FastPath: Missing fast_list', ['fast_list' => $fastList]);
            $event->failAll('Missing fast_list configuration');
            return;
        }

        // Get schema entity from database
        $schema = $this->entityManager->getRepository(Schema::class)->find($schemaId);

        if (!$schema) {
            $this->logger->error('FastPath: Schema not found', [
                'schema_id' => $schemaId,
            ]);

            $event->passAllWithError("Schema not found: {$schemaId}");
            return;
        }

        // Process each log entry (each contact) individually
        $logs = $event->getPending();
        $this->logger->info('FastPath: Processing logs individually', ['count' => $logs->count()]);

        foreach ($logs as $log) {
            $lead = $log->getLead();

            $this->logger->info('FastPath: Processing lead', [
                'lead_id' => $lead->getId(),
                'log_id' => $log->getId(),
            ]);

            // Send lead data to FastPath SOAP service
            $result = $this->fastPathSender->send($lead, $schema, $config);

            if ($result['success']) {
                $this->logger->info('FastPath: Lead sent successfully', [
                    'lead_id' => $lead->getId(),
                    'message_id' => $result['message_id'] ?? null,
                ]);

                $log->setIsScheduled(false);
                $log->setDateTriggered(new \DateTime());
                $event->pass($log);
            } else {
                $this->logger->error('FastPath: Failed to send lead', [
                    'lead_id' => $lead->getId(),
                    'error' => $result['error'] ?? 'Unknown error',
                ]);

                $event->fail($log, $result['error'] ?? 'Failed to send lead to FastPath');
            }
        }
    }
}
