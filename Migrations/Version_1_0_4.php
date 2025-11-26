<?php

declare(strict_types=1);

namespace MauticPlugin\MauticAspectFileBundle\Migrations;

use Doctrine\DBAL\Schema\Schema;
use MauticPlugin\MauticAspectFileBundle\Migration\AbstractMigration;

/**
 * Add destination type and network path fields to schemas table
 */
class Version_1_0_4 extends AbstractMigration
{
    protected function isApplicable(Schema $schema): bool
    {
        try {
            $table = $schema->getTable($this->concatPrefix('aspect_file_schemas'));

            return !$table->hasColumn('destination_type') || !$table->hasColumn('network_path');
        } catch (\Exception $e) {
            return false;
        }
    }

    protected function up(): void
    {
        $table = $this->concatPrefix('aspect_file_schemas');

        // Add destination_type column if not exists
        if (!$this->hasColumn($table, 'destination_type')) {
            $this->addSql("ALTER TABLE {$table} ADD destination_type VARCHAR(20) NOT NULL DEFAULT 'S3'");
        }

        // Add network_path column if not exists
        if (!$this->hasColumn($table, 'network_path')) {
            $this->addSql("ALTER TABLE {$table} ADD network_path VARCHAR(500) NULL DEFAULT NULL");
        }
    }
}
