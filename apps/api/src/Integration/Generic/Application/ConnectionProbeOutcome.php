<?php

declare(strict_types=1);

namespace App\Integration\Generic\Application;

use App\Integration\Generic\Domain\Enum\ConnectionStatus;

/**
 * Interprets a base-URL probe's HTTP status into a connection health outcome
 * (APIC, fix for the test false-negative #1890).
 *
 * A reachable host that returns a non-2xx code on its bare base URL is NOT a
 * connection failure — many REST APIs (e.g. IdoSell `/api/admin/v5`) serve
 * nothing at the base and answer `404`, yet the credentials and host are fine.
 * Only an auth rejection (`401`/`403`) is a real, actionable problem from a
 * base-URL ping; transport/SSRF failures (no response at all) are handled by
 * the caller before this mapper runs.
 */
final readonly class ConnectionProbeOutcome
{
    public function __construct(
        public ConnectionStatus $status,
        public bool $reachable,
        public ?string $note,
    ) {
    }

    public static function fromHttpStatus(int $httpStatus): self
    {
        if (401 === $httpStatus || 403 === $httpStatus) {
            return new self(
                ConnectionStatus::Error,
                false,
                'Host responded but rejected authentication — check the API key and its permissions.',
            );
        }

        if ($httpStatus >= 200 && $httpStatus < 300) {
            return new self(ConnectionStatus::Active, true, null);
        }

        return new self(
            ConnectionStatus::Active,
            true,
            \sprintf(
                'Host reachable; the base URL returned HTTP %d — expected for an API base path with no resource. Configure a read endpoint to probe a real resource.',
                $httpStatus,
            ),
        );
    }
}
