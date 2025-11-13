<?php

declare(strict_types=1);

namespace MauticPlugin\MauticAspectFileBundle\Service;

use Doctrine\ORM\EntityManagerInterface;
use Mautic\LeadBundle\Model\LeadModel;
use MauticPlugin\MauticAspectFileBundle\Entity\AspectFileBatch;
use MauticPlugin\MauticAspectFileBundle\Entity\AspectFileBatchLead;
use Psr\Log\LoggerInterface;

/**
 * Service to manage batch processing
 */
class BatchManager
{
    private EntityManagerInterface $em;
    private FileGenerator $fileGenerator;
    private MinIOUploader $minioUploader;
    private NetworkUploader $networkUploader;
    private LoggerInterface $logger;
    private FieldMapper $fieldMapper;
    private LeadModel $leadModel;

    public function __construct(
        EntityManagerInterface $em,
        FileGenerator $fileGenerator,
        MinIOUploader $minioUploader,
        NetworkUploader $networkUploader,
        LoggerInterface $logger,
        FieldMapper $fieldMapper,
        LeadModel $leadModel
    ) {
        $this->em = $em;
        $this->fileGenerator = $fileGenerator;
        $this->minioUploader = $minioUploader;
        $this->networkUploader = $networkUploader;
        $this->logger = $logger;
        $this->fieldMapper = $fieldMapper;
        $this->leadModel = $leadModel;
    }

