<?php

declare(strict_types=1);

namespace MauticPlugin\MauticAspectFileBundle\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\SchemaException;
use MauticPlugin\MauticAspectFileBundle\Migration\AbstractMigration;

/**
 * Create aspect_file_schemas table
 */
class Version_1_0_0 extends AbstractMigration
{
    private string $table = 'aspect_file_schemas';

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
        $idxName = $this->generatePropertyName($this->table, 'idx', ['name']);
        $idxPublished = $this->generatePropertyName($this->table, 'idx', ['is_published']);

        $this->addSql("
            CREATE TABLE `{$tableName}` (
                `id` INT UNSIGNED AUTO_INCREMENT NOT NULL,
                `name` VARCHAR(191) NOT NULL,
                `description` LONGTEXT DEFAULT NULL,
                `fields` JSON NOT NULL,
                `file_extension` VARCHAR(10) DEFAULT 'raw',
                `line_length` INT DEFAULT NULL,
                `is_published` TINYINT(1) DEFAULT 1,
                `created_at` DATETIME NOT NULL,
                PRIMARY KEY(`id`),
                INDEX `{$idxName}` (`name`),
                INDEX `{$idxPublished}` (`is_published`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }
}
