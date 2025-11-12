<?php

declare(strict_types=1);

namespace MauticPlugin\MauticAspectFileBundle\Model;

use Doctrine\ORM\EntityManagerInterface;
use Mautic\LeadBundle\Entity\Lead;
use MauticPlugin\MauticAspectFileBundle\Entity\AspectFileBatch;
use MauticPlugin\MauticAspectFileBundle\Entity\AspectFileBatchLead;
use MauticPlugin\MauticAspectFileBundle\Entity\Schema;
use MauticPlugin\MauticAspectFileBundle\Service\BatchManager;
use MauticPlugin\MauticAspectFileBundle\Service\FieldMapper;
use Psr\Log\LoggerInterface;

/**
 * Model for AspectFile business logic
 */
class AspectFileModel
{
    private BatchManager $batchManager;
    private FieldMapper $fieldMapper;
    private EntityManagerInterface $em;
    private LoggerInterface $logger;

    public function __construct(
        BatchManager $batchManager,
        FieldMapper $fieldMapper,
        EntityManagerInterface $em,
        LoggerInterface $logger
    ) {
        $this->batchManager = $batchManager;
        $this->fieldMapper = $fieldMapper;
        $this->em = $em;
        $this->logger = $logger;
    }

    /**
     * Find or create a batch for the given campaign/event/schema combination
     */
    private function findOrCreateBatch(int $campaignId, int $eventId, Schema $schema, string $bucketName, string $fileNameTemplate = ''): AspectFileBatch
    {
        $batch = $this->em->createQueryBuilder()
            ->select('b')
            ->from(AspectFileBatch::class, 'b')
            ->where('b.campaignId = :campaignId')
            ->andWhere('b.eventId = :eventId')
            ->andWhere('b.schema = :schema')
            ->andWhere('b.bucketName = :bucketName')
            ->andWhere('b.status = :status')
            ->setParameter('campaignId', $campaignId)
            ->setParameter('eventId', $eventId)
            ->setParameter('schema', $schema)
            ->setParameter('bucketName', $bucketName)
            ->setParameter('status', AspectFileBatch::STATUS_PENDING)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        if (!$batch) {
            $batch = new AspectFileBatch();
            $batch->setCampaignId($campaignId);
            $batch->setEventId($eventId);
            $batch->setSchema($schema);
            $batch->setBucketName($bucketName);
            $batch->setFileNameTemplate($fileNameTemplate ?: null);
            $batch->setStatus(AspectFileBatch::STATUS_PENDING);
        }

        return $batch;
    }

    /**
     * Queue a lead for processing
     *
     * @param Lead $lead
     * @param int $campaignId
     * @param int $eventId
     * @param Schema $schema
     * @param string $bucketName
     * @param string $fileNameTemplate
     * @return array{success: bool, batch_id?: int, error?: string}
     */
    public function queueLead(Lead $lead, int $campaignId, int $eventId, Schema $schema, string $bucketName, string $fileNameTemplate = ''): array
    {
        try {
            // Find or create batch
            $batch = $this->findOrCreateBatch($campaignId, $eventId, $schema, $bucketName, $fileNameTemplate);

            if (!$batch->getId()) {
                $this->em->persist($batch);
                $this->em->flush();
            }

            // Add lead to batch
            $batchLead = new AspectFileBatchLead();
            $batchLead->setBatch($batch);
            $batchLead->setLead($lead);
            $batchLead->setStatus(AspectFileBatchLead::STATUS_PENDING);

            $this->em->persist($batchLead);
            $batch->incrementLeadsCount();
            $this->em->flush();

            $this->logger->info('AspectFile: Lead queued', [
                'batch_id' => $batch->getId(),
                'lead_id' => $lead->getId(),
                'leads_count' => $batch->getLeadsCount(),
            ]);

            return [
                'success' => true,
                'batch_id' => $batch->getId(),
            ];
        } catch (\Exception $e) {
            $this->logger->error('AspectFile: Failed to queue lead', [
                'lead_id' => $lead->getId(),
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Process pending batches
     *
     * @param int $limit Maximum number of batches to process
     * @return array{processed: int, succeeded: int, failed: int}
     */
    public function processPendingBatches(int $limit = 10): array
    {
        $batches = $this->em->createQueryBuilder()
            ->select('b')
            ->from(AspectFileBatch::class, 'b')
            ->where('b.status = :status')
            ->setParameter('status', AspectFileBatch::STATUS_PENDING)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        $stats = [
            'processed' => 0,
            'succeeded' => 0,
            'failed' => 0,
        ];

        foreach ($batches as $batch) {
            $result = $this->batchManager->processBatch($batch);

            ++$stats['processed'];

            if ($result['success']) {
                ++$stats['succeeded'];
            } else {
                ++$stats['failed'];
            }
        }

        return $stats;
    }

    /**
     * Get batch by ID
     */
    public function getBatch(int $id): ?AspectFileBatch
    {
        return $this->em->getRepository(AspectFileBatch::class)->find($id);
    }

    /**
     * Get pending batches count
     */
    public function getPendingBatchesCount(): int
    {
        $qb = $this->em->createQueryBuilder();
        $qb->select('COUNT(b.id)')
            ->from(AspectFileBatch::class, 'b')
            ->where('b.status = :status')
            ->setParameter('status', AspectFileBatch::STATUS_PENDING);

        return (int) $qb->getQuery()->getSingleScalarResult();
    }
}
