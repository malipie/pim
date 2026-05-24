<?php

declare(strict_types=1);

namespace App\Catalog\Application\Command\UpdateCatalogObject;

use Symfony\Component\Uid\Uuid;

/**
 * PATCH a {@see \App\Catalog\Domain\Entity\CatalogObject}.
 *
 * Every mutable field is nullable; the handler only calls the
 * corresponding domain method when the value is non-null. `code` and
 * `objectTypeId` are intentionally absent — they are immutable post-
 * creation (lessons #0.0.3 — fields outside `:patch` group are silently
 * ignored).
 *
 * `clearParent` is a sentinel used by API Platform JSON Merge Patch to
 * distinguish "do not touch" (parent omitted) from "remove parent"
 * (parent → null). Without it a `null` value would clash with the
 * "absent" semantics of the nullable property.
 */
final readonly class UpdateCatalogObjectCommand
{
    /**
     * @param array<string, mixed>|null $attributes per-attribute partial
     *                                              update (`null` = no
     *                                              attribute change)
     */
    public function __construct(
        public Uuid $id,
        public ?bool $enabled = null,
        public ?string $status = null,
        public ?Uuid $parentId = null,
        public bool $clearParent = false,
        public ?string $path = null,
        public bool $clearPath = false,
        public ?array $attributes = null,
        /**
         * MODR-10 (#932) — optimistic-lock guard. NULL = caller does not
         * care about concurrency (backward compat with pre-MODR-10
         * clients). Non-null = handler compares to current `version` and
         * throws 409 on mismatch.
         */
        public ?int $expectedVersion = null,
    ) {
    }
}
