# Domena E — FrankenPHP / Pamięć / Stabilność procesów

Audyt adwersarski, część statyczna (benchmarki 50k = osobny przebieg).
Data: 2026-06-16. cwd: /Users/mlipieclocal/dev/PIM. Stack żywy na https://pim.localhost.

## Metodyka — co i jak sprawdzono

1. **Inwentaryzacja handlerów Messenger** — `grep -rn "implements MessageHandler|#[AsMessageHandler|AbstractBatchHandler" apps/api/src`. Wynik: ~45 handlerów/subscriberów. Sklasyfikowane: które dziedziczą `AbstractBatchHandler` (ImportRunHandler, ImageDownloadHandler, ExportJobHandler, RebuildAttributesIndexedHandler, CheckSchemaDriftHandler, ReconcileChannelPlacementsForCategoryHandler), które są lekkie (single-entity CRUD command handlers — nie pętlują).
2. **`AbstractBatchHandler`** przeczytany w całości (`src/Shared/Application/AbstractBatchHandler.php`) — `flushAndClear()` = `flush()` + `clear()`, `shouldFlush()` modulo batchSize. Wzorzec poprawny.
3. **flush/clear w całym src** — `grep -rn -- "->flush()" / "->clear()"` (z wyłączeniem tests). Przejrzane pętle batch w: ImportRunHandler, RelationImportStep, SyncExportRunner, BulkCatalogObjectIndexer, wszystkie `Catalog/Application/Bulk/Bulk*Handler`, BackfillRequiredAttributesCommand, RecalculateCompletenessCommand, BulkImportBenchmarkCommand. Każda pętla batch ma `clear()` po `flush()`.
4. **Custom PHPStan rule flush-bez-clear** — szukana w `apps/api/src/PHPStan/Rules` (`ls`, `find`), w configu (`raw/phpstan-config.txt`, `phpstan/services.neon`). Skonfrontowane z `Project Plan/01-architektura-pim.md` §3.10 pkt 5.
5. **Długie procesy** — ImportRunHandler (1751 linii, przeczytane 1-1320 + kluczowe metody), RelationImportStep (cały), SyncExportRunner (cały), ExportJobHandler (cały), RebuildAttributesIndexedHandler (cały), AssetThumbnailHandler (cały), ImageDownloadHandler (fragmenty 60-238), BulkCatalogObjectIndexer (pętla reindex).
6. **Crash-safety** — `config/packages/messenger.yaml` (retry_strategy, failure_transport, redeliver_timeout), `ImportRunDeadLetterListener`, `IdempotencyMiddleware`, mechanizm checkpoint/resume w ImportRunHandler, licznik `pending_image_batches`.
7. **Empiryczna weryfikacja na żywym stacku**: `docker compose ps` (status kontenerów), `docker inspect pim-worker-1` (health), `docker compose logs worker`, `messenger:failed:show`, `psql` (to_regclass processed_messages, rowcounts, messenger_messages backlog).
8. **Graceful degradation** — CatalogSearchService, CatalogObjectIndexer, MercurePublisher (try/catch wokół klientów Meili/Mercure), ścieżka listy produktów (Postgres vs Meili).
9. **Worker healthcheck + resource limits** — `docker-compose.yml` (anchors `x-resource-limits-*`, definicja serwisu `worker`, dziedziczony HEALTHCHECK).
10. **doctrine.dbal logging/profiling** — `config/packages/doctrine.yaml` (logging:false, profiling_collect_backtrace:false), bloki `when@dev/test/prod`.

## Czego NIE dało się sprawdzić (luki audytu)

