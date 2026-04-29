<?php

declare(strict_types=1);

namespace App\Search\Infrastructure;

use LogicException;
use Meilisearch\Client;

/**
 * DI factory for the Meilisearch SDK client (#49 / 0.5.1).
 *
 * `meilisearch/meilisearch-php` ships its own HTTP client discovery
 * (Guzzle / Symfony HttpClient via PSR-18). The factory wraps the URL
 * + master key from env so DI can autowire `Meilisearch\Client` into
 * indexers and the health command without each consumer re-reading
 * `MEILI_URL` / `MEILI_KEY` from the container.
 *
 * Per ADR-004 Meilisearch is the canonical search backend until 200k
 * SKU; the factory is the single switch point if Faza 2 introduces a
 * Elasticsearch adapter behind the same `IndexerInterface`.
 */
final readonly class MeilisearchClientFactory
{
    public function __construct(
        private ?string $url,
        private ?string $masterKey,
    ) {
    }

    public function create(): Client
    {
        if (null === $this->url || '' === $this->url) {
            throw new LogicException('MEILI_URL is not configured — point it at the Meilisearch hub before calling the search backend.');
        }

        return new Client($this->url, $this->masterKey ?? '');
    }
}
