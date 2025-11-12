<?php

declare(strict_types=1);

namespace MauticPlugin\MauticAspectFileBundle\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Types;
use Mautic\CoreBundle\Doctrine\PreUpAssertionMigration;

/**
 * Create aspect_file_batches table
 */
final class Version_1_0_1 extends PreUpAssertionMigration
{
    protected const TABLE_NAME = 'aspect_file_batches';

    protected function preUpAssertions(): void
    {
        $this->skipAssertion(
            fn (Schema $schema) => $schema->hasTable($this->getPrefixedTableName()),
            'Table '.self::TABLE_NAME.' already exists'
        );
    }

    public function up(Schema $schema): void
    {
        $table = $schema->createTable($this->getPrefixedTableName());

        $table->addColumn('id', Types::INTEGER, ['autoincrement' => true, 'unsigned' => true]);
        $table->addColumn('schema_id', Types::INTEGER, ['unsigned' => true]);
        $table->addColumn('campaign_id', Types::INTEGER, ['unsigned' => true]);
        $table->addColumn('event_id', Types::INTEGER, ['unsigned' => true]);
        $table->addColumn('bucket_name', Types::STRING, ['length' => 191]);
        $table->addColumn('file_name', Types::STRING, ['length' => 191, 'notnull' => false]);
        $table->addColumn('file_path', Types::STRING, ['length' => 191, 'notnull' => false]);
        $table->addColumn('file_name_template', Types::STRING, ['length' => 191, 'notnull' => false]);
        $table->addColumn('status', Types::STRING, ['length' => 191, 'default' => 'PENDING']);
        $table->addColumn('leads_count', Types::INTEGER, ['default' => 0]);
        $table->addColumn('file_size_bytes', Types::BIGINT, ['notnull' => false]);
        $table->addColumn('generated_at', Types::DATETIME_MUTABLE, ['notnull' => false]);
        $table->addColumn('uploaded_at', Types::DATETIME_MUTABLE, ['notnull' => false]);
        $table->addColumn('error_message', Types::TEXT, ['notnull' => false]);
        $table->addColumn('created_at', Types::DATETIME_MUTABLE, ['notnull' => true]);

        $table->setPrimaryKey(['id']);

        // Add foreign key to aspect_file_schemas
        $table->addForeignKeyConstraint(
            $this->getPrefixedTableName('aspect_file_schemas'),
            ['schema_id'],
            ['id'],
            ['onDelete' => 'CASCADE'],
            $this->generatePropertyName(self::TABLE_NAME, 'fk', ['schema_id'])
        );

        // Add indexes
        $table->addIndex(['schema_id'], $this->generatePropertyName(self::TABLE_NAME, 'idx', ['schema_id']));
        $table->addIndex(['campaign_id'], $this->generatePropertyName(self::TABLE_NAME, 'idx', ['campaign_id']));
        $table->addIndex(['event_id'], $this->generatePropertyName(self::TABLE_NAME, 'idx', ['event_id']));
        $table->addIndex(['status'], $this->generatePropertyName(self::TABLE_NAME, 'idx', ['status']));
    }

    public function down(Schema $schema): void
    {
        $schema->dropTable($this->getPrefixedTableName());
    }
}
