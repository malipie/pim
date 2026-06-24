<?php

declare(strict_types=1);

namespace App\Import\Application\Service\Structural;

use App\Catalog\Application\Command\CreateAttributeGroup\CreateAttributeGroupCommand;
use App\Catalog\Application\Command\UpdateAttributeGroup\UpdateAttributeGroupCommand;
use App\Catalog\Application\ObjectTypeAttributeGroupAssigner;
use App\Catalog\Domain\Entity\AttributeGroup;
use App\Catalog\Domain\Entity\ObjectType;
use App\Catalog\Domain\Repository\AttributeGroupRepositoryInterface;
use App\Catalog\Domain\Repository\ObjectTypeRepositoryInterface;
use App\Import\Application\Service\ImportColumnGrammar;
use App\Import\Application\Service\MultiValueSplitter;
use App\Import\Domain\Enum\ImportErrorType;
use App\Import\Domain\Enum\ImportLogLevel;
use App\Shared\Domain\Tenant;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Uid\Uuid;
use Throwable;

/**
 * Creates or updates one AttributeGroup definition from a structural-import
 * row, the mirror of the `attribute_groups` export. Upserts by `code` (the
 * natural key, unique per tenant). Empty optional cells leave the value
 * untouched (operator decision); the `object_types` cell re-attaches the group
 * to its modules additively (re-import never detaches). Unknown object-type
 * codes are skipped with a warning, never failing the row. The `is_built_in`
 * column is export-only metadata and is ignored here.
 */
