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
use Symfony\Component\HttpFoundation\StreamedResponse;
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

        // IMP2-2.7 (#1483) — true streaming: write each row straight to the
        // output buffer via a memory-flat iterator (the repo detaches every 500
        // rows), instead of building the whole CSV in php://temp first. Keeps the
        // worker footprint flat for a 200k-row report.
        $response = new StreamedResponse(function () use ($session): void {
            $output = fopen('php://output', 'w');
            \assert(false !== $output);

            fputcsv($output, ['row_number', 'sku', 'error_type', 'error_message', 'column', 'value'], escape: '\\');

            foreach ($this->logs->iterateBySession(
                session: $session,
                levels: [ImportLogLevel::Error, ImportLogLevel::Warning],
            ) as $log) {
                fputcsv($output, [
                    (string) $log->getRowNumber(),
                    $log->getSku() ?? '',
                    $log->getErrorType() ?? '',
                    $log->getMessage(),
                    $log->getColumnName() ?? '',
                    $log->getColumnValue() ?? '',
                ], escape: '\\');
            }

            fclose($output);
        });
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
