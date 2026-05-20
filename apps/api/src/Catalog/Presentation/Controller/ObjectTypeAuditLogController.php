<?php

declare(strict_types=1);

namespace App\Catalog\Presentation\Controller;

use App\Catalog\Domain\Repository\ObjectTypeRepositoryInterface;
use App\Identity\Domain\Attribute\RequiresPermission;
use App\Shared\Infrastructure\Audit\CursorCodec;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Stringable;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Uid\Uuid;

/**
 * VIEW-01 (#372) — `GET /api/object_types/{id}/audit_log` for the
 * "Historia zmian (5 ostatnich)" card on the modeling Detail view.
 *
 * Adapter over `object_types_audit` (DH Auditor's per-entity table from
 * `dh_auditor.yaml`). Stays in DBAL because dh_auditor's reader API is
 * tied to the bundle's own DI graph and we just need a flat list for the
 * compact widget.
 *
 * Default `limit=10`, max 50 — the FE widget uses 5 but operators with a
 * deeper need land here through the `?limit=` query param.
 */
final class ObjectTypeAuditLogController
{
    private const string UUID_REGEX = '[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}';
    private const int MAX_LIMIT = 50;

    public function __construct(
        private readonly ObjectTypeRepositoryInterface $objectTypes,
        private readonly Connection $connection,
    ) {
    }

    #[Route(
        '/api/object_types/{id}/audit_log',
        name: 'pim_object_types_audit_log',
        requirements: ['id' => self::UUID_REGEX],
        methods: ['GET'],
    )]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    #[RequiresPermission(module: 'modeling', action: 'view')]
    public function __invoke(string $id, Request $request): JsonResponse
    {
        $objectType = $this->objectTypes->findById(Uuid::fromString($id));
        if (null === $objectType) {
            throw new NotFoundHttpException(\sprintf('ObjectType "%s" was not found.', $id));
        }

        $limit = (int) $request->query->get('limit', '10');
        if ($limit < 1 || $limit > self::MAX_LIMIT) {
            throw new BadRequestHttpException(\sprintf('limit must be between 1 and %d.', self::MAX_LIMIT));
        }

        // HARD-05 — opaque cursor over (created_at, id). Default
        // limit=10 was sufficient for the compact widget, but the
        // detail-page "Show all" link had nothing to call into;
        // operators chasing an older incident were stuck.
        $cursor = CursorCodec::decode($request->query->get('cursor'));

        $sql = 'SELECT id, type, source, target, blame, diffs, created_at'
            .' FROM object_types_audit'
            .' WHERE object_id = :objectId';
        $params = ['objectId' => $id];
        $types = ['limit' => ParameterType::INTEGER];

        if (null !== $cursor) {
            $sql .= ' AND (created_at, id) < (:cursorT, :cursorI)';
            $params['cursorT'] = $cursor['t'];
            $params['cursorI'] = (string) $cursor['i'];
            $types['cursorI'] = ParameterType::INTEGER;
        }

        $sql .= ' ORDER BY created_at DESC, id DESC LIMIT :limit';
        $params['limit'] = (string) $limit;

        $rows = $this->connection->fetchAllAssociative($sql, $params, $types);

        $entries = [];
        foreach ($rows as $row) {
            $blameRaw = $row['blame'] ?? null;
            $blame = \is_string($blameRaw) ? json_decode($blameRaw, true) : (\is_array($blameRaw) ? $blameRaw : []);
            $diffsRaw = $row['diffs'] ?? null;
            $diffs = \is_string($diffsRaw) ? json_decode($diffsRaw, true) : (\is_array($diffsRaw) ? $diffsRaw : []);

            $entries[] = [
                'id' => self::stringify($row['id'] ?? ''),
                'action' => self::stringify($row['type'] ?? 'update'),
                'actorId' => \is_array($blame) && isset($blame['user_id']) ? self::stringify($blame['user_id']) : null,
                'actorName' => \is_array($blame) && isset($blame['username']) ? self::stringify($blame['username']) : null,
                'occurredAt' => self::stringify($row['created_at'] ?? ''),
                'diff' => \is_array($diffs) ? $diffs : [],
            ];
        }

        $nextCursor = null;
        if (\count($rows) === $limit) {
            $last = $rows[\count($rows) - 1];
            $lastCreatedAt = $last['created_at'] ?? null;
            $lastId = $last['id'] ?? null;
            if (\is_string($lastCreatedAt) && (\is_int($lastId) || (\is_string($lastId) && ctype_digit($lastId)))) {
                $nextCursor = CursorCodec::encode($lastCreatedAt, (int) $lastId);
            }
        }

        return new JsonResponse([
            'entries' => $entries,
            'next_cursor' => $nextCursor,
        ]);
    }

    private static function stringify(mixed $value): string
    {
        return \is_scalar($value) || $value instanceof Stringable ? (string) $value : '';
    }
}
