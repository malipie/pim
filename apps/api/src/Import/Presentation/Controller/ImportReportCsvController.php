<?php

declare(strict_types=1);

namespace App\Import\Presentation\Controller;

use App\Identity\Contracts\Attribute\RequiresPermission;
use App\Identity\Domain\Entity\User;
use App\Import\Domain\Entity\ImportSession;
use App\Import\Domain\Enum\ImportLogLevel;
use App\Import\Domain\Repository\ImportLogRepositoryInterface;
use App\Import\Domain\Repository\ImportSessionRepositoryInterface;
use InvalidArgumentException;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;

/**
 * IMP-05 (#446) — CSV report download for the wizard results screen.
 *
 * Streams every error/warning row from import_logs in the format
 * spec §5.7 nailed down: `row_number,sku,error_type,error_message,
 * column,value`. The streamed response keeps the worker memory
 * footprint flat for 5k+ row reports.
 */
final class ImportReportCsvController
{
    public function __construct(
        private readonly ImportSessionRepositoryInterface $sessions,
        private readonly ImportLogRepositoryInterface $logs,
        private readonly Security $security,
    ) {
    }

    #[Route(
        path: '/api/import-sessions/{id}/report.csv',
        name: 'imports_report_csv',
        requirements: ['id' => '[0-9a-fA-F-]{36}'],
        methods: ['GET'],
    )]
    #[RequiresPermission(module: 'import_session', action: 'read')]
    public function __invoke(string $id): Response
    {
        $session = $this->loadOwned($id);

        $buffer = fopen('php://temp', 'w+');
        \assert(false !== $buffer);

        fputcsv($buffer, ['row_number', 'sku', 'error_type', 'error_message', 'column', 'value'], escape: '\\');

        // SAMPLE_LIMIT (100) on the dry-run capped what the wizard
        // shows inline; the persisted log carries the full set, so
        // the report streams everything for the session.
        foreach ($this->logs->findBySession(
            session: $session,
            levels: [ImportLogLevel::Error, ImportLogLevel::Warning],
            limit: 100_000,
        ) as $log) {
            fputcsv($buffer, [
                (string) $log->getRowNumber(),
                $log->getSku() ?? '',
                $log->getErrorType() ?? '',
                $log->getMessage(),
                $log->getColumnName() ?? '',
                $log->getColumnValue() ?? '',
            ], escape: '\\');
        }

        rewind($buffer);
        $body = stream_get_contents($buffer);
        fclose($buffer);

        $response = new Response($body, Response::HTTP_OK);
        $response->headers->set('Content-Type', 'text/csv; charset=utf-8');
        $response->headers->set(
            'Content-Disposition',
            \sprintf('attachment; filename="import-%s-report.csv"', $session->getId()->toRfc4122()),
        );

        return $response;
    }

    private function loadOwned(string $rawId): ImportSession
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new UnauthorizedHttpException('JWT', 'Authenticated user required.');
        }

        try {
            $id = Uuid::fromString($rawId);
        } catch (InvalidArgumentException) {
            throw new NotFoundHttpException(\sprintf('Import session "%s" was not found.', $rawId));
        }

        $session = $this->sessions->findById($id);
        if (!$session instanceof ImportSession || $session->getUserId()->toRfc4122() !== $user->getId()->toRfc4122()) {
            throw new NotFoundHttpException(\sprintf('Import session "%s" was not found.', $rawId));
        }

        return $session;
    }
}
