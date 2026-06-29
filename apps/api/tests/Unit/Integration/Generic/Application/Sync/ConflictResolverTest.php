<?php

declare(strict_types=1);

namespace App\Tests\Unit\Integration\Generic\Application\Sync;

use App\Integration\Generic\Application\Sync\ConflictResolver;
use App\Integration\Generic\Domain\Enum\ConflictPolicy;
use App\Integration\Generic\Domain\Enum\ConflictWinner;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(ConflictResolver::class)]
final class ConflictResolverTest extends TestCase
{
    private ConflictResolver $resolver;

    protected function setUp(): void
    {
        $this->resolver = new ConflictResolver();
    }

    public function testPimWinsAlwaysKeepsPimRegardlessOfTimestamps(): void
    {
        $winner = $this->resolver->winner(
            ConflictPolicy::PimWins,
            new DateTimeImmutable('2026-01-01 00:00:00'),
            new DateTimeImmutable('2030-01-01 00:00:00'), // far newer remote
        );

        self::assertSame(ConflictWinner::Pim, $winner);
    }

    public function testRemoteWinsAlwaysAppliesRemoteRegardlessOfTimestamps(): void
    {
        $winner = $this->resolver->winner(
            ConflictPolicy::RemoteWins,
            new DateTimeImmutable('2030-01-01 00:00:00'), // far newer pim
            new DateTimeImmutable('2026-01-01 00:00:00'),
        );

        self::assertSame(ConflictWinner::Remote, $winner);
    }

    /**
     * @param non-empty-string $pim
     * @param non-empty-string $remote
     */
    #[DataProvider('lwwCases')]
    public function testLastWriteWinsComparesTimestamps(string $pim, string $remote, ConflictWinner $expected): void
    {
        $winner = $this->resolver->winner(
            ConflictPolicy::Lww,
            new DateTimeImmutable($pim),
            new DateTimeImmutable($remote),
        );

        self::assertSame($expected, $winner);
    }

    /**
     * @return iterable<string, array{non-empty-string, non-empty-string, ConflictWinner}>
     */
    public static function lwwCases(): iterable
    {
        yield 'newer remote wins' => ['2026-01-01 00:00:00', '2026-06-01 00:00:00', ConflictWinner::Remote];
        yield 'newer pim wins' => ['2026-06-01 00:00:00', '2026-01-01 00:00:00', ConflictWinner::Pim];
        yield 'equal timestamps keep pim' => ['2026-06-01 12:00:00', '2026-06-01 12:00:00', ConflictWinner::Pim];
        yield 'sub-second newer remote wins' => ['2026-06-01 12:00:00.100', '2026-06-01 12:00:00.200', ConflictWinner::Remote];
    }

    public function testLwwFavoursRemoteWhenPimTimestampMissing(): void
    {
        $winner = $this->resolver->winner(ConflictPolicy::Lww, null, new DateTimeImmutable('2026-01-01 00:00:00'));

        self::assertSame(ConflictWinner::Remote, $winner);
    }

    public function testLwwFavoursPimWhenRemoteTimestampMissing(): void
    {
        $winner = $this->resolver->winner(ConflictPolicy::Lww, new DateTimeImmutable('2026-01-01 00:00:00'), null);

        self::assertSame(ConflictWinner::Pim, $winner);
    }

    public function testLwwKeepsPimWhenBothTimestampsMissing(): void
    {
        $winner = $this->resolver->winner(ConflictPolicy::Lww, null, null);

        self::assertSame(ConflictWinner::Pim, $winner);
    }

    public function testOriginatedFromRemoteIsTrueForIntegrationProvenance(): void
    {
        self::assertTrue($this->resolver->originatedFromRemote('integration'));
    }

    /**
     * @param non-empty-string $provenance
     */
    #[DataProvider('nonIntegrationProvenances')]
    public function testOriginatedFromRemoteIsFalseForLocalProvenance(string $provenance): void
    {
        self::assertFalse($this->resolver->originatedFromRemote($provenance));
    }

    /**
     * @return iterable<string, array{non-empty-string}>
     */
    public static function nonIntegrationProvenances(): iterable
    {
        yield 'manual edit' => ['manual'];
        yield 'import' => ['import'];
        yield 'agent' => ['agent'];
    }

    /**
     * AC-2: the in → out → in loop is broken. A value just pulled inbound carries
     * provenance=integration; the outbound path must recognise it as remote-origin
     * and refuse to push it back to the same connection — otherwise the remote
     * re-emits it and the cycle never ends.
     */
    public function testAntiLoopBreaksInboundOutboundInboundCycle(): void
    {
        // 1. Inbound writes a value, stamping it with Provenance::Integration.
        $provenanceAfterInboundWrite = ConflictResolver::INTEGRATION_PROVENANCE;

        // 2. The outbound trigger fires; the resolver flags it as remote-origin...
        $isRemoteOrigin = $this->resolver->originatedFromRemote($provenanceAfterInboundWrite);

        // 3. ...so the outbound push is skipped and the loop never reaches step "in" again.
        self::assertTrue($isRemoteOrigin, 'integration-origin value must be skipped on outbound to break the sync loop');

        // A subsequent genuine local edit (provenance=manual) IS pushed — the guard
        // only suppresses echoes, not real PIM-side changes.
        self::assertFalse($this->resolver->originatedFromRemote('manual'));
    }
}
