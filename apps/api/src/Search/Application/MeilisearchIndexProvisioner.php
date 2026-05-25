<?php

declare(strict_types=1);

namespace App\Search\Application;

use App\Catalog\Domain\ObjectKind;
use App\Search\Infrastructure\MeilisearchClientFactory;
use Throwable;

/**
 * Idempotent index bootstrap for Meilisearch (ULV-02 / #983).
 *
 * Creates the consolidated `objects` index if missing and writes its
 * settings template (`searchable`, `filterable`, `sortable`, `displayed`,
 * `ranking`, `pagination`). Re-runs are no-ops because Meili's
 * `updateSettings` is itself idempotent.
 *
 * Pre-ULV provisioned four per-kind indexes (`products`, `categories`,
 * `assets`, `brands`); the cleanup of those legacy indexes happens via
 * `pim:search:cleanup-legacy-indexes` (a one-shot CLI) so a tenant can
 * stage the migration: provision new → reindex → drop old.
 */
final readonly class MeilisearchIndexProvisioner
{
    public function __construct(
        private MeilisearchClientFactory $clientFactory,
        private IndexSettingsTemplate $template,
    ) {
    }

    /**
     * Provisions the single `objects` index. Returns the task UID Meili
     * returned for the settings update so the health command can surface
     * a concrete handle. Keyed by `'objects'` for backward-compatible
     * call sites (was keyed by kind value pre-ULV).
     *
     * @return array<string, string>
     */
    public function provision(): array
    {
        $client = $this->clientFactory->create();
        $name = IndexSettingsTemplate::indexName();

        $client->createIndex($name, ['primaryKey' => 'id']);
        $task = $client->index($name)->updateSettings($this->template->settingsFor());
        $taskUid = $task['taskUid'] ?? $task['uid'] ?? '';

        return [
            $name => \is_scalar($taskUid) ? (string) $taskUid : '',
        ];
    }

    /**
     * `$kind` is retained as a legacy hint — every kind resolves to the
     * universal `objects` index post-ULV.
     */
    public function indexExists(?ObjectKind $kind = null): bool
    {
        $client = $this->clientFactory->create();
        try {
            $client->index(IndexSettingsTemplate::indexName())->fetchInfo();

            return true;
        } catch (Throwable) {
            return false;
        }
    }
}
