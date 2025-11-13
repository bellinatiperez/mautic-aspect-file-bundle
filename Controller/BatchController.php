<?php

declare(strict_types=1);

namespace MauticPlugin\MauticAspectFileBundle\Controller;

use Doctrine\Persistence\ManagerRegistry;
use Mautic\CoreBundle\Service\FlashBag;
use Mautic\CoreBundle\Translation\Translator;
use MauticPlugin\MauticAspectFileBundle\Entity\AspectFileBatch;
use MauticPlugin\MauticAspectFileBundle\Entity\AspectFileBatchLead;
use MauticPlugin\MauticAspectFileBundle\Model\AspectFileModel;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Environment;

/**
 * Controller for managing AspectFile batches
 * Compatible with Mautic 5+ / 7.0
 */
class BatchController
{
    private ManagerRegistry $doctrine;
    private Translator $translator;
    private FlashBag $flashBag;
    private Environment $twig;
    private AspectFileModel $aspectFileModel;
    private UrlGeneratorInterface $urlGenerator;

    public function __construct(
        ManagerRegistry $doctrine,
        Translator $translator,
        FlashBag $flashBag,
        Environment $twig,
        AspectFileModel $aspectFileModel,
        UrlGeneratorInterface $urlGenerator
    ) {
        $this->doctrine = $doctrine;
        $this->translator = $translator;
        $this->flashBag = $flashBag;
        $this->twig = $twig;
        $this->aspectFileModel = $aspectFileModel;
        $this->urlGenerator = $urlGenerator;
    }

