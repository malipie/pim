<?php

declare(strict_types=1);

namespace App\Catalog\Presentation\Controller;

use App\Catalog\Application\Service\SchemaSnapshotFactory;
use App\Catalog\Domain\Repository\CatalogObjectRepositoryInterface;
use App\Identity\Contracts\Attribute\RequiresPermission;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Uid\Uuid;

/**
 * CHC-04 (#1288) — operator acknowledges a product's schema drift: clears the
 * `schema_drift` flag and re-baselines the snapshot to the current effective
 * attribute-group set, so future moves are measured against what the operator
 * just confirmed.
 */
final class SchemaDriftAcknowledgeController
{
    private const string UUID_REGEX = '[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}';

    public function __construct(
        private readonly CatalogObjectRepositoryInterface $objects,
        private readonly SchemaSnapshotFactory $snapshots,
        private readonly EntityManagerInterface $em,
    ) {
    }

    #[Route('/api/products/{id}/schema-drift/acknowledge', name: 'pim_products_schema_drift_acknowledge', requirements: ['id' => self::UUID_REGEX], methods: ['POST'], format: 'json')]
    #[Route('/api/objects/{id}/schema-drift/acknowledge', name: 'pim_objects_schema_drift_acknowledge', requirements: ['id' => self::UUID_REGEX], methods: ['POST'], format: 'json')]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    #[RequiresPermission(module: 'products', action: 'edit')]
    public function __invoke(string $id): JsonResponse
    {
        try {
            $uuid = Uuid::fromString($id);
        } catch (InvalidArgumentException $e) {
            throw new NotFoundHttpException(\sprintf('Object "%s" was not found.', $id), $e);
        }

        $object = $this->objects->findById($uuid);
        if (null === $object) {
            throw new NotFoundHttpException(\sprintf('Object "%s" was not found.', $id));
        }

        $object->recordSchemaSnapshot($this->snapshots->build($object));
        $object->flagSchemaDrift(false);
        $this->em->flush();

        return new JsonResponse(['schemaDrift' => false]);
    }
}
