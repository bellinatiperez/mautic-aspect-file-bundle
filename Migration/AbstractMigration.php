<?php

declare(strict_types=1);

namespace MauticPlugin\MauticAspectFileBundle\Migration;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\ORM\EntityManager;

abstract class AbstractMigration implements MigrationInterface
{
    /**
     * @var string[]
     */
    private array $queries = [];

    public function __construct(
        protected EntityManager $entityManager,
        protected string $tablePrefix,
    ) {
    }

    public function shouldExecute(): bool
    {
        return $this->isApplicable($this->entityManager->getConnection()->createSchemaManager()->introspectSchema());
    }

    /**
     * @throws \Doctrine\DBAL\Exception
     */
    public function execute(): void
    {
        $this->up();

        if (!$this->queries) {
            return;
        }

        $connection = $this->entityManager->getConnection();

        foreach ($this->queries as $sql) {
            try {
                $stmt = $connection->prepare($sql);
                $stmt->executeStatement();
            } catch (\Doctrine\DBAL\Exception $e) {
                // Ignore "column already exists" and "column doesn't exist" errors
                // These can happen due to race conditions in multi-pod environments
                $errorCode = $e->getPrevious() ? $e->getPrevious()->getCode() : null;

                // MySQL error codes:
                // 1060 = Duplicate column name (column already exists)
                // 1091 = Can't DROP; check that column/key exists
                if (!in_array($errorCode, ['42S21', '42000', 1060, 1091], false)) {
                    throw $e;
                }
                // Silently ignore race condition errors
            }
        }
    }

    /**
     * Generate the ALTER TABLE query that adds the foreign key.
     *
     * @param string[] $columns
     * @param string[] $referenceColumns
     * @param string   $suffix           usually a 'ON DELETE ...' statement
     */
    protected function generateAlterTableForeignKeyStatement(
        string $table,
        array $columns,
        string $referenceTable,
        array $referenceColumns,
        string $suffix = '',
    ): string {
        return "ALTER TABLE {$this->concatPrefix($table)}
            ADD CONSTRAINT {$this->generatePropertyName($table, 'fk', $columns)}
            FOREIGN KEY ({$this->columnsToString($columns)})
            REFERENCES {$this->concatPrefix($referenceTable)} ({$this->columnsToString($referenceColumns)}) {$suffix}
        ";
    }

    /**
     * @param string[] $columns
     */
    protected function generateIndexStatement(string $table, array $columns): string
    {
        return "INDEX {$this->generatePropertyName($table, 'idx', $columns)} ({$this->columnsToString($columns)})";
    }

    /**
     * @param string[] $columns
     */
    protected function columnsToString(array $columns): string
    {
        return implode(',', $columns);
    }

    /**
     * Generate the name for the property.
     *
     * @param string[] $columnNames
     */
    protected function generatePropertyName(string $table, string $type, array $columnNames): string
    {
        $columnNames = array_merge([$this->tablePrefix.$table], $columnNames);
        $hash        = implode(
            '',
            array_map(
                fn ($column): string => dechex(crc32($column)),
                $columnNames
            )
        );

        return substr(strtoupper($type.'_'.$hash), 0, 63);
    }

    protected function addSql(string $sql): void
    {
        $this->queries[] = $sql;
    }

    /**
     * Concatenates table/index prefix to the provided name.
     */
    protected function concatPrefix(string $name): string
    {
        return $this->tablePrefix.$name;
    }

    /**
     * Check if a table has a specific column
     */
    protected function hasColumn(string $tableName, string $columnName): bool
    {
        try {
            $schema = $this->entityManager->getConnection()->createSchemaManager()->introspectSchema();
            $table = $schema->getTable($tableName);
            return $table->hasColumn($columnName);
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Define in the child migration whether the migration should be executed.
     * Check if the migration is applied in the schema already.
     */
    abstract protected function isApplicable(Schema $schema): bool;

    /**
     * Define queries for migration up.
     */
    abstract protected function up(): void;
}
