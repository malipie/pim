<?php

declare(strict_types=1);

namespace App\Search\Application;

use App\Catalog\Domain\ObjectKind;
use App\Search\Infrastructure\MeilisearchClientFactory;
use Throwable;

/**
 * Idempotent index bootstrap for Meilisearch (#49 / 0.5.1).
 *
 * Creates each kind's index if missing and writes the settings
 * template (`searchable`, `filterable`, `sortable`, `displayed`,
 * `ranking`). Re-runs are no-ops because Meili's `updateSettings`
 * is itself idempotent — same payload, same task acknowledgment.
 *
 * Used by the `pim:search:health` CLI (this ticket) and the future
 * `pim:search:reindex` command (#51) so a fresh stack always has
 * the right index shape before any document push.
 */
final readonly class MeilisearchIndexProvisioner
{
    public function __construct(
        private MeilisearchClientFactory $clientFactory,
        private IndexSettingsTemplate $template,
    ) {
    }

    /**
     * @return array<string, string> Map of `ObjectKind->value` to the
     *                               task UID returned by Meili. Useful
     *                               for the health command to surface a
     *                               concrete handle per kind.
     */
    public function provision(): array
    {
        $client = $this->clientFactory->create();
        $tasks = [];

        foreach (IndexSettingsTemplate::indexedKinds() as $kind) {
            $name = IndexSettingsTemplate::indexName($kind);
            $client->createIndex($name, ['primaryKey' => 'id']);

            $task = $client->index($name)->updateSettings($this->template->settingsFor($kind));
            $taskUid = $task['taskUid'] ?? $task['uid'] ?? '';
            $tasks[$kind->value] = \is_scalar($taskUid) ? (string) $taskUid : '';
        }

        return $tasks;
    }

    public function indexExists(ObjectKind $kind): bool
    {
        $client = $this->clientFactory->create();
        try {
            $client->index(IndexSettingsTemplate::indexName($kind))->fetchInfo();

            return true;
        } catch (Throwable) {
            return false;
        }
    }
}
