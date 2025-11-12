<?php

declare(strict_types=1);

namespace MauticPlugin\MauticAspectFileBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Mautic\CoreBundle\Doctrine\Mapping\ClassMetadataBuilder;

/**
 * @ORM\Entity
 * @ORM\Table(name="aspect_file_schemas")
 */
class Schema
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     * @ORM\Column(type="integer")
     */
    private ?int $id = null;

    /**
     * @ORM\Column(type="string", length=191)
     */
    private string $name;

    /**
     * @ORM\Column(type="text", nullable=true)
     */
    private ?string $description = null;

    /**
     * @ORM\Column(type="json")
     */
    private array $fields = [];

    /**
     * @ORM\Column(type="string", length=10)
     */
    private string $fileExtension = 'raw';

    /**
     * @ORM\Column(type="integer", nullable=true)
     */
    private ?int $lineLength = null;

    /**
     * @ORM\Column(type="boolean")
     */
    private bool $isPublished = true;

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

        $builder->setTable('aspect_file_schemas');

        $builder->addId();

        $builder->createField('name', 'string')
            ->length(191)
            ->build();

        $builder->createField('description', 'text')
            ->nullable()
            ->build();

        $builder->createField('fields', 'json')
            ->build();

        $builder->createField('fileExtension', 'string')
            ->columnName('file_extension')
            ->length(10)
            ->build();

        $builder->createField('lineLength', 'integer')
            ->columnName('line_length')
            ->nullable()
            ->build();

        $builder->createField('isPublished', 'boolean')
            ->columnName('is_published')
            ->build();

        $builder->createField('createdAt', 'datetime')
            ->columnName('created_at')
            ->build();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): self
    {
        $this->description = $description;

        return $this;
    }

    public function getFields(): array
    {
        return $this->fields;
    }

    public function setFields(array $fields): self
    {
        $this->fields = $fields;

        return $this;
    }

    public function getFileExtension(): string
    {
        return $this->fileExtension;
    }

    public function setFileExtension(string $fileExtension): self
    {
        $this->fileExtension = $fileExtension;

        return $this;
    }

    public function getLineLength(): ?int
    {
        return $this->lineLength;
    }

    public function setLineLength(?int $lineLength): self
    {
        $this->lineLength = $lineLength;

        return $this;
    }

    public function getIsPublished(): bool
    {
        return $this->isPublished;
    }

    public function setIsPublished(bool $isPublished): self
    {
        $this->isPublished = $isPublished;

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

    /**
     * Calculate line length from fields
     */
    public function calculateLineLength(): int
    {
        $maxPosition = 0;
        foreach ($this->fields as $field) {
            $endPosition = ($field['start_position'] ?? 0) + ($field['length'] ?? 0);
            if ($endPosition > $maxPosition) {
                $maxPosition = $endPosition;
            }
        }

        return $maxPosition;
    }
}