final readonly class AttributeGroupImportCreator
{
    public function __construct(
        private MessageBusInterface $commandBus,
        private AttributeGroupRepositoryInterface $groups,
        private ObjectTypeRepositoryInterface $objectTypes,
        private ObjectTypeAttributeGroupAssigner $assigner,
        private ImportColumnGrammar $grammar,
    ) {
    }

    /**
     * @param array<string, string|null> $cells header => raw cell value
     */
    public function create(int $rowNumber, array $cells, Tenant $tenant): StructuralImportRowResult
    {
        $result = new StructuralImportRowResult();

        $code = trim($cells['code'] ?? '');
        if ('' === $code) {
            $result->setOutcome(StructuralImportRowResult::OUTCOME_ERROR);
            $result->log(ImportLogLevel::Error, 'Wiersz pominięty: brak wymaganej kolumny "code".', ImportErrorType::MissingRequired->value, 'code');

            return $result;
        }
        $result->code = $code;

        $label = $this->extractLocalized($cells, 'label', $tenant);
        $description = $this->extractLocalized($cells, 'description', $tenant);
        $icon = $this->stringCell($cells['icon'] ?? null);
        $color = $this->stringCell($cells['color'] ?? null);
        $requiredSection = $this->boolCell($cells['is_required_section'] ?? null);
        $shared = $this->boolCell($cells['is_shared'] ?? null);
        $position = $this->intCell($cells['position'] ?? null);
        $objectTypeCodes = MultiValueSplitter::split($cells['object_types'] ?? '');

        $existing = $this->groups->findByCode($code, $tenant);

        try {
            $group = null === $existing
                ? $this->createGroup($code, $label, $description, $icon, $color, $position, $requiredSection, $shared, $tenant, $result)
                : $this->updateGroup($existing, $label, $description, $icon, $color, $position, $requiredSection, $shared, $result);
        } catch (Throwable $exception) {
            $result->setOutcome(StructuralImportRowResult::OUTCOME_ERROR);
            $result->log(ImportLogLevel::Error, \sprintf('Grupa "%s": %s', $code, $exception->getMessage()), ImportErrorType::InvalidValue->value, 'code', $code);

            return $result;
        }

        if (!$group instanceof AttributeGroup) {
            return $result;
        }

        $this->assignObjectTypes($group, $objectTypeCodes, $tenant, $result);

        return $result;
    }

    /**
     * @param array<string, string> $label
     * @param array<string, string> $description
     */
    private function createGroup(
        string $code,
        array $label,
        array $description,
        ?string $icon,
        ?string $color,
        ?int $position,
        ?bool $requiredSection,
        ?bool $shared,
        Tenant $tenant,
        StructuralImportRowResult $result,
    ): ?AttributeGroup {
        $command = new CreateAttributeGroupCommand(
            code: $code,
            label: $label,
            description: [] === $description ? null : $description,
            icon: $icon,
            color: $color,
            position: $position ?? 0,
            requiredSection: $requiredSection ?? false,
            shared: $shared ?? true,
        );
        $newId = $this->dispatch($command);
        $result->setOutcome(StructuralImportRowResult::OUTCOME_CREATED);

        return $newId instanceof Uuid ? $this->groups->findById($newId) : $this->groups->findByCode($code, $tenant);
    }

    /**
     * @param array<string, string> $label
     * @param array<string, string> $description
     */
    private function updateGroup(
        AttributeGroup $existing,
        array $label,
        array $description,
        ?string $icon,
        ?string $color,
        ?int $position,
        ?bool $requiredSection,
        ?bool $shared,
        StructuralImportRowResult $result,
    ): AttributeGroup {
        // Empty cells leave the value untouched (operator decision): pass null
        // (don't touch) rather than the clear* flags.
        $command = new UpdateAttributeGroupCommand(
            id: $existing->getId(),
            label: [] === $label ? null : $label,
            description: [] === $description ? null : $description,
            icon: $icon,
            color: $color,
            position: $position,
            requiredSection: $requiredSection,
            shared: $shared,
        );
        $this->dispatch($command);
        $result->setOutcome(StructuralImportRowResult::OUTCOME_UPDATED);

        return $existing;
    }

    /**
     * @param list<string> $objectTypeCodes
     */
    private function assignObjectTypes(AttributeGroup $group, array $objectTypeCodes, Tenant $tenant, StructuralImportRowResult $result): void
    {
        foreach ($objectTypeCodes as $objectTypeCode) {
            $objectType = $this->objectTypes->findByCode($objectTypeCode, $tenant);
            if (!$objectType instanceof ObjectType) {
                $result->log(ImportLogLevel::Warning, \sprintf('Pominięto nieznany typ obiektu "%s".', $objectTypeCode), ImportErrorType::InvalidValue->value, 'object_types', $objectTypeCode);

                continue;
            }
            $this->assigner->assign($objectType, $group);
        }
    }

    private function dispatch(object $command): mixed
    {
        try {
            $envelope = $this->commandBus->dispatch($command);
        } catch (HandlerFailedException $exception) {
            throw $exception->getPrevious() ?? $exception;
        }
        $stamp = $envelope->last(HandledStamp::class);

        return $stamp instanceof HandledStamp ? $stamp->getResult() : null;
    }

    /**
     * @param array<string, string|null> $cells
     *
     * @return array<string, string>
     */
    private function extractLocalized(array $cells, string $base, Tenant $tenant): array
    {
        $out = [];
        foreach ($cells as $header => $value) {
            if (null === $value || '' === trim($value)) {
                continue;
            }
            $parsed = $this->grammar->parse($header, $tenant);
            if ($parsed->base === $base && null !== $parsed->locale) {
                $out[$parsed->locale] = trim($value);
            }
        }

        return $out;
    }

    private function stringCell(?string $raw): ?string
    {
        if (null === $raw) {
            return null;
        }
        $raw = trim($raw);

        return '' === $raw ? null : $raw;
    }

    private function boolCell(?string $raw): ?bool
    {
        if (null === $raw) {
            return null;
        }
        $raw = strtolower(trim($raw));
        if ('' === $raw) {
            return null;
        }

        return \in_array($raw, ['1', 'true', 'yes', 'on'], true);
    }

    private function intCell(?string $raw): ?int
    {
        if (null === $raw) {
            return null;
        }
        $raw = trim($raw);

        return '' === $raw || !is_numeric($raw) ? null : (int) $raw;
    }
}
