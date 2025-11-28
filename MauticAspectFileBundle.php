<?php

declare(strict_types=1);

namespace MauticPlugin\MauticAspectFileBundle;

use Mautic\PluginBundle\Bundle\PluginBundleBase;

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
}
