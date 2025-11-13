<?php

declare(strict_types=1);

namespace MauticPlugin\MauticAspectFileBundle\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\SchemaException;
use MauticPlugin\MauticAspectFileBundle\Migration\AbstractMigration;

/**
 * Create aspect_file_batches table
 */
class Version_1_0_1 extends AbstractMigration
{
    private string $table = 'aspect_file_batches';

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
        $schemaTable = $this->concatPrefix('aspect_file_schemas');

        $fkSchema = $this->generatePropertyName($this->table, 'fk', ['schema_id']);
        $idxSchema = $this->generatePropertyName($this->table, 'idx', ['schema_id']);
        $idxCampaign = $this->generatePropertyName($this->table, 'idx', ['campaign_id']);
        $idxEvent = $this->generatePropertyName($this->table, 'idx', ['event_id']);
        $idxStatus = $this->generatePropertyName($this->table, 'idx', ['status']);

        $this->addSql("
            CREATE TABLE `{$tableName}` (
                `id` INT UNSIGNED AUTO_INCREMENT NOT NULL,
                `schema_id` INT UNSIGNED NOT NULL,
                `campaign_id` INT UNSIGNED NOT NULL,
                `event_id` INT UNSIGNED NOT NULL,
                `bucket_name` VARCHAR(191) NOT NULL,
                `file_name` VARCHAR(191) DEFAULT NULL,
                `file_path` VARCHAR(191) DEFAULT NULL,
                `file_name_template` VARCHAR(191) DEFAULT NULL,
                `status` VARCHAR(191) DEFAULT 'PENDING',
                `leads_count` INT DEFAULT 0,
                `file_size_bytes` BIGINT DEFAULT NULL,
                `generated_at` DATETIME DEFAULT NULL,
                `uploaded_at` DATETIME DEFAULT NULL,
                `error_message` LONGTEXT DEFAULT NULL,
                `created_at` DATETIME NOT NULL,
                PRIMARY KEY(`id`),
                INDEX `{$idxSchema}` (`schema_id`),
                INDEX `{$idxCampaign}` (`campaign_id`),
                INDEX `{$idxEvent}` (`event_id`),
                INDEX `{$idxStatus}` (`status`),
                CONSTRAINT `{$fkSchema}`
                    FOREIGN KEY (`schema_id`)
                    REFERENCES `{$schemaTable}` (`id`)
                    ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }
}
