<?php

declare(strict_types=1);

namespace App\Tests\Unit\Catalog;

use App\Catalog\Domain\Provenance;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ProvenanceTest extends TestCase
{
    #[Test]
    public function threeMvpCasesAreDefinedExactly(): void
    {
        // Phase 2 adds the `agent` case (epic 0.7). Until then we ship
        // exactly three so a stale fixture cannot accidentally claim
        // agent provenance — guard against drift.
        self::assertCount(3, Provenance::cases());
    }

    #[Test]
    public function backingValuesRoundTrip(): void
    {
        foreach (Provenance::cases() as $case) {
            self::assertSame(strtolower($case->value), $case->value);
            self::assertSame($case, Provenance::from($case->value));
        }
    }

    #[Test]
    public function agentCaseIsExplicitlyAbsent(): void
    {
        // Negative guard: phase 2 will add this case alongside the agent
        // approval inbox in epic 0.7. If someone adds it here without
        // shipping the inbox, this test fails and forces a conscious
        // change.
        self::assertNull(Provenance::tryFrom('agent'));
    }
}
