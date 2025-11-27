<?php

declare(strict_types=1);

namespace MauticPlugin\MauticAspectFileBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Mautic\CoreBundle\Doctrine\Mapping\ClassMetadataBuilder;
use Mautic\LeadBundle\Entity\Lead;

/**
 * Entity to store FastPath SOAP dispatch logs
 */
class FastPathLog
{
    public const STATUS_SUCCESS = 'SUCCESS';
    public const STATUS_FAILED = 'FAILED';

    private ?int $id = null;

    private ?Lead $lead = null;

    private ?Schema $schema = null;

    private ?int $campaignId = null;

    private ?int $eventId = null;

    private string $wsdlUrl;

    private string $fastList;

    private int $functionType = 1;

    private string $messageId;

    private string $status = self::STATUS_SUCCESS;

    /**
     * The SOAP request XML payload
     */
    private ?string $requestPayload = null;

    /**
     * The SOAP response XML
     */
    private ?string $responsePayload = null;

    /**
     * Record line sent to FastPath
     */
    private ?string $recordLine = null;

    /**
     * Custom field values sent
     */
    private ?string $customFields = null;

    /**
     * Error message if failed
     */
    private ?string $errorMessage = null;

    /**
     * SOAP fault code if applicable
     */
    private ?string $faultCode = null;

    /**
     * Request duration in milliseconds
     */
    private ?int $durationMs = null;

