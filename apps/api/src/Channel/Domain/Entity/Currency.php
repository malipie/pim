<?php

declare(strict_types=1);

namespace App\Channel\Domain\Entity;

use App\Channel\Infrastructure\Doctrine\Repository\CurrencyRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * ISO 4217 currency code (`PLN`, `EUR`, `USD`, …) shared across tenants.
 *
 * Mirrors {@see Locale}: global, opt-in via the Channel ↔ Currency M2M.
 */
#[ORM\Entity(repositoryClass: CurrencyRepository::class)]
#[ORM\Table(name: 'currencies')]
#[ORM\UniqueConstraint(name: 'currencies_code_uniq', columns: ['code'])]
class Currency
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    private Uuid $id;

    #[ORM\Column(type: 'string', length: 8)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 8)]
    private string $code;

    #[ORM\Column(type: Types::STRING, length: 8)]
    private string $symbol;

    #[ORM\Column(type: Types::STRING, length: 64)]
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
