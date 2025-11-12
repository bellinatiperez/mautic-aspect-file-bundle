<?php

declare(strict_types=1);

namespace MauticPlugin\MauticAspectFileBundle\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Mautic\CoreBundle\Service\FlashBag;
use Mautic\CoreBundle\Translation\Translator;
use Mautic\LeadBundle\Model\FieldModel as LeadFieldModel;
use MauticPlugin\MauticAspectFileBundle\Entity\Schema;
use MauticPlugin\MauticAspectFileBundle\Model\SchemaModel;
use MauticPlugin\MauticAspectFileBundle\Service\SchemaParser;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Environment;

class SchemaController
{
    private SchemaModel $schemaModel;
    private SchemaParser $schemaParser;
    private FormFactoryInterface $formFactory;
    private UrlGeneratorInterface $router;
    private Translator $translator;
    private FlashBag $flashBag;
    private Environment $twig;
    private LeadFieldModel $leadFieldModel;
    private EntityManagerInterface $em;

    public function __construct(
        SchemaModel $schemaModel,
        SchemaParser $schemaParser,
        FormFactoryInterface $formFactory,
        UrlGeneratorInterface $router,
        Translator $translator,
        FlashBag $flashBag,
        Environment $twig,
        LeadFieldModel $leadFieldModel,
        EntityManagerInterface $em
    ) {
        $this->schemaModel = $schemaModel;
        $this->schemaParser = $schemaParser;
        $this->formFactory = $formFactory;
        $this->router = $router;
        $this->translator = $translator;
        $this->flashBag = $flashBag;
        $this->twig = $twig;
        $this->leadFieldModel = $leadFieldModel;
        $this->em = $em;
    }

    /**
     * List schemas
     */
    public function indexAction(Request $request, int $page = 1): Response
    {
        $schemas = $this->schemaModel->getRepository()->findAll();

        $content = $this->twig->render('@MauticAspectFile/Schema/list.html.twig', [
            'schemas' => $schemas,
            'page' => $page,
            'activeLink' => '#mautic_aspectfile_index',
            'mauticContent' => 'aspectfileSchema',
        ]);

        // If AJAX request, return JSON with the content
        if ($request->isXmlHttpRequest()) {
            return new JsonResponse([
                'newContent' => $content,
                'route' => $this->router->generate('mautic_aspectfile_index', ['page' => $page]),
                'mauticContent' => 'aspectfileSchema',
            ]);
        }

        return new Response($content);
    }