- **Realne zużycie pamięci pod 50k SKU** — benchmark to osobny przebieg (poza zakresem części statycznej). Twierdzenia o „<256 MiB" w docblockach NIE są tu zweryfikowane empirycznie — DB ma 0 produktów (`objects` puste), więc nie można było odpalić realnego importu/eksportu i zmierzyć RSS workera.
- **Zachowanie pod prawdziwym OOM** — nie symulowano OOM-killa workera w połowie importu; analiza resume oparta na czytaniu kodu checkpointu, nie na wymuszonym crashu.
- **Produkcyjny docker-compose.prod.yml** — nie istnieje w repo (komentarz mówi „separate ticket — Faza 1"). Limity pamięci/CPU dla prod, restart policy i healthcheck workera w prod = nieaudytowalne.
- **Faktyczne dead-letterowanie ImageDownloadMessage end-to-end** — zaobserwowano historyczny artefakt w `failed` transport, ale nie odtworzono świeżego scenariusza (MinIO down → 5 retry → dead-letter → sprawdzenie czy sesja zostaje stuck). Wniosek z analizy kodu + historycznego dowodu, nie z live-repro.
- **Prometheus alert `frankenphp_worker_memory_bytes > 256MB`** — architektura go wymaga (§3.10 pkt 4); nie weryfikowano czy metryka jest faktycznie eksportowana ani czy alert istnieje (poza zakresem domeny E statycznej — to observability).

## Findings (z dowodami)

### E-01 [HIGH] Brak custom PHPStan rule „flush-bez-clear" — twarda wytyczna §3.10 nieegzekwowana
Architektura (`Project Plan/01-architektura-pim.md` §3.10 pkt 5) i CLAUDE.md deklarują jako NIENEGOCJOWALNE: „CI gate: PHPStan custom rule blokująca handlery Messenger, które flushują w pętli bez clear()". Reguła NIE ISTNIEJE.

Dowód — `apps/api/src/PHPStan/Rules/` zawiera tylko 2 pliki:
```
RequiresPermissionAnnotationRule.php
HardcodedRoleCheckRule.php
```
`phpstan/services.neon` rejestruje tylko te dwie reguły. Komentarz w `phpstan-config.txt` linie 14-15 wprost: „A third rule (flush-without-clear) is tracked as follow-up after the first batch handler that does not extend AbstractBatchHandler." Czyli: gwarancja jest manualna (dyscyplina dziedziczenia AbstractBatchHandler), nie automatyczna. Pierwszy handler napisany przez nowego kontrybutora z `flush()` w pętli bez `clear()` przejdzie CI zielono i może zOOM-ować workera w prod pod 50k SKU — dokładnie ryzyko R-25, które reguła miała zamknąć.

### E-02 [HIGH] Worker NIE ma `deploy.resources.limits` — wbrew własnemu komentarzowi w docker-compose
`docker-compose.yml` linie 17-29 (HARD-02): „CLAUDE.md §3.10 calls out FrankenPHP worker mode as the highest memory-leak risk... Without `deploy.resources.limits` the worker can swallow the entire host memory before the leak surfaces in metrics." Mimo to serwis `worker` (linie 180-206) ma TYLKO `<<: *default_restart` — brak `*resource_limits_api` ani żadnego limitu pamięci na poziomie kontenera. `api` (linia 124) dostaje 1024M, ale to NIE jest długo-żyjący consumer; faktyczny consumer (`worker`, `messenger:consume import`) jest na poziomie kontenera NIEOGRANICZONY.

Mitygacja częściowa: flaga `--memory-limit=256M` (linia 190) — ale to soft-recycle Symfony sprawdzany MIĘDZY wiadomościami; pojedyncza wiadomość, która leakuje w trakcie przetwarzania (np. import bez clear()), urośnie ponad 256M i ubije hosta zanim Messenger zdąży zrecyklować proces. Limit kontenera jest jedyną twardą barierą — i jej brak.

### E-03 [MEDIUM] ImageDownloadMessage bez dead-letter listenera — sesja importu może utknąć non-terminal na zawsze
`ImportRunDeadLetterListener` (`src/Import/Infrastructure/Messenger/ImportRunDeadLetterListener.php`) obsługuje WYŁĄCZNIE `ImportRunMessage` — linia 44: `if (!$message instanceof ImportRunMessage) { return; }`. Dla `ImageDownloadMessage` NIE ma żadnego `WorkerMessageFailedEvent` listenera (`grep -rln "WorkerMessageFailedEvent" apps/api/src` → tylko ten jeden plik).

Finalizacja sesji wymaga `pending_image_batches === 0` (`ImportSession::canFinalizeMedia()` linia 357). Dekrementacja licznika (`ImageDownloadHandler` linie 195-207, atomic UPDATE) jest na KOŃCU `__invoke`. Jeśli `__invoke` rzuci PRZED tym UPDATE (błąd flush w fazie 2 APPLY, błąd DB, SSRF) → 5 retry → dead-letter → dekrementacja nigdy nie wykonana → `pending_image_batches > 0` na stałe → sesja z `row_phase_complete=true` NIGDY nie przejdzie do `completed`, utyka w `running`.

Dowód empiryczny istnienia tej ścieżki: `messenger:failed:show` pokazuje realne dead-letterowanie tej wiadomości:
```
Id 5  App\Import\Domain\Message\ImageDownloadMessage  2026-06-14 09:28:30
Error: SQLSTATE[42P01]: Undefined table ... relation "processed_messages" does not exist
```
(Tu przyczyną była przejściowo niespójna schema — patrz E-04 — ale udowadnia, że batch potrafi dead-letterować, a wtedy sesja zostaje osierocona.)

### E-04 [MEDIUM] `processed_messages` zależy od migracji, a worker uruchamia transport z `auto_setup=1` — race przy świeżym deployu
`IdempotencyMiddleware` (linia 70) robi `INSERT INTO processed_messages`. Tabela powstaje wyłącznie z migracji (`Version20260429170000`, recreated w `Version20260430092112` linia 183). Worker konsumuje z `doctrine://default?auto_setup=1` (`docker-compose.yml` linia 196) — `auto_setup` tworzy TYLKO `messenger_messages`, NIE `processed_messages`.

Stan obecny OK: `SELECT to_regclass('public.processed_messages')` → `processed_messages` (istnieje), `count(*)`=22, backlog `messenger_messages`: tylko `failed|1`. Ale dowód z 2026-06-14 09:28:30 (E-03) pokazuje okno, w którym tabeli nie było, a worker konsumował: KAŻDA async wiadomość rzucała `42P01` → retry → dead-letter. W świeżym deployu, gdzie worker wstaje przed `migrations:migrate` (kolejność w entrypoint), cała kolejka import się wykrzaczy. To regresja-pułapka: idempotency middleware jest twardą zależnością od migracji, której auto_setup nie pokrywa.

### E-05 [MEDIUM] Worker healthcheck permanentnie `unhealthy` (false-positive) — błędna definicja, nie awaria procesu
`docker compose ps`: `pim-worker-1 ... Up 24 minutes (unhealthy)`. `docker inspect pim-worker-1`: `"Status":"unhealthy","FailingStreak":145`, `"Output":"curl: (7) Failed to connect to 127.0.0.1 port 80..."`.

Przyczyna: serwis `worker` (docker-compose.yml 180-206) NIE definiuje własnego `healthcheck:`, więc dziedziczy HEALTHCHECK z obrazu (ten sam co `api`: `curl -fsS http://127.0.0.1/api`). Worker uruchamia `messenger:consume import` — proces CLI, NIE serwer HTTP — port 80 nie nasłuchuje, healthcheck wiecznie failuje.

Proces faktycznie DZIAŁA — `docker compose logs worker`: „[OK] Consuming messages from transport import". To kosmetyczny/operacyjny problem (auditability), ALE realnie szkodliwy: maskuje prawdziwą awarię (gdy worker padnie, status i tak był `unhealthy`, więc orkiestrator/monitoring nie odróżni), oraz w compose z `depends_on: condition: service_healthy` worker nigdy by nie spełnił warunku.

### E-06 [MEDIUM] Eksport non-streaming materializuje cały graf obiektów do pamięci (scope Selected/Filter lub All+variants)
`SyncExportRunner::canStream()` (linia 104) zwraca true TYLKO dla `scope=All && !includesVariants()`. Każdy inny eksport idzie przez `resolveTargets()` (linia 116) → `findByObjectType()` / `findByIds()` zwraca `list<CatalogObject>` — CAŁY zbiór encji naraz w pamięci. Następnie `applyVariantFanout()` (linia 149) buduje DRUGĄ tablicę `$result` + mapy `$childrenByParent`/`$seen`/`$masterIds`, a `ExportBuilder::build($targets,...)` hydratuje wartości/relacje dla wszystkich obiektów jednocześnie.

Skutek: eksport np. 100k produktów z włączonymi wariantami albo z filtrem ładuje cały graf (obiekty + ich ObjectValue + relacje) do RAM jednym ciągiem — ścieżka NIE jest objęta keyset-pagingiem ze streaming path. Docblock ExportJobHandler twierdzi „stays under its 50 MB budget" (linia 45), ale to prawda tylko dla streamowalnej ścieżki All-bez-wariantów. Mimo `--memory-limit=256M` i braku limitu kontenera (E-02) duży eksport filtrowany/z wariantami to realny wektor OOM.

### E-07 [LOW] RelationImportStep buforuje wszystkie linki przez cały plik + monotoniczny `$seenTriples`
`RelationImportStep` (`src/Import/Application/Service/RelationImportStep.php`) trzyma `$parentLinks` i `$relationLinks` jako `list<array{...string}>` przez CAŁY import (linie 47-50) — bufor pass-2 rośnie liniowo z liczbą wierszy mających relacje (50k wierszy z `parent_sku` = 50k małych tablic). W `resolveRelations()` `$seenTriples` (linia 173) rośnie monotonicznie przez całą pętlę i NIE jest czyszczony przy `flushClearReattach()` (linia 239) — dla importu z dużą liczbą relacji to akumulacja stringów `source|attr|target`.

To string DTO (nie encje Doctrine), więc przeżywają `clear()` celowo i są tańsze niż encje — ryzyko ograniczone, ale niezerowe przy bardzo dużych plikach z gęstymi relacjami. Brak twardego capu na rozmiar buforów.

### E-08 [LOW] Degradacja Meili: search zwraca „0 trafień" zamiast błędu — może mylić operatora
`CatalogSearchService::search()` (linie 134-146): Meili down → `catch (Throwable)` → log warning → `emptyResult()` (0 hits). Dobre, że nie ma 500. Ale UX-owo: podczas awarii Meili użytkownik widzi „brak wyników" identyczne jak dla pustego katalogu — bez sygnału, że to awaria indeksu, nie brak danych. Brak rozróżnienia degraded-mode od empty-result. (Lista produktów `/api/products` NIE zależy od Meili — idzie po Postgres — więc katalog pozostaje widoczny; to plus.)

## Co zweryfikowano jako POPRAWNE (z dowodem)

- **AbstractBatchHandler** (`flushAndClear()` = flush+clear) i jego dziedziczenie w ImportRunHandler/ExportJobHandler/RebuildAttributesIndexedHandler/ImageDownloadHandler — wzorzec memory poprawny.
- **ImportRunHandler**: touched IDs jako stringi (przeżywają clear), throttling Mercure (~100 zamiast 50k POST-ów dla 50k), `set_time_limit(0)`, re-merge sesji po clear, BulkContext wyłączający per-flush rebuild/index, checkpoint+resume (IMP2-2.3), reset manager po EM-close exception. Bardzo dojrzały.
- **doctrine.yaml**: `logging: false` globalnie (linia 41), `profiling_collect_backtrace: false` (linia 33) — komentarze świadczą o znajomości wektora BacktraceDebugDataHolder/worker.
- **messenger.yaml**: retry_strategy (5 retry, backoff 30s→300s), `failure_transport: failed`, dedykowana kolejka `import` z `redeliver_timeout: 14400`, TenantContextRebindingMiddleware + RLS GUC + IdempotencyMiddleware + doctrine_transaction.
- **RebuildAttributesIndexedHandler**: flush+clear PER ID + retry na OptimisticLock z resetManager — flat memory, odporny na version conflict.
- **BulkCatalogObjectIndexer**: `toIterable()` + `clear()` co FLUSH_CLEAR_INTERVAL — reindex Meili w stałej pamięci.
- **CatalogObjectIndexer / MercurePublisher**: wszystkie operacje Meili/Mercure w try/catch → warning, nigdy nie wywracają requestu (graceful degradation Meili/Mercure down).
- **InMemoryMercureHub**: scoped do `when@test` (services.yaml linia 414) — brak ryzyka prod-akumulacji.
- **PHPStan baseline**: pusty (`ignoreErrors: []`), `reportUnmatchedIgnoredErrors: true` — brak maskowania.