    /**
     * Process a batch: generate file and upload to MinIO
     *
     * @param AspectFileBatch $batch
     * @return array{success: bool, error?: string}
     */
    public function processBatch(AspectFileBatch $batch): array
    {
        try {
            $this->logger->info('AspectFile: Processing batch', [
                'batch_id' => $batch->getId(),
                'schema' => $batch->getSchema()->getName(),
                'leads_count' => $batch->getLeadsCount(),
            ]);

            // Mark as processing
            $batch->setStatus(AspectFileBatch::STATUS_GENERATING);
            $this->em->flush();

            // Get pending lead IDs (lightweight query)
            $batchLeadIds = $this->em->createQueryBuilder()
                ->select('bl.id')
                ->from(AspectFileBatchLead::class, 'bl')
                ->where('bl.batch = :batch')
                ->andWhere('bl.status = :status')
                ->setParameter('batch', $batch)
                ->setParameter('status', AspectFileBatchLead::STATUS_PENDING)
                ->getQuery()
                ->getScalarResult();

            if (empty($batchLeadIds)) {
                $this->logger->warning('AspectFile: No pending leads found for batch', [
                    'batch_id' => $batch->getId(),
                ]);

                $batch->setStatus(AspectFileBatch::STATUS_FAILED);
                $batch->setErrorMessage('No pending leads found');
                $this->em->flush();

                return [
                    'success' => false,
                    'error' => 'No pending leads found',
                ];
            }

            // Extract IDs
            $batchLeadIds = array_column($batchLeadIds, 'id');
            $totalLeads = count($batchLeadIds);

            // Generate temporary file
            $fileName = $batch->getFileName() ?? $this->generateFileName($batch);
            $localFilePath = $this->fileGenerator->generateTempFilePath(
                pathinfo($fileName, PATHINFO_FILENAME),
                $batch->getSchema()->getFileExtension()
            );

            // Open file for writing
            $handle = fopen($localFilePath, 'w');
            if (!$handle) {
                throw new \RuntimeException("Cannot open file for writing: {$localFilePath}");
            }

            $linesWritten = 0;
            $chunkSize = 50; // Process 50 leads at a time
            $batchId = $batch->getId();
            $schemaId = $batch->getSchema()->getId();

            // Process in chunks
            for ($offset = 0; $offset < $totalLeads; $offset += $chunkSize) {
                $chunkIds = array_slice($batchLeadIds, $offset, $chunkSize);

                // Fetch fresh entities for this chunk
                $batchLeads = $this->em->createQueryBuilder()
                    ->select('bl', 'l')
                    ->from(AspectFileBatchLead::class, 'bl')
                    ->join('bl.lead', 'l')
                    ->where('bl.id IN (:ids)')
                    ->setParameter('ids', $chunkIds)
                    ->getQuery()
                    ->getResult();

                // Re-fetch schema (might be detached after clear)
                $schema = $this->em->find(\MauticPlugin\MauticAspectFileBundle\Entity\Schema::class, $schemaId);
                if (!$schema) {
                    throw new \RuntimeException('Schema entity was removed during processing');
                }

                foreach ($batchLeads as $batchLead) {
                    // Get lead and reload with LeadModel to load all fields
                    $lead = $batchLead->getLead();
                    $leadId = $lead->getId();

                    // Reload lead with all fields using LeadModel
                    $lead = $this->leadModel->getEntity($leadId);
                    if (!$lead) {
                        $this->logger->warning('AspectFile: Lead not found, skipping', [
                            'lead_id' => $leadId,
                        ]);
                        continue;
                    }

                    // Get lead data using FieldMapper
                    $leadData = $this->fieldMapper->mapLeadToSchema($lead, $schema);

                    // Generate fixed-width line
                    $line = $this->fileGenerator->generateLine($schema, $leadData);

                    // Write to file
                    fwrite($handle, $line . "\n");
                    ++$linesWritten;

                    // Mark as generated
                    $batchLead->setStatus(AspectFileBatchLead::STATUS_GENERATED);
                    $this->em->persist($batchLead);
                }

                // Flush and clear to free memory
                $this->em->flush();
                $this->em->clear();

                $this->logger->info('AspectFile: Processed chunk', [
                    'batch_id' => $batchId,
                    'offset' => $offset,
                    'chunk_size' => count($chunkIds),
                    'lines_written' => $linesWritten,
                ]);
            }

            fclose($handle);

            $fileSize = filesize($localFilePath);

            $this->logger->info('AspectFile: File generated', [
                'batch_id' => $batchId,
                'file_path' => $localFilePath,
                'file_size' => $fileSize,
                'lines_count' => $linesWritten,
            ]);

            // Re-fetch batch for final update (entity may be detached after clear)
            $batch = $this->em->find(AspectFileBatch::class, $batchId);
            if (!$batch) {
                throw new \RuntimeException('Batch entity was removed during processing');
            }

            $this->logger->info('AspectFile: Updating batch with file size', [
                'batch_id' => $batchId,
                'file_size' => $fileSize,
            ]);

            $batch->setFileSizeBytes($fileSize);
            $batch->setGeneratedAt(new \DateTime());
            $this->em->persist($batch);
            $this->em->flush();

            // Get destination type from batch
            $destinationType = $batch->getDestinationType();

            // Upload based on destination type
            $this->logger->info('AspectFile: Setting batch status to UPLOADING', [
                'batch_id' => $batchId,
                'destination_type' => $destinationType,
            ]);

            $batch->setStatus(AspectFileBatch::STATUS_UPLOADING);
            $this->em->persist($batch);
            $this->em->flush();

            if ($destinationType === 'NETWORK') {
                // Upload to network directory
                $networkPath = $batch->getNetworkPath();
                if (empty($networkPath)) {
                    throw new \RuntimeException('Network path is not configured');
                }

                $uploadResult = $this->networkUploader->upload(
                    $localFilePath,
                    $networkPath,
                    $fileName
                );

                $filePath = $uploadResult['path'] ?? ($networkPath . '/' . $fileName);
            } else {
                // Upload to S3/MinIO (default)
                $uploadResult = $this->minioUploader->upload(
                    $localFilePath,
                    $batch->getBucketName(),
                    $fileName
                );

                $filePath = $batch->getBucketName() . '/' . $fileName;
            }

            if (!$uploadResult['success']) {
                throw new \RuntimeException('Upload failed: ' . ($uploadResult['error'] ?? 'Unknown error'));
            }

            $this->logger->info('AspectFile: Setting batch status to UPLOADED', [
                'batch_id' => $batchId,
                'file_name' => $fileName,
                'destination_type' => $destinationType,
            ]);

            $batch->setStatus(AspectFileBatch::STATUS_UPLOADED);
            $batch->setFileName($fileName);
            $batch->setFilePath($filePath);
            $batch->setUploadedAt(new \DateTime());
            $this->em->persist($batch);
            $this->em->flush();

            // Clean up temporary file
            if (file_exists($localFilePath)) {
                unlink($localFilePath);
            }

            $this->logger->info('AspectFile: Batch processed successfully', [
                'batch_id' => $batch->getId(),
                'file_path' => $batch->getFilePath(),
            ]);

            return ['success' => true];
        } catch (\Exception $e) {
            $batchId = $batch->getId();

            $this->logger->error('AspectFile: Batch processing failed', [
                'batch_id' => $batchId,
                'error' => $e->getMessage(),
            ]);

            // Re-fetch batch if it was detached
            if (!$this->em->contains($batch)) {
                $batch = $this->em->find(AspectFileBatch::class, $batchId);
            }

            if ($batch) {
                // Reset batch leads to PENDING to allow reprocessing
                $this->logger->info('AspectFile: Resetting batch leads to PENDING for reprocessing', [
                    'batch_id' => $batchId,
                ]);

                $this->em->createQueryBuilder()
                    ->update(AspectFileBatchLead::class, 'bl')
                    ->set('bl.status', ':pending')
                    ->where('bl.batch = :batch')
                    ->andWhere('bl.status = :generated')
                    ->setParameter('pending', AspectFileBatchLead::STATUS_PENDING)
                    ->setParameter('generated', AspectFileBatchLead::STATUS_GENERATED)
                    ->setParameter('batch', $batch)
                    ->getQuery()
                    ->execute();

                $batch->setStatus(AspectFileBatch::STATUS_PENDING);
                $batch->setErrorMessage($e->getMessage());
                $this->em->flush();
            }

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Generate file name with timestamp
     */
    private function generateFileName(AspectFileBatch $batch): string
    {
        $schema = $batch->getSchema();
        $template = $batch->getFileNameTemplate();

        // If template is provided, use it with variable replacement
        if ($template) {
            $fileName = $this->replaceFileNameVariables($template, $batch);
        } else {
            // Default format
            $timestamp = date('Ymd_His');
            $batchId = $batch->getId();
            $fileName = sprintf(
                'aspect_%s_%s_%d',
                str_replace(' ', '_', strtolower($schema->getName())),
                $timestamp,
                $batchId
            );
        }

        // Add extension
        return $fileName . '.' . $schema->getFileExtension();
    }

    /**
     * Replace variables in file name template
     */
    private function replaceFileNameVariables(string $template, AspectFileBatch $batch): string
    {
        $now = new \DateTime();
        $schema = $batch->getSchema();

        $variables = [
            '{date}' => $now->format('Ymd'),
            '{datetime}' => $now->format('Ymd_His'),
            '{timestamp}' => $now->getTimestamp(),
            '{batch_id}' => $batch->getId(),
            '{campaign_id}' => $batch->getCampaignId(),
            '{schema_name}' => str_replace(' ', '_', strtolower($schema->getName())),
            '{year}' => $now->format('Y'),
            '{month}' => $now->format('m'),
            '{day}' => $now->format('d'),
            '{hour}' => $now->format('H'),
            '{minute}' => $now->format('i'),
            '{second}' => $now->format('s'),
        ];

        return str_replace(array_keys($variables), array_values($variables), $template);
    }
}
