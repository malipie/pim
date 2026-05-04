<?php

declare(strict_types=1);

namespace App\Identity\Domain\Value;

use InvalidArgumentException;
use Symfony\Component\Uid\Uuid;

/**
 * VIEW-08 (#427) — single entry of `MenuConfiguration.items` JSONB array.
 *
 * `kind`:
 *   - `system` → `ref` is a slug from {@see \App\Identity\Domain\SystemMenuItemRegistry}
 *   - `object_type` → `ref` is the ObjectType UUID (string form)
 *
 * Stored as a plain map in JSONB to keep the per-tenant config atomic
 * (one row, one PUT replaces the whole list — no half-applied state).
 */
final readonly class MenuItemRecord
{
    public const string KIND_SYSTEM = 'system';
    public const string KIND_OBJECT_TYPE = 'object_type';

    public function __construct(
        public string $kind,
        public string $ref,
        public int $position,
        public bool $visible,
    ) {
        if (self::KIND_SYSTEM !== $kind && self::KIND_OBJECT_TYPE !== $kind) {
            throw new InvalidArgumentException(\sprintf(
                'MenuItemRecord.kind must be "%s" or "%s", got "%s".',
                self::KIND_SYSTEM,
                self::KIND_OBJECT_TYPE,
                $kind,
            ));
        }
        if ('' === trim($ref)) {
            throw new InvalidArgumentException('MenuItemRecord.ref must not be empty.');
        }
        if (self::KIND_OBJECT_TYPE === $kind && !Uuid::isValid($ref)) {
            throw new InvalidArgumentException(\sprintf(
                'MenuItemRecord.ref for kind=object_type must be a UUID, got "%s".',
                $ref,
            ));
        }
        if ($position < 0) {
            throw new InvalidArgumentException(\sprintf(
                'MenuItemRecord.position must be >= 0, got %d.',
                $position,
            ));
        }
    }

    /**
     * @return array{kind: string, ref: string, position: int, visible: bool}
     */
    public function toArray(): array
    {
        return [
            'kind' => $this->kind,
            'ref' => $this->ref,
            'position' => $this->position,
            'visible' => $this->visible,
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        if (!isset($data['kind'], $data['ref'], $data['position'], $data['visible'])) {
            throw new InvalidArgumentException(
                'MenuItemRecord array must contain kind, ref, position, visible keys.',
            );
        }
        if (!\is_string($data['kind']) || !\is_string($data['ref'])) {
            throw new InvalidArgumentException('MenuItemRecord kind/ref must be strings.');
        }
        if (!\is_int($data['position'])) {
            throw new InvalidArgumentException('MenuItemRecord position must be an int.');
        }
        if (!\is_bool($data['visible'])) {
            throw new InvalidArgumentException('MenuItemRecord visible must be a bool.');
        }

        return new self(
            kind: $data['kind'],
            ref: $data['ref'],
            position: $data['position'],
            visible: $data['visible'],
        );
    }

    public function withPosition(int $position): self
    {
        return new self($this->kind, $this->ref, $position, $this->visible);
    }

    public function withVisible(bool $visible): self
    {
        return new self($this->kind, $this->ref, $this->position, $visible);
    }
}
