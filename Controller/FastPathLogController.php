<?php

declare(strict_types=1);

namespace MauticPlugin\MauticAspectFileBundle\Controller;

use Doctrine\Persistence\ManagerRegistry;
use Mautic\CoreBundle\Service\FlashBag;
use Mautic\CoreBundle\Translation\Translator;
use MauticPlugin\MauticAspectFileBundle\Entity\FastPathLog;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Environment;

/**
 * Controller for managing FastPath dispatch logs
 */
class FastPathLogController
{
    private ManagerRegistry $doctrine;
    private Translator $translator;
    private FlashBag $flashBag;
    private Environment $twig;
    private UrlGeneratorInterface $urlGenerator;

    public function __construct(
        ManagerRegistry $doctrine,
        Translator $translator,
        FlashBag $flashBag,
        Environment $twig,
        UrlGeneratorInterface $urlGenerator
    ) {
        $this->doctrine = $doctrine;
        $this->translator = $translator;
        $this->flashBag = $flashBag;
        $this->twig = $twig;
        $this->urlGenerator = $urlGenerator;
    }

    /**
     * List all FastPath logs with filtering and pagination
     */
    public function indexAction(Request $request, int $page = 1): Response
    {
        $limit = 30;
        $offset = ($page - 1) * $limit;

        $em = $this->doctrine->getManager();

        // Get filter parameters
        $statusFilter = $request->query->get('status', '');
        $environmentFilter = $request->query->get('environment', '');
        $searchFilter = $request->query->get('search', '');

        // Build query
        $qb = $em->createQueryBuilder();
        $qb->select('l', 'lead', 's')
            ->from(FastPathLog::class, 'l')
            ->leftJoin('l.lead', 'lead')
            ->leftJoin('l.schema', 's')
            ->orderBy('l.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->setFirstResult($offset);

        // Apply filters
        if ($statusFilter) {
            $qb->andWhere('l.status = :status')
                ->setParameter('status', $statusFilter);
        }

        if ($searchFilter) {
            $qb->andWhere('l.messageId LIKE :search OR l.fastList LIKE :search OR lead.email LIKE :search')
                ->setParameter('search', '%' . $searchFilter . '%');
        }

        $logs = $qb->getQuery()->getResult();

        // Get total count with same filters
        $countQb = $em->createQueryBuilder();
        $countQb->select('COUNT(l.id)')
            ->from(FastPathLog::class, 'l')
            ->leftJoin('l.lead', 'lead');

        if ($statusFilter) {
            $countQb->andWhere('l.status = :status')
                ->setParameter('status', $statusFilter);
        }

        if ($searchFilter) {
            $countQb->andWhere('l.messageId LIKE :search OR l.fastList LIKE :search OR lead.email LIKE :search')
                ->setParameter('search', '%' . $searchFilter . '%');
        }

        $totalCount = (int) $countQb->getQuery()->getSingleScalarResult();
        $totalPages = (int) ceil($totalCount / $limit);

        // Get statistics
        $statsQb = $em->createQueryBuilder();
        $statsQb->select('l.status', 'COUNT(l.id) as count')
            ->from(FastPathLog::class, 'l')
            ->groupBy('l.status');

        $statsResults = $statsQb->getQuery()->getResult();
        $statistics = [
            'total' => 0,
            'success' => 0,
            'failed' => 0,
        ];

        foreach ($statsResults as $stat) {
            $statistics['total'] += $stat['count'];
            $key = strtolower($stat['status']);
            if (isset($statistics[$key])) {
                $statistics[$key] = $stat['count'];
            }
        }

        // Get unique environments (WSDL hosts)
        $envQb = $em->createQueryBuilder();
        $envQb->select('DISTINCT l.wsdlUrl')
            ->from(FastPathLog::class, 'l');
        $environments = [];
        foreach ($envQb->getQuery()->getResult() as $row) {
            $parsed = parse_url($row['wsdlUrl']);
            $host = $parsed['host'] ?? 'unknown';
            if (!in_array($host, $environments)) {
                $environments[] = $host;
            }
        }

        $content = $this->twig->render('@MauticAspectFile/FastPathLog/list.html.twig', [
            'logs' => $logs,
            'statistics' => $statistics,
            'environments' => $environments,
            'page' => $page,
            'totalPages' => $totalPages,
            'totalCount' => $totalCount,
            'statusFilter' => $statusFilter,
            'environmentFilter' => $environmentFilter,
            'searchFilter' => $searchFilter,
            'activeLink' => '#mautic_fastpath_log_index',
            'mauticContent' => 'fastpathLog',
        ]);

        if ($request->isXmlHttpRequest()) {
            return new JsonResponse([
                'newContent' => $content,
                'route' => $this->urlGenerator->generate('mautic_fastpath_log_index', ['page' => $page]),
                'mauticContent' => 'fastpathLog',
            ]);
        }

        return new Response($content);
    }

    /**
     * View log details with full SOAP request/response
     */
    public function viewAction(Request $request, int $id): Response
    {
        $em = $this->doctrine->getManager();

        $log = $em->getRepository(FastPathLog::class)->find($id);

        if (!$log) {
            $this->flashBag->add('mautic.fastpath.log.error.notfound', FlashBag::LEVEL_ERROR);

            return new RedirectResponse($this->urlGenerator->generate('mautic_fastpath_log_index'));
        }

        // Format XML payloads for display
        $requestXml = $this->formatXml($log->getRequestPayload());
        $responseXml = $this->formatXml($log->getResponsePayload());

        $content = $this->twig->render('@MauticAspectFile/FastPathLog/view.html.twig', [
            'log' => $log,
            'requestXml' => $requestXml,
            'responseXml' => $responseXml,
            'activeLink' => '#mautic_fastpath_log_index',
            'mauticContent' => 'fastpathLog',
        ]);

        if ($request->isXmlHttpRequest()) {
            return new JsonResponse([
                'newContent' => $content,
                'route' => $this->urlGenerator->generate('mautic_fastpath_log_view', ['id' => $id]),
                'mauticContent' => 'fastpathLog',
            ]);
        }

        return new Response($content);
    }

    /**
     * Delete a log entry
     */
    public function deleteAction(Request $request, int $id): Response
    {
        $em = $this->doctrine->getManager();

        $log = $em->getRepository(FastPathLog::class)->find($id);

        if (!$log) {
            if ($request->isXmlHttpRequest()) {
                return new JsonResponse(['success' => false, 'message' => 'Log not found'], 404);
            }

            $this->flashBag->add('mautic.fastpath.log.error.notfound', FlashBag::LEVEL_ERROR);

            return new RedirectResponse($this->urlGenerator->generate('mautic_fastpath_log_index'));
        }

        $em->remove($log);
        $em->flush();

        if ($request->isXmlHttpRequest()) {
            return new JsonResponse([
                'success' => true,
                'message' => $this->translator->trans('mautic.fastpath.log.deleted'),
            ]);
        }

        $this->flashBag->add('mautic.fastpath.log.deleted', FlashBag::LEVEL_SUCCESS);

        return new RedirectResponse($this->urlGenerator->generate('mautic_fastpath_log_index'));
    }

    /**
     * Clear all logs or logs older than specified days
     */
    public function clearAction(Request $request): Response
    {
        $days = (int) $request->query->get('days', 30);

        $em = $this->doctrine->getManager();

        $cutoffDate = new \DateTime("-{$days} days");

        $qb = $em->createQueryBuilder();
        $deleted = $qb->delete(FastPathLog::class, 'l')
            ->where('l.createdAt < :cutoff')
            ->setParameter('cutoff', $cutoffDate)
            ->getQuery()
            ->execute();

        if ($request->isXmlHttpRequest()) {
            return new JsonResponse([
                'success' => true,
                'deleted' => $deleted,
                'message' => $this->translator->trans('mautic.fastpath.log.cleared', ['%count%' => $deleted]),
            ]);
        }

        $this->flashBag->add(
            $this->translator->trans('mautic.fastpath.log.cleared', ['%count%' => $deleted]),
            FlashBag::LEVEL_SUCCESS
        );

        return new RedirectResponse($this->urlGenerator->generate('mautic_fastpath_log_index'));
    }

    /**
     * Export logs to CSV
     */
    public function exportAction(Request $request): Response
    {
        $em = $this->doctrine->getManager();

        // Get filter parameters
        $statusFilter = $request->query->get('status', '');
        $days = (int) $request->query->get('days', 7);

        $cutoffDate = new \DateTime("-{$days} days");

        $qb = $em->createQueryBuilder();
        $qb->select('l', 'lead', 's')
            ->from(FastPathLog::class, 'l')
            ->leftJoin('l.lead', 'lead')
            ->leftJoin('l.schema', 's')
            ->where('l.createdAt >= :cutoff')
            ->setParameter('cutoff', $cutoffDate)
            ->orderBy('l.createdAt', 'DESC');

        if ($statusFilter) {
            $qb->andWhere('l.status = :status')
                ->setParameter('status', $statusFilter);
        }

        $logs = $qb->getQuery()->getResult();

        // Build CSV
        $csv = "ID,Message ID,Status,Environment,WSDL URL,FastList,Function Type,Lead ID,Lead Email,Schema,Campaign ID,Duration (ms),Error,Created At\n";

        foreach ($logs as $log) {
            $lead = $log->getLead();
            $schema = $log->getSchema();

            $csv .= sprintf(
                "%d,%s,%s,%s,%s,%s,%d,%s,%s,%s,%s,%s,%s,%s\n",
                $log->getId(),
                $log->getMessageId(),
                $log->getStatus(),
                $log->getEnvironment(),
                $log->getWsdlUrl(),
                $log->getFastList(),
                $log->getFunctionType(),
                $lead ? $lead->getId() : '',
                $lead ? $lead->getEmail() : '',
                $schema ? $schema->getName() : '',
                $log->getCampaignId() ?? '',
                $log->getDurationMs() ?? '',
                str_replace(["\n", "\r", ','], [' ', '', ';'], $log->getErrorMessage() ?? ''),
                $log->getCreatedAt()->format('Y-m-d H:i:s')
            );
        }

        $response = new Response($csv);
        $response->headers->set('Content-Type', 'text/csv');
        $response->headers->set('Content-Disposition', 'attachment; filename="fastpath_logs_' . date('Y-m-d') . '.csv"');

        return $response;
    }

    /**
     * Format XML string for display
     */
    private function formatXml(?string $xml): ?string
    {
        if (empty($xml)) {
            return null;
        }

        $dom = new \DOMDocument('1.0', 'UTF-8');
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = true;

        // Suppress errors for malformed XML
        $previousValue = libxml_use_internal_errors(true);

        if ($dom->loadXML($xml)) {
            $formatted = $dom->saveXML();
            libxml_use_internal_errors($previousValue);

            return $formatted;
        }

        libxml_use_internal_errors($previousValue);

        // Return original if formatting fails
        return $xml;
    }
}
