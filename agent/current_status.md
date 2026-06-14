# Status — jednym spojrzeniem

> Zwięzły status bieżący (CLAUDE.md §Workflow pkt 2). Pełna historia: `git log`, GitHub Issues/milestones, `agent/lessons.md`, `Project Plan/*`.
> Przepisany 2026-06-13 (poprzednie 2066 linii append-only logu, m.in. epiki NUI/UI/RBAC — w historii gita).

## Gdzie jesteśmy
- **Epik:** IMP2 — Import/Export v2 engine ([#1499](https://github.com/malipie/PIM/issues/1499), ADR-0019). Kontrakty silnika + warstwy Deptrac Import/Export.
- **Backlog ticketów (source of truth speców):** `Project Plan/UI/feature-imports-v2-tickets.md` (Issues #1460–#1499).

## Postęp epiku IMP2
- **Fala A — KOMPLETNA (8/8):** #1460–62 quick-winy, #1463 ADR-0019, #1464 kanon JSONB, #1465 tryby CREATE/UPDATE/UPSERT, #1466 ValueWriteCore+BatchValueWriter, #1467 golden round-trip v0, #1468/#1509 transport Messenger `import`+worker.
- **Fala B — w toku:**
  - [x] **IMP2-1.6 (#1469)** — gramatyka kolumn `code.locale.channel` + zapis channelId. **MERGED** (PR #1510, `440a63a3`, 2026-06-13). Live smoke 3/3 na pim.localhost, issue zamknięte z dowodem. Grammar przez `Channel\Contracts\ScopeEnumerator` (deptrac-clean, channelId raz/sesja), export 3-segment + fan-out #1146 + R-47 preflight 422, kolizja kanał↔locale (oba kierunki), golden + kanały.
  - [x] **IMP2-1.7 (#1470)** — multi-kategorie pipe-split + import status/enabled. **MERGED** (PR #1511, `5dd98b8c`, 2026-06-13). Live smoke 3/3, zamknięte z dowodem. CREATE multi-kat (primary+position), replace/append (D2), status/enabled z kolumn (`__status__`/`__enabled__`), FE StepMapping + Playwright, golden round-trip.
  - [x] **IMP2-1.8 (#1471)** — warianty parent_sku two-pass + relacje ObjectRelation + fan-out + galerie + variant_axes. **MERGED** (PR #1512, `0722c50e`, 2026-06-14). Live smoke ✅ (generate-variants→export include_variants→reimport renamed→psql parent_id+variant_axes), #598 zamknięte z dowodem. Dwuprzebieg (`RelationImportStep`): pass-1 buforuje kody, pass-2 resolve tenant-scoped. Galerie pipe-split asset_id + prefetch istnienia per chunk. variant_axes pełny shape `code:v,v|code:v`. **Item 2 (pole fazy checkpoint) DEFERRED** — substrat checkpoint z 1.6a nie istnieje (#1509 = transport only), pełny resume to IMP2-2.3; dwuprzebieg już redelivery-safe (idempotentny upsert + dedupe trójki).
  - [x] **IMP2-1.9 (#1472)** — izolacja błędów per wiersz + severity + partial. **MERGED** (PR #1513, `192afe78`, 2026-06-14). Live smoke `dirty.csv` ✅ (partial 5/2, skip 1, raport z numerami wierszy), #1472 zamknięte z dowodem. severity-driven `isRowBlocking` (Warning nie blokuje), dup SKU→skip (D1), pre-check setowy identifierów (JSONB `value->>'value'`), wiersz śmieciowy→błąd wiersza, recovery przez ManagerRegistry po zamknięciu EM. **Item 3 per-row replay DEFERRED→IMP2-2.3** (EM-lifecycle, ServiceEntityRepository cache'uje EM).
  - [x] **IMP2-1.10 (#1473)** — golden CSV+XLSX matryca + pierwsze testy async. **MERGED** (#1514).
  - [x] **IMP2-1.11 (#1474)** — higiena backlogu (8→1 open IMP-16..19). **MERGED** (#1515).
  - [x] **IMP2-1.12 (#1475)** — media z URL. **MERGED** (#1516, `c0aa5c01`). Live smoke ✅ (async: import→consume→success, envelope `{asset_id}` nie URL, dedup content_hash, link, gating, TenantStamp), #1475/#600/#604 zamknięte. Szew `Asset\Contracts\AssetIngestor` (decyzja operatora). **Adversarial review (16 agentów) złapał 3 critical** → NoPrivateNetworkHttpClient (SSRF rebinding+redirect), TenantStamp, atomowy decrement — naprawione przed merge.
  - [x] **IMP2-1.13 (#1476)** — media z ZIP. **MERGED** (#1517, `d31d42b5`). Live smoke ZIP ✅ (extract case-insensitive+subdir, link, success, zip-deleted), #1476 zamknięte. `ZipImageExtractor` (NFC/NFD, traversal, zip-bomb), controller zip_file≤500MB→MinIO+image_source, reużycie pipeline 1.12. 2 fixy CI (libzip OVERWRITE, tempnam string|false).

### ✅ ETAP 1 IMP2 KOMPLETNY (1.1–1.13) — silnik importu v2 z mediami
### ETAP 2 — w toku
  - [x] **IMP2-2.1 (#1477)** — streaming readers (openspout XLSX, league/csv stream CSV). **MERGED** (PR #1518, `1ee88ae8`, 2026-06-14). Live HTTP smoke na pim.localhost ✅ (bosch.csv dup+puste nagłówki→200/51 wierszy/zero SyntaxError; GA_List.csv 10MB/90k→200/0.88s/90032), #1477 zamknięte z dowodem. ImportRowReader+FileParserService strumieniowo (`Reader::from`+iconv stream-filter / openspout row iterator), detekcja na 16 KiB prefix, `HeaderNormalizer` dedup + mapowanie POZYCYJNE (duplikaty/puste kolumny bez `getRecords($header)` throw). Adversarial review (29 agentów, 8/25 confirmed): UTF-8 boundary misdetect + suffix collision + empty-prefix guard naprawione; CP1250/ISO + billion-row DoS świadomie deferred (→2.7). CI fix: memory test = delta nad baseline (absolutny peak failował przez arena 121MB pełnego suite).
  - [→] **IMP2-2.2 (#1478)** — staged upload. **PR otwarty, gates+smoke zielone.** `import_staged_files` (TenantScoped + RLS `app.current_tenant`), `StagedFileService` (stage/resolveOwned/downloadToTemp/copyToKey), parse-preview→`staged_file_id`, validate-dry-run+start przyjmują id (owner-scoped 404, back-compat multipart), `pim:import:purge-staged` TTL 24h (batch flush/tenant), FE `useImportWizard.stagedFileId`+StepDetect capture+StepValidation debounce 500ms+StepConfirm. Live smoke pim.localhost ✅ (upload 1× → validate 200/51 → start 202 server-side copy → unknown id 404 → purge dry-run). Adversarial review 28 agentów (7/24 confirmed): naprawione 2 stream-leak + purge batch + basename defence; 404-echo/FK-RESTRICT/FORCE-RLS świadomie accept. OpenAPI: zero drift (custom kontrolery poza spec).
  - [ ] Etap 2 reszta (#1479–1486): pauza/resume+checkpoint, undo-log, RLS GUC, perf/bench, security plików, równoległość, backup.

## Ostatnie akcje
1. IMP2-2.1 zmergowane (#1518, `1ee88ae8`), #1477 zamknięte z live-stack HTTP proof. Adversarial review złapał UTF-8-boundary + suffix-collision (naprawione przed merge); CI memory-test fix (delta nad baseline).
2. ETAP 1 IMP2 KOMPLETNY (1.8–1.13: #1512–1517).
3. Operator: ultracode ON; kontynuować etap 2 BEZ przeglądu zakresu.

## Następny krok — IMP2-2.2 (#1478)
Staged upload: `parse-preview` staguje plik w MinIO `{tenant}/staged/{uuid}/{filename}` + zwraca `staged_file_id`; nowa tabela `import_staged_files` (tenant_id NOT NULL + ORM default, RLS policy); `validate-dry-run`+`POST import-sessions` przyjmują `staged_file_id` (back-compat multipart) + ownership check (cross-tenant 404); komenda `pim:import:purge-staged` (TTL 24h); FE `useImportWizard` trzyma stagedFileId + debounce dry-run 500ms; OpenAPI regen. Single-context (Import) → marathon direct, bez Plan Mode.

## Aktywne blokery
- Brak (IMP2-1.8 pozostałe items to praca, nie bloker).

## Stałe przypomnienia (workflow IMP2)
- Testy `Api/*`: `cache:clear --env=test` + `docker compose exec -T -e APP_ENV=test api php vendor/bin/phpunit ...` (inaczej `test.service_container` not found).
- PHPStan: `cache:warmup --env=dev` + `composer phpstan -- --memory-limit=1G`.
- Deptrac: Import/Export sięgają Channel TYLKO przez `Channel\Contracts` (port `ScopeEnumeratorInterface`).
- `apps/api/config/reference.php` auto-regeneruje się dirty (środowiskowy szum) — nie commitować.
- Przed `gh issue close`: live-stack smoke na pim.localhost z dowodem (CLOSED MEANS CLOSED).
