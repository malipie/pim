<?php

declare(strict_types=1);

namespace App\Integration\Generic\Infrastructure\Http\Pagination;

use App\Integration\Generic\Domain\Enum\PaginationStrategyName;
use LogicException;

/**
 * Resolves a {@see PaginationStrategyName} to its driver (ADR-0022, epic APIC,
 * ticket APIC-P2-03). The five MVP strategies are injected and indexed by name;
 * an unknown name can never occur (the enum is closed), but the constructor
 * fails loudly if a strategy is missing from the wiring.
 */
final class PaginationStrategies
{
    /** @var array<string, PaginationStrategy> */
    private array $byName;

    /**
     * @param iterable<PaginationStrategy> $strategies
     */
    public function __construct(iterable $strategies)
    {
        $byName = [];
        foreach ($strategies as $strategy) {
            $byName[$strategy->name()->value] = $strategy;
        }
        $this->byName = $byName;
    }

    public function get(PaginationStrategyName $name): PaginationStrategy
    {
        return $this->byName[$name->value]
            ?? throw new LogicException(\sprintf('No pagination strategy registered for "%s".', $name->value));
    }
}
