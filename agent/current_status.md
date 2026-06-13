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
  - [→] **IMP2-1.7 (#1470)** — multi-kategorie pipe-split + import status/enabled. Branch `feat/imp2-1.7-multicat-status`. **W TRAKCIE.**
  - [ ] IMP2-1.8 (#1471) warianty/relacje · 1.9 (#1472) izolacja błędów · 1.10 (#1473) golden pełna matryca + async · 1.11+ (media, etap 2/3).

## Ostatnie akcje
1. IMP2-1.6 zaimplementowane, zmergowane (PR #1510) i zamknięte z live-smoke proof (#1469).
2. Lekcje 1.6 → `lessons.md` + pamięć trwała (APP_ENV=test dla Api/*, assert status≠detail, ScopeEnumerator port).
3. Start IMP2-1.7: branch + research backendu (SystemColumn, ReservedMappingTarget, extractCategoryCode).

## Następny krok
Implementacja IMP2-1.7: `extractCategoryCodes()` pipe-split + `ObjectCategory` per kod (primary+position), polityka replace/append (D2), `status`/`enabled` z jawnych kolumn (`__status__`/`__enabled__`, usunięcie z `SystemColumn`), FE `StepMapping.tsx`, golden + ApiTest, PR → CI → merge → live smoke.

## Aktywne blokery
- Brak.

## Stałe przypomnienia (workflow IMP2)
- Testy `Api/*`: `cache:clear --env=test` + `docker compose exec -T -e APP_ENV=test api php vendor/bin/phpunit ...` (inaczej `test.service_container` not found).
- PHPStan: `cache:warmup --env=dev` + `composer phpstan -- --memory-limit=1G`.
- Deptrac: Import/Export sięgają Channel TYLKO przez `Channel\Contracts` (port `ScopeEnumeratorInterface`).
- `apps/api/config/reference.php` auto-regeneruje się dirty (środowiskowy szum) — nie commitować.
- Przed `gh issue close`: live-stack smoke na pim.localhost z dowodem (CLOSED MEANS CLOSED).
