<?php

declare(strict_types=1);

namespace MauticPlugin\MauticAspectFileBundle;

use Mautic\CoreBundle\Helper\CoreParametersHelper;
use Mautic\PluginBundle\Bundle\PluginBundleBase;
use MauticPlugin\MauticAspectFileBundle\Migration\Engine;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * MauticAspectFileBundle - Generate fixed-width files from leads
 */
class MauticAspectFileBundle extends PluginBundleBase
{
    /**
     * {@inheritdoc}
     */
    public function getPath(): string
    {
        return __DIR__;
    }

    /**
     * Boot the bundle and run migrations
     */
    public function boot(): void
    {
        parent::boot();

        // Run migrations on bundle boot if container is available
        if ($this->container instanceof ContainerInterface) {
            try {
                $this->runMigrations();
            } catch (\Exception $e) {
                // Log error but don't break the application
                if ($this->container->has('monolog.logger.mautic')) {
                    $logger = $this->container->get('monolog.logger.mautic');
                    $logger->error('AspectFile migrations failed: ' . $e->getMessage());
                }
            }
        }
    }

    /**
     * Run plugin migrations
     */
    private function runMigrations(): void
    {
        if (!$this->container->has('doctrine.orm.entity_manager')) {
            return;
        }

        $entityManager = $this->container->get('doctrine.orm.entity_manager');
        $coreParams = $this->container->get(CoreParametersHelper::class);
        $tablePrefix = $coreParams->get('db_table_prefix') ?? '';

        $migrationEngine = new Engine(
            $entityManager,
            $tablePrefix,
            $this->getPath(),
            'MauticAspectFileBundle'
        );

        $migrationEngine->up();
    }
}
