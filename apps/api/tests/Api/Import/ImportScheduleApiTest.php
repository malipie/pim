<?php

declare(strict_types=1);

namespace App\Tests\Api\Import;

use App\Tests\Api\Catalog\CatalogApiTestCase;
use PHPUnit\Framework\Attributes\Test;

use const JSON_THROW_ON_ERROR;

/**
 * VIEW-IMP-04 (#502) — ApiTestCase smoke for ImportSchedule.
 *
 * CRUD + cron parsing + toggle + run-now + upcoming horizon. The cron
 * worker daemon ships in the follow-up; `runNow` here only stamps a
 * `pending` schedule_run row + refreshes nextRun.
 */
final class ImportScheduleApiTest extends CatalogApiTestCase
{
    #[Test]
    public function listRequiresAuthentication(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/import-schedules');
        self::assertResponseStatusCodeSame(401);
    }

    #[Test]
    public function listReturnsEmptyCollectionForFreshUser(): void
    {
        $client = $this->authenticatedClient();
        $client->request('GET', '/api/import-schedules');
        self::assertResponseIsSuccessful();
        $body = json_decode((string) $client->getResponse()?->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($body);
        $items = $body['member'] ?? $body['hydra:member'] ?? null;
        self::assertIsArray($items);
        self::assertSame([], $items);
    }

    #[Test]
    public function postCreatesScheduleAndComputesNextRun(): void
    {
        $client = $this->authenticatedClient();
        $client->request('POST', '/api/import-schedules', [
            'headers' => ['content-type' => 'application/ld+json'],
            'body' => json_encode([
                'name' => 'Daily catalogue',
                'code' => 'daily-catalogue',
                'cron' => '0 6 * * *',
                'priority' => 'normal',
            ], JSON_THROW_ON_ERROR),
        ]);
        self::assertResponseStatusCodeSame(201);
        $created = json_decode((string) $client->getResponse()?->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($created);
        self::assertSame('Daily catalogue', $created['name']);
        self::assertSame('0 6 * * *', $created['cron']);
        self::assertNotNull($created['nextRun']);
    }

    #[Test]
    public function postRejectsInvalidCron(): void
    {
        $client = $this->authenticatedClient();
        $client->request('POST', '/api/import-schedules', [
            'headers' => ['content-type' => 'application/ld+json'],
            'body' => json_encode([
                'name' => 'Bad cron',
                'code' => 'bad-cron',
                'cron' => 'not-a-cron',
            ], JSON_THROW_ON_ERROR),
        ]);
        self::assertResponseStatusCodeSame(400);
    }

    #[Test]
    public function toggleFlipsEnabledFlag(): void
    {
        $client = $this->authenticatedClient();
        $client->request('POST', '/api/import-schedules', [
            'headers' => ['content-type' => 'application/ld+json'],
            'body' => json_encode([
                'name' => 'Toggle me',
                'code' => 'toggle-me',
                'cron' => '*/30 * * * *',
            ], JSON_THROW_ON_ERROR),
        ]);
        $created = json_decode((string) $client->getResponse()?->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($created);
        $scheduleId = $created['id'];
        self::assertIsString($scheduleId);

        $client->request('POST', \sprintf('/api/import-schedules/%s/toggle', $scheduleId));
        self::assertResponseIsSuccessful();
        $body = json_decode((string) $client->getResponse()?->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($body);
        self::assertFalse($body['enabled']);
        self::assertNull($body['next_run']);

        $client->request('POST', \sprintf('/api/import-schedules/%s/toggle', $scheduleId));
        self::assertResponseIsSuccessful();
        $body = json_decode((string) $client->getResponse()?->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($body);
        self::assertTrue($body['enabled']);
        self::assertNotNull($body['next_run']);
    }

    #[Test]
    public function runNowCreatesPendingRunAndRefreshesNextRun(): void
    {
        $client = $this->authenticatedClient();
        $client->request('POST', '/api/import-schedules', [
            'headers' => ['content-type' => 'application/ld+json'],
            'body' => json_encode([
                'name' => 'Run now',
                'code' => 'run-now',
                'cron' => '0 6 * * *',
            ], JSON_THROW_ON_ERROR),
        ]);
        $created = json_decode((string) $client->getResponse()?->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($created);
        $scheduleId = $created['id'];
        self::assertIsString($scheduleId);

        $client->request('POST', \sprintf('/api/import-schedules/%s/run-now', $scheduleId));
        self::assertResponseStatusCodeSame(202);
        $body = json_decode((string) $client->getResponse()?->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($body);
        self::assertSame('pending', $body['status']);
        self::assertNotNull($body['next_run']);
        self::assertIsString($body['run_id']);

        // Audit drawer feed lists the run.
        $client->request('GET', \sprintf('/api/import-schedules/%s/runs', $scheduleId));
        self::assertResponseIsSuccessful();
        $runs = json_decode((string) $client->getResponse()?->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($runs);
        $runMembers = $runs['member'];
        self::assertIsArray($runMembers);
        self::assertGreaterThanOrEqual(1, \count($runMembers));
    }

    #[Test]
    public function upcomingReturnsEnabledSchedulesWithinHorizon(): void
    {
        $client = $this->authenticatedClient();
        $client->request('POST', '/api/import-schedules', [
            'headers' => ['content-type' => 'application/ld+json'],
            'body' => json_encode([
                'name' => 'Hourly',
                'code' => 'hourly',
                'cron' => '0 * * * *',
            ], JSON_THROW_ON_ERROR),
        ]);
        self::assertResponseStatusCodeSame(201);

        $client->request('GET', '/api/import-schedules/upcoming?hours=24');
        self::assertResponseIsSuccessful();
        $body = json_decode((string) $client->getResponse()?->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($body);
        $members = $body['member'];
        self::assertIsArray($members);
        self::assertGreaterThanOrEqual(1, \count($members));
        self::assertSame(24, $body['horizonHours']);
    }

    #[Test]
    public function upcomingRejectsOutOfRangeHorizon(): void
    {
        $client = $this->authenticatedClient();
        $client->request('GET', '/api/import-schedules/upcoming?hours=999');
        self::assertResponseStatusCodeSame(400);
    }
}
