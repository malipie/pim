# Prompt startowy — kontynuacja marathonu bug-fix (smoke test 2026-05-30)

> Wklej treść poniżej („=== PROMPT ===") jako pierwszą wiadomość w nowej sesji.
> Zawiera pełen kontekst + gotchy, żeby nie odkrywać ich od nowa.

---
=== PROMPT ===

Kontynuujemy batch bug-fix z manualnego smoke testu operatora (2026-05-30), issues #1130–#1147 w repo `malipie/PIM`.

## Stan na teraz (zrobione w poprzedniej sesji)
**14 z 18 zgłoszeń wdrożone na `main`** (PR-y #1157–#1166), każde z live-stack smoke + zielonym CI:
- #1141 MFA enforcement przy logowaniu (KRYTYCZNE) · #1143 miniatury 403 · #1142 redirect /settings · #1144 przycisk „Dodaj" · #1136 systemowe atrybuty · #1145 presety Smart Filters scope · #1131/#1132/#1133 ObjectType wizard cleanup · #1134/#1135 widok kategorii · #1139/#1140 grupa atrybutów · #1137 zmiana nazwy kategorii.

Stack lokalny jest UP na `main`, login `admin@demo.localhost / changeme` → 200, MFA posprzątane (admin niezablokowany).

## Do zrobienia (4 pozostałe — pełne tickety już rozpisane w issue)
1. **#1130 import round-trip** (duży, data-critical, BE). Root cause = 4 niezgodności:
   - composite values: eksport pisze `"20.99 EUR"`/`"0.3 g"`, import `ImportValidationService::validateScalarType` (`apps/api/src/Import/Application/Service/ImportValidationService.php:277`) robi tylko `is_numeric` → odrzuca.
   - kolumny lokalizowane: eksport `name.pl`, import wymaga gołego `name` (`ImportValidationService.php:37` REQUIRED=['sku','name']) + `AutoMapper` normalizuje `name.pl`→`namepl`≠`name` → unmapped → „Missing required name".
   - kolumny systemowe (`created_at`…) w eksporcie → import powinien auto-SKIP.
   - sparse cells: `ImportRowReader.php:69-84` mapuje pozycyjnie `headers[$i]=>cells[$i]` → mapować po cell-coordinate.
   - Pliki: `Import/Application/Service/{ImportValidationService,ImportRowReader,AutoMapper}.php`, persistence handler, `Export/Application/Builder/{ColumnResolver,ValueSerializer}.php`. Plik testowy operatora: `Zrodla/importy/import pim-export-20260530-144844.xlsx`.
2. **#1138 atrybut asset jako picker** (FE+BE). `attr-row.tsx` nie ma case'a `asset` → fallback do `<Input text>`. Istniejący `features/catalog/products/components/asset-library-picker.tsx` jest sprzężony z produktem (POST `/api/products/{id}/assets`, `onPicked()` nic nie zwraca) → trzeba wariant zwracający asset-id. Wartość = referencja asset id w envelope; podgląd przez `/api/assets/{id}/preview` (działa po #1143).
3. **#1146 wersje językowe** (epik, parent + sub #1148–#1152) — **design-first / Plan Mode**. Cross-context: read/zapis wartości per-locale dla wszystkich ObjectType, dynamiczny picker z `/api/tenant-locales`, flaga `is_localizable`, completeness per-locale.
4. **#1147 kanały** (epik, parent + sub #1153–#1156) — **design-first / Plan Mode**. Strona ustawień kanałów + `/api/channels`, read/zapis per-channel, picker dynamiczny.

## Jak procedować
- **Najpierw przeczytaj treść każdego issue** (`gh issue view <N>`) — mają pełny root cause + affected files + acceptance.
- #1146/#1147 → **Plan Mode** (to epiki). #1130/#1138 → SKILL-BUG-FIX-TICKET.
- Każdy ticket = własny branch + PR + CI + merge (marathon rules w CLAUDE.md). Tickety w tym samym pliku można grupować w 1 PR (udokumentuj w opisie).
- Plan referencyjny wszystkich 18: `~/.claude/plans/efektem-planu-niech-b-d-enumerated-fox.md`.

## Gotchy z poprzedniej sesji (NIE odkrywaj od nowa)
- **`composer audit` czerwony w CI = PRE-EXISTING**, NIE blokuje merge: (a) krok CI odpala audit bez `composer install` („No installed packages"), (b) realne advisory `twig/twig` CVE-2026-46634 / CVE-2026-47732. `main` jest **nieprotected** (brak required checks) — te same czerwone na #1129 i wcześniej. → Osobny maintenance ticket: bump twig + fix kroku CI (`--locked` albo dodać install).
- **`gh pr checks <pr>` bywa nieaktualne** (pokazuje „pending" gdy job już `completed success`). Weryfikuj przez `gh run view <run-id> --json jobs -q '.jobs[]|...'`.
- **PHPUnit w CI ~17-18 min** (wolny). PR-y FE-only pomijają joby PHP (path filter) → szybsze.
- **Stack**: `docker compose` już UP. Po `git checkout`/zmianie kodu API: `docker compose restart api` (FrankenPHP worker; ~6s warmup, pierwszy request może dać 502 zanim się rozgrzeje).
- **PHPUnit Api/***: `docker compose exec -T -e APP_ENV=test api php bin/console cache:clear --env=test` PRZED runem (kolizja z dev DB — memory `feedback_phpunit_dev_db_collision.md`).
- **Ścieżki w kontenerze api** są względne do `apps/api` (kontener `/app` = `apps/api`). PHPStan: `vendor/bin/phpstan analyse src/... tests/... --level=max`.
- **Smoke TOTP** liczony pythonem (hmac-sha1 + base32, period 30). Po smoke MFA na adminie — ZAWSZE disable na końcu (inaczej blokada logowania).
- **Admin typecheck/build**: `NODE_OPTIONS=--max-old-space-size=4096` (OOM — memory `feedback_tsc_memory_limit_pim.md`).
- Komendy nie-readonly (docker/gh/git push) odpalaj z `dangerouslyDisableSandbox`.
- Konwencje: `type(scope): subject` ang., PR body PL, BEZ `Co-Authored-By`, stopka `🤖 Generated with [Claude Code]`. PATCH atrybutów: `{attributes:{code: value}}` (upserter owija w `{value}`).

Zacznij od przeczytania issue #1130, #1138, #1146, #1147 i zaproponuj kolejność (proponuję: #1130 → #1138 → Plan Mode #1146 → Plan Mode #1147), potem ruszaj marathonem.

=== /PROMPT ===
