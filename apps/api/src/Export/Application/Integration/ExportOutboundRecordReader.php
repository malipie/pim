<?php

declare(strict_types=1);

namespace App\Export\Application\Integration;

use App\Catalog\Contracts\Integration\OutboundRecord;
use App\Catalog\Contracts\Integration\OutboundRecordReader;
use App\Catalog\Domain\Entity\CatalogObject;
use App\Catalog\Domain\Entity\ObjectValue;
use App\Catalog\Domain\Repository\CatalogObjectRepositoryInterface;
use App\Catalog\Domain\Repository\ObjectTypeRepositoryInterface;
use App\Export\Application\Builder\ValueSerializer;
use App\Shared\Application\TenantContext;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

/**
 * Export-side implementation of the outbound-sync read seam (APIC-P3-06).
 *
 * Reuses the export {@see ValueSerializer} (the cell serializer) instead of a
 * bespoke one, so the outbound payload matches what a file export would emit.
 * Iterates the ObjectType's objects and yields each global (locale/channel-null)
 * value for the requested attribute codes, serialised to a scalar string.
 *
 * MVP reads the full object set per run; keyset paging for 50k+ catalogs is a
 * follow-up (the inbound runner has the same bounded-scope note).
 */
final readonly class ExportOutboundRecordReader implements OutboundRecordReader
{
    public function __construct(
        private ObjectTypeRepositoryInterface $objectTypes,
        private CatalogObjectRepositoryInterface $objects,
        private ValueSerializer $serializer,
        private TenantContext $tenantContext,
        private EntityManagerInterface $em,
    ) {
    }

    public function read(Uuid $objectTypeId, array $codes): iterable
    {
        $tenant = $this->tenantContext->get();
        if (null === $tenant) {
            return;
        }

        $objectType = $this->objectTypes->findById($objectTypeId);
        if (null === $objectType) {
            return;
        }

        $wanted = array_fill_keys($codes, true);

        foreach ($this->objects->findByObjectType($objectType, $tenant) as $object) {
            $values = [];
            foreach ($this->globalValues($object) as $code => $objectValue) {
                if (isset($wanted[$code])) {
                    $values[$code] = $this->serializer->serialize($objectValue);
                }
            }

            yield new OutboundRecord($object->getId()->toRfc4122(), $values);
        }
    }

    /**
     * @return iterable<string, ObjectValue> attribute code => the global value
     *                                       (locale/channel-null) only
     */
    private function globalValues(CatalogObject $object): iterable
    {
        $values = $this->em->getRepository(ObjectValue::class)->findBy(['object' => $object]);
        foreach ($values as $value) {
            if (null === $value->getLocale() && null === $value->getChannelId()) {
                yield $value->getAttribute()->getCode() => $value;
            }
        }
    }
}
