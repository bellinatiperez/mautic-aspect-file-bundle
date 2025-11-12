<?php

declare(strict_types=1);

namespace MauticPlugin\MauticAspectFileBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Mautic\CoreBundle\Doctrine\Mapping\ClassMetadataBuilder;

/**
 * @ORM\Entity
 * @ORM\Table(name="aspect_file_batches")
 */
class AspectFileBatch
{
    public const STATUS_PENDING = 'PENDING';
    public const STATUS_GENERATING = 'GENERATING';
    public const STATUS_UPLOADING = 'UPLOADING';
    public const STATUS_UPLOADED = 'UPLOADED';
    public const STATUS_FAILED = 'FAILED';

    /**
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     * @ORM\Column(type="integer")
     */
    private ?int $id = null;

    /**
     * @ORM\ManyToOne(targetEntity="Schema")
     * @ORM\JoinColumn(name="schema_id", referencedColumnName="id", nullable=false)
     */
    private Schema $schema;

    /**
     * @ORM\Column(type="integer")
     */
    private int $campaignId;

    /**
     * @ORM\Column(type="integer")
     */
    private int $eventId;

    /**
     * @ORM\Column(type="string", length=191)
     */
    private string $bucketName;

    /**
     * @ORM\Column(type="string", length=191, nullable=true)
     */
    private ?string $fileName = null;

    /**
     * @ORM\Column(type="string", length=191, nullable=true)
     */
    private ?string $filePath = null;

    /**
     * @ORM\Column(type="string", length=191, nullable=true)
     */
    private ?string $fileNameTemplate = null;

    /**
     * @ORM\Column(type="string", length=191)
     */
    private string $status = self::STATUS_PENDING;

    /**
     * @ORM\Column(type="integer")
     */
    private int $leadsCount = 0;

    /**
     * @ORM\Column(type="bigint", nullable=true)
     */
    private ?int $fileSizeBytes = null;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     */
    private ?\DateTime $generatedAt = null;

    /**
     * @ORM\Column(type="datetime", nullable=true)
     */
    private ?\DateTime $uploadedAt = null;

    /**
     * @ORM\Column(type="text", nullable=true)
     */
    private ?string $errorMessage = null;

    /**
     * @ORM\Column(type="datetime")
     */
    private \DateTime $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
    }

    public static function loadMetadata(ORM\ClassMetadata $metadata): void
    {
        $builder = new ClassMetadataBuilder($metadata);

        $builder->setTable('aspect_file_batches');

        $builder->addId();

        $builder->createManyToOne('schema', Schema::class)
            ->addJoinColumn('schema_id', 'id', false)
            ->build();

        $builder->createField('campaignId', 'integer')
            ->columnName('campaign_id')
            ->build();

        $builder->createField('eventId', 'integer')
            ->columnName('event_id')
            ->build();

        $builder->createField('bucketName', 'string')
            ->columnName('bucket_name')
            ->length(191)
            ->build();

        $builder->createField('fileName', 'string')
            ->columnName('file_name')
            ->length(191)
            ->nullable()
            ->build();

        $builder->createField('filePath', 'string')
            ->columnName('file_path')
            ->length(191)
            ->nullable()
            ->build();

        $builder->createField('fileNameTemplate', 'string')
            ->columnName('file_name_template')
            ->length(191)
            ->nullable()
            ->build();

        $builder->createField('status', 'string')
            ->length(191)
            ->build();

        $builder->createField('leadsCount', 'integer')
            ->columnName('leads_count')
            ->build();

        $builder->createField('fileSizeBytes', 'bigint')
            ->columnName('file_size_bytes')
            ->nullable()
            ->build();

        $builder->createField('generatedAt', 'datetime')
            ->columnName('generated_at')
            ->nullable()
            ->build();

        $builder->createField('uploadedAt', 'datetime')
            ->columnName('uploaded_at')
            ->nullable()
            ->build();

        $builder->createField('errorMessage', 'text')
            ->columnName('error_message')
            ->nullable()
            ->build();

        $builder->createField('createdAt', 'datetime')
            ->columnName('created_at')
            ->build();
    }

    // Getters and Setters
    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSchema(): Schema
    {
        return $this->schema;
    }

    public function setSchema(Schema $schema): self
    {
        $this->schema = $schema;

        return $this;
    }

    public function getCampaignId(): int
    {
        return $this->campaignId;
    }

    public function setCampaignId(int $campaignId): self
    {
        $this->campaignId = $campaignId;

        return $this;
    }

    public function getEventId(): int
    {
        return $this->eventId;
    }

    public function setEventId(int $eventId): self
    {
        $this->eventId = $eventId;

        return $this;
    }

    public function getBucketName(): string
    {
        return $this->bucketName;
    }

    public function setBucketName(string $bucketName): self
    {
        $this->bucketName = $bucketName;

        return $this;
    }

    public function getFileName(): ?string
    {
        return $this->fileName;
    }

    public function setFileName(?string $fileName): self
    {
        $this->fileName = $fileName;

        return $this;
    }

    public function getFilePath(): ?string
    {
        return $this->filePath;
    }

    public function setFilePath(?string $filePath): self
    {
        $this->filePath = $filePath;

        return $this;
    }

    public function getFileNameTemplate(): ?string
    {
        return $this->fileNameTemplate;
    }

    public function setFileNameTemplate(?string $fileNameTemplate): self
    {
        $this->fileNameTemplate = $fileNameTemplate;

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

    public function getLeadsCount(): int
    {
        return $this->leadsCount;
    }

    public function setLeadsCount(int $leadsCount): self
    {
        $this->leadsCount = $leadsCount;

        return $this;
    }

    public function incrementLeadsCount(): self
    {
        ++$this->leadsCount;

        return $this;
    }

    public function getFileSizeBytes(): ?int
    {
        return $this->fileSizeBytes;
    }

    public function setFileSizeBytes(?int $fileSizeBytes): self
    {
        $this->fileSizeBytes = $fileSizeBytes;

        return $this;
    }

    public function getGeneratedAt(): ?\DateTime
    {
        return $this->generatedAt;
    }

    public function setGeneratedAt(?\DateTime $generatedAt): self
    {
        $this->generatedAt = $generatedAt;

        return $this;
    }

    public function getUploadedAt(): ?\DateTime
    {
        return $this->uploadedAt;
    }

    public function setUploadedAt(?\DateTime $uploadedAt): self
    {
        $this->uploadedAt = $uploadedAt;

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

    public function getCreatedAt(): \DateTime
    {
        return $this->createdAt;
    }
}
