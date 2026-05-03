<?php

declare(strict_types=1);

namespace App\Catalog\Presentation\Controller;

use App\Catalog\Application\ObjectTypeService;
use Doctrine\DBAL\Connection;
use InvalidArgumentException;
use Stringable;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Uid\Uuid;

/**
 * VIEW-01c (#414) — sidebar menu surface for ObjectTypes.
 *
 * `GET /api/object_types/menu` returns the lean payload the dynamic
 * sidebar reads (id / code / kind / label / icon / color / builtIn /
 * menuPosition / hierarchical / hasVariants), filtered to
 * `display_in_menu = TRUE` and ordered by `menu_position ASC, code ASC`.
 *
 * `POST /api/object_types/menu/reorder` accepts the full ordered list
 * of UUIDs the operator dragged into shape and rewrites positions in
 * one transaction (multiples of 10 to leave room for inserts).
 */
final class ObjectTypeMenuController
{
    public function __construct(
        private readonly Connection $connection,
        private readonly ObjectTypeService $service,
    ) {
    }

    #[Route(
        '/api/object_types/menu',
        name: 'pim_object_types_menu',
        methods: ['GET'],
        priority: 200,
    )]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function list(): JsonResponse
    {
        $rows = $this->connection->fetchAllAssociative(
            <<<'SQL'
                    SELECT id, code, kind, label, icon, color, is_built_in,
                           menu_position, hierarchical, has_variants
                    FROM object_types
                    WHERE display_in_menu = TRUE
                    ORDER BY menu_position ASC, code ASC
                SQL,
        );

        $entries = [];
        foreach ($rows as $row) {
            $labelRaw = $row['label'] ?? null;
            if (\is_string($labelRaw)) {
                $decoded = json_decode($labelRaw, true);
                $labelRaw = \is_array($decoded) ? $decoded : [];
            }
            $label = [];
            if (\is_array($labelRaw)) {
                foreach ($labelRaw as $k => $v) {
                    if (\is_string($k) && \is_string($v)) {
                        $label[$k] = $v;
                    }
                }
            }

            $entries[] = [
                'id' => self::stringify($row['id'] ?? ''),
                'code' => self::stringify($row['code'] ?? ''),
                'kind' => self::stringify($row['kind'] ?? ''),
                'label' => $label,
                'icon' => $row['icon'] ?? null,
                'color' => $row['color'] ?? null,
                'builtIn' => (bool) ($row['is_built_in'] ?? false),
                'menuPosition' => self::intify($row['menu_position'] ?? 0),
                'hierarchical' => (bool) ($row['hierarchical'] ?? false),
                'hasVariants' => (bool) ($row['has_variants'] ?? false),
            ];
        }

        return new JsonResponse($entries);
    }

    #[Route(
        '/api/object_types/menu/reorder',
        name: 'pim_object_types_menu_reorder',
        methods: ['POST'],
        priority: 200,
    )]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function reorder(Request $request): JsonResponse
    {
        $payload = json_decode($request->getContent(), true);
        if (!\is_array($payload)) {
            throw new BadRequestHttpException('Body must be a JSON object.');
        }
        $rawOrder = $payload['order'] ?? null;
        if (!\is_array($rawOrder)) {
            throw new BadRequestHttpException('Field "order" must be an array of UUIDs.');
        }

        $uuids = [];
        foreach ($rawOrder as $rawId) {
            if (!\is_string($rawId) || !Uuid::isValid($rawId)) {
                throw new BadRequestHttpException(\sprintf('Invalid UUID in "order": %s', \is_string($rawId) ? $rawId : '(non-string)'));
            }
            $uuids[] = Uuid::fromString($rawId);
        }

        try {
            $this->service->reorderMenu($uuids);
        } catch (InvalidArgumentException $e) {
            throw new HttpException(Response::HTTP_UNPROCESSABLE_ENTITY, $e->getMessage(), $e);
        }

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    private static function stringify(mixed $value): string
    {
        return \is_scalar($value) || $value instanceof Stringable ? (string) $value : '';
    }

    private static function intify(mixed $value): int
    {
        return \is_scalar($value) ? (int) $value : 0;
    }
}
