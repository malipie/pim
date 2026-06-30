<?php

declare(strict_types=1);

namespace App\Integration\Generic\Application\Mapping;

use App\Integration\Generic\Domain\Entity\Connection;
use App\Integration\Generic\Domain\Entity\FieldMapping;
use App\Integration\Generic\Domain\Enum\RemoteFieldDataType;
use App\Integration\Generic\Domain\Repository\FieldMappingRepositoryInterface;
use App\Integration\Generic\Domain\Repository\RemoteEndpointRepositoryInterface;
use App\Integration\Generic\Domain\Repository\RemoteFieldRepositoryInterface;

/**
 * Validates a connection's field mappings (ADR-0022, epic APIC, ticket APIC-P2-08).
 *
 * Two checks:
 *  - **error** — an inbound (or both-way) sync needs at least one match key to
 *    identify the row to upsert; without one the mapping set is invalid.
 *  - **warning** — a 1:1 mapping is meant for scalars, so a composite (object/
 *    array) remote field, or a path absent from the discovered schema, is
 *    flagged but not blocked (minimal coercion, ADR-0016 decision 5).
 *
 * The PIM target's own type is not resolved here (that would reach into the
 * Catalog context); the realistic, cross-context-free signal is the remote
 * field's shape, which is what the warnings report.
 */
final readonly class MappingValidator
{
    public function __construct(
        private FieldMappingRepositoryInterface $mappings,
        private RemoteEndpointRepositoryInterface $endpoints,
        private RemoteFieldRepositoryInterface $fields,
    ) {
    }

    public function validate(Connection $connection): MappingValidationResult
    {
        $mappings = $this->mappings->findByConnection($connection);
        $typesByPath = $this->discoveredTypes($connection);

        return new MappingValidationResult(
            $this->collectErrors($mappings),
            $this->collectWarnings($mappings, $typesByPath),
        );
    }

    /**
     * @param list<FieldMapping> $mappings
     *
     * @return list<string>
     */
    private function collectErrors(array $mappings): array
    {
        $hasInbound = false;
        $hasMatchKey = false;
        foreach ($mappings as $mapping) {
            if ($mapping->getDirection()->appliesInbound()) {
                $hasInbound = true;
            }
            if ($mapping->isMatchKey()) {
                $hasMatchKey = true;
            }
        }

        $errors = [];
        if ($hasInbound && !$hasMatchKey) {
            $errors[] = 'Inbound sync requires at least one mapping marked as a match key.';
        }

        return $errors;
    }

    /**
     * @param list<FieldMapping>                 $mappings
     * @param array<string, RemoteFieldDataType> $typesByPath
     *
     * @return list<MappingWarning>
     */
    private function collectWarnings(array $mappings, array $typesByPath): array
    {
        $warnings = [];
        foreach ($mappings as $mapping) {
            $path = $mapping->getRemoteFieldPath();
            $type = $typesByPath[$path] ?? null;

            if (null === $type) {
                // Discovery reflects the remote's READ schema. An outbound-only
                // (write) path legitimately differs from it — e.g. IdoSell writes
                // `productNames` but reads `productDescriptionsLangData` — so only
                // flag a missing path when the mapping actually reads (#1888).
                if ($mapping->getDirection()->appliesInbound()) {
                    $warnings[] = new MappingWarning(
                        $mapping->getPimTarget(),
                        $path,
                        'Remote field path is not in the discovered schema; run discovery or check the path.',
                    );
                }

                continue;
            }

            if (!$type->isScalar()) {
                $warnings[] = new MappingWarning(
                    $mapping->getPimTarget(),
                    $path,
                    \sprintf('Remote field is %s; a 1:1 mapping to a scalar target may lose data.', $type->value),
                );
            }
        }

        return $warnings;
    }

    /**
     * Builds the remoteFieldPath → dataType index from the connection's
     * discovered fields (across all its endpoints).
     *
     * @return array<string, RemoteFieldDataType>
     */
    private function discoveredTypes(Connection $connection): array
    {
        $byPath = [];
        foreach ($this->endpoints->findByConnection($connection) as $endpoint) {
            foreach ($this->fields->findByEndpoint($endpoint) as $field) {
                $byPath[$field->getPath()] = $field->getDataType();
            }
        }

        return $byPath;
    }
}
