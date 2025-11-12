<?php

declare(strict_types=1);

namespace MauticPlugin\MauticAspectFileBundle\Service;

use Aws\S3\S3Client;
use Mautic\PluginBundle\Helper\IntegrationHelper;
use MauticPlugin\MauticAspectFileBundle\Integration\AspectFileIntegration;
use Psr\Log\LoggerInterface;

/**
 * Service to upload files to MinIO/S3
 */
class MinIOUploader
{
    private IntegrationHelper $integrationHelper;
    private LoggerInterface $logger;

    public function __construct(
        IntegrationHelper $integrationHelper,
        LoggerInterface $logger
    ) {
        $this->integrationHelper = $integrationHelper;
        $this->logger = $logger;
    }

    /**
     * Upload file to MinIO/S3
     *
     * @param string $localFilePath Local file path
     * @param string $bucket Bucket name
     * @param string $key Object key (file name in bucket)
     * @return array{success: bool, url?: string, error?: string}
     */
    public function upload(string $localFilePath, string $bucket, string $key): array
    {
        try {
            /** @var AspectFileIntegration $integration */
            $integration = $this->integrationHelper->getIntegrationObject('AspectFile');

            if (!$integration || !$integration->getIntegrationSettings()->getIsPublished()) {
                throw new \RuntimeException('AspectFile integration is not configured or published');
            }

            $endpoint = $integration->getMinIOEndpoint();
            $accessKey = $integration->getMinIOAccessKey();
            $secretKey = $integration->getMinIOSecretKey();
            $region = $integration->getMinIORegion();
            $useSSL = $integration->useSSL();

            if (!$endpoint || !$accessKey || !$secretKey) {
                throw new \RuntimeException('MinIO credentials are not configured');
            }

            $this->logger->info('AspectFile: Uploading to MinIO', [
                'endpoint' => $endpoint,
                'bucket' => $bucket,
                'key' => $key,
                'file_size' => filesize($localFilePath),
            ]);

            // Configure S3 client for MinIO
            $s3Client = new S3Client([
                'version' => 'latest',
                'region' => $region,
                'endpoint' => $endpoint,
                'use_path_style_endpoint' => true,
                'credentials' => [
                    'key' => $accessKey,
                    'secret' => $secretKey,
                ],
            ]);

            // Upload file
            $result = $s3Client->putObject([
                'Bucket' => $bucket,
                'Key' => $key,
                'SourceFile' => $localFilePath,
                'ContentType' => 'text/plain',
            ]);

            $objectUrl = $result['ObjectURL'] ?? "{$endpoint}/{$bucket}/{$key}";

            $this->logger->info('AspectFile: File uploaded successfully', [
                'bucket' => $bucket,
                'key' => $key,
                'url' => $objectUrl,
            ]);

            return [
                'success' => true,
                'url' => $objectUrl,
            ];
        } catch (\Aws\S3\Exception\S3Exception $e) {
            $this->logger->error('AspectFile: MinIO upload failed (S3 error)', [
                'bucket' => $bucket,
                'key' => $key,
                'error' => $e->getMessage(),
                'aws_error_code' => $e->getAwsErrorCode(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        } catch (\Exception $e) {
            $this->logger->error('AspectFile: MinIO upload failed', [
                'bucket' => $bucket,
                'key' => $key,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }
}
