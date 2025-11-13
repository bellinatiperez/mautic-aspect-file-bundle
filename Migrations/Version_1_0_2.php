<?php

declare(strict_types=1);

namespace MauticPlugin\MauticAspectFileBundle\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\SchemaException;
use MauticPlugin\MauticAspectFileBundle\Migration\AbstractMigration;

/**
 * Create aspect_file_batch_leads table
 */
class Version_1_0_2 extends AbstractMigration
{
    private string $table = 'aspect_file_batch_leads';

    protected function isApplicable(Schema $schema): bool
    {
        try {
            return !$schema->hasTable($this->concatPrefix($this->table));
        } catch (SchemaException) {
            return false;
        }
    }

    protected function up(): void
    {
        $tableName = $this->concatPrefix($this->table);
        $batchTable = $this->concatPrefix('aspect_file_batches');
        $leadsTable = $this->concatPrefix('leads');

        $fkBatch = $this->generatePropertyName($this->table, 'fk', ['batch_id']);
        $fkLead = $this->generatePropertyName($this->table, 'fk', ['lead_id']);
        $idxBatch = $this->generatePropertyName($this->table, 'idx', ['batch_id']);
        $idxLead = $this->generatePropertyName($this->table, 'idx', ['lead_id']);
        $idxStatus = $this->generatePropertyName($this->table, 'idx', ['status']);

        $this->addSql("
            CREATE TABLE `{$tableName}` (
                `id` INT UNSIGNED AUTO_INCREMENT NOT NULL,
                `batch_id` INT UNSIGNED NOT NULL,
                `lead_id` BIGINT UNSIGNED NOT NULL,
                `status` VARCHAR(191) DEFAULT 'PENDING',
                `created_at` DATETIME NOT NULL,
                PRIMARY KEY(`id`),
                INDEX `{$idxBatch}` (`batch_id`),
                INDEX `{$idxLead}` (`lead_id`),
                INDEX `{$idxStatus}` (`status`),
                CONSTRAINT `{$fkBatch}`
                    FOREIGN KEY (`batch_id`)
                    REFERENCES `{$batchTable}` (`id`)
                    ON DELETE CASCADE,
                CONSTRAINT `{$fkLead}`
                    FOREIGN KEY (`lead_id`)
                    REFERENCES `{$leadsTable}` (`id`)
                    ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }
}
