<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\Security;

use App\Catalog\Domain\Entity\Product;
use App\Identity\Infrastructure\Security\AbstractRbacVoter;

/**
 * Authorisation gate for the Product API resource.
 *
 * Product is the first concrete catalog entity (Sprint-0). After ADR-009 it
 * becomes one shape of the generic `Object` (kind='product'); this voter
 * already maps to the RBAC resource code "object" so the same matrix wins
 * on day one of epic 0.3 — no permission rename needed.
 *
 * READ / WRITE / DELETE map onto the matching RBAC actions. Symfony emits
 * "READ" / "EDIT" / "DELETE" by default; API Platform passes its own strings
 * (READ / CREATE / UPDATE / DELETE) through `is_granted(...)` expressions on
 * each operation.
 */
final class ProductVoter extends AbstractRbacVoter
{
    public const string READ = 'READ';
    public const string CREATE = 'CREATE';
    public const string UPDATE = 'UPDATE';
    public const string DELETE = 'DELETE';

    protected function resource(): string
    {
        return 'object';
    }

    protected function subjectClass(): string
    {
        return Product::class;
    }

    protected function attributeMap(): array
    {
        return [
            self::READ => 'read',
            self::CREATE => 'write',
            self::UPDATE => 'write',
            self::DELETE => 'delete',
        ];
    }
}
