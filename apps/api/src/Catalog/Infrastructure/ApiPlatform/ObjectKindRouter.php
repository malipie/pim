<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\ApiPlatform;

use App\Catalog\Domain\Exception\DisabledFeatureException;
use App\Catalog\Domain\ObjectKind;

/**
 * Pure mapping table: `ObjectKind` → API surface metadata.
 *
 * Built-in kinds (Product, Category, Asset) ship with dedicated sugar paths
 * (`/api/products`, `/api/categories`, `/api/assets`) and per-kind serializer
 * groups so AP4 can branch context off the operation's `extraProperties.kind`
 * (#41 wires the actual `#[ApiResource]` declarations). `Custom` is the
 * escape hatch for tenant-defined kinds in phase 2/3 — until then it has
 * no public API surface, so the lookup throws.
 *
 * The class is stateless + pure so callers (AP4 metadata layer, admin UI
 * for navigation, agent tool in phase 2) can rely on the same answers
 * without wiring an EntityManager. The custom-kind feature flag is checked
 * by {@see CustomObjectTypeApiGuard}, not here — this file maps, the guard
 * gates.
 */
final class ObjectKindRouter
{
    /**
     * @var array<value-of<ObjectKind>, array{path: string, groups: list<string>}>
     */
    private const array BUILT_IN_ROUTES = [
        'product' => [
            'path' => '/api/products',
            'groups' => ['object:read', 'object:read:product'],
        ],
        'category' => [
            'path' => '/api/categories',
            'groups' => ['object:read', 'object:read:category'],
        ],
        'asset' => [
            'path' => '/api/assets',
            'groups' => ['object:read', 'object:read:asset'],
        ],
    ];

    /**
     * Sugar path for a built-in kind. `Custom` raises — the API surface for
     * custom kinds lives behind `/api/objects?kind=...` (phase 2) and the
     * router will not pretend to know its path.
     */
    public function pathFor(ObjectKind $kind): string
    {
        if (ObjectKind::Custom === $kind) {
            throw DisabledFeatureException::customObjectTypesDisabled();
        }

        return self::BUILT_IN_ROUTES[$kind->value]['path'];
    }

    /**
     * Serializer groups to merge into the AP4 context for the given kind.
     * Always includes the shared `object:read` group plus a kind-specific
     * one so #41's normalizers can opt fields in/out per kind.
     *
     * @return list<string>
     */
    public function groupsFor(ObjectKind $kind): array
    {
        if (ObjectKind::Custom === $kind) {
            // Custom kinds get only the shared group in MVP — phase 2's
            // schema editor will register per-tenant groups dynamically.
            return ['object:read'];
        }

        return self::BUILT_IN_ROUTES[$kind->value]['groups'];
    }

    /**
     * Convenience for the AP4 metadata factory in #41: returns the list of
     * built-in kinds so it can stamp one `#[ApiResource]` declaration per
     * kind without hardcoding the list.
     *
     * ADR-014 / MOD-10 (#902) — Brand was removed from the built-in pool.
     * UX-01 finished the cleanup by dropping the enum case altogether.
     *
     * @return list<ObjectKind>
     */
    public static function builtInKinds(): array
    {
        return [ObjectKind::Product, ObjectKind::Category, ObjectKind::Asset];
    }
}
