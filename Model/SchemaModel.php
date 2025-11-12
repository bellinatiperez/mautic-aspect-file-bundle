<?php

declare(strict_types=1);

namespace MauticPlugin\MauticAspectFileBundle\Model;

use Doctrine\ORM\EntityManagerInterface;
use Mautic\CoreBundle\Helper\CoreParametersHelper;
use Mautic\CoreBundle\Helper\UserHelper;
use Mautic\CoreBundle\Security\Permissions\CorePermissions;
use Doctrine\ORM\EntityRepository;
use MauticPlugin\MauticAspectFileBundle\Entity\Schema;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Model for Schema management
 */
class SchemaModel
{
    private EntityManagerInterface $em;
    private CorePermissions $security;
    private EventDispatcherInterface $dispatcher;
    private RouterInterface $router;
    private TranslatorInterface $translator;
    private UserHelper $userHelper;
    private LoggerInterface $logger;
    private CoreParametersHelper $coreParametersHelper;

    public function __construct(
        EntityManagerInterface $em,
        CorePermissions $security,
        EventDispatcherInterface $dispatcher,
        RouterInterface $router,
        TranslatorInterface $translator,
        UserHelper $userHelper,
        LoggerInterface $logger,
        CoreParametersHelper $coreParametersHelper
    ) {
        $this->em = $em;
        $this->security = $security;
        $this->dispatcher = $dispatcher;
        $this->router = $router;
        $this->translator = $translator;
        $this->userHelper = $userHelper;
        $this->logger = $logger;
        $this->coreParametersHelper = $coreParametersHelper;
    }

    /**
     * Get repository
     */
    public function getRepository(): EntityRepository
    {
        return $this->em->getRepository(Schema::class);
    }

    /**
     * Get entity by ID
     */
    public function getEntity(int $id): ?Schema
    {
        return $this->getRepository()->find($id);
    }

    /**
     * Get published schemas
     */
    public function getPublishedSchemas(): array
    {
        return $this->em->createQueryBuilder()
            ->select('s')
            ->from(Schema::class, 's')
            ->where('s.isPublished = :published')
            ->setParameter('published', true)
            ->orderBy('s.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Save entity
     */
    public function saveEntity(Schema $entity, bool $unlock = true): void
    {
        $fieldsJson = json_encode($entity->getFields());
        error_log('SchemaModel::saveEntity - Fields JSON to save: ' . $fieldsJson);
        error_log('SchemaModel::saveEntity - Entity ID: ' . ($entity->getId() ?? 'NULL'));

        $this->em->persist($entity);
        $this->em->flush();

        // Force update of JSON field using DQL
        if ($entity->getId()) {
            $result = $this->em->createQuery(
                'UPDATE ' . Schema::class . ' s SET s.fields = :fields WHERE s.id = :id'
            )
            ->setParameter('fields', $fieldsJson)
            ->setParameter('id', $entity->getId())
            ->execute();

            error_log('SchemaModel::saveEntity - DQL UPDATE affected rows: ' . $result);

            // Clear entity manager to force reload on next access
            $this->em->clear(Schema::class);
        }
    }

    /**
     * Delete entity
     */
    public function deleteEntity(Schema $entity): void
    {
        $this->em->remove($entity);
        $this->em->flush();
    }

    /**
     * Get list of entities
     */
    public function getEntities(array $args = []): array
    {
        return $this->em->createQueryBuilder()
            ->select('s')
            ->from(Schema::class, 's')
            ->orderBy('s.name', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
