<?php

declare(strict_types=1);

namespace App\Identity\Domain\Entity;

use DateTimeImmutable;
use Symfony\Component\Uid\Uuid;

/**
 * Atomic RBAC permission — a (resource, action) pair, e.g. (product, write).
 *
 * `code` is a convenience identifier for seeders and references in code
 * (e.g. `product.write`). Schema uniqueness is enforced on (resource, action)
 * because the matrix is the source of truth; `code` is a derived label.
 */
class Permission
{
    public const string ACTION_READ = 'read';
    public const string ACTION_WRITE = 'write';
    public const string ACTION_DELETE = 'delete';
    public const string ACTION_ADMIN = 'admin';

    private Uuid $id;

    private string $code;

    private string $resource;

    private string $action;

    private DateTimeImmutable $createdAt;

    public function __construct(
        string $resource,
        string $action,
        ?string $code = null,
        ?Uuid $id = null,
    ) {
        $this->id = $id ?? Uuid::v7();
        $this->resource = $resource;
        $this->action = $action;
        $this->code = $code ?? \sprintf('%s.%s', $resource, $action);
        $this->createdAt = new DateTimeImmutable();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function getResource(): string
    {
        return $this->resource;
    }

    public function getAction(): string
    {
        return $this->action;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }
}
