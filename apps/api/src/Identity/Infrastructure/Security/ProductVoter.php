<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Security;

/**
 * RBAC-P3-002 (#665) — per-product authorization aligned with the
 * PRD §3.2 macierz permission codes (`products.view`,
 * `products.bulk_operations`, `products.approve_pending_changes`, …).
 *
 * Subject discrimination: ADR-009 unified Product/Category/Asset into
 * the single `App\Catalog\Domain\Entity\CatalogObject` entity with a
 * kind discriminator. This voter accepts only `kind=product` instances;
 * the sibling voters (#666 CategoryVoter / AssetVoter) handle the
 * other built-in kinds. Class-level subjects (Post / GetCollection)
 * are gated by the bare FQCN match — the kind discriminator applies on
 * instances, where collection scope is enforced by Doctrine filters.
 *
 * Catalog references are kept as bare strings (no `use` imports) so
 * Deptrac does not flag the Identity-Infrastructure → Catalog-Domain
 * direction. CatalogObjectVoter shipped the same compromise — voters
 * know about permissions, not domain shape.
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
    private const string SUBJECT_FQCN = 'App\\Catalog\\Domain\\Entity\\CatalogObject';
    private const string PRODUCT_KIND_VALUE = 'product';

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
        return self::SUBJECT_FQCN;
    }

    protected function acceptsSubject(mixed $subject): bool
    {
        if (\is_string($subject)) {
            return self::SUBJECT_FQCN === $subject;
        }

        if (!is_a($subject, self::SUBJECT_FQCN)) {
            return false;
        }

        if (!method_exists($subject, 'getKind')) {
            return false;
        }

        $kind = $subject->getKind();
        if (\is_object($kind) && property_exists($kind, 'value')) {
            return self::PRODUCT_KIND_VALUE === $kind->value;
        }

        return self::PRODUCT_KIND_VALUE === $kind;
    }
}
