# UAT1 marathon — ZAKOŃCZONE ✅

Wszystkie 15 ticketów z `Testy/UAT1/testy1.docx` (#1348–#1362) zaimplementowane,
otestowane (FE+BE), CI green, squash-merged do `main`. Plus infra #1364.

## Zmergowane (15 ticketów / 15 PR-ów + infra)
| Ticket | PR | Skrót |
|--------|-----|-------|
| infra | #1364 | unblock CI: pnpm lock + symfony 7.4 pin + cursor test |
| #1348 | #1363 | ukryj pustą zakładkę „Atrybuty" |
| #1362 | #1365 | atrybut relation przez grupę w `/relations` |
| #1349 | #1367 | drag-drop sortowanie grup atrybutów |
| #1350 | #1368 | toggle „Wymagany" w edycji atrybutu |
| #1351 | #1369 | detal od razu w edycji + „Zapisz i wróć do listy" |
| #1353 | #1370 | wartość atrybutu: pole „Nazwa" (auto-slug) |
| #1357 | #1372 | usuń „Marka" + ad-hoc z `/products/new` |
| #1358 | #1371 | drzewo kategorii: Nazwa (create wysyła `attributes.name`) |
| #1355+#1356 | #1374 | usuń niedziałające toggle Unique/Indexed |
| #1361 | #1373 | label „Nazwa" zamiast „Kod (np. CAR-001)" |
| #1359 | #1375 | custom OT: kategoria wymagana + przypisanie (BE+FE) |
| #1360 | #1376 | hint reguły osi wariantów (select/multiselect) |
| #1354 | #1393 | filtr zaawansowany: ŚCIŚLE tylko atrybuty `filterable` |
| #1352 | #1394 | formularze atrybutów honorują wszystkie locale (PL/EN/DE) |

`main @ 62beb003`. Wszystkie issues #1348–#1362 CLOSED przez merge.

## Świadome odejścia / uwagi
- **#1354**: realny bug był w `AttributePicker` (pobierał WSZYSTKIE atrybuty, ignorował
  `is_filterable`), nie tylko w hardcodowanym `PANEL_ATTRS`. Fix: `filterableOnly` na pickerze
  + panel czerpie katalog z żywego `/api/attributes?filterable`. Seeder oznacza sensowny
  podzbiór produktu jako filterable (brand/color/size/tags/price/weight/height/in_stock/
  release_date), żeby demo nie miało pustego pickera I żeby klauzule realnie działały w Meili.
  Smoke: `filter[brand]=Acme`→200/3; `filter[description]`→0 (pułapka usunięta).
- **#1352**: FE-only — backend już przechowuje etykiety jako JSONB o dowolnych kluczach locale.
  Reużyty `LocaleTabsField` sterowany `useCurrentWorkspace().enabledLocales`. Smoke włączył
  **DE** na workspace demo (`enabledLocales: [pl,en,de]`) — reversible w Ustawieniach.
- Dev DB był PUSTY na starcie tej sesji (0 atrybutów/obiektów/tenantów) — załadowałem fixtures
  (`doctrine:fixtures:load`) żeby zrobić smoke #1354/#1352. Operator ma teraz świeże demo + DE.

## Lekcje sesji (kandydaci do lessons.md)
- **CI flake powtarzalny**: „Start api container / pull access denied for pim-database" oraz
  „pim_test does not exist" (PHPUnit) → `gh run rerun <id> --failed`. Trafiał ~6×/sesję.
- **PHPStan zielony lokalnie, czerwony w CI**: `cache:warmup --env=dev APP_DEBUG=1` przed
  `phpstan`, `--memory-limit=1G`. Debug-kontener ujawnia błędy `array<mixed>` return-type.
- **PHPUnit Integration**: uruchamiać z `-e APP_ENV=test` (inaczej `test.service_container`
  not found) + `cache:clear --env=test` najpierw (kolizja z dev DB).
- **Typed-array docblock**: nowy klucz w array-shape (`filterable?: bool`) wymaga aktualizacji
  `@return array{...}` PHPDoc, bo `nullCoalesce.offset` na nieistniejącym offsecie = błąd PHPStan.
