# Database Migrations for MauticAspectFileBundle

This document explains how database migrations work for the AspectFile plugin.

## Overview

The plugin uses a **custom migration system** (similar to Mautic's IntegrationsBundle) to manage database schema changes automatically. Migrations run automatically when the plugin is loaded.

There are four migration files that create the necessary database tables:

1. **Version_1_0_0.php** - Creates the `aspect_file_schemas` table
2. **Version_1_0_1.php** - Creates the `aspect_file_batches` table with foreign key to schemas
3. **Version_1_0_2.php** - Creates the `aspect_file_batch_leads` table with foreign keys to batches and leads
4. **Version_1_0_3.php** - Adds missing performance indexes to all three tables

## How It Works

The plugin includes a custom migration engine (`Migration/Engine.php`) that:

1. Automatically discovers migration files in the `Migrations/` directory
2. Runs migrations in alphabetical order (Version_1_0_0, Version_1_0_1, Version_1_0_2, Version_1_0_3)
3. Checks if each migration is applicable before executing (won't try to recreate existing tables)
4. Executes migrations within a database transaction for safety
5. Runs automatically when the bundle boots (no manual intervention needed)

## Database Tables Created

### aspect_file_schemas
- Stores schema definitions for fixed-width file generation
- Contains field mappings and configurations

### aspect_file_batches
- Tracks batch file generation jobs
- Links to campaigns and schemas
- Stores file upload status and metadata

### aspect_file_batch_leads
- Links leads to batch jobs
- Tracks processing status for each lead in a batch

## Running Migrations

### Automatic Execution (Recommended)

Migrations run **automatically** when:

1. The plugin is first installed
2. The plugin is updated with new migrations
3. Mautic cache is cleared: `php bin/console cache:clear`
4. The application is loaded (migrations run on bundle boot)

**No manual intervention is required!** Simply:

```bash
# Clear cache to trigger migrations
php bin/console cache:clear
```

### Manual Execution (If Needed)

If you need to run migrations manually or they didn't execute automatically:

#### Option 1: Reload Plugins

```bash
php bin/console mautic:plugins:reload
```

This will reload the plugin and trigger migrations.

#### Option 2: Manual Schema Update (Development Only)

```bash
php bin/console doctrine:schema:update --force
```

**Warning**: This method bypasses the migration system entirely and should only be used for development or troubleshooting.

## Installation Steps

### Fresh Installation

1. Place the plugin in `plugins/MauticAspectFileBundle`
2. Clear cache: `php bin/console cache:clear`
3. That's it! Migrations run automatically during cache clear.

### Existing Installation (Upgrading)

If you have an existing installation with tables already created:

1. Update the plugin code
2. Clear cache: `php bin/console cache:clear`
3. The migrations have built-in checks (`isApplicable()`) that skip table creation if tables already exist
4. Safe to run multiple times - idempotent design prevents errors

## Migration Features

### Idempotent Design

Each migration checks if its changes are already applied before executing:

```php
protected function preUpAssertions(): void
{
    $this->skipAssertion(
        fn (Schema $schema) => $schema->hasTable($this->getPrefixedTableName()),
        'Table '.self::TABLE_NAME.' already exists'
    );
}
```

This means:
- Safe to run multiple times
- Won't fail if tables already exist
- Handles table prefix correctly

### Foreign Key Constraints

The migrations properly set up foreign key relationships:

- `aspect_file_batches.schema_id` → `aspect_file_schemas.id` (CASCADE DELETE)
- `aspect_file_batch_leads.batch_id` → `aspect_file_batches.id` (CASCADE DELETE)
- `aspect_file_batch_leads.lead_id` → `leads.id` (CASCADE DELETE)

This ensures referential integrity and automatic cleanup when parent records are deleted.

### Indexes

Indexes are created on commonly queried fields for optimal performance:

- **Schema**: name, is_published
- **Batch**: schema_id, campaign_id, event_id, status
- **BatchLead**: batch_id, lead_id, status

**Note**: Version_1_0_3 was added to include missing indexes that were not created by the initial migrations. This ensures optimal query performance when filtering by status, campaign_id, event_id, name, or is_published.

## Rollback

The custom migration system **does not support rollback** (similar to IntegrationsBundle).

To remove tables manually:

```sql
DROP TABLE IF EXISTS `aspect_file_batch_leads`;
DROP TABLE IF EXISTS `aspect_file_batches`;
DROP TABLE IF EXISTS `aspect_file_schemas`;
```

Or use Doctrine schema update after removing entities:

```bash
php bin/console doctrine:schema:update --force --complete
```

## Troubleshooting

### Migrations Not Running

If migrations don't run automatically:

1. **Check logs**: `var/logs/dev-YYYY-MM-DD.php` or `var/logs/prod-YYYY-MM-DD.php`
   - Look for "AspectFile migrations failed" messages

2. **Verify plugin is loaded**:
```bash
php bin/console debug:container MauticAspectFileBundle
```

3. **Manually clear cache**:
```bash
rm -rf var/cache/*
php bin/console cache:clear
```

4. **Check database permissions**: Ensure the database user has CREATE TABLE permissions

### Table Already Exists Error

The migration's `isApplicable()` method should prevent this, but if it still occurs:

1. Check if tables exist:
```sql
SHOW TABLES LIKE '%aspect_file%';
```

2. If tables exist and match the schema, migrations will automatically skip
3. If partial tables exist, manually drop them and re-run migrations

### Verifying Tables

To verify that tables were created correctly:

```bash
php bin/console dbal:run-sql "SHOW TABLES LIKE '%aspect_file%'"
```

Or check the schema:

```bash
php bin/console doctrine:schema:validate
```

## Development

### Creating New Migrations

To create a new migration for schema changes:

1. Create a new file in `plugins/MauticAspectFileBundle/Migrations/` named `Version_X_X_X.php`
2. Use the existing migrations as a template
3. Extend `MauticPlugin\MauticAspectFileBundle\Migration\AbstractMigration`
4. Implement two methods:
   - `isApplicable(Schema $schema): bool` - Check if migration should run
   - `up(): void` - Define SQL queries using `$this->addSql()`

Example:

```php
<?php

declare(strict_types=1);

namespace MauticPlugin\MauticAspectFileBundle\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\SchemaException;
use MauticPlugin\MauticAspectFileBundle\Migration\AbstractMigration;

class Version_1_0_3 extends AbstractMigration
{
    private string $table = 'aspect_file_schemas';

    protected function isApplicable(Schema $schema): bool
    {
        try {
            $table = $schema->getTable($this->concatPrefix($this->table));
            return !$table->hasColumn('new_column');
        } catch (SchemaException) {
            return false;
        }
    }

    protected function up(): void
    {
        $tableName = $this->concatPrefix($this->table);

        $this->addSql("
            ALTER TABLE `{$tableName}`
            ADD COLUMN `new_column` VARCHAR(255) NULL
        ");
    }
}
```

### Testing Migrations

1. **Test on development database**:
   - Ensure tables don't exist or use a fresh database
   - Clear cache: `php bin/console cache:clear`
   - Check logs for any migration errors

2. **Verify tables were created**:
```bash
php bin/console dbal:run-sql "SHOW TABLES LIKE '%aspect_file%'"
```

3. **Test idempotency** (run twice to ensure it doesn't fail):
```bash
php bin/console cache:clear
php bin/console cache:clear
```

4. **Validate schema**:
```bash
php bin/console doctrine:schema:validate
```

## Support

For issues related to migrations, check:

1. Mautic logs: `var/logs/dev-YYYY-MM-DD.php` or `var/logs/prod-YYYY-MM-DD.php`
2. Database errors in Symfony profiler (dev environment)
3. Doctrine migration version table: `SELECT * FROM migration_versions;`
