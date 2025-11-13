<?php

declare(strict_types=1);

namespace MauticPlugin\MauticAspectFileBundle\EventListener;

use Doctrine\ORM\EntityManagerInterface;
use Mautic\CampaignBundle\CampaignEvents;
use Mautic\CampaignBundle\Event\CampaignBuilderEvent;
use Mautic\CampaignBundle\Event\PendingEvent;
use MauticPlugin\MauticAspectFileBundle\Entity\Schema;
use MauticPlugin\MauticAspectFileBundle\Form\Type\AspectFileActionType;
use MauticPlugin\MauticAspectFileBundle\Model\AspectFileModel;
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

    public function __construct(
        AspectFileModel $aspectFileModel,
        LoggerInterface $logger,
        EntityManagerInterface $entityManager
    ) {
        $this->aspectFileModel = $aspectFileModel;
        $this->logger = $logger;
        $this->entityManager = $entityManager;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            CampaignEvents::CAMPAIGN_ON_BUILD => ['onCampaignBuild', 0],
            'mautic.aspectfile.on_campaign_trigger_action' => ['onCampaignTriggerAction', 0],
        ];
    }

    /**
     * Register AspectFile actions to campaigns
     */
    public function onCampaignBuild(CampaignBuilderEvent $event): void
    {
        // Register Generate Aspect File action
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

        if (!$schemaId || !$bucketName) {
            $this->logger->error('AspectFile: Missing required configuration', [
                'schema_id' => $schemaId,
                'bucket_name' => $bucketName,
            ]);

            $event->failAll('Missing schema_id or bucket_name configuration');

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
                $fileNameTemplate
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
}
