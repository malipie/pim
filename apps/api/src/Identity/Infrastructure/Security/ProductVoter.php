<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Security;

use App\Catalog\Domain\Entity\CatalogObject;
use App\Catalog\Domain\ObjectKind;

/**
 * RBAC-P3-002 (#665) — per-product authorization aligned with the
 * PRD §3.2 macierz permission codes (`products.view`,
 * `products.bulk_operations`, `products.approve_pending_changes`, …).
 *
 * Subject discrimination: ADR-009 unified Product/Category/Asset into
 * the single {@see CatalogObject} entity with a {@see ObjectKind}
 * discriminator. This voter accepts only `kind=Product` instances; the
 * sibling voters (#666 CategoryVoter / AssetVoter) handle the other
 * built-in kinds. Class-level subjects (Post / GetCollection) are
 * gated by {@see CatalogObject::class} alone — the kind discriminator
 * applies on instances, where collection scope is enforced by
 * Doctrine filters.
 *
 * Per-attribute restrictions (#671 AttributePermissionPolicy),
 * locale/channel scope (#672 LocaleChannelScopePolicy), workflow-state
 * gating (#674 WorkflowStatePolicy) hook in via separate voters /
 * policies — this voter handles only the broad PRD §3.2 gate. The
 * EndpointGuardListener (#664) calls `Security::isGranted` against
 * this voter when `#[RequiresPermission(module: 'products', action:
 * 'edit', subject: '...')]` declares a subject.
 */
final class ProductVoter extends AbstractPrdVoter
{
    /**
     * @return array<string, string>
     */
    protected function permissionMap(): array
    {
        return [
            'view' => 'products.view',
            'add' => 'products.add',
            'edit' => 'products.edit',
            'delete' => 'products.delete',
            'bulk_operations' => 'products.bulk_operations',
            'approve_pending_changes' => 'products.approve_pending_changes',
        ];
    }

    protected function subjectClass(): string
    {
        return CatalogObject::class;
    }

    protected function acceptsSubject(mixed $subject): bool
    {
        if (\is_string($subject)) {
            return CatalogObject::class === $subject;
        }

        if (!$subject instanceof CatalogObject) {
            return false;
        }

        return ObjectKind::Product === $subject->getKind();
    }
}
