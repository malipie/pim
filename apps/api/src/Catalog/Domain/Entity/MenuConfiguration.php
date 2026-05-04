<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Entity;

use App\Catalog\Domain\Value\MenuItemRecord;
use App\Shared\Application\TenantScoped;
use App\Shared\Domain\Tenant;
use DateTimeImmutable;
use LogicException;
use Symfony\Component\Uid\Uuid;

/**
 * VIEW-08 (#427) — singleton per-tenant configuration of the main sidebar.
 *
 * Stores ordering + visibility for both `system` items (Pulpit, Multimedia,
 * Workflow, Integracje, Ustawienia, Modelowanie — see SystemMenuItemRegistry)
 * and `object_type` items (ObjectType.exposeToMainMenu = TRUE candidates).
 *
 * Single-row-per-tenant by `uniq_menu_config_per_tenant` constraint. PUT
 * replaces the entire `items` array atomically — no partial state, no
 * reorder-vs-toggle race. Default seed lives in `DefaultMenuSeeder`.
 *
 * `items` payload shape (JSONB):
 * ```json
 * [
 *   {"kind": "system", "ref": "dashboard", "position": 0, "visible": true},
 *   {"kind": "object_type", "ref": "01...uuid", "position": 1, "visible": true},
 *   ...
 * ]
 * ```
 */
class MenuConfiguration implements TenantScoped
{
    private Uuid $id;
    private ?Tenant $tenant = null;

    /**
     * @var list<array{kind: string, ref: string, position: int, visible: bool}>
     */
    private array $items = [];

    private DateTimeImmutable $createdAt;
    private DateTimeImmutable $updatedAt;

    public function __construct(?Uuid $id = null)
    {
        $this->id = $id ?? Uuid::v7();
        $this->createdAt = new DateTimeImmutable();
        $this->updatedAt = $this->createdAt;
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getTenant(): ?Tenant
    {
        return $this->tenant;
    }

    /**
     * @internal stamped by TenantAssignmentListener on prePersist
     */
    public function assignTenant(Tenant $tenant): void
    {
        if (null !== $this->tenant) {
            throw new LogicException('Tenant is already assigned and cannot be reassigned.');
        }

        $this->tenant = $tenant;
    }

    /**
     * @return list<MenuItemRecord>
     */
    public function getItems(): array
    {
        return array_map(
            static fn (array $row): MenuItemRecord => MenuItemRecord::fromArray($row),
            $this->items,
        );
    }

    /**
     * @param list<MenuItemRecord> $items
     */
    public function replaceItems(array $items): void
    {
        $this->items = array_map(
            static fn (MenuItemRecord $r): array => $r->toArray(),
            $items,
        );
        $this->touch();
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    private function touch(): void
    {
        $this->updatedAt = new DateTimeImmutable();
    }
}
