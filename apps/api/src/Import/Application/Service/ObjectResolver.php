<?php

declare(strict_types=1);

namespace App\Import\Application\Service;

use App\Catalog\Domain\Entity\CatalogObject;
use App\Catalog\Domain\Repository\CatalogObjectRepositoryInterface;
use App\Import\Domain\Enum\ImportMode;
use App\Shared\Domain\Tenant;
use Doctrine\ORM\EntityManagerInterface;

/**
 * ADR-0019 D1 / IMP2-1.3 (#1465) — resolves an import row to an existing
 * CatalogObject by the configured match key: `objects.code` (SKU, default)
 * or one attribute of type `identifier` (e.g. EAN — the Bosch benchmark
 * files update by EAN).
 *
 * Deliberately per-row in this ticket (the validator already paid the same
 * per-row lookup for its duplicate check); chunk prefetch arrives with the
 * ImportValueWriter in IMP2-1.4 (#1466).
 */
final readonly class ObjectResolver
{
    public function __construct(
        private CatalogObjectRepositoryInterface $catalogObjects,
        private EntityManagerInterface $em,
    ) {
    }

    public function resolve(
        string $key,
        \App\Catalog\Domain\Entity\ObjectType $objectType,
        Tenant $tenant,
        ?string $matchAttributeCode,
    ): ?CatalogObject {
        $key = trim($key);
        if ('' === $key) {
            return null;
        }

        if (null === $matchAttributeCode) {
            return $this->catalogObjects->findByCode($key, $objectType->getKind(), $tenant);
        }

        // Identifier attributes store the canonical {value: "<scalar>"}
        // envelope (ADR-0019 D7); comparison is case-sensitive after trim.
        // Native SQL (no registered JSONB DQL functions) — tenant scoping is
        // therefore explicit, the TenantFilter does not cover native queries.
        $id = $this->em->getConnection()->fetchOne(
            <<<'SQL'
                SELECT o.id
                FROM objects o
                JOIN object_values ov ON ov.object_id = o.id
                JOIN attributes a ON a.id = ov.attribute_id
                WHERE a.code = :code
                  AND o.object_type_id = :objectTypeId
                  AND o.tenant_id = :tenantId
                  AND ov.value->>'value' = :key
                LIMIT 1
                SQL,
            [
                'code' => $matchAttributeCode,
                'objectTypeId' => $objectType->getId()->toRfc4122(),
                'tenantId' => $tenant->getId()->toRfc4122(),
                'key' => $key,
            ],
        );

        if (false === $id || null === $id) {
            return null;
        }

        return $this->em->find(CatalogObject::class, $id);
    }

    /**
     * IMP2-1.4 (#1466) — batch resolve for a chunk: one query instead of one
     * per row. Keys are trimmed, empty keys ignored.
     *
     * @param list<string> $keys
     *
     * @return array<string, CatalogObject> key => object (missing keys absent)
     */
    public function resolveMany(
        array $keys,
        \App\Catalog\Domain\Entity\ObjectType $objectType,
        Tenant $tenant,
        ?string $matchAttributeCode,
    ): array {
        $keys = array_values(array_unique(array_filter(array_map(trim(...), $keys), static fn (string $k): bool => '' !== $k)));
        if ([] === $keys) {
            return [];
        }

        if (null === $matchAttributeCode) {
            $rows = $this->em->createQueryBuilder()
                ->select('o')
                ->from(CatalogObject::class, 'o')
                ->where('o.code IN (:keys)')
                ->andWhere('o.objectType = :objectType')
                ->setParameter('keys', $keys)
                ->setParameter('objectType', $objectType)
                ->getQuery()
                ->getResult();

            /** @var list<CatalogObject> $rows */
            $map = [];
            foreach ($rows as $row) {
                $map[$row->getCode()] = $row;
            }

            return $map;
        }

        $idRows = $this->em->getConnection()->fetchAllAssociative(
            <<<'SQL'
                SELECT o.id, ov.value->>'value' AS match_key
                FROM objects o
                JOIN object_values ov ON ov.object_id = o.id
                JOIN attributes a ON a.id = ov.attribute_id
                WHERE a.code = :code
                  AND o.object_type_id = :objectTypeId
                  AND o.tenant_id = :tenantId
                  AND ov.value->>'value' IN (:keys)
                SQL,
            [
                'code' => $matchAttributeCode,
                'objectTypeId' => $objectType->getId()->toRfc4122(),
                'tenantId' => $tenant->getId()->toRfc4122(),
                'keys' => $keys,
            ],
            ['keys' => \Doctrine\DBAL\ArrayParameterType::STRING],
        );

        $map = [];
        foreach ($idRows as $idRow) {
            $object = $this->em->find(CatalogObject::class, $idRow['id']);
            if ($object instanceof CatalogObject && \is_string($idRow['match_key'])) {
                $map[$idRow['match_key']] = $object;
            }
        }

        return $map;
    }

    /**
     * CREATE skips matched rows, UPDATE skips unmatched ones, UPSERT
     * branches — returns the decision for the run loop.
     */
    public function decide(ImportMode $mode, ?CatalogObject $existing): ImportRowDecision
    {
        return match (true) {
            ImportMode::Create === $mode && null !== $existing => ImportRowDecision::SkipExists,
            ImportMode::Update === $mode && null === $existing => ImportRowDecision::SkipNoMatch,
            null === $existing => ImportRowDecision::Create,
            default => ImportRowDecision::Update,
        };
    }
}