    private \DateTime $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
    }

    public static function loadMetadata(ORM\ClassMetadata $metadata): void
    {
        $builder = new ClassMetadataBuilder($metadata);

        $builder->setTable('fastpath_logs');

        $builder->addId();

        $builder->createManyToOne('lead', Lead::class)
            ->addJoinColumn('lead_id', 'id', true, false, 'SET NULL')
            ->build();

        $builder->createManyToOne('schema', Schema::class)
            ->addJoinColumn('schema_id', 'id', true, false, 'SET NULL')
            ->build();

        $builder->createField('campaignId', 'integer')
            ->columnName('campaign_id')
            ->nullable()
            ->build();

        $builder->createField('eventId', 'integer')
            ->columnName('event_id')
            ->nullable()
            ->build();

        $builder->createField('wsdlUrl', 'string')
            ->columnName('wsdl_url')
            ->length(500)
            ->build();

        $builder->createField('fastList', 'string')
            ->columnName('fast_list')
            ->length(191)
            ->build();

        $builder->createField('functionType', 'integer')
            ->columnName('function_type')
            ->build();

        $builder->createField('messageId', 'string')
            ->columnName('message_id')
            ->length(100)
            ->build();

        $builder->createField('status', 'string')
            ->length(20)
            ->build();

        $builder->createField('requestPayload', 'text')
            ->columnName('request_payload')
            ->nullable()
            ->build();

        $builder->createField('responsePayload', 'text')
            ->columnName('response_payload')
            ->nullable()
            ->build();

        $builder->createField('recordLine', 'text')
            ->columnName('record_line')
            ->nullable()
            ->build();

        $builder->createField('customFields', 'text')
            ->columnName('custom_fields')
            ->nullable()
            ->build();

        $builder->createField('errorMessage', 'text')
            ->columnName('error_message')
            ->nullable()
            ->build();

        $builder->createField('faultCode', 'string')
            ->columnName('fault_code')
            ->length(100)
            ->nullable()
            ->build();

        $builder->createField('durationMs', 'integer')
            ->columnName('duration_ms')
            ->nullable()
            ->build();

        $builder->createField('createdAt', 'datetime')
            ->columnName('created_at')
            ->build();

        // Add indexes for common queries
        $builder->addIndex(['status'], 'idx_fastpath_log_status');
        $builder->addIndex(['created_at'], 'idx_fastpath_log_created');
        $builder->addIndex(['wsdl_url'], 'idx_fastpath_log_wsdl');
    }

    // Getters and Setters

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getLead(): ?Lead
    {
        return $this->lead;
    }

    public function setLead(?Lead $lead): self
    {
        $this->lead = $lead;

        return $this;
    }

    public function getSchema(): ?Schema
    {
        return $this->schema;
    }

    public function setSchema(?Schema $schema): self
    {
        $this->schema = $schema;

        return $this;
    }

    public function getCampaignId(): ?int
    {
        return $this->campaignId;
    }

    public function setCampaignId(?int $campaignId): self
    {
        $this->campaignId = $campaignId;

        return $this;
    }

    public function getEventId(): ?int
    {
        return $this->eventId;
    }

    public function setEventId(?int $eventId): self
    {
        $this->eventId = $eventId;

        return $this;
    }

    public function getWsdlUrl(): string
    {
        return $this->wsdlUrl;
    }

    public function setWsdlUrl(string $wsdlUrl): self
    {
        $this->wsdlUrl = $wsdlUrl;

        return $this;
    }

    public function getFastList(): string
    {
        return $this->fastList;
    }

    public function setFastList(string $fastList): self
    {
        $this->fastList = $fastList;

        return $this;
    }

    public function getFunctionType(): int
    {
        return $this->functionType;
    }

    public function setFunctionType(int $functionType): self
    {
        $this->functionType = $functionType;

        return $this;
    }

    public function getMessageId(): string
    {
        return $this->messageId;
    }

    public function setMessageId(string $messageId): self
    {
        $this->messageId = $messageId;

        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): self
    {
        $this->status = $status;

        return $this;
    }

    public function getRequestPayload(): ?string
    {
        return $this->requestPayload;
    }

    public function setRequestPayload(?string $requestPayload): self
    {
        $this->requestPayload = $requestPayload;

        return $this;
    }

    public function getResponsePayload(): ?string
    {
        return $this->responsePayload;
    }

    public function setResponsePayload(?string $responsePayload): self
    {
        $this->responsePayload = $responsePayload;

        return $this;
    }

    public function getRecordLine(): ?string
    {
        return $this->recordLine;
    }

    public function setRecordLine(?string $recordLine): self
    {
        $this->recordLine = $recordLine;

        return $this;
    }

    public function getCustomFields(): ?string
    {
        return $this->customFields;
    }

    public function setCustomFields(?string $customFields): self
    {
        $this->customFields = $customFields;

        return $this;
    }

    public function getCustomFieldsArray(): array
    {
        if (empty($this->customFields)) {
            return [];
        }

        $decoded = json_decode($this->customFields, true);

        return is_array($decoded) ? $decoded : [];
    }

    public function setCustomFieldsFromArray(array $fields): self
    {
        $this->customFields = json_encode($fields);

        return $this;
    }

    public function getErrorMessage(): ?string
    {
        return $this->errorMessage;
    }

    public function setErrorMessage(?string $errorMessage): self
    {
        $this->errorMessage = $errorMessage;

        return $this;
    }

    public function getFaultCode(): ?string
    {
        return $this->faultCode;
    }

    public function setFaultCode(?string $faultCode): self
    {
        $this->faultCode = $faultCode;

        return $this;
    }

    public function getDurationMs(): ?int
    {
        return $this->durationMs;
    }

    public function setDurationMs(?int $durationMs): self
    {
        $this->durationMs = $durationMs;

        return $this;
    }

    public function getCreatedAt(): \DateTime
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTime $createdAt): self
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function isSuccess(): bool
    {
        return $this->status === self::STATUS_SUCCESS;
    }

    public function isFailed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }

    /**
     * Get environment name from WSDL URL
     */
    public function getEnvironment(): string
    {
        // Extract hostname from WSDL URL
        $parsed = parse_url($this->wsdlUrl);
        $host = $parsed['host'] ?? 'unknown';

        // Common environment patterns
        if (str_contains($host, 'prod') || str_contains($host, 'prd')) {
            return 'PRODUCTION';
        }
        if (str_contains($host, 'hom') || str_contains($host, 'homolog')) {
            return 'HOMOLOGATION';
        }
        if (str_contains($host, 'dev') || str_contains($host, 'develop')) {
            return 'DEVELOPMENT';
        }
        if (str_contains($host, 'test') || str_contains($host, 'tst')) {
            return 'TEST';
        }
        if (str_contains($host, 'local')) {
            return 'LOCAL';
        }

        return $host;
    }
}
