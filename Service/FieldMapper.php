<?php

declare(strict_types=1);

namespace MauticPlugin\MauticAspectFileBundle\Service;

use Mautic\LeadBundle\Entity\Lead;
use MauticPlugin\MauticAspectFileBundle\Entity\Schema;
use Psr\Log\LoggerInterface;

/**
 * Service to map Lead data to Schema fields
 */
class FieldMapper
{
    private LoggerInterface $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    /**
     * Map a Lead to schema field values
     *
     * @param Lead $lead
     * @param Schema $schema
     * @return array<string, mixed> Mapped field values
     */
    public function mapLeadToSchema(Lead $lead, Schema $schema): array
    {
        $fields = $schema->getFields();
        $mappedData = [];

        foreach ($fields as $field) {
            $leadField = $field['lead_field'] ?? '';

            if (empty($leadField)) {
                continue; // Skip fields without mapping
            }

            // Get value from lead
            $value = '';

            // Try different methods to get the value
            try {
                // Method 1: Try getter method first (works for core fields like firstname, lastname, email)
                $getter = 'get' . str_replace('_', '', ucwords($leadField, '_'));
                if (method_exists($lead, $getter)) {
                    $value = $lead->$getter();
                }

                // Method 2: If empty, try getFieldValue (works for custom fields)
                if (null === $value || '' === $value) {
                    $value = $lead->getFieldValue($leadField);
                }

                // Method 3: If still empty, try direct property access via getFields()
                if (null === $value || '' === $value) {
                    $allFields = $lead->getFields();
                    if (isset($allFields['all'][$leadField])) {
                        $value = $allFields['all'][$leadField];
                    }
                }
            } catch (\Exception $e) {
                $this->logger->warning('AspectFile: Failed to get lead field value', [
                    'lead_id' => $lead->getId(),
                    'field' => $leadField,
                    'error' => $e->getMessage(),
                ]);
            }

            // Convert to string and handle arrays
            if (is_array($value)) {
                $value = implode(', ', $value);
            }
            $value = (string) ($value ?? '');

            // Store mapped value
            $mappedData[$leadField] = $value;
        }

        return $mappedData;
    }
}
