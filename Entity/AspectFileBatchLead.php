<?php

declare(strict_types=1);

namespace MauticPlugin\MauticAspectFileBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Mautic\CoreBundle\Doctrine\Mapping\ClassMetadataBuilder;
use Mautic\LeadBundle\Entity\Lead;

/**
 * @ORM\Entity
 * @ORM\Table(name="aspect_file_batch_leads")
 */
class AspectFileBatchLead
{
    public const STATUS_PENDING = 'PENDING';
    public const STATUS_GENERATED = 'GENERATED';
    public const STATUS_FAILED = 'FAILED';

    /**
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     * @ORM\Column(type="integer")
     */
    private ?int $id = null;

    /**
     * @ORM\ManyToOne(targetEntity="AspectFileBatch")
     * @ORM\JoinColumn(name="batch_id", referencedColumnName="id", nullable=false)
     */
    private AspectFileBatch $batch;

    /**
     * @ORM\ManyToOne(targetEntity="Mautic\LeadBundle\Entity\Lead")
     * @ORM\JoinColumn(name="lead_id", referencedColumnName="id", nullable=false)
     */
    private Lead $lead;

    /**
     * @ORM\Column(type="string", length=191)
     */
    private string $status = self::STATUS_PENDING;

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

        $builder->setTable('aspect_file_batch_leads');

        $builder->addId();

        $builder->createManyToOne('batch', AspectFileBatch::class)
            ->addJoinColumn('batch_id', 'id', false)
            ->build();

        $builder->createManyToOne('lead', Lead::class)
            ->addJoinColumn('lead_id', 'id', false)
            ->build();

        $builder->createField('status', 'string')
            ->length(191)
            ->build();

        $builder->createField('createdAt', 'datetime')
            ->columnName('created_at')
            ->build();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getBatch(): AspectFileBatch
    {
        return $this->batch;
    }

    public function setBatch(AspectFileBatch $batch): self
    {
        $this->batch = $batch;

        return $this;
    }

    public function getLead(): Lead
    {
        return $this->lead;
    }

    public function setLead(Lead $lead): self
    {
        $this->lead = $lead;

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

    public function getCreatedAt(): \DateTime
    {
        return $this->createdAt;
    }
}
