<?php

declare(strict_types=1);

return [
    'name' => 'AspectFile',
    'description' => 'Generate fixed-width text files from Mautic leads and upload to MinIO/S3',
    'version' => '1.0.0',
    'author' => 'Bellinati',

    'routes' => [
        'main' => [
            'mautic_aspectfile_import' => [
                'path' => '/aspectfile/schemas/import',
                'controller' => 'MauticPlugin\MauticAspectFileBundle\Controller\SchemaController::importAction',
            ],
            'mautic_aspectfile_action' => [
                'path' => '/aspectfile/schemas/{objectAction}/{objectId}',
                'controller' => 'MauticPlugin\MauticAspectFileBundle\Controller\SchemaController::executeAction',
                'defaults' => ['objectId' => 0],
            ],
            'mautic_aspectfile_index' => [
                'path' => '/aspectfile/schemas/{page}',
                'controller' => 'MauticPlugin\MauticAspectFileBundle\Controller\SchemaController::indexAction',
                'defaults' => ['page' => 1],
            ],
            // Batch routes
            'mautic_aspectfile_batch_index' => [
                'path' => '/aspectfile/batches/{page}',
                'controller' => 'MauticPlugin\MauticAspectFileBundle\Controller\BatchController::indexAction',
                'defaults' => ['page' => 1],
            ],
            'mautic_aspectfile_batch_view' => [
                'path' => '/aspectfile/batch/{id}',
                'controller' => 'MauticPlugin\MauticAspectFileBundle\Controller\BatchController::viewAction',
                'requirements' => ['id' => '\d+'],
            ],
            'mautic_aspectfile_batch_reprocess' => [
                'path' => '/aspectfile/batch/{id}/reprocess',
                'controller' => 'MauticPlugin\MauticAspectFileBundle\Controller\BatchController::reprocessAction',
                'requirements' => ['id' => '\d+'],
            ],
            'mautic_aspectfile_batch_delete' => [
                'path' => '/aspectfile/batch/{id}/delete',
                'controller' => 'MauticPlugin\MauticAspectFileBundle\Controller\BatchController::deleteAction',
                'requirements' => ['id' => '\d+'],
            ],
            'mautic_aspectfile_batch_process' => [
                'path' => '/aspectfile/batches/process',
                'controller' => 'MauticPlugin\MauticAspectFileBundle\Controller\BatchController::processAction',
            ],
            // FastPath Log routes
            'mautic_fastpath_log_index' => [
                'path' => '/fastpath/logs/{page}',
                'controller' => 'MauticPlugin\MauticAspectFileBundle\Controller\FastPathLogController::indexAction',
                'defaults' => ['page' => 1],
            ],
            'mautic_fastpath_log_view' => [
                'path' => '/fastpath/log/{id}',
                'controller' => 'MauticPlugin\MauticAspectFileBundle\Controller\FastPathLogController::viewAction',
                'requirements' => ['id' => '\d+'],
            ],
            'mautic_fastpath_log_delete' => [
                'path' => '/fastpath/log/{id}/delete',
                'controller' => 'MauticPlugin\MauticAspectFileBundle\Controller\FastPathLogController::deleteAction',
                'requirements' => ['id' => '\d+'],
            ],
            'mautic_fastpath_log_clear' => [
                'path' => '/fastpath/logs/clear',
                'controller' => 'MauticPlugin\MauticAspectFileBundle\Controller\FastPathLogController::clearAction',
            ],
            'mautic_fastpath_log_export' => [
                'path' => '/fastpath/logs/export',
                'controller' => 'MauticPlugin\MauticAspectFileBundle\Controller\FastPathLogController::exportAction',
            ],
        ],
    ],

    'menu' => [
        'main' => [
            'items' => [
                'mautic.aspectfile.menu.root' => [
                    'route' => 'mautic_aspectfile_index',
                    'parent' => 'mautic.core.channels',
                    'priority' => 65,
                    'checks' => [
                        'integration' => [
                            'AspectFile' => [
                                'enabled' => true,
                            ],
                        ],
                    ],
                ],
                'mautic.aspectfile.menu.batches' => [
                    'route' => 'mautic_aspectfile_batch_index',
                    'parent' => 'mautic.core.channels',
                    'priority' => 64,
                    'checks' => [
                        'integration' => [
                            'AspectFile' => [
                                'enabled' => true,
                            ],
                        ],
                    ],
                ],
                'mautic.fastpath.menu.logs' => [
                    'route' => 'mautic_fastpath_log_index',
                    'parent' => 'mautic.core.channels',
                    'priority' => 63,
                    'checks' => [
                        'integration' => [
                            'AspectFile' => [
                                'enabled' => true,
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ],

    'services' => [
        'events' => [
            'mautic.aspectfile.campaign.subscriber' => [
                'class' => \MauticPlugin\MauticAspectFileBundle\EventListener\CampaignSubscriber::class,
                'arguments' => [
                    'mautic.aspectfile.model.aspectfile',
                    'monolog.logger.mautic',
                    'doctrine.orm.entity_manager',
                    'mautic.aspectfile.service.fastpath_sender',
                ],
            ],
            'mautic.aspectfile.assets.subscriber' => [
                'class' => \MauticPlugin\MauticAspectFileBundle\EventListener\AssetsSubscriber::class,
                'arguments' => [
                    'request_stack',
                ],
            ],
        ],
        'forms' => [
            'mautic.aspectfile.form.type.action' => [
                'class' => \MauticPlugin\MauticAspectFileBundle\Form\Type\AspectFileActionType::class,
                'arguments' => ['doctrine.orm.entity_manager'],
                'alias' => 'aspectfile_action',
            ],
            'mautic.aspectfile.form.type.fastpath_action' => [
                'class' => \MauticPlugin\MauticAspectFileBundle\Form\Type\FastPathActionType::class,
                'arguments' => ['doctrine.orm.entity_manager'],
                'alias' => 'fastpath_action',
            ],
            'mautic.aspectfile.form.type.schema' => [
                'class' => \MauticPlugin\MauticAspectFileBundle\Form\Type\SchemaType::class,
                'alias' => 'aspectfile_schema',
            ],
        ],
        'models' => [
            'mautic.aspectfile.model.aspectfile' => [
                'class' => \MauticPlugin\MauticAspectFileBundle\Model\AspectFileModel::class,
                'arguments' => [
                    'mautic.aspectfile.service.batch_manager',
                    'mautic.aspectfile.service.field_mapper',
                    'doctrine.orm.entity_manager',
                    'monolog.logger.mautic',
                ],
            ],
            'mautic.aspectfile.model.schema' => [
                'class' => \MauticPlugin\MauticAspectFileBundle\Model\SchemaModel::class,
                'arguments' => [
                    'doctrine.orm.entity_manager',
                    'mautic.security',
                    'event_dispatcher',
                    'router',
                    'translator',
                    'mautic.helper.user',
                    'monolog.logger.mautic',
                    'mautic.helper.core_parameters',
                ],
            ],
        ],
        'other' => [
            'mautic.aspectfile.service.batch_manager' => [
                'class' => \MauticPlugin\MauticAspectFileBundle\Service\BatchManager::class,
                'arguments' => [
                    'doctrine.orm.entity_manager',
                    'mautic.aspectfile.service.file_generator',
                    'mautic.aspectfile.service.minio_uploader',
                    'mautic.aspectfile.service.network_uploader',
                    'monolog.logger.mautic',
                    'mautic.aspectfile.service.field_mapper',
                    'mautic.lead.model.lead',
                ],
            ],
            'mautic.aspectfile.service.file_generator' => [
                'class' => \MauticPlugin\MauticAspectFileBundle\Service\FileGenerator::class,
                'arguments' => ['monolog.logger.mautic'],
            ],
            'mautic.aspectfile.service.field_mapper' => [
                'class' => \MauticPlugin\MauticAspectFileBundle\Service\FieldMapper::class,
                'arguments' => ['monolog.logger.mautic'],
            ],
            'mautic.aspectfile.service.minio_uploader' => [
                'class' => \MauticPlugin\MauticAspectFileBundle\Service\MinIOUploader::class,
                'arguments' => [
                    'mautic.helper.integration',
                    'monolog.logger.mautic',
                ],
            ],
            'mautic.aspectfile.service.network_uploader' => [
                'class' => \MauticPlugin\MauticAspectFileBundle\Service\NetworkUploader::class,
                'arguments' => ['monolog.logger.mautic'],
            ],
            'mautic.aspectfile.service.schema_parser' => [
                'class' => \MauticPlugin\MauticAspectFileBundle\Service\SchemaParser::class,
                'arguments' => ['monolog.logger.mautic'],
            ],
            'mautic.aspectfile.service.fastpath_sender' => [
                'class' => \MauticPlugin\MauticAspectFileBundle\Service\FastPathSender::class,
                'arguments' => [
                    'monolog.logger.mautic',
                    'mautic.aspectfile.service.field_mapper',
                    'mautic.aspectfile.service.file_generator',
                    'doctrine.orm.entity_manager',
                ],
            ],
        ],
        'controllers' => [
            'mautic.aspectfile.controller.schema' => [
                'class'     => \MauticPlugin\MauticAspectFileBundle\Controller\SchemaController::class,
                'arguments' => [
                    'mautic.aspectfile.model.schema',
                    'mautic.aspectfile.service.schema_parser',
                    'form.factory',
                    'router',
                    'translator',
                    'mautic.core.service.flashbag',
                    'twig',
                    'mautic.lead.model.field',
                    'doctrine.orm.entity_manager',
                ],
                'public' => true,
            ],
            'mautic.aspectfile.controller.batch' => [
                'class' => \MauticPlugin\MauticAspectFileBundle\Controller\BatchController::class,
                'arguments' => [
                    'doctrine',
                    'translator',
                    'mautic.core.service.flashbag',
                    'twig',
                    'mautic.aspectfile.model.aspectfile',
                    'router',
                ],
                'public' => true,
                'tags' => [
                    'controller.service_arguments',
                ],
            ],
            'mautic.aspectfile.controller.fastpath_log' => [
                'class' => \MauticPlugin\MauticAspectFileBundle\Controller\FastPathLogController::class,
                'arguments' => [
                    'doctrine',
                    'translator',
                    'mautic.core.service.flashbag',
                    'twig',
                    'router',
                ],
                'public' => true,
                'tags' => [
                    'controller.service_arguments',
                ],
            ],
        ],
        'commands' => [
            'mautic.aspectfile.command.process' => [
                'class' => \MauticPlugin\MauticAspectFileBundle\Command\ProcessAspectFilesCommand::class,
                'arguments' => ['mautic.aspectfile.model.aspectfile'],
                'tag' => 'console.command',
            ],
        ],
        'integrations' => [
            'mautic.integration.aspectfile' => [
                'class' => \MauticPlugin\MauticAspectFileBundle\Integration\AspectFileIntegration::class,
                'arguments' => [
                    'event_dispatcher',
                    'mautic.helper.cache_storage',
                    'doctrine.orm.entity_manager',
                    'request_stack',
                    'router',
                    'translator',
                    'monolog.logger.mautic',
                    'mautic.helper.encryption',
                    'mautic.lead.model.lead',
                    'mautic.lead.model.company',
                    'mautic.helper.paths',
                    'mautic.core.model.notification',
                    'mautic.lead.model.field',
                    'mautic.plugin.model.integration_entity',
                    'mautic.lead.model.dnc',
                    'mautic.lead.field.fields_with_unique_identifier',
                ],
            ],
        ],
    ],

    'parameters' => [
        'default_batch_size' => 1000,
        'default_time_window' => 300,
    ],
];
