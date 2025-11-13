<?php

declare(strict_types=1);

use MauticPlugin\MauticAspectFileBundle\Controller\BatchController;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return function (ContainerConfigurator $configurator) {
    $services = $configurator->services()
        ->defaults()
        ->autowire()
        ->autoconfigure()
        ->public();

    // Register BatchController as a service
    $services->set(BatchController::class)
        ->arg('$doctrine', service('doctrine'))
        ->arg('$translator', service('translator'))
        ->arg('$flashBag', service('mautic.core.service.flashbag'))
        ->arg('$twig', service('twig'))
        ->arg('$aspectFileModel', service('mautic.aspectfile.model.aspectfile'))
        ->arg('$urlGenerator', service('router'))
        ->tag('controller.service_arguments');
};
