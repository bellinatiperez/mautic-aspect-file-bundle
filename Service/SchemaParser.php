<?php

declare(strict_types=1);

namespace MauticPlugin\MauticAspectFileBundle\Service;

use Psr\Log\LoggerInterface;

/**
 * Service to parse schema files (Excel)
 *
 * Expected Excel format:
 * | No. | Name      | Data Type | Access Type | Offset | Length |
 * | 1   | cdBanco   | STRING    | READ-WRITE  | 1      | 11     |
 */
class SchemaParser
{
    private LoggerInterface $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    /**
     * Parse Excel file to schema format
     *
     * @param string $filePath Path to Excel file
     * @return array{success: bool, fields?: array, error?: string}
     */
    public function parseExcelFile(string $filePath): array
    {
        try {
            if (!file_exists($filePath)) {
                throw new \RuntimeException("File not found: {$filePath}");
            }

            // Check if PhpSpreadsheet is available
            if (!class_exists(\PhpOffice\PhpSpreadsheet\IOFactory::class)) {
                throw new \RuntimeException('PhpSpreadsheet library is not installed. Please run: composer require phpoffice/phpspreadsheet');
            }

            $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($filePath);
            $worksheet = $spreadsheet->getActiveSheet();

            $fields = [];
            $headerRow = $this->findHeaderRow($worksheet);

            if (!$headerRow) {
                throw new \RuntimeException('Could not find header row in Excel file');
            }

            // Map column names to indices
            $columnMap = $this->mapColumns($worksheet, $headerRow);

            $this->logger->info('AspectFile: Column mapping', $columnMap);

            // Read data rows
            $rowIndex = $headerRow + 1;
            $fieldIndex = 1; // Sequential index for field mapping
            while (true) {
                $noValue = $worksheet->getCell($columnMap['no'] . $rowIndex)->getValue();
                $no = trim((string) $noValue);

                // Stop if empty number (end of data)
                if (empty($no)) {
                    break;
                }

                $nameValue = $worksheet->getCell($columnMap['name'] . $rowIndex)->getValue();
                $name = trim((string) $nameValue);

                if (empty($name)) {
                    break;
                }

                $field = [
                    'no' => $fieldIndex,
                    'name' => $name,
                    'start_position' => (int) ($worksheet->getCell($columnMap['offset'] . $rowIndex)->getValue() ?? 0),
                    'length' => (int) ($worksheet->getCell($columnMap['length'] . $rowIndex)->getValue() ?? 0),
                    'data_type' => strtoupper(trim((string) ($worksheet->getCell($columnMap['data_type'] . $rowIndex)->getValue() ?? 'STRING'))),
                    'access_type' => strtoupper(trim((string) ($worksheet->getCell($columnMap['access_type'] . $rowIndex)->getValue() ?? 'READ-ONLY'))),
                    'lead_field' => '', // Will be mapped manually
                    'padding_type' => 'RIGHT',
                    'padding_char' => ' ',
                    'alignment' => 'LEFT',
                    'format' => '',
                ];

                // Validation
                if ($field['start_position'] < 1) {
                    $this->logger->warning('AspectFile: Invalid start_position for field', [
                        'field' => $field['name'],
                        'start_position' => $field['start_position'],
                    ]);
                    continue;
                }

                if ($field['length'] < 1) {
                    $this->logger->warning('AspectFile: Invalid length for field', [
                        'field' => $field['name'],
                        'length' => $field['length'],
                    ]);
                    continue;
                }

                $fields[] = $field;
                ++$rowIndex;
                ++$fieldIndex;
            }

            $this->logger->info('AspectFile: Excel parsed successfully', [
                'file_path' => $filePath,
                'fields_count' => count($fields),
            ]);

            return [
                'success' => true,
                'fields' => $fields,
            ];
        } catch (\Exception $e) {
            $this->logger->error('AspectFile: Excel parsing failed', [
                'file_path' => $filePath,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Find header row
     */
    private function findHeaderRow($worksheet): ?int
    {
        // Try first 10 rows
        for ($row = 1; $row <= 10; ++$row) {
            $cellA = strtolower(trim((string) $worksheet->getCell("A{$row}")->getValue()));
            $cellB = strtolower(trim((string) $worksheet->getCell("B{$row}")->getValue()));

            // Look for "No." and "Name" or "No" and "Name"
            if ((str_contains($cellA, 'no') || $cellA === '#') &&
                (str_contains($cellB, 'name') || str_contains($cellB, 'campo'))) {
                return $row;
            }
        }

        return null;
    }

    /**
     * Map column headers to Excel column letters
     *
     * Fixed mapping: A=No, B=Name, C=DataType, D=AccessType, E=Offset, F=Length
     *
     * @param \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $worksheet
     * @param int $headerRow
     * @return array<string, string>
     */
    private function mapColumns($worksheet, int $headerRow): array
    {
        // FIXED mapping - always use columns A through F
        // These are the determinant columns for AspectFile and FastPath schemas
        $map = [
            'no' => 'A',           // Column A: No. / Number
            'name' => 'B',         // Column B: Name / Field Name
            'data_type' => 'C',    // Column C: Data Type
            'access_type' => 'D',  // Column D: Access Type
            'offset' => 'E',       // Column E: Offset / Start Position
            'length' => 'F',       // Column F: Length / Field Length
        ];

        // Log header names for debugging
        $headerNames = [];
        for ($col = 'A'; $col <= 'F'; ++$col) {
            $cellValue = $worksheet->getCell($col . $headerRow)->getValue();
            $value = trim((string) $cellValue);
            if (!empty($value)) {
                $headerNames[$col] = $value;
            }
        }

        $this->logger->info('AspectFile: Using FIXED column mapping (A-F)', [
            'mapping' => $map,
            'headers_found' => $headerNames,
        ]);

        return $map;
    }
}
