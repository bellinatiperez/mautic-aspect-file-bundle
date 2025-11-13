<?php

declare(strict_types=1);

namespace MauticPlugin\MauticAspectFileBundle\Service;

use Psr\Log\LoggerInterface;

/**
 * Service to upload files to network directories (SMB, NFS, local mounts)
 */
class NetworkUploader
{
    private LoggerInterface $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    /**
     * Upload file to network directory
     *
     * @param string $localFilePath Local file path
     * @param string $networkPath Network directory path (e.g., /mnt/share/uploads or \\server\share\uploads)
     * @param string $fileName Destination file name
     * @return array ['success' => bool, 'path' => string|null, 'error' => string|null]
     */
    public function upload(string $localFilePath, string $networkPath, string $fileName): array
    {
        try {
            // Validate local file exists
            if (!file_exists($localFilePath)) {
                throw new \RuntimeException("Local file not found: {$localFilePath}");
            }

            if (!is_readable($localFilePath)) {
                throw new \RuntimeException("Local file is not readable: {$localFilePath}");
            }

            // Normalize network path (remove trailing slashes)
            $networkPath = rtrim($networkPath, '/\\');

            // Validate network directory exists and is writable
            if (!is_dir($networkPath)) {
                throw new \RuntimeException("Network directory does not exist: {$networkPath}");
            }

            if (!is_writable($networkPath)) {
                throw new \RuntimeException("Network directory is not writable: {$networkPath}");
            }

            // Build destination path
            $destinationPath = $networkPath . DIRECTORY_SEPARATOR . $fileName;

            // Check if file already exists
            if (file_exists($destinationPath)) {
                $this->logger->warning('AspectFile: Network file already exists, will overwrite', [
                    'destination' => $destinationPath,
                ]);
            }

            // Copy file to network directory
            $result = copy($localFilePath, $destinationPath);

            if (!$result) {
                throw new \RuntimeException("Failed to copy file to network directory");
            }

            // Verify file was copied successfully
            if (!file_exists($destinationPath)) {
                throw new \RuntimeException("File was not found after copy operation");
            }

            $sourceSize = filesize($localFilePath);
            $destSize = filesize($destinationPath);

            if ($sourceSize !== $destSize) {
                throw new \RuntimeException(
                    "File size mismatch after copy (source: {$sourceSize}, dest: {$destSize})"
                );
            }

            $this->logger->info('AspectFile: File uploaded to network directory', [
                'source' => $localFilePath,
                'destination' => $destinationPath,
                'size' => $destSize,
            ]);

            return [
                'success' => true,
                'path' => $destinationPath,
                'error' => null,
            ];
        } catch (\Exception $e) {
            $this->logger->error('AspectFile: Network upload failed', [
                'local_file' => $localFilePath,
                'network_path' => $networkPath,
                'file_name' => $fileName,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'path' => null,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Check if network path is accessible
     *
     * @param string $networkPath Network directory path
     * @return array ['accessible' => bool, 'writable' => bool, 'error' => string|null]
     */
    public function checkAccess(string $networkPath): array
    {
        try {
            $networkPath = rtrim($networkPath, '/\\');

            if (!file_exists($networkPath)) {
                return [
                    'accessible' => false,
                    'writable' => false,
                    'error' => 'Path does not exist',
                ];
            }

            if (!is_dir($networkPath)) {
                return [
                    'accessible' => false,
                    'writable' => false,
                    'error' => 'Path is not a directory',
                ];
            }

            $accessible = is_readable($networkPath);
            $writable = is_writable($networkPath);

            return [
                'accessible' => $accessible,
                'writable' => $writable,
                'error' => null,
            ];
        } catch (\Exception $e) {
            return [
                'accessible' => false,
                'writable' => false,
                'error' => $e->getMessage(),
            ];
        }
    }
}
