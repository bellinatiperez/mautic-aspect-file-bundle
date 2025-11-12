<?php

declare(strict_types=1);

namespace MauticPlugin\MauticAspectFileBundle\Integration;

use Doctrine\ORM\EntityManager;
use Mautic\CoreBundle\Helper\CacheStorageHelper;
use Mautic\CoreBundle\Helper\EncryptionHelper;
use Mautic\CoreBundle\Helper\PathsHelper;
use Mautic\CoreBundle\Model\NotificationModel;
use Mautic\LeadBundle\Field\FieldsWithUniqueIdentifier;
use Mautic\LeadBundle\Model\CompanyModel;
use Mautic\LeadBundle\Model\DoNotContact as DoNotContactModel;
use Mautic\LeadBundle\Model\FieldModel;
use Mautic\LeadBundle\Model\LeadModel;
use Mautic\PluginBundle\Integration\AbstractIntegration;
use Mautic\PluginBundle\Model\IntegrationEntityModel;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * AspectFile Integration for plugin configuration
 */
class AspectFileIntegration extends AbstractIntegration
{
    public function __construct(
        EventDispatcherInterface $dispatcher,
        CacheStorageHelper $cacheStorageHelper,
        EntityManager $em,
        RequestStack $requestStack,
        RouterInterface $router,
        TranslatorInterface $translator,
        LoggerInterface $logger,
        EncryptionHelper $encryptionHelper,
        LeadModel $leadModel,
        CompanyModel $companyModel,
        PathsHelper $pathsHelper,
        NotificationModel $notificationModel,
        FieldModel $fieldModel,
        IntegrationEntityModel $integrationEntityModel,
        DoNotContactModel $doNotContact,
        FieldsWithUniqueIdentifier $fieldsWithUniqueIdentifier,
    ) {
        parent::__construct(
            $dispatcher,
            $cacheStorageHelper,
            $em,
            $requestStack,
            $router,
            $translator,
            $logger,
            $encryptionHelper,
            $leadModel,
            $companyModel,
            $pathsHelper,
            $notificationModel,
            $fieldModel,
            $integrationEntityModel,
            $doNotContact,
            $fieldsWithUniqueIdentifier,
        );
    }

    public function getName(): string
    {
        return 'AspectFile';
    }

    public function getDisplayName(): string
    {
        return 'Aspect File Generator';
    }

    public function getAuthenticationType(): string
    {
        return 'none';
    }

    /**
     * @return array<string>
     */
    public function getSupportedFeatures(): array
    {
        return [];
    }

    /**
     * @return array<string, string>
     */
    public function getRequiredKeyFields(): array
    {
        return [];
    }

    /**
     * @return array<string, mixed>
     */
    public function getFormSettings(): array
    {
        return [
            'requires_callback' => false,
            'requires_authorization' => false,
        ];
    }

    /**
     * Add configuration fields
     */
    public function appendToForm(&$builder, $data, $formArea): void
    {
        if ('features' === $formArea) {
            // MinIO/S3 Configuration
            $builder->add(
                'minio_endpoint',
                TextType::class,
                [
                    'label' => 'MinIO/S3 Endpoint',
                    'label_attr' => ['class' => 'control-label'],
                    'required' => true,
                    'data' => $data['minio_endpoint'] ?? 'http://localhost:9000',
                    'attr' => [
                        'class' => 'form-control',
                        'tooltip' => 'MinIO/S3 API endpoint (e.g., http://localhost:9000)',
                        'placeholder' => 'http://localhost:9000',
                    ],
                ]
            );

            $builder->add(
                'minio_access_key',
                TextType::class,
                [
                    'label' => 'Access Key',
                    'label_attr' => ['class' => 'control-label'],
                    'required' => true,
                    'data' => $data['minio_access_key'] ?? '',
                    'attr' => [
                        'class' => 'form-control',
                        'tooltip' => 'MinIO/S3 Access Key',
                    ],
                ]
            );

            $builder->add(
                'minio_secret_key',
                TextType::class,
                [
                    'label' => 'Secret Key',
                    'label_attr' => ['class' => 'control-label'],
                    'required' => true,
                    'data' => $data['minio_secret_key'] ?? '',
                    'attr' => [
                        'class' => 'form-control',
                        'tooltip' => 'MinIO/S3 Secret Key',
                    ],
                ]
            );

            $builder->add(
                'minio_region',
                TextType::class,
                [
                    'label' => 'Region',
                    'label_attr' => ['class' => 'control-label'],
                    'required' => false,
                    'data' => $data['minio_region'] ?? 'us-east-1',
                    'attr' => [
                        'class' => 'form-control',
                        'tooltip' => 'AWS S3 Region',
                    ],
                ]
            );

            // Batch Processing Configuration
            $builder->add(
                'default_batch_size',
                IntegerType::class,
                [
                    'label' => 'Default Batch Size',
                    'label_attr' => ['class' => 'control-label'],
                    'required' => false,
                    'data' => $data['default_batch_size'] ?? 1000,
                    'attr' => [
                        'class' => 'form-control',
                        'tooltip' => 'Number of leads to process per batch',
                        'min' => 1,
                        'max' => 10000,
                    ],
                ]
            );

            $builder->add(
                'default_time_window',
                IntegerType::class,
                [
                    'label' => 'Default Time Window (seconds)',
                    'label_attr' => ['class' => 'control-label'],
                    'required' => false,
                    'data' => $data['default_time_window'] ?? 300,
                    'attr' => [
                        'class' => 'form-control',
                        'tooltip' => 'Time window in seconds before processing batch',
                        'min' => 60,
                        'max' => 3600,
                    ],
                ]
            );
        }
    }

    /**
     * Get MinIO endpoint from integration settings
     */
    public function getMinIOEndpoint(): ?string
    {
        $featureSettings = $this->getIntegrationSettings()->getFeatureSettings();

        return $featureSettings['minio_endpoint'] ?? null;
    }

    /**
     * Get MinIO access key
     */
    public function getMinIOAccessKey(): ?string
    {
        $featureSettings = $this->getIntegrationSettings()->getFeatureSettings();

        return $featureSettings['minio_access_key'] ?? null;
    }

    /**
     * Get MinIO secret key
     */
    public function getMinIOSecretKey(): ?string
    {
        $featureSettings = $this->getIntegrationSettings()->getFeatureSettings();

        return $featureSettings['minio_secret_key'] ?? null;
    }

    /**
     * Get MinIO region
     */
    public function getMinIORegion(): string
    {
        $featureSettings = $this->getIntegrationSettings()->getFeatureSettings();

        return $featureSettings['minio_region'] ?? 'us-east-1';
    }

    /**
     * Check if SSL should be used
     */
    public function useSSL(): bool
    {
        $endpoint = $this->getMinIOEndpoint();

        return $endpoint && str_starts_with($endpoint, 'https://');
    }

    /**
     * Get default batch size from integration settings
     */
    public function getDefaultBatchSize(): int
    {
        $featureSettings = $this->getIntegrationSettings()->getFeatureSettings();

        return (int) ($featureSettings['default_batch_size'] ?? 1000);
    }

    /**
     * Get default time window from integration settings
     */
    public function getDefaultTimeWindow(): int
    {
        $featureSettings = $this->getIntegrationSettings()->getFeatureSettings();

        return (int) ($featureSettings['default_time_window'] ?? 300);
    }
}