    /**
     * List all batches
     */
    public function indexAction(Request $request, int $page = 1): Response
    {
        $limit = 20;
        $offset = ($page - 1) * $limit;

        // Get entity manager
        $em = $this->doctrine->getManager();

        // Get batches with pagination
        $qb = $em->createQueryBuilder();
        $qb->select('b', 's')
            ->from(AspectFileBatch::class, 'b')
            ->leftJoin('b.schema', 's')
            ->orderBy('b.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->setFirstResult($offset);

        $batches = $qb->getQuery()->getResult();

        // Get total count
        $countQb = $em->createQueryBuilder();
        $countQb->select('COUNT(b.id)')
            ->from(AspectFileBatch::class, 'b');
        $totalCount = (int) $countQb->getQuery()->getSingleScalarResult();

        $totalPages = (int) ceil($totalCount / $limit);

        // Get lead counts for each batch
        $batchStats = [];
        foreach ($batches as $batch) {
            $stats = $em->createQueryBuilder()
                ->select('bl.status', 'COUNT(bl.id) as count')
                ->from(AspectFileBatchLead::class, 'bl')
                ->where('bl.batch = :batch')
                ->setParameter('batch', $batch)
                ->groupBy('bl.status')
                ->getQuery()
                ->getResult();

            $batchStats[$batch->getId()] = [
                'total' => 0,
                'pending' => 0,
                'generated' => 0,
                'failed' => 0,
            ];

            foreach ($stats as $stat) {
                $batchStats[$batch->getId()]['total'] += $stat['count'];
                $status = strtolower($stat['status']);
                $batchStats[$batch->getId()][$status] = $stat['count'];
            }
        }

        $content = $this->twig->render('@MauticAspectFile/Batch/list.html.twig', [
            'batches' => $batches,
            'batchStats' => $batchStats,
            'page' => $page,
            'totalPages' => $totalPages,
            'totalCount' => $totalCount,
            'activeLink' => '#mautic_aspectfile_batch_index',
            'mauticContent' => 'aspectfileBatch',
        ]);

        if ($request->isXmlHttpRequest()) {
            return new JsonResponse([
                'newContent' => $content,
                'route' => $this->urlGenerator->generate('mautic_aspectfile_batch_index', ['page' => $page]),
                'mauticContent' => 'aspectfileBatch',
            ]);
        }

        return new Response($content);
    }

    /**
     * View batch details
     */
    public function viewAction(Request $request, int $id): Response
    {
        $em = $this->doctrine->getManager();

        $batch = $em->getRepository(AspectFileBatch::class)->find($id);

        if (!$batch) {
            $this->flashBag->add('mautic.aspectfile.batch.error.notfound', FlashBag::LEVEL_ERROR);

            return new RedirectResponse($this->urlGenerator->generate('mautic_aspectfile_batch_index'));
        }

        // Get batch leads
        $leads = $em->createQueryBuilder()
            ->select('bl', 'l')
            ->from(AspectFileBatchLead::class, 'bl')
            ->join('bl.lead', 'l')
            ->where('bl.batch = :batch')
            ->setParameter('batch', $batch)
            ->orderBy('bl.createdAt', 'ASC')
            ->getQuery()
            ->getResult();

        // Get statistics
        $stats = $em->createQueryBuilder()
            ->select('bl.status', 'COUNT(bl.id) as count')
            ->from(AspectFileBatchLead::class, 'bl')
            ->where('bl.batch = :batch')
            ->setParameter('batch', $batch)
            ->groupBy('bl.status')
            ->getQuery()
            ->getResult();

        $statistics = [
            'total' => 0,
            'pending' => 0,
            'generated' => 0,
            'failed' => 0,
        ];

        foreach ($stats as $stat) {
            $statistics['total'] += $stat['count'];
            $status = strtolower($stat['status']);
            $statistics[$status] = $stat['count'];
        }

        $content = $this->twig->render('@MauticAspectFile/Batch/view.html.twig', [
            'batch' => $batch,
            'leads' => $leads,
            'statistics' => $statistics,
            'activeLink' => '#mautic_aspectfile_batch_index',
            'mauticContent' => 'aspectfileBatch',
        ]);

        if ($request->isXmlHttpRequest()) {
            return new JsonResponse([
                'newContent' => $content,
                'route' => $this->urlGenerator->generate('mautic_aspectfile_batch_view', ['id' => $id]),
                'mauticContent' => 'aspectfileBatch',
            ]);
        }

        return new Response($content);
    }

    /**
     * Reprocess a failed batch
     */
    public function reprocessAction(Request $request, int $id): Response
    {
        $em = $this->doctrine->getManager();

        $batch = $em->getRepository(AspectFileBatch::class)->find($id);

        if (!$batch) {
            if ($request->isXmlHttpRequest()) {
                return new JsonResponse(['success' => false, 'message' => 'Batch not found'], 404);
            }

            $this->flashBag->add('mautic.aspectfile.batch.error.notfound', FlashBag::LEVEL_ERROR);

            return new RedirectResponse($this->urlGenerator->generate('mautic_aspectfile_batch_index'));
        }

        // Reset batch and leads to PENDING
        $em->createQueryBuilder()
            ->update(AspectFileBatchLead::class, 'bl')
            ->set('bl.status', ':pending')
            ->where('bl.batch = :batch')
            ->setParameter('pending', AspectFileBatchLead::STATUS_PENDING)
            ->setParameter('batch', $batch)
            ->getQuery()
            ->execute();

        $batch->setStatus(AspectFileBatch::STATUS_PENDING);
        $batch->setErrorMessage(null);
        $batch->setGeneratedAt(null);
        $batch->setUploadedAt(null);
        $batch->setFileName(null);
        $batch->setFilePath(null);
        $batch->setFileSizeBytes(null);

        $em->persist($batch);
        $em->flush();

        if ($request->isXmlHttpRequest()) {
            return new JsonResponse([
                'success' => true,
                'message' => $this->translator->trans('mautic.aspectfile.batch.reprocessed'),
            ]);
        }

        $this->flashBag->add('mautic.aspectfile.batch.reprocessed', FlashBag::LEVEL_SUCCESS);

        return new RedirectResponse($this->urlGenerator->generate('mautic_aspectfile_batch_view', ['id' => $id]));
    }

    /**
     * Delete a batch
     */
    public function deleteAction(Request $request, int $id): Response
    {
        $em = $this->doctrine->getManager();

        $batch = $em->getRepository(AspectFileBatch::class)->find($id);

        if (!$batch) {
            if ($request->isXmlHttpRequest()) {
                return new JsonResponse(['success' => false, 'message' => 'Batch not found'], 404);
            }

            $this->flashBag->add('mautic.aspectfile.batch.error.notfound', FlashBag::LEVEL_ERROR);

            return new RedirectResponse($this->urlGenerator->generate('mautic_aspectfile_batch_index'));
        }

        // Delete batch (cascade will delete batch leads)
        $em->remove($batch);
        $em->flush();

        if ($request->isXmlHttpRequest()) {
            return new JsonResponse([
                'success' => true,
                'message' => $this->translator->trans('mautic.aspectfile.batch.deleted'),
            ]);
        }

        $this->flashBag->add('mautic.aspectfile.batch.deleted', FlashBag::LEVEL_SUCCESS);

        return new RedirectResponse($this->urlGenerator->generate('mautic_aspectfile_batch_index'));
    }

    /**
     * Process pending batches manually
     */
    public function processAction(Request $request): Response
    {
        $limit = (int) $request->query->get('limit', 10);

        $stats = $this->aspectFileModel->processPendingBatches($limit);

        if ($request->isXmlHttpRequest()) {
            return new JsonResponse([
                'success' => $stats['failed'] === 0,
                'stats' => $stats,
                'message' => $this->translator->trans('mautic.aspectfile.batch.processed', [
                    '%succeeded%' => $stats['succeeded'],
                    '%failed%' => $stats['failed'],
                ]),
            ]);
        }

        $flashType = $stats['failed'] > 0 ? FlashBag::LEVEL_WARNING : FlashBag::LEVEL_SUCCESS;
        $this->flashBag->add(
            $this->translator->trans('mautic.aspectfile.batch.processed', [
                '%succeeded%' => $stats['succeeded'],
                '%failed%' => $stats['failed'],
            ]),
            $flashType
        );

        return new RedirectResponse($this->urlGenerator->generate('mautic_aspectfile_batch_index'));
    }
}
