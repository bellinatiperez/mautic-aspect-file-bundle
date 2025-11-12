<?php

declare(strict_types=1);

namespace MauticPlugin\MauticAspectFileBundle\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Types;
use Mautic\CoreBundle\Doctrine\PreUpAssertionMigration;

/**
 * Create aspect_file_batch_leads table
 */
final class Version_1_0_2 extends PreUpAssertionMigration
{
    protected const TABLE_NAME = 'aspect_file_batch_leads';

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
        $table->addColumn('batch_id', Types::INTEGER, ['unsigned' => true]);
        $table->addColumn('lead_id', Types::INTEGER, ['unsigned' => true]);
        $table->addColumn('status', Types::STRING, ['length' => 191, 'default' => 'PENDING']);
        $table->addColumn('created_at', Types::DATETIME_MUTABLE, ['notnull' => true]);

        $table->setPrimaryKey(['id']);

        // Add foreign key to aspect_file_batches
        $table->addForeignKeyConstraint(
            $this->getPrefixedTableName('aspect_file_batches'),
            ['batch_id'],
            ['id'],
            ['onDelete' => 'CASCADE'],
            $this->generatePropertyName(self::TABLE_NAME, 'fk', ['batch_id'])
        );

        // Add foreign key to leads table
        $table->addForeignKeyConstraint(
            $this->getPrefixedTableName('leads'),
            ['lead_id'],
            ['id'],
            ['onDelete' => 'CASCADE'],
            $this->generatePropertyName(self::TABLE_NAME, 'fk', ['lead_id'])
        );

        // Add indexes
        $table->addIndex(['batch_id'], $this->generatePropertyName(self::TABLE_NAME, 'idx', ['batch_id']));
        $table->addIndex(['lead_id'], $this->generatePropertyName(self::TABLE_NAME, 'idx', ['lead_id']));
        $table->addIndex(['status'], $this->generatePropertyName(self::TABLE_NAME, 'idx', ['status']));
    }

    public function down(Schema $schema): void
    {
        $schema->dropTable($this->getPrefixedTableName());
    }
}
