<?php

declare(strict_types=1);

namespace App\Channel\Domain\Entity;

use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * ISO 4217 currency code (`PLN`, `EUR`, `USD`, …) shared across tenants.
 *
 * Mirrors {@see Locale}: global, opt-in via the Channel ↔ Currency M2M.
 */
class Currency
{
    private Uuid $id;

    #[Assert\NotBlank]
    #[Assert\Length(max: 8)]
    private string $code;

    private string $symbol;

    private string $label;

    public function __construct(string $code, string $symbol, string $label, ?Uuid $id = null)
    {
        $this->id = $id ?? Uuid::v7();
        $this->code = $code;
        $this->symbol = $symbol;
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

    public function getSymbol(): string
    {
        return $this->symbol;
    }

    public function getLabel(): string
    {
        return $this->label;
    }
}