    /**
     * Create/edit schema
     */
    public function executeAction(Request $request, $objectAction, $objectId = 0, $objectSubId = 0, $objectModel = ''): Response
    {
        if ('new' === $objectAction) {
            $entity = new Schema();
        } elseif ('edit' === $objectAction) {
            $entity = $this->schemaModel->getEntity((int) $objectId);
            if (!$entity) {
                return new Response('Schema not found', 404);
            }
        } elseif ('delete' === $objectAction) {
            error_log("Delete action called for schema ID: {$objectId}");

            $entity = $this->schemaModel->getEntity((int) $objectId);
            if (!$entity) {
                error_log("Schema not found: {$objectId}");
                return new JsonResponse(['success' => false, 'error' => 'Schema not found'], 404);
            }

            try {
                error_log("Attempting to delete schema: {$entity->getName()}");

                // First, delete all associated batches
                $batchRepository = $this->em->getRepository(\MauticPlugin\MauticAspectFileBundle\Entity\AspectFileBatch::class);
                $batches = $batchRepository->findBy(['schema' => $entity]);

                error_log("Found " . count($batches) . " batches to delete");

                foreach ($batches as $batch) {
                    // Also delete batch leads if they exist
                    $batchLeadRepository = $this->em->getRepository(\MauticPlugin\MauticAspectFileBundle\Entity\AspectFileBatchLead::class);
                    $batchLeads = $batchLeadRepository->findBy(['batch' => $batch]);

                    foreach ($batchLeads as $batchLead) {
                        $this->em->remove($batchLead);
                    }

                    $this->em->remove($batch);
                }

                $this->em->flush();
                error_log("Deleted all associated batches and batch leads");

                // Now delete the schema
                $this->schemaModel->deleteEntity($entity);

                $this->flashBag->add(
                    $this->translator->trans(
                        'mautic.core.notice.deleted',
                        [
                            '%name%' => $entity->getName(),
                        ],
                        'flashes'
                    )
                );

                error_log("Schema deleted successfully");
                $response = new JsonResponse(['success' => true]);
                error_log("Response JSON: " . json_encode(['success' => true]));
                return $response;
            } catch (\Exception $e) {
                error_log("Delete failed with exception: " . $e->getMessage());
                return new JsonResponse(['success' => false, 'error' => $e->getMessage()], 500);
            }
        } else {
            return new Response('Invalid action', 403);
        }

        $action = $this->router->generate('mautic_aspectfile_action', [
            'objectAction' => $objectAction,
            'objectId' => $objectId,
        ]);

        $form = $this->formFactory->create(
            \MauticPlugin\MauticAspectFileBundle\Form\Type\SchemaType::class,
            $entity,
            ['action' => $action]
        );

        if ($request->getMethod() === 'POST') {
            $form->handleRequest($request);

            if ($form->isSubmitted() && $form->isValid()) {
                // Process field mappings from request
                $fieldMappings = $request->request->all('field_mapping');

                // Log what we received
                error_log('Field mappings received: ' . print_r($fieldMappings, true));

                if ($fieldMappings && is_array($fieldMappings)) {
                    $fields = $entity->getFields();

                    error_log('Current fields before update: ' . print_r($fields, true));

                    foreach ($fields as $key => &$field) {
                        $fieldNo = $field['no'];
                        if (isset($fieldMappings[$fieldNo])) {
                            $newValue = $fieldMappings[$fieldNo] ?: null;
                            $field['lead_field'] = $newValue;
                        }
                    }
                    unset($field); // Break reference

                    error_log('Updated fields after mapping: ' . print_r($fields, true));

                    // Always set fields to ensure Doctrine detects the change
                    $entity->setFields($fields);
                }

                $this->schemaModel->saveEntity($entity);

                $this->flashBag->add(
                    $this->translator->trans(
                        'mautic.core.notice.updated',
                        [
                            '%name%' => $entity->getName(),
                            '%url%' => $this->router->generate('mautic_aspectfile_action', [
                                'objectAction' => 'edit',
                                'objectId' => $entity->getId(),
                            ]),
                        ],
                        'flashes'
                    )
                );

                return new RedirectResponse($this->router->generate('mautic_aspectfile_index'));
            }
        }

        // Get available lead fields
        $leadFields = $this->leadFieldModel->getEntities([
            'filter' => [
                'isPublished' => true,
            ],
            'orderBy' => 'f.label',
        ]);

        $leadFieldChoices = [];
        foreach ($leadFields as $field) {
            $leadFieldChoices[$field->getAlias()] = $field->getLabel();
        }

        $content = $this->twig->render('@MauticAspectFile/Schema/form.html.twig', [
            'entity' => $entity,
            'form' => $form->createView(),
            'activeLink' => '#mautic_aspectfile_index',
            'leadFields' => $leadFieldChoices,
        ]);

        return new Response($content);
    }

    /**
     * Import schema from Excel file
     */
    public function importAction(Request $request): Response
    {
        if ($request->getMethod() === 'POST') {
            $uploadedFile = $request->files->get('schema_file');

            if ($uploadedFile) {
                $result = $this->schemaParser->parseExcelFile($uploadedFile->getPathname());

                if ($result['success']) {
                    $schema = new Schema();
                    $schema->setName($request->request->get('name', 'Imported Schema'));
                    $schema->setFields($result['fields']);
                    $schema->setIsPublished(true);
                    $schema->setLineLength($schema->calculateLineLength());

                    $this->schemaModel->saveEntity($schema);

                    return new JsonResponse([
                        'success' => true,
                        'schema_id' => $schema->getId(),
                        'fields_count' => count($result['fields']),
                    ]);
                }

                return new JsonResponse([
                    'success' => false,
                    'error' => $result['error'] ?? 'Unknown error',
                ]);
            }

            return new JsonResponse([
                'success' => false,
                'error' => 'No file uploaded',
            ]);
        }

        $content = $this->twig->render('@MauticAspectFile/Schema/import.html.twig', [
            'activeLink' => '#mautic_aspectfile_index',
        ]);

        return new Response($content);
    }
}
