<?php

declare(strict_types=1);

namespace App\Integration\Generic\Application\Sync;

use App\Catalog\Contracts\Integration\OutboundRecord;
use App\Catalog\Contracts\Integration\OutboundRecordReader;
use App\Integration\Generic\Domain\Entity\FieldMapping;
use App\Integration\Generic\Domain\Entity\RemoteEndpoint;
use App\Integration\Generic\Domain\Entity\SyncBinding;
use App\Integration\Generic\Domain\Entity\SyncRun;
use App\Integration\Generic\Domain\Entity\SyncRunLog;
use App\Integration\Generic\Domain\Enum\RemoteEndpointRole;
use App\Integration\Generic\Domain\Enum\SyncDirection;
use App\Integration\Generic\Domain\Enum\SyncRecordAction;
use App\Integration\Generic\Domain\Enum\SyncRunStatus;
use App\Integration\Generic\Domain\Exception\RemoteRequestFailedException;
use App\Integration\Generic\Domain\Exception\SsrfBlockedException;
use App\Integration\Generic\Domain\Repository\FieldMappingRepositoryInterface;
use App\Integration\Generic\Domain\Repository\SyncRunRepositoryInterface;
use App\Integration\Generic\Infrastructure\Http\RemoteRequester;
use Doctrine\ORM\EntityManagerInterface;

use const JSON_THROW_ON_ERROR;

/**
 * Runs one outbound (PIM → remote) sync of a {@see SyncBinding} (APIC-P3-06).
 *
 * Reads the ObjectType's objects serialised by the Export engine
 * ({@see OutboundRecordReader}), builds a 1:1 push body ({@see PayloadBuilder})
 * and POSTs/PUTs each through the SSRF-safe, backoff-retrying client
 * ({@see RemoteRequester}) to the binding's write endpoint. Per-record failures
 * are logged and counted (a bad record never fails the whole run); transport
 * dead-lettering is the message transport's job. Audited as a {@see SyncRun}
 * with per-record {@see SyncRunLog}.
 */
final readonly class OutboundSyncRunner
{
    public function __construct(
        private FieldMappingRepositoryInterface $mappings,
        private PayloadBuilder $payloadBuilder,
        private OutboundRecordReader $reader,
        private RemoteRequester $requester,
        private SyncRunRepositoryInterface $runs,
        private EntityManagerInterface $em,
    ) {
    }

    public function run(SyncBinding $binding): SyncRun
    {
        $run = new SyncRun($binding, SyncDirection::Outbound);
        $this->runs->save($run);

        $writeEndpoint = $binding->getWriteEndpoint();
        if (null === $writeEndpoint) {
            $run->markFinished(SyncRunStatus::Failed);
            $this->runs->save($run);

            return $run;
        }

        $mappings = array_values(array_filter(
            $this->mappings->findByConnection($binding->getConnection()),
            static fn (FieldMapping $m): bool => $m->getDirection()->appliesOutbound(),
        ));
        $matchCode = $this->matchCode($mappings);
        $codes = array_values(array_unique(array_map(static fn (FieldMapping $m): string => $m->getPimTarget(), $mappings)));

        $action = RemoteEndpointRole::WriteUpdate === $writeEndpoint->getRole()
            ? SyncRecordAction::Updated
            : SyncRecordAction::Created;

        foreach ($this->reader->read($binding->getObjectTypeId(), $codes) as $record) {
            $this->push($run, $binding, $writeEndpoint, $record, $mappings, $matchCode, $action);
            $this->em->flush();
        }

        $run->markFinished();
        $this->runs->save($run);

        return $run;
    }

    /**
     * @param list<FieldMapping> $mappings
     */
    private function push(
        SyncRun $run,
        SyncBinding $binding,
        RemoteEndpoint $writeEndpoint,
        OutboundRecord $record,
        array $mappings,
        ?string $matchCode,
        SyncRecordAction $successAction,
    ): void {
        $body = $this->payloadBuilder->build($record->values, $mappings);
        if ([] === $body) {
            $run->recordSkipped();
            $this->log($run, SyncRecordAction::Skipped, null, $body, 'No outbound values to push.');

            return;
        }

        $matchValue = null !== $matchCode ? ($record->values[$matchCode] ?? null) : null;
        $url = $this->buildUrl($binding, $writeEndpoint, $matchValue);

        try {
            $response = $this->requester->request(
                $binding->getConnection(),
                $writeEndpoint->getHttpMethod(),
                $url,
                [],
                [],
                json_encode($body, JSON_THROW_ON_ERROR),
            );
        } catch (SsrfBlockedException|RemoteRequestFailedException $exception) {
            $run->recordFailed();
            $this->log($run, SyncRecordAction::Failed, $matchValue, $body, $exception->getMessage());

            return;
        }

        if ($response->isSuccessful()) {
            SyncRecordAction::Updated === $successAction ? $run->recordUpdated() : $run->recordCreated();
            $this->log($run, $successAction, $matchValue, $body, null);

            return;
        }

        $run->recordFailed();
        $this->log($run, SyncRecordAction::Failed, $matchValue, $body, \sprintf('HTTP %d', $response->statusCode));
    }

    /**
     * @param list<FieldMapping> $mappings
     */
    private function matchCode(array $mappings): ?string
    {
        foreach ($mappings as $mapping) {
            if ($mapping->isMatchKey()) {
                return $mapping->getPimTarget();
            }
        }

        return null;
    }

    /**
     * Resolves the write URL, substituting any `{token}` placeholder in the
     * path with the match value (e.g. `PUT /products/{id}`).
     */
    private function buildUrl(SyncBinding $binding, RemoteEndpoint $endpoint, ?string $matchValue): string
    {
        $path = $endpoint->getPathTemplate();
        if (null !== $matchValue) {
            $path = (string) preg_replace('/\{[^}]+\}/', rawurlencode($matchValue), $path);
        }

        $path = ltrim($path, '/');
        $base = rtrim($binding->getConnection()->getBaseUrl(), '/');

        return '' === $path ? $base : $base.'/'.$path;
    }

    /**
     * @param array<string, mixed> $fields
     */
    private function log(SyncRun $run, SyncRecordAction $action, ?string $matchKey, array $fields, ?string $message): void
    {
        $log = new SyncRunLog($run, $action);
        $log->setMatchKey($matchKey);
        $log->setFields($fields);
        $log->setMessage($message);
        $this->em->persist($log);
    }
}
