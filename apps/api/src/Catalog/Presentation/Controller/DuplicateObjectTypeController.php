<?php

declare(strict_types=1);

namespace App\Catalog\Presentation\Controller;

use App\Catalog\Application\ObjectTypeService;
use App\Catalog\Domain\Exception\DisabledFeatureException;
use App\Catalog\Domain\Exception\ObjectTypeCodeConflictException;
use App\Catalog\Domain\Repository\ObjectTypeRepositoryInterface;
use App\Identity\Domain\Attribute\RequiresPermission;
use App\Shared\Application\TenantContext;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Uid\Uuid;

/**
 * VIEW-01 (#372) — `POST /api/object_types/{id}/duplicate` for the
 * "Duplikuj" button on the modeling Detail header. Source can be built-in
 * or custom; the result is always a fresh custom ObjectType seeded with
 * the source's icon / color / settings. Caller supplies a unique code +
 * label payload.
 */
final class DuplicateObjectTypeController
{
    private const string UUID_REGEX = '[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}';

    public function __construct(
        private readonly ObjectTypeService $service,
        private readonly ObjectTypeRepositoryInterface $objectTypes,
        private readonly TenantContext $tenantContext,
    ) {
    }

    #[Route(
        '/api/object_types/{id}/duplicate',
        name: 'pim_object_types_duplicate',
        requirements: ['id' => self::UUID_REGEX],
        methods: ['POST'],
    )]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    #[RequiresPermission(module: 'modeling.object_types', action: 'add')]
    public function __invoke(string $id, Request $request): JsonResponse
    {
        $tenant = $this->tenantContext->get();
        if (null === $tenant) {
            throw new BadRequestHttpException('No tenant context.');
        }

        $source = $this->objectTypes->findById(Uuid::fromString($id));
        if (null === $source) {
            throw new NotFoundHttpException(\sprintf('ObjectType "%s" was not found.', $id));
        }

        /** @var array<string, mixed> $body */
        $body = json_decode($request->getContent(), true) ?? [];

        $newCode = $body['newCode'] ?? null;
        if (!\is_string($newCode) || '' === trim($newCode)) {
            throw new BadRequestHttpException('newCode is required.');
        }
        $newCode = trim($newCode);

        $rawLabel = $body['newLabel'] ?? null;
        if (!\is_array($rawLabel) || [] === $rawLabel) {
            throw new BadRequestHttpException('newLabel is required (per-locale map).');
        }
        $newLabel = [];
        foreach ($rawLabel as $locale => $value) {
            if (\is_string($locale) && \is_string($value) && '' !== trim($value)) {
                $newLabel[$locale] = trim($value);
            }
        }
        if ([] === $newLabel) {
            throw new BadRequestHttpException('newLabel must contain at least one non-empty entry.');
        }

        if (null !== $this->objectTypes->findByCode($newCode, $tenant)) {
            throw new ConflictHttpException(\sprintf('ObjectType with code "%s" already exists.', $newCode));
        }

        try {
            $clone = $this->service->duplicate($source, $newCode, $newLabel);
        } catch (DisabledFeatureException $e) {
            throw new HttpException(Response::HTTP_FORBIDDEN, $e->getMessage(), $e);
        } catch (ObjectTypeCodeConflictException $e) {
            throw new ConflictHttpException($e->getMessage(), $e);
        }

        return new JsonResponse([
            'id' => $clone->getId()->toRfc4122(),
            'code' => $clone->getCode(),
            'kind' => $clone->getKind()->value,
            'label' => $clone->getLabel(),
            'icon' => $clone->getIcon(),
            'color' => $clone->getColor(),
            'builtIn' => $clone->isBuiltIn(),
            'hierarchical' => $clone->isHierarchical(),
            'hasVariants' => $clone->hasVariants(),
            'abstract' => $clone->isAbstract(),
            'allowedParentTypeIds' => $clone->getAllowedParentTypeIds(),
            'schemaVersion' => $clone->getSchemaVersion(),
        ], Response::HTTP_CREATED);
    }
}
