<?php

declare(strict_types=1);

namespace App\Channel\Domain\Entity;

use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * BCP-47-shaped locale code (`pl_PL`, `en_US`, …) shared across tenants.
 *
 * Locales are global infrastructure rows — every tenant references the
 * same `pl_PL` row rather than carrying its own copy. The seeded set in
 * the migration covers the MVP-supported locales; tenants opt-in to a
 * subset via the Channel ↔ Locale M2M.
 *
 * No TenantScoped: the table sits on the audit allowlist as global
 * infrastructure, like `permissions` and the global RBAC roles.
 */
class Locale
{
    private Uuid $id;

    #[Assert\NotBlank]
    #[Assert\Length(max: 16)]
    private string $code;

    private string $label;

    public function __construct(string $code, string $label, ?Uuid $id = null)
    {
        $this->id = $id ?? Uuid::v7();
        $this->code = $code;
        $this->label = $label;
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function rename(string $label): void
    {
        $this->label = $label;
    }
}
