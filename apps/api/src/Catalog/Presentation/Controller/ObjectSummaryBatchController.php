<?php

declare(strict_types=1);

namespace App\Catalog\Presentation\Controller;

use App\Catalog\Domain\Repository\CatalogObjectRepositoryInterface;
use App\Identity\Contracts\Attribute\RequiresPermission;
use InvalidArgumentException;
use JsonException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Uid\Uuid;

use const JSON_THROW_ON_ERROR;

/**
 * MODR-08 (#930) — batch object summary endpoint.
 *
 *   POST /api/objects/summaries
 *   Body: { "ids": ["<uuid>", ...] }      max 200 ids
 *   Resp: [{ id, code, name, objectType: { code, kind } }, ...]
 *
 * Lets the product detail page resolve linked target objects in a single
 * round trip rather than N individual `GET /api/objects/{id}` requests
 * — the prior pattern for the relation widget. The response intentionally
 * stays small (no attributes_indexed, no completeness): a richer rich-
 * preview-card payload should request its own dedicated fields list.
 *
 * Cross-tenant ids return as missing — `findByIds()` applies TenantFilter.
 */
final class ObjectSummaryBatchController
{
    private const int MAX_IDS_PER_REQUEST = 200;

    public function __construct(
        private readonly CatalogObjectRepositoryInterface $objects,
    ) {
    }

    #[Route(
        '/api/objects/summaries',
        name: 'pim_objects_summaries_batch',
        methods: ['POST'],
    )]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    #[RequiresPermission(module: 'products', action: 'view')]
    public function __invoke(Request $request): JsonResponse
    {
        $body = $request->getContent();
        if ('' === $body) {
            return new JsonResponse([]);
        }

        try {
            $decoded = json_decode($body, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new BadRequestHttpException('Request body is not valid JSON.', $e);
        }
        if (!\is_array($decoded) || !\array_key_exists('ids', $decoded) || !\is_array($decoded['ids'])) {
            throw new BadRequestHttpException('Body must contain `ids` (array of UUID strings).');
        }

        if (\count($decoded['ids']) > self::MAX_IDS_PER_REQUEST) {
            throw new UnprocessableEntityHttpException(\sprintf(
                'At most %d ids per batch (got %d).',
                self::MAX_IDS_PER_REQUEST,
                \count($decoded['ids']),
            ));
        }

        $uuids = [];
        $seen = [];
        foreach ($decoded['ids'] as $raw) {
            if (!\is_string($raw) || '' === $raw) {
                continue;
            }
            try {
                $uuid = Uuid::fromString($raw);
            } catch (InvalidArgumentException) {
                continue;
            }
            $key = $uuid->toRfc4122();
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $uuids[] = $uuid;
        }

        if ([] === $uuids) {
            return new JsonResponse([]);
        }

        $summaries = [];
        foreach ($uuids as $uuid) {
            $obj = $this->objects->findById($uuid);
            if (null === $obj) {
                continue;
            }
            $indexed = $obj->getAttributesIndexed();
            $name = self::pickName($indexed);
            $type = $obj->getObjectType();
            $summaries[] = [
                'id' => $obj->getId()->toRfc4122(),
                'code' => $obj->getCode(),
                'name' => $name,
                'objectType' => [
                    'id' => $type->getId()->toRfc4122(),
                    'code' => $type->getCode(),
                    'kind' => $type->getKind()->value,
                ],
            ];
        }

        return new JsonResponse($summaries);
    }

    /**
     * Best-effort display name resolution from `attributes_indexed`.
     * Falls back to NULL when no obvious candidate exists; the FE then
     * uses the object code instead.
     *
     * @param array<string, mixed>|null $indexed
     */
    private static function pickName(?array $indexed): ?string
    {
        if (null === $indexed) {
            return null;
        }
        foreach (['name', 'title', 'label'] as $candidate) {
            $value = $indexed[$candidate] ?? null;
            if (\is_string($value) && '' !== $value) {
                return $value;
            }
            if (\is_array($value)) {
                // Locale-scoped string: prefer pl, then en, then any.
                foreach (['pl', 'en'] as $locale) {
                    $localized = $value[$locale] ?? null;
                    if (\is_string($localized) && '' !== $localized) {
                        return $localized;
                    }
                }
                foreach ($value as $localized) {
                    if (\is_string($localized) && '' !== $localized) {
                        return $localized;
                    }
                }
            }
        }

        return null;
    }
}
