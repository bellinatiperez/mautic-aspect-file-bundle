<?php

declare(strict_types=1);

namespace MauticPlugin\MauticAspectFileBundle\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Types;
use Mautic\CoreBundle\Doctrine\PreUpAssertionMigration;

/**
 * Create aspect_file_schemas table
 */
final class Version_1_0_0 extends PreUpAssertionMigration
{
    protected const TABLE_NAME = 'aspect_file_schemas';

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
        $table->addColumn('name', Types::STRING, ['length' => 191]);
        $table->addColumn('description', Types::TEXT, ['notnull' => false]);
        $table->addColumn('fields', Types::JSON, ['notnull' => true]);
        $table->addColumn('file_extension', Types::STRING, ['length' => 10, 'default' => 'raw']);
        $table->addColumn('line_length', Types::INTEGER, ['notnull' => false]);
        $table->addColumn('is_published', Types::BOOLEAN, ['default' => true]);
        $table->addColumn('created_at', Types::DATETIME_MUTABLE, ['notnull' => true]);

        $table->setPrimaryKey(['id']);
        $table->addIndex(['name'], $this->generatePropertyName(self::TABLE_NAME, 'idx', ['name']));
        $table->addIndex(['is_published'], $this->generatePropertyName(self::TABLE_NAME, 'idx', ['is_published']));
    }

    public function down(Schema $schema): void
    {
        $schema->dropTable($this->getPrefixedTableName());
    }
}
