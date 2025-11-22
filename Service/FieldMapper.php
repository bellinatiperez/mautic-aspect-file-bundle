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

            // Handle computed/special fields first
            $value = $this->getComputedFieldValue($lead, $leadField);

            // If not a computed field, try standard methods
            if (null === $value || '' === $value) {
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

    /**
     * Get value for computed/special fields that don't exist as database columns
     *
     * @param Lead $lead
     * @param string $fieldName
     * @return string|null
     */
    private function getComputedFieldValue(Lead $lead, string $fieldName): ?string
    {
        switch (strtolower($fieldName)) {
            case 'fullname':
            case 'full_name':
                // Compute fullname from firstname + lastname
                $firstname = $lead->getFirstname() ?? '';
                $lastname = $lead->getLastname() ?? '';
                $fullname = trim($firstname . ' ' . $lastname);
                return $fullname !== '' ? $fullname : null;

            case 'name':
                // Alias for fullname
                $firstname = $lead->getFirstname() ?? '';
                $lastname = $lead->getLastname() ?? '';
                $name = trim($firstname . ' ' . $lastname);
                return $name !== '' ? $name : null;

            default:
                // Not a computed field
                return null;
        }
    }
}
