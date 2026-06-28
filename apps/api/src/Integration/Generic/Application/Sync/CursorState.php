<?php

declare(strict_types=1);

namespace App\Integration\Generic\Application\Sync;

use App\Integration\Generic\Domain\Enum\CursorType;

/**
 * The parsed delta-sync cursor of a SyncBinding (ADR-0022, epic APIC, ticket
 * APIC-P3-03): the source `field`, its {@see CursorType} and the last persisted
 * `value` (null until the first run advances it). Serialises back to the
 * binding's `{field, type, state}` JSONB envelope.
 */
final readonly class CursorState
{
    public function __construct(
        public CursorType $type,
        public string $field,
        public ?string $value = null,
    ) {
    }

    /**
     * @param array<string, mixed>|null $cursor the binding's cursor JSONB
     */
    public static function fromArray(?array $cursor): ?self
    {
        if (null === $cursor) {
            return null;
        }

        $type = \is_string($cursor['type'] ?? null) ? CursorType::tryFrom($cursor['type']) : null;
        $field = $cursor['field'] ?? null;
        if (null === $type || !\is_string($field) || '' === $field) {
            return null;
        }

        $value = $cursor['state'] ?? null;

        return new self($type, $field, \is_string($value) ? $value : null);
    }

    public function withValue(string $value): self
    {
        return new self($this->type, $this->field, $value);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'field' => $this->field,
            'type' => $this->type->value,
            'state' => $this->value,
        ];
    }
}
