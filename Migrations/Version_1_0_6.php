<?php

declare(strict_types=1);

namespace MauticPlugin\MauticAspectFileBundle\Migrations;

use Doctrine\DBAL\Schema\Schema;
use MauticPlugin\MauticAspectFileBundle\Migration\AbstractMigration;

/**
 * Create fastpath_logs table for tracking SOAP dispatch logs
 */
class Version_1_0_6 extends AbstractMigration
{
    protected function isApplicable(Schema $schema): bool
    {
        try {
            // Check if the table already exists
            return !$schema->hasTable($this->concatPrefix('fastpath_logs'));
        } catch (\Exception $e) {
            return true;
        }
    }

    protected function up(): void
    {
        $tableName = $this->concatPrefix('fastpath_logs');
        $leadsTable = $this->concatPrefix('leads');
        $schemasTable = $this->concatPrefix('aspect_file_schemas');

        $this->addSql("
            CREATE TABLE {$tableName} (
                id INT AUTO_INCREMENT NOT NULL,
                lead_id BIGINT UNSIGNED NULL,
                schema_id INT NULL,
                campaign_id INT NULL,
                event_id INT NULL,
                wsdl_url VARCHAR(500) NOT NULL,
                fast_list VARCHAR(191) NOT NULL,
                function_type INT NOT NULL DEFAULT 1,
                message_id VARCHAR(100) NOT NULL,
                status VARCHAR(20) NOT NULL DEFAULT 'SUCCESS',
                request_payload LONGTEXT NULL,
                response_payload LONGTEXT NULL,
                record_line LONGTEXT NULL,
                custom_fields TEXT NULL,
                error_message LONGTEXT NULL,
                fault_code VARCHAR(100) NULL,
                duration_ms INT NULL,
                created_at DATETIME NOT NULL,
                PRIMARY KEY(id),
                INDEX idx_fastpath_log_status (status),
                INDEX idx_fastpath_log_created (created_at),
                INDEX idx_fastpath_log_wsdl (wsdl_url(191)),
                INDEX idx_fastpath_log_lead (lead_id),
                INDEX idx_fastpath_log_message (message_id),
                CONSTRAINT fk_fastpath_log_lead
                    FOREIGN KEY (lead_id)
                    REFERENCES {$leadsTable} (id)
                    ON DELETE SET NULL,
                CONSTRAINT fk_fastpath_log_schema
                    FOREIGN KEY (schema_id)
                    REFERENCES {$schemasTable} (id)
                    ON DELETE SET NULL
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB
        ");
    }
}
