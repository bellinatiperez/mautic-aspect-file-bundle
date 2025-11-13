<?php

declare(strict_types=1);

namespace MauticPlugin\MauticAspectFileBundle\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\SchemaException;
use MauticPlugin\MauticAspectFileBundle\Migration\AbstractMigration;

/**
 * Add missing indexes to aspect_file tables for better performance
 */
class Version_1_0_3 extends AbstractMigration
{
    protected function isApplicable(Schema $schema): bool
    {
        try {
            // Check if any of the indexes are missing
            $schemasTable = $schema->getTable($this->concatPrefix('aspect_file_schemas'));
            $batchesTable = $schema->getTable($this->concatPrefix('aspect_file_batches'));
            $batchLeadsTable = $schema->getTable($this->concatPrefix('aspect_file_batch_leads'));

            // Generate index names
            $idxSchemaName = $this->generatePropertyName('aspect_file_schemas', 'idx', ['name']);
            $idxSchemaPublished = $this->generatePropertyName('aspect_file_schemas', 'idx', ['is_published']);
            $idxBatchCampaign = $this->generatePropertyName('aspect_file_batches', 'idx', ['campaign_id']);
            $idxBatchEvent = $this->generatePropertyName('aspect_file_batches', 'idx', ['event_id']);
            $idxBatchStatus = $this->generatePropertyName('aspect_file_batches', 'idx', ['status']);
            $idxBatchLeadStatus = $this->generatePropertyName('aspect_file_batch_leads', 'idx', ['status']);

            // If any index is missing, migration is applicable
            return !$schemasTable->hasIndex($idxSchemaName)
                || !$schemasTable->hasIndex($idxSchemaPublished)
                || !$batchesTable->hasIndex($idxBatchCampaign)
                || !$batchesTable->hasIndex($idxBatchEvent)
                || !$batchesTable->hasIndex($idxBatchStatus)
                || !$batchLeadsTable->hasIndex($idxBatchLeadStatus);
        } catch (SchemaException) {
            return false;
        }
    }

    protected function up(): void
    {
        // Add indexes to aspect_file_schemas
        $schemasTable = $this->concatPrefix('aspect_file_schemas');
        $idxSchemaName = $this->generatePropertyName('aspect_file_schemas', 'idx', ['name']);
        $idxSchemaPublished = $this->generatePropertyName('aspect_file_schemas', 'idx', ['is_published']);

        $this->addSql("
            ALTER TABLE `{$schemasTable}`
            ADD INDEX `{$idxSchemaName}` (`name`),
            ADD INDEX `{$idxSchemaPublished}` (`is_published`)
        ");

        // Add indexes to aspect_file_batches
        $batchesTable = $this->concatPrefix('aspect_file_batches');
        $idxBatchCampaign = $this->generatePropertyName('aspect_file_batches', 'idx', ['campaign_id']);
        $idxBatchEvent = $this->generatePropertyName('aspect_file_batches', 'idx', ['event_id']);
        $idxBatchStatus = $this->generatePropertyName('aspect_file_batches', 'idx', ['status']);

        $this->addSql("
            ALTER TABLE `{$batchesTable}`
            ADD INDEX `{$idxBatchCampaign}` (`campaign_id`),
            ADD INDEX `{$idxBatchEvent}` (`event_id`),
            ADD INDEX `{$idxBatchStatus}` (`status`)
        ");

        // Add index to aspect_file_batch_leads
        $batchLeadsTable = $this->concatPrefix('aspect_file_batch_leads');
        $idxBatchLeadStatus = $this->generatePropertyName('aspect_file_batch_leads', 'idx', ['status']);

        $this->addSql("
            ALTER TABLE `{$batchLeadsTable}`
            ADD INDEX `{$idxBatchLeadStatus}` (`status`)
        ");
    }
}
