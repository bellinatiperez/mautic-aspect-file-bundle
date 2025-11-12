<?php

declare(strict_types=1);

namespace MauticPlugin\MauticAspectFileBundle\Service;

use MauticPlugin\MauticAspectFileBundle\Entity\Schema;
use Psr\Log\LoggerInterface;

/**
 * Service to generate fixed-width text files
 */
class FileGenerator
{
    private LoggerInterface $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    /**
     * Generate a single line based on schema
     *
     * Uses array-based approach to avoid string memory issues
     *
     * @param Schema $schema
     * @param array<string, mixed> $data Lead field values
     * @return string Fixed-width line
     */
    public function generateLine(Schema $schema, array $data): string
    {
        $fields = $schema->getFields();
        $lineLength = $schema->getLineLength() ?? $schema->calculateLineLength();

        // Use array of characters to avoid string concatenation memory issues
        $line = array_fill(0, $lineLength, ' ');

        foreach ($fields as $field) {
            $leadField = $field['lead_field'] ?? '';
            if (empty($leadField)) {
                continue; // Skip fields without mapping
            }

            $value = $data[$leadField] ?? '';
            $formattedValue = $this->formatValue($value, $field);

            // Insert value into array
            $this->insertValue($line, $formattedValue, $field);
        }

        return implode('', $line);
    }

    /**
     * Format value according to field configuration
     *
     * @param mixed $value
     * @param array{name: string, start_position: int, length: int, lead_field: string, padding_type: string, padding_char: string, alignment: string, data_type: string, format?: string} $field
     * @return string
     */
    private function formatValue($value, array $field): string
    {
        // Convert to string
        $stringValue = (string) $value;

        // Apply data type formatting
        switch ($field['data_type'] ?? 'STRING') {
            case 'DATE':
                $stringValue = $this->formatDate($value, $field['format'] ?? 'Ymd');
                break;

            case 'NUMBER':
                $stringValue = $this->formatNumber($value, $field);
                break;

            case 'STRING':
            default:
                // Remove line breaks and tabs
                $stringValue = str_replace(["\r", "\n", "\t"], ' ', $stringValue);
                break;
        }

        // Truncate if longer than field length
        $maxLength = $field['length'] ?? 0;
        if (mb_strlen($stringValue) > $maxLength) {
            $stringValue = mb_substr($stringValue, 0, $maxLength);
        }

        // Apply padding
        $paddingType = $field['padding_type'] ?? 'RIGHT';
        $paddingChar = $field['padding_char'] ?? ' ';

        if ('LEFT' === $paddingType) {
            $stringValue = str_pad($stringValue, $maxLength, $paddingChar, STR_PAD_LEFT);
        } else {
            $stringValue = str_pad($stringValue, $maxLength, $paddingChar, STR_PAD_RIGHT);
        }

        return $stringValue;
    }

    /**
     * Format date value
     */
    private function formatDate($value, string $format): string
    {
        if (empty($value)) {
            return '';
        }

        if ($value instanceof \DateTime) {
            return $value->format($format);
        }

        try {
            $date = new \DateTime($value);

            return $date->format($format);
        } catch (\Exception $e) {
            return (string) $value;
        }
    }

    /**
     * Format number value
     */
    private function formatNumber($value, array $field): string
    {
        if (empty($value) && '0' !== (string) $value) {
            return '';
        }

        // Remove non-numeric characters except decimal point
        $cleaned = preg_replace('/[^0-9.]/', '', (string) $value);

        // Pad with zeros on the left if needed
        if (isset($field['zero_fill']) && $field['zero_fill']) {
            $length = $field['length'] ?? strlen($cleaned);
            $cleaned = str_pad($cleaned, $length, '0', STR_PAD_LEFT);
        }

        return $cleaned;
    }

    /**
     * Insert value into line array at specified position
     *
     * @param array $line Current line (array of characters)
     * @param string $value Formatted value
     * @param array{start_position: int, length: int} $field
     */
    private function insertValue(array &$line, string $value, array $field): void
    {
        $start = ($field['start_position'] ?? 1) - 1; // Convert to 0-based index
        $length = $field['length'] ?? 0;

        // Validate indices
        if ($start < 0 || $start >= count($line)) {
            $this->logger->warning('AspectFile: Invalid start_position', [
                'field' => $field['name'] ?? 'unknown',
                'start_position' => $field['start_position'] ?? 0,
                'line_length' => count($line),
            ]);

            return;
        }

        // Ensure value fits the field length
        $value = mb_substr($value, 0, $length);

        // Insert character by character
        $valueLength = mb_strlen($value);
        for ($i = 0; $i < $valueLength && ($start + $i) < count($line); ++$i) {
            $line[$start + $i] = mb_substr($value, $i, 1);
        }
    }

    /**
     * Generate temporary file path
     */
    public function generateTempFilePath(string $fileName, string $extension): string
    {
        $tempDir = sys_get_temp_dir() . '/mautic_aspectfile';

        if (!is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        $timestamp = date('YmdHis');
        $uniqueId = uniqid();

        return "{$tempDir}/{$fileName}_{$timestamp}_{$uniqueId}.{$extension}";
    }
}
