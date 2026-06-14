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
  - [→] **IMP2-1.8 (#1471)** — warianty parent_sku two-pass + relacje ObjectRelation + fan-out. Branch `feat/imp2-1.8-variants-relations` (push'nięty). **CZĘŚCIOWE — parent_sku two-pass DONE** (commit `f8f22973`, NIE na main).
  - [ ] 1.9 (#1472) izolacja błędów · 1.10 (#1473) golden pełna matryca + async · 1.11+ (media, etap 2/3).

## Ostatnie akcje
1. IMP2-1.6 + IMP2-1.7 zmergowane i zamknięte z live-smoke proof (#1469, #1470).
2. Lekcje 1.6/1.7 → `lessons.md` + 2 wpisy pamięci trwałej. `current_status.md` zslimowany (2066→~40).
3. Start IMP2-1.8: parent_sku two-pass (`RelationImportStep`) zacommitowany na branchu (f8f22973).

## Następny krok — WZNOWIENIE IMP2-1.8 (świeży kontekst)
Branch `feat/imp2-1.8-variants-relations` @ `f8f22973`. **Zrobione:** parent_sku two-pass (RelationImportStep buforuje child→parent po code, resolve po pass-1, cycle/self/missing guard; reserved target `__parent_sku__`; StartImportApiTest 5/5; PHPStan/deptrac/Unit zielone). **Pozostało (items #1471):**
- **Relacje → ObjectRelation** (item 4): rozszerz `RelationImportStep` o relation-linki; Relation/Reference cele NIE piszą `ObjectValue{object_id}` (zmień `ImportObjectCreator::buildValuePayload`), pass-2 tworzy `ObjectRelation(source,target,attr,position)` z resolve targetów po code. **UWAGA: target może być w innym ObjectType** (`relationTargetObjectTypeIds` na atrybucie) — `findByCode` wymaga `kind`; trzeba iterować docelowe OT atrybutu. **Test izolacji cross-tenant** (target tylko w tenant B → 0 linków + błąd).
- **Export relations-by-code** (item 5): `ExportBuilder` czyta `object_relations` (ObjectRelationRepositoryInterface, `findBySourceAndAttribute`) i emituje pipe-joined **kody** zamiast UUID.
- **include_variants fan-out** (item 8): nowa metoda repo `findChildrenByParentIds(parentIds, tenant)` (brak jej w `CatalogObjectRepositoryInterface`); `SyncExportRunner::resolveTargets` przy true dokłada dzieci (master, potem warianty); przy false tylko mastery. Ścieżka krytyczna golden wariantów.
- **variant_axes** (item 6): built-in kolumna; shape to `[{code, values}]`. **DECYZJA OPERATORA: pełny shape w eksporcie** (np. `color:red,blue|size:s,m`) — round-trip naprawdę identyczny (AC). Export serializuje code+values, import parsuje + `declareVariantAxes()` z walidacją że kody to atrybuty select.
- **Galerie** (item 7): pipe-split `asset_id` + walidacja istnienia assetów tenant-scoped (prefetch per chunk).
- **Checkpoint faza** (item 2, values/links) · **golden** (item 10: master+2warianty+relacja+galeria) · **testy integracyjne** (items 4,9, realny Postgres, no-mocking) · **zamknięcie #598** z dowodem.

## Aktywne blokery
- Brak (IMP2-1.8 pozostałe items to praca, nie bloker).

## Stałe przypomnienia (workflow IMP2)
- Testy `Api/*`: `cache:clear --env=test` + `docker compose exec -T -e APP_ENV=test api php vendor/bin/phpunit ...` (inaczej `test.service_container` not found).
- PHPStan: `cache:warmup --env=dev` + `composer phpstan -- --memory-limit=1G`.
- Deptrac: Import/Export sięgają Channel TYLKO przez `Channel\Contracts` (port `ScopeEnumeratorInterface`).
- `apps/api/config/reference.php` auto-regeneruje się dirty (środowiskowy szum) — nie commitować.
- Przed `gh issue close`: live-stack smoke na pim.localhost z dowodem (CLOSED MEANS CLOSED).
