<?php

declare(strict_types=1);

namespace MauticPlugin\MauticAspectFileBundle\Migrations;

use Doctrine\DBAL\Schema\Schema;
use MauticPlugin\MauticAspectFileBundle\Migration\AbstractMigration;

/**
 * Move destination type and network path fields from schemas to batches table
 */
class Version_1_0_5 extends AbstractMigration
{
    protected function isApplicable(Schema $schema): bool
    {
        try {
            $batchesTable = $schema->getTable($this->concatPrefix('aspect_file_batches'));
            $schemasTable = $schema->getTable($this->concatPrefix('aspect_file_schemas'));

            // Apply if batches table doesn't have the columns yet OR schemas table still has them
            return !$batchesTable->hasColumn('destination_type')
                || !$batchesTable->hasColumn('network_path')
                || $schemasTable->hasColumn('destination_type')
                || $schemasTable->hasColumn('network_path');
        } catch (\Exception $e) {
            return false;
        }
    }

    protected function up(): void
    {
        $batchesTable = $this->concatPrefix('aspect_file_batches');
        $schemasTable = $this->concatPrefix('aspect_file_schemas');

        // Check and add destination_type to batches if needed
        if (!$this->hasColumn($batchesTable, 'destination_type')) {
            $this->addSql("ALTER TABLE {$batchesTable} ADD destination_type VARCHAR(20) NOT NULL DEFAULT 'S3'");
        }

        // Check and add network_path to batches if needed
        if (!$this->hasColumn($batchesTable, 'network_path')) {
            $this->addSql("ALTER TABLE {$batchesTable} ADD network_path VARCHAR(500) NULL DEFAULT NULL");
        }

        // Check and remove destination_type from schemas if exists
        if ($this->hasColumn($schemasTable, 'destination_type')) {
            $this->addSql("ALTER TABLE {$schemasTable} DROP COLUMN destination_type");
        }

        // Check and remove network_path from schemas if exists
        if ($this->hasColumn($schemasTable, 'network_path')) {
            $this->addSql("ALTER TABLE {$schemasTable} DROP COLUMN network_path");
        }
    }

    /**
     * Check if a table has a specific column
     */
    private function hasColumn(string $tableName, string $columnName): bool
    {
        try {
            $schema = $this->connection->createSchemaManager()->introspectSchema();
            $table = $schema->getTable($tableName);
            return $table->hasColumn($columnName);
        } catch (\Exception $e) {
            return false;
        }
    }
}
