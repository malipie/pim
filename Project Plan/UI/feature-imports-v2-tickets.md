# Import v2 (IMP2) — backlog ticketów

> Wygenerowane 2026-06-12 z zaakceptowanego planu `feature-imports-v2.md`. Tracking: GitHub issues #1460–#1498, epic #1499.
> Milestone'y: Etap 0 → #19, Etap 1 → #20, Etap 2 → #21, Etap 3 → #22, Etap 4 → #23. Label: `epik-IMP2`.

## Mapa ticketów

| Klucz | Issue | Tytuł | Est. | Zależy od |
|---|---|---|---|---|
| **ETAP 0 — Quick-winy (~3–5 h)** | | | | |
| IMP2-0.1 | [#1460](https://github.com/malipie/PIM/issues/1460) | fix(admin/imports): pobieranie raportu CSV i eksportu profilu z nagłówkiem Authorization (fetch+blob) | 1–2 h | — |
| IMP2-0.2 | [#1461](https://github.com/malipie/PIM/issues/1461) | fix(imports): topic Mercure z konfiguracji zamiast hardcoded pim.localhost | 1–2 h | — |
| IMP2-0.3 | [#1462](https://github.com/malipie/PIM/issues/1462) | fix(admin/imports): ukrycie przycisków pauza/wznów/anuluj do czasu realnej implementacji | 1 h | — |
| **ETAP 1 / Fala A — silnik + golden v0** | | | | |
| IMP2-1.1 | [#1463](https://github.com/malipie/PIM/issues/1463) | docs(import): ADR Import v2 — kontrakty silnika (tryby, match key, semantyka komórek, kanon JSONB, Deptrac) | 5–7 h | — |
| IMP2-1.2 | [#1464](https://github.com/malipie/PIM/issues/1464) | feat(catalog): migracja legacy shape'ów JSONB do kanonu (D7) | 10–16 h | IMP2-1.1 |
| IMP2-1.3 | [#1465](https://github.com/malipie/PIM/issues/1465) | feat(import): ObjectResolver + realne tryby create/update/upsert | 10–14 h | IMP2-1.1 |
| IMP2-1.4 | [#1466](https://github.com/malipie/PIM/issues/1466) | refactor(catalog,import): wspólny rdzeń walidacji+normalizacji wartości + ImportValueWriter | 18–24 h | IMP2-1.1, IMP2-1.2 |
| IMP2-1.5 | [#1467](https://github.com/malipie/PIM/issues/1467) | test(import): golden round-trip test v0 (eksport → import → równość envelope) | 4–6 h | IMP2-1.3, IMP2-1.4 |
| IMP2-1.6a | [#1468](https://github.com/malipie/PIM/issues/1468) | chore(import): transport Messenger 'import' + worker w dev i prod (D8) | 3–5 h | — |
| **ETAP 1 / Fala B — pełna matryca + media (~105–150 h razem z falą A)** | | | | |
| IMP2-1.6 | [#1469](https://github.com/malipie/PIM/issues/1469) | feat(import,export): gramatyka kolumn code.locale.channel + zapis channelId | 9–13 h | IMP2-1.1, IMP2-1.4, IMP2-1.5 |
| IMP2-1.7 | [#1470](https://github.com/malipie/PIM/issues/1470) | feat(import): multi-kategorie pipe-split + import status/enabled | 4–6 h | IMP2-1.4, IMP2-1.5 |
| IMP2-1.8 | [#1471](https://github.com/malipie/PIM/issues/1471) | feat(import,export): warianty parent_sku (two-pass) + relacje ObjectRelation + fan-out include_variants | 14–20 h | IMP2-1.4, IMP2-1.6 |
| IMP2-1.9 | [#1472](https://github.com/malipie/PIM/issues/1472) | feat(import): izolacja błędów per wiersz + naprawa maszyny stanów + semantyka severity | 6–8 h | IMP2-1.4 |
| IMP2-1.10 | [#1473](https://github.com/malipie/PIM/issues/1473) | test(import): golden test pełna matryca + pierwsze testy ścieżki async | 8–12 h | IMP2-1.5, IMP2-1.6, IMP2-1.7, IMP2-1.8, IMP2-1.9, IMP2-1.6a |
| IMP2-1.11 | [#1474](https://github.com/malipie/PIM/issues/1474) | chore(import): higiena backlogu importów | 1–2 h | — |
| IMP2-1.12 | [#1475](https://github.com/malipie/PIM/issues/1475) | feat(import,asset): pobieranie zdjęć z URL-i (media w imporcie, część 1) | 8–12 h | IMP2-1.4 |
| IMP2-1.13 | [#1476](https://github.com/malipie/PIM/issues/1476) | feat(import,asset): zdjęcia z pliku ZIP (media w imporcie, część 2) | 6–8 h | IMP2-1.12 |
| **ETAP 2 — Odporność i ochrona bazy (~58–80 h)** | | | | |
| IMP2-2.1 | [#1477](https://github.com/malipie/PIM/issues/1477) | refactor(import): streaming readers — openspout dla XLSX, league/csv stream dla CSV | 8–10 h | — |
| IMP2-2.2 | [#1478](https://github.com/malipie/PIM/issues/1478) | feat(import): staged upload — plik wgrywany raz i reużywany w preview/dry-run/start | 5–7 h | IMP2-2.1 |
| IMP2-2.3 | [#1479](https://github.com/malipie/PIM/issues/1479) | feat(import): realna pauza/wznowienie/anulowanie + checkpoint odporny na crash | 7–9 h | IMP2-1.6a, IMP2-0.3 |
| IMP2-2.4 | [#1480](https://github.com/malipie/PIM/issues/1480) | feat(import): undo-log operacji + rollback v2 (delete created + replay updated, rebuild indeksów) | 12–18 h | IMP2-1.3, IMP2-1.4 |
| IMP2-2.5 | [#1481](https://github.com/malipie/PIM/issues/1481) | feat(import,identity): tenant_id na import_logs + GUC app.current_tenant w workerach (RLS-ready) | 3–5 h | — |
| IMP2-2.6 | [#1482](https://github.com/malipie/PIM/issues/1482) | perf(import,export): bulk-path wydajność + benchmark RAM jako test | 10–14 h | IMP2-1.4, IMP2-2.1 |
| IMP2-2.7 | [#1483](https://github.com/malipie/PIM/issues/1483) | feat(import): limity i guardraile (D10) + streamowany raport CSV | 3–5 h | IMP2-2.1 |
| IMP2-2.8 | [#1484](https://github.com/malipie/PIM/issues/1484) | security(import,export): bezpieczeństwo plików — zip-bomb, folder-probe, CSV injection, limit body | 4–6 h | — |
| IMP2-2.9 | [#1485](https://github.com/malipie/PIM/issues/1485) | fix(catalog): macierz równoległości — BulkOperationLock dla bulk-edit + OptimisticLock per-id (D11) | 4–6 h | IMP2-2.6 |
| IMP2-2.10 | [#1486](https://github.com/malipie/PIM/issues/1486) | feat(import,backup): spięcie do_backup z modułem Backup i sesją importu | 2–3 h | — |
| **ETAP 3 — Kreator + UI (~82–120 h)** | | | | |
| IMP2-3.1 | [#1487](https://github.com/malipie/PIM/issues/1487) | feat(import): detekcja rozszerzona pliku — header offset, wiersz startu danych, arkusz, separatory, .xls | 9–13 h | IMP2-2.1 |
| IMP2-3.2 | [#1488](https://github.com/malipie/PIM/issues/1488) | feat(catalog): endpoint import-schema — schemat importu generowany z ObjectType | 4–6 h | IMP2-1.4 |
| IMP2-3.3 | [#1489](https://github.com/malipie/PIM/issues/1489) | feat(import,admin): Mapping UI v2 — mapping po indeksie kolumny, multi-kolumny, wymiary, bulk-create atrybutów | 12–17 h | IMP2-3.1, IMP2-3.2 |
| IMP2-3.4 | [#1490](https://github.com/malipie/PIM/issues/1490) | feat(import): TransformPipeline — deklaratywne transformacje wartości per kolumna | 10–14 h | IMP2-3.3 |
| IMP2-3.5 | [#1491](https://github.com/malipie/PIM/issues/1491) | feat(import,admin): profile v2 + mapping memory — zapis i aplikowanie pełnej konfiguracji przebiegu | 6–8 h | IMP2-3.3, IMP2-3.4 |
| IMP2-3.6 | [#1492](https://github.com/malipie/PIM/issues/1492) | feat(import): dry-run v2 — dwupoziomowy, kubełki utworzy/zaktualizuje/wyczyści, plik odrzutów | 8–10 h | IMP2-1.3, IMP2-3.4, IMP2-2.2 |
| IMP2-3.7 | [#1493](https://github.com/malipie/PIM/issues/1493) | feat(admin/imports): wizard 6 kroków + widok sesji v2 + hub delta (absorbuje NUI-10/NUI-11) | 30–44 h | IMP2-3.1, IMP2-3.2, IMP2-3.3, IMP2-3.4, IMP2-3.5, IMP2-3.6, IMP2-2.3 |
| IMP2-3.8 | [#1494](https://github.com/malipie/PIM/issues/1494) | feat(import): generator szablonu importu XLSX z ObjectType | 3–5 h | IMP2-3.2 |
| **ETAP 4 — Operacjonalizacja, na żądanie (~26–36 h)** | | | | |
| IMP2-4.1 | [#1495](https://github.com/malipie/PIM/issues/1495) | feat(import): harmonogramy realne (cron daemon) | 6–8 h | IMP2-1.6a, IMP2-4.2 |
| IMP2-4.2 | [#1496](https://github.com/malipie/PIM/issues/1496) | feat(import): źródła realne lub uczciwe (driver SFTP/HTTP, polling, health „off” zamiast fałszywego „ok”) | 8–12 h | IMP2-1.6a |
| IMP2-4.3 | [#1497](https://github.com/malipie/PIM/issues/1497) | feat(import): content-hash skip-unchanged dla feedów cyklicznych | 6–8 h | IMP2-1.4, IMP2-2.6 |
| IMP2-4.4 | [#1498](https://github.com/malipie/PIM/issues/1498) | feat(import,admin): notyfikacje końca importu + telemetria | 6–8 h | IMP2-1.6a |

---

## Pełne treści ticketów

## IMP2-0.1 — fix(admin/imports): pobieranie raportu CSV i eksportu profilu z nagłówkiem Authorization (fetch+blob) (#1460)

**Labels:** frontend, bug, epik-IMP2 · **Estymata:** 1–2 h · **Zależy od:** brak

## Po co to robimy (kontekst nietechniczny)
Przycisk „Pobierz raport CSV" na stronie zakończonego importu oraz „Eksportuj" przy profilu importu najprawdopodobniej **nie działają od tygodni** — otwierają link bez tokenu logowania, więc serwer odpowiada „401 Unauthorized" zamiast plikiem. Użytkownik klika i nic sensownego się nie dzieje. Po tym tickecie oba przyciski pobierają plik poprawnie, tak jak robi to już moduł eksportów.

## Stan obecny
- `apps/admin/src/features/imports/show/ImportShowPage.tsx` — raport pobierany przez goły `<a href="/api/import-sessions/{id}/report.csv">`; endpoint ma `#[RequiresPermission]` → bez nagłówka `Authorization: Bearer` zwraca 401.
- `apps/admin/src/features/imports/profiles/ImportProfilesView.tsx` — eksport profilu (`GET /api/import-profiles/{id}/export`) przez anchor, ten sam bug.
- Wzorzec poprawny istnieje w eksportach: pobieranie przez `fetch` z Bearer + `URL.createObjectURL(blob)` (komentarz o naprawie w `apps/admin/src/features/exports/` — sesje eksportu pobierają plik przez fetch+blob).

## Zakres prac
1. `ImportShowPage.tsx`: zamiana anchora na handler pobierający przez `jsonFetch`/`fetch` z nagłówkiem Authorization (reuse helpera auth z `apps/admin/src/lib/http.ts`), odpowiedź jako blob → `URL.createObjectURL` → programatyczny download z nazwą pliku z `Content-Disposition` (fallback: `import-report-{id}.csv`).
2. `ImportProfilesView.tsx`: to samo dla eksportu profilu JSON.
3. Stan ładowania na przyciskach (disabled + spinner) i toast błędu przy nie-200.

## Poza zakresem tego ticketu
- Streaming raportu po stronie BE (`ImportReportCsvController` ładuje do 100k encji do RAM) — IMP2-2.7.
- Pozostałe poprawki UI importów — IMP2-3.7.

## Kryteria akceptacji
- [ ] Klik „Pobierz raport CSV" na sesji z błędami pobiera plik CSV (DevTools Network: 200, request ma nagłówek `Authorization`).
- [ ] Klik „Eksportuj" przy profilu pobiera JSON profilu (200 + Authorization).
- [ ] Przy 4xx/5xx użytkownik widzi toast z komunikatem, nie pustą kartę.
- [ ] Zero gołych `<a href` do autoryzowanych endpointów w `features/imports` (grep w CI/review).

## Jak zwalidować (smoke test po wykonaniu)
1. `pnpm stack:up`, zaloguj się na https://pim.localhost (admin@demo.localhost / changeme).
2. Uruchom mały import CSV z celowym błędem (np. wiersz bez SKU) → wejdź na stronę sesji.
3. Kliknij „Pobierz raport CSV" → plik się pobiera, w DevTools request ma status 200 i nagłówek Authorization; otwórz plik — zawiera wiersz błędu.
4. Imports → Profile → menu profilu → Eksportuj → pobiera się JSON.
5. Konsola DevTools bez czerwonych błędów.

## Zależności
Blokowany przez: —. Blokuje: —.

## Referencje
Plan: `Project Plan/UI/feature-imports-v2.md` §2.7, §5 ETAP 0. Kod: `ImportShowPage.tsx`, `ImportProfilesView.tsx`, wzorzec w `features/exports`.

## Definition of Done
Biome + tsc (`NODE_OPTIONS=--max-old-space-size=4096`) zielone; smoke wg sekcji „Jak zwalidować" z artefaktem dowodu (screenshot Network 200) w komentarzu zamykającym (CLOSED MEANS CLOSED).

---

## IMP2-0.2 — fix(imports): topic Mercure z konfiguracji zamiast hardcoded pim.localhost (#1461)

**Labels:** frontend, backend, bug, epik-IMP2 · **Estymata:** 1–2 h · **Zależy od:** brak

## Po co to robimy (kontekst nietechniczny)
Pasek postępu importu na żywo działa tylko dlatego, że aplikacja chodzi na adresie deweloperskim `pim.localhost` — adres jest wpisany na sztywno w kod. Po wdrożeniu na domenę produkcyjną postęp na żywo przestałby działać po cichu (subskrypcja nie trafi w temat publikowany przez serwer). To łamie też regułę projektu „zero hardcodowanych URL-i".

## Stan obecny
- `apps/admin/src/features/imports/hooks/useImportProgress.ts` — buduje topic `https://pim.localhost/imports/{sessionId}` na sztywno.
- `apps/api/src/Import/Application/Service/ImportProgressPublisher.php` — default parametru DI `topicBase` to dev URL (parametr jest, ale default zły).
- Porównaj `useExportSessionsStream` w `apps/admin/src/features/exports/` — sprawdzić, czy eksporty mają ten sam problem; jeśli tak, naprawić oba w tym tickecie (publisher eksportów: `ExportProgressPublisher` ma ten sam hardcoded default).

## Zakres prac
1. FE: topic base z konfiguracji środowiskowej (`import.meta.env.VITE_MERCURE_TOPIC_BASE` z fallbackiem na `window.location.origin`) — wspólny helper, użyty w `useImportProgress` (+ `useExportSessionsStream`, jeśli dotknięty).
2. BE: parametr `topicBase` w `services.yaml` z env (`MERCURE_TOPIC_BASE` / istniejący `MERCURE_PUBLIC_URL`), bez defaultu wskazującego dev; oba publishery (`ImportProgressPublisher`, `ExportProgressPublisher`) korzystają z parametru.
3. `.env` + `.env.dev`: wartość dla dev (`https://pim.localhost`); docs jednym zdaniem w `docs/` lub komentarzu konfiguracyjnym.

## Poza zakresem tego ticketu
- Throttling eventów Mercure (per chunk zamiast per wiersz) — IMP2-2.6.
- ImportsLiveBridge / inbox — IMP2-4.4.

## Kryteria akceptacji
- [ ] `grep -r "pim.localhost" apps/admin/src/features/imports apps/api/src/Import apps/api/src/Export` → 0 trafień w kodzie (poza testami/configiem dev).
- [ ] Progress na żywo działa na dev: uruchom import >50 wierszy, licznik rośnie bez odświeżania strony.
- [ ] Topic publikowany przez BE == topic subskrybowany przez FE (zweryfikowane w DevTools: URL EventSource zawiera topic zgodny z konfiguracją).

## Jak zwalidować (smoke test po wykonaniu)
1. `pnpm stack:up`, login na https://pim.localhost.
2. Uruchom import pliku ~100 wierszy (np. wycinek z `Zrodla/Importy przykładowe/990.csv` zmapowany minimalnie).
3. Na stronie sesji: licznik przetworzonych wierszy aktualizuje się na żywo (bez F5); w DevTools → Network → EventSource widać subskrypcję z topikiem z konfiguracji.
4. Konsola bez czerwonych błędów.

## Zależności
Blokowany przez: —. Blokuje: — (IMP2-4.4 korzysta z poprawnego topic base).

## Referencje
Plan: §2.7, §5 ETAP 0. Reguła CLAUDE.md: „Brak hardkodowanych URL-i". Kod: `useImportProgress.ts`, `ImportProgressPublisher.php`, `ExportProgressPublisher.php`.

## Definition of Done
PHPStan max + Biome + tsc zielone; smoke wg sekcji walidacji z dowodem (screenshot EventSource + rosnący licznik); CLOSED MEANS CLOSED.

---

## IMP2-0.3 — fix(admin/imports): ukrycie przycisków pauza/wznów/anuluj do czasu realnej implementacji (#1462)

**Labels:** frontend, bug, epik-IMP2 · **Estymata:** 1 h · **Zależy od:** brak

## Po co to robimy (kontekst nietechniczny)
Na widoku trwającego importu są przyciski „Pauza" i „Anuluj", które wyglądają, jakby działały — ale silnik ich nie honoruje: import idzie dalej do końca, a na dodatek sesja kończy się wtedy błędnym statusem „FAILED", mimo że dane zostały zaimportowane. Użytkownik, który kliknie pauzę, dostaje fałszywą informację o porażce. Do czasu prawdziwej implementacji (IMP2-2.3) przyciski muszą zniknąć — zgodnie z zasadą „zero fałszywych obietnic w UI".

## Stan obecny
- `apps/api/src/Import/Presentation/Controller/ImportSessionStateController.php` — pause/resume/cancel zmieniają TYLKO status na encji.
- `apps/api/src/Import/Application/Handler/ImportRunHandler.php` — pętla NIE sprawdza statusu między chunkami (komentarz „follow-up"); po pauzie `markCompleted()` rzuca `LogicException` → sesja FAILED.
- FE: przyciski w widoku sesji/karcie live (`ImportShowPage.tsx`, `LiveSessionCard.tsx` — zweryfikować oba miejsca).

## Zakres prac
1. Ukryć (nie usuwać) przyciski pauza/wznów/anuluj w `ImportShowPage.tsx` i `LiveSessionCard.tsx` — za stałą feature-flagą w kodzie (np. `IMPORT_PAUSE_ENABLED = false`) z komentarzem `// re-enable in IMP2-2.3`.
2. Endpointy BE zostają bez zmian (IMP2-2.3 je wykorzysta).
3. Usunąć/zaktualizować asercje Playwright dotykające tych przycisków (jeśli istnieją).

## Poza zakresem tego ticketu
- Realna pauza/wznowienie/anulowanie z checkpointem — IMP2-2.3 (przywróci przyciski).

## Kryteria akceptacji
- [ ] Na widoku trwającego importu nie ma przycisków pauza/wznów/anuluj.
- [ ] Flaga + komentarz wskazują IMP2-2.3.
- [ ] Playwright dla importów zielony.

## Jak zwalidować (smoke test po wykonaniu)
1. Uruchom import >50 wierszy, otwórz widok sesji w trakcie biegu.
2. Brak przycisków pauzy/anulowania; import kończy się statusem success/partial (nie FAILED).

## Zależności
Blokowany przez: —. Blokuje: — (IMP2-2.3 odwraca ten ticket).

## Referencje
Plan: §2.7, §5 ETAP 0, filar 12. Kod: `ImportSessionStateController.php`, `ImportRunHandler.php`.

## Definition of Done
Biome + tsc + Playwright zielone; screenshot widoku sesji bez przycisków w komentarzu zamykającym.

---

## IMP2-1.1 — docs(import): ADR Import v2 — kontrakty silnika (tryby, match key, semantyka komórek, kanon JSONB, Deptrac) (#1463)

**Labels:** docs, backend, epik-IMP2 · **Estymata:** 5–7 h · **Zależy od:** brak

## Po co to robimy (kontekst nietechniczny)
Moduł importu będzie przebudowywany przez kilkanaście ticketów i kilka sesji agenta. Żeby każdy wykonawca podejmował te same decyzje (co znaczy pusta komórka, po czym dopasowujemy wiersz do produktu, jak wygląda „poprawna" wartość w bazie), wszystkie kontrakty muszą być spisane w jednym, autorytatywnym miejscu — zanim powstanie kod. Dziś w repo żyją równolegle trzy sprzeczne wersje prawdy o trybach importu (docblock mówi „zawsze upsert", spec v1 mówi „tylko dodawanie", kod robi „tylko create + blokada duplikatów"), a dokument shape'ów JSONB jest sprzeczny z kodem. Po tym tickecie istnieje jeden dokument decyzyjny (ADR), zaktualizowana dokumentacja shape'ów i reguła architektoniczna w CI, która pilnuje, żeby kod importu nie sięgał do wnętrzności katalogu.

## Stan obecny
- **ADR-y**: nowsze decyzje są per-file MADR w `docs/adr/` — ostatni to `docs/adr/0018-channel-publication-profile.md`; każdy ma streszczenie w `Project Plan/01-architektura-pim.md` sekcja `## 13. Architecture Decision Records (ADR)` (linia ~951; wzorzec wpisu zbiorczego: `### ADR-0016, ADR-0017, ADR-0018 (per-file MADR)` linia ~1255). **Następny wolny numer: ADR-0019.**
- **Deptrac**: `apps/api/deptrac.yaml` definiuje warstwy Catalog/Channel/Asset/Identity (Internals+Contracts), Shared, Search, Integration, Agent, ApiConfigurator, Tooling — **warstw Import i Export w ogóle nie ma**, więc kod w `apps/api/src/Import/` i `apps/api/src/Export/` może bezkarnie zależeć od czegokolwiek (i zależy: `apps/api/src/Import/Application/Service/ImportObjectCreator.php` importuje encje `App\Catalog\Domain\Entity\*` bezpośrednio).
- **`docs/api/jsonb-schemas.md`** (272 linie, authoritative wg CLAUDE.md): sekcja 1 (`attributes_indexed`) wymaga envelope z kluczem `value` dla KAŻDEGO typu (`"required": ["value"]`), podczas gdy kod eksportu (`apps/api/src/Export/Application/Builder/ValueSerializer.php`, linia 62) i flattener Meilisearch (`apps/api/src/Search/Application/DocumentFlattener.php`) czytają dla selecta `{option_code}`, dla multiselect `{option_codes}`, dla price `{amount, currency}`. Brak sekcji opisującej kanon `object_values.value` per typ atrybutu (17 typów `AttributeType`).
- **Trzy wersje prawdy o trybach**: docblock `apps/api/src/Import/Domain/Enum/ImportMode.php` („The worker today always upserts"), spec v1 (`Project Plan/UI/feature-imports.md`), kod (`ImportObjectCreator` robi zawsze `new CatalogObject`; `apps/api/src/Import/Presentation/Controller/ListImportSessionsController.php:142` hardcoduje `'mode' => 'UPDATE'`).
- **Makieta NUI-10 (#1429)** pokazuje kubełek „Aktualizacje", którego silnik nie umie wyprodukować — wizard potrzebuje „karty prawdy": co realnie działa na każdym etapie przebudowy.
- Limity i progi rozproszone: `StartImportController::SYNC_THRESHOLD_ROWS = 50` (ale porównuje bajty), brak `redeliver_timeout` w `apps/api/config/packages/messenger.yaml` (doctrine default 3600 s).

## Zakres prac
1. **Nowy ADR `docs/adr/0019-import-v2-engine-contracts.md`** (format MADR jak `0016`–`0018`), normujący — z uzasadnieniem i odrzuconymi alternatywami — następujące kontrakty (źródło: `Project Plan/UI/feature-imports-v2.md` §4.4 + §9):
   - **Tryby importu (D3)**: `create` / `update` / `upsert`, default `upsert`; MERGE/INCREMENT/DELETE usunięte z enum (z migracją danych — implementacja w IMP2-1.3).
   - **Klucz dopasowania (D1)**: `objects.code` (SKU) default; opcjonalnie atrybut typu `identifier` per profil (np. EAN). Porównanie case-sensitive + trim. Duplikat klucza w pliku: pierwsze wystąpienie wygrywa, kolejne skip + warning (pre-flight, nie constraint DB).
   - **Semantyka komórek (D2)**: kolumna nieobecna = nie ruszaj; komórka pusta = nie ruszaj (default); czyszczenie tylko opt-in `clear_if_empty` per kolumna (UI dopiero po domknięciu migracji D7); kolekcje: `replace` default przy obecnej kolumnie, `append` opt-in; próg ostrzegawczy w dry-run (>20% niepustych wartości do skasowania = explicit confirm).
   - **Gramatyka kolumn**: `code` / `code.locale` / `code.channel` / `code.locale.channel`; dezambiguacja przez rejestr locali i kanałów tenanta + reguła precedencji przy kolizji kodów (kanał `en` vs locale `en`) + zakaz tworzenia kanału o kodzie kolidującym z locale; egzekwowany charset kodów atrybutów (bez kropek).
   - **Kanon shape'ów JSONB** per 17 typów `AttributeType` (`apps/api/src/Catalog/Domain/AttributeType.php`): scalar `{value}` (text/textarea/wysiwyg/number/date/datetime/boolean/color/email/identifier), `{option_code}` (select), `{option_codes: []}` (multiselect), `{amount, currency}` (price), `{value, unit}` (metric), `{asset_id}` (asset), `{object_id}` (relation/reference). Wskazanie legacy-wariantów do migracji (D7, IMP2-1.2).
   - **Umiejscowienie writera**: współdzielony rdzeń walidacji+normalizacji w `Catalog\Application`, wystawiony przez kontrakt (interfejs + DTO) w `Catalog\Contracts`; konsumenci: `ObjectAttributesUpserter` (per-request, HTTP-exceptions) i `ImportValueWriter` (result-based, persist-only) — implementacja w IMP2-1.4.
   - **Kontrakt `import_session_id` (D11)**: stampowany WYŁĄCZNIE na `new CatalogObject` = marker „utworzony przez sesję"; obiekty aktualizowane śledzi undo-log (etap 2); last-writer-wins na `object_values` udokumentowane.
   - **Limity (D8/D10)**: inline sync ≤50 WIERSZY (nie bajtów); dry-run dwupoziomowy (sync próbka ~1000 wierszy / pełny async z flagą dryRun); max 100 MB / 200k wierszy per plik; transport Messenger `import` z `redeliver_timeout` > max czas importu.
   - **Profile (D9)**: per-user (decyzja operatora 2026-06-12), `columnMapping` v2 wersjonowany, mapping kluczowany indeksem kolumny (D12); `.xls` przez PhpSpreadsheet ≤20 MB read-only (D13); jeden arkusz per sesja (D14).
   - **Reguły normalizacji golden testu** (wersjonowane, minimalne — docelowo byte-equality): jawna lista dopuszczalnych różnic eksport↔import (np. reprezentacja float `1.50`→`1.5`, ISO 8601 dat, trim whitespace). Każda zmiana listy = bump wersji reguł w ADR.
2. **Streszczenie ADR-0019 w `Project Plan/01-architektura-pim.md`** sekcja 13 — wzorem wpisu o ADR-0016..0018 (krótki akapit + link do pliku MADR).
3. **Aktualizacja `docs/api/jsonb-schemas.md`**: nowa sekcja „`object_values.value` — kanon per AttributeType" (tabela typ → shape → przykład → legacy warianty oznaczone DEPRECATED do D7) + korekta sekcji 1 (`attributes_indexed` envelope niesie shape per-typ, nie zawsze klucz `value`). Dokument ma przestać być sprzeczny z `ValueSerializer`/`DocumentFlattener`.
4. **`apps/api/deptrac.yaml`**: nowe warstwy `Import` i `Export` (collectors: `src/Import/.*`, `src/Export/.*`) z rulesetem docelowym: **tylko `Catalog_Contracts` + `Shared`** (+ `Identity_Contracts` dla atrybutu `#[RequiresPermission]` — wzorzec już obecny w pliku dla warstwy Shared). Wszystkie ISTNIEJĄCE zależności do Internals (np. `ImportObjectCreator` → `App\Catalog\Domain\Entity\CatalogObject`, kontrolery → `App\Identity\Domain\Entity\User`) trafiają do `skip_violations` jako baseline-TODO (dokładnie wzorem istniejącego bloku w pliku — „new violations not in this list fail the build through CI"). Baseline zdejmowany w IMP2-1.4.
5. **Karta prawdy dla wizarda** (NUI-10): nowy plik `Project Plan/UI/imports-v2-karta-prawdy.md` — tabela: element UI (krok wizarda / kubełek dry-run / tryb / status sesji) → stan (działa / placeholder do ticketu IMP2-X / usunięte). Komentarz z linkiem w issue #1429 i #1430. To jest warunek wstępny zlecenia ticketu 3.7 (plan §5, uwaga o kolejności).

## Poza zakresem tego ticketu
- Jakakolwiek implementacja kodu produkcyjnego (tryby → IMP2-1.3, rdzeń writera → IMP2-1.4, migracja shape'ów → IMP2-1.2, transport → IMP2-1.6a).
- Gramatyka kolumn w kodzie (`ImportColumnGrammar`) → IMP2-1.6 (fala B).
- Zdejmowanie baseline'u `skip_violations` Deptraca → IMP2-1.4.
- Aktualizacja `Project Plan/UI/feature-imports.md` (banner „superseded by v2") → IMP2-1.11 (higiena backlogu, fala B).

## Kryteria akceptacji
- [ ] Istnieje `docs/adr/0019-import-v2-engine-contracts.md` w formacie MADR pokrywający WSZYSTKIE punkty z zakresu 1 (każda decyzja D1–D14 z planu ma swój akapit lub jawne odesłanie do przyszłego ADR-a).
- [ ] `Project Plan/01-architektura-pim.md` sekcja 13 zawiera streszczenie ADR-0019 z linkiem.
- [ ] `docs/api/jsonb-schemas.md` zawiera tabelę kanonu `object_values.value` dla wszystkich 17 typów `AttributeType` i nie jest sprzeczny z `ValueSerializer` (select = `{option_code}`).
- [ ] `apps/api/deptrac.yaml` ma warstwy `Import` i `Export` z rulesetem `Catalog_Contracts + Shared (+ Identity_Contracts)`; `composer deptrac` w `apps/api` przechodzi na zielono (istniejące naruszenia w `skip_violations` z komentarzem TODO → IMP2-1.4).
- [ ] Świadomie dodany do `skip_violations` baseline NIE zawiera wpisów-wildcardów — każda klasa wymieniona jawnie.
- [ ] Istnieje `Project Plan/UI/imports-v2-karta-prawdy.md`; issues #1429 i #1430 mają komentarz z linkiem.
- [ ] Reguły normalizacji golden testu mają numer wersji (v1) i jawną, zamkniętą listę dopuszczalnych różnic.

## Jak zwalidować (smoke test po wykonaniu)
1. `cd /Users/mlipieclocal/dev/PIM && ls docs/adr/ | grep 0019` → plik istnieje.
2. `docker compose exec api composer deptrac` → `0 violations` (baseline w skip_violations dozwolony), exit code 0.
3. Celowo dodaj w dowolnym pliku `apps/api/src/Import/` tymczasowy `use App\Catalog\Domain\Entity\ObjectType;` z referencją w kodzie (klasa spoza baseline'u) → `composer deptrac` zgłasza violation → cofnij zmianę. (Dowód, że reguła realnie strzeże granicy.)
4. `grep -n "option_code" docs/api/jsonb-schemas.md` → kanon selecta opisany.
5. Otwórz `Project Plan/UI/imports-v2-karta-prawdy.md` — każdy krok wizarda z makiety `Import-nowy.html` ma wiersz w tabeli.
6. `gh issue view 1429 --comments` → komentarz z linkiem do karty prawdy.

## Zależności
Blokowany przez: — (pierwszy ticket fali A).
Blokuje: IMP2-1.2, IMP2-1.3, IMP2-1.4 (a pośrednio 1.5); karta prawdy odblokowuje też przyszłe 3.7.

## Referencje
- `Project Plan/UI/feature-imports-v2.md` §3 (filary), §4.4 (D1–D14), §5 (tabela fali A, wiersz 1.1), §9 (decyzje operatora).
- `docs/adr/0018-channel-publication-profile.md` (wzorzec MADR), `docs/adr/0013-deptrac-rollout.md` (wzorzec baseline'u).
- `docs/api/jsonb-schemas.md`, `apps/api/deptrac.yaml`.
- Issues: #1429 (NUI-10), #1430 (NUI-11), #1130 (round-trip), #598–#605 (IMP-16..19 — porządkowane w IMP2-1.11).
- Kod cytowany w „Stan obecny": `ImportMode.php`, `ImportObjectCreator.php`, `ListImportSessionsController.php`, `ValueSerializer.php`, `DocumentFlattener.php`.

## Definition of Done
- Deptrac zielony (`composer deptrac` w apps/api) — to jest jedyna „kodowa" zmiana ticketu; PHPStan max zielony (zmiana yaml nie powinna go ruszyć, ale CI musi przejść w całości).
- Brak zmian endpointów → bez regeneracji `docs/api-spec/v0.json`, bez Playwright (zmiany doc-only + config CI).
- Dokumenty po polsku, identyfikatory kodu po angielsku; commit `docs(import): ...` zgodny z Conventional Commits.
- Manual smoke wg sekcji „Jak zwalidować" — artefakt dowodu w komentarzu zamykającym issue: output `composer deptrac` + linki do trzech zmienionych/utworzonych dokumentów (CLOSED MEANS CLOSED).

---

## IMP2-1.2 — feat(catalog): migracja legacy shape'ów JSONB do kanonu (D7) (#1464)

**Labels:** backend, frontend, bug, epik-IMP2 · **Estymata:** 10–16 h · **Zależy od:** IMP2-1.1

## Po co to robimy (kontekst nietechniczny)
W bazie żyją dziś równolegle dwa „dialekty" zapisu wartości atrybutów. Skutek widoczny gołym okiem: **wartość pola typu select wpisana w adminie eksportuje się jako PUSTA komórka** — bo admin zapisuje ją w starym dialekcie, a eksport czyta tylko nowy. Round-trip (eksport → poprawka w Excelu → import), który jest celem całego epiku, nie ma szans działać, dopóki baza mówi dwoma językami. Ten ticket ujednolica zapis: naprawia miejsca, które produkują stary dialekt, i jednorazowo migruje istniejące dane. Po wykonaniu: selecty z admina poprawnie się eksportują, walidacja opcji przestaje być omijana, a golden test (IMP2-1.5) ma stabilny grunt.

## Stan obecny
- **`apps/api/src/Catalog/Application/ObjectAttributesUpserter.php`**, metoda `wrapValue()` (linie 241–257): każdy skalar ślepo opakowywany w `{value: ...}`, bez patrzenia na typ atrybutu. Admin FE wysyła dla selecta goły kod opcji (w `apps/admin/src` nie ma ANI JEDNEGO wystąpienia `option_code` — zweryfikowane grepem), więc select z admina ląduje w `object_values.value` jako `{"value":"red"}` zamiast kanonicznego `{"option_code":"red"}`.
- Konsekwencja 1 — walidacja omijana: `ObjectAttributesUpserter::hasValidatableContent()` (linie 195–212) czyta dla selecta klucz `option_code`; legacy `{value}` zwraca false → walidacja #1261 (membership w `AttributeOption`) w ogóle się nie uruchamia.
- Konsekwencja 2 — eksport pusty: `apps/api/src/Export/Application/Builder/ValueSerializer.php` linia 62: `AttributeType::Select => $this->pickKey($payload, 'option_code')` — dla `{value:'red'}` zwraca `''`. **Bug żywy dziś.** Uwaga: dla price fallback już istnieje (#1271, linie 168–181: `$payload['amount'] ?? $payload['value']`) — to wzorzec do powtórzenia dla selecta na okres przejściowy.
- **`apps/api/src/Catalog/Presentation/Controller/GenerateVariantsController.php`** (~linia 250–256): osie wariantów stampowane jako `value: ['value' => $axisValue]`, a osie są ZAWSZE typu select/multiselect (kontrakt `variant_axes` w `docs/api/jsonb-schemas.md`, „Tylko select/multiselect attributes mogą być axes") → każdy wygenerowany wariant produkuje kolejne legacy wiersze.
- **FE**: `apps/admin/src/lib/attributes-indexed.ts` — `unwrapAttributesIndexed()` podnosi wyłącznie klucz `.value` (linie 26–27); envelope `{option_code}` przechodzi nietknięty jako obiekt.
- **Defensywne read-paths BE** (czytają OBA shape'y): `apps/api/src/Search/Application/DocumentFlattener.php` (linie 44–61) i `apps/api/src/Catalog/Domain/Rule/VisibleWhenRuleEvaluator.php` (linie 81–88).
- Rebuild cache'u: `apps/api/src/Catalog/Application/AttributesIndexedRebuilder.php`; reindex Meilisearch: komenda `pim:search:reindex` (`apps/api/src/Search/Presentation/Command/SearchReindexCommand.php`).
- Migracje: ostatnia `apps/api/migrations/Version20260610120000.php` (konwencja `VersionYYYYMMDDHHMMSS`).
- **Decyzja operatora 9.4 (plan §9)**: przed migracją OBOWIĄZKOWY dump dev DB; **NIE resetować bazy** (`pim:db:reset` zabronione — kasuje ręczny stan operatora, patrz lessons).

## Zakres prac
1. **Attribute-aware `wrapValue()`** w `ObjectAttributesUpserter`: sygnatura dostaje `Attribute` i normalizuje skalar do kanonu per typ (zgodnie z ADR-0019 / `docs/api/jsonb-schemas.md`): select → `{option_code}`, multiselect (lista kodów lub string) → `{option_codes: [...]}`, price → `{amount, currency}` (parsowanie `"299.99 PLN"` i gołej liczby), metric → `{value, unit}`, asset → `{asset_id}`, relation/reference → `{object_id}`, pozostałe → `{value}`. Tablice już-kanoniczne przechodzą bez zmian. `hasValidatableContent` zaczyna realnie walidować selecty z admina — sprawdzić, że istniejące testy Upsertera przechodzą lub świadomie zaktualizować.
2. **Fix `GenerateVariantsController`**: stamp osi jako `{option_code: $axisValue}` (osie multiselect: pojedyncza wartość kombinacji nadal jako `{option_code}` na atrybucie? — NIE: dla osi typu multiselect zapis `{option_codes: [$axisValue]}`; rozstrzygnięcie zgodne z kanonem ADR-0019, udokumentować w PR).
3. **Migracja SQL** (nowa klasa w `apps/api/migrations/`): idempotentny UPDATE `object_values` dla typów strukturalnych, np. dla selecta:
   ```sql
   UPDATE object_values ov SET value = jsonb_build_object('option_code', ov.value->>'value')
   FROM attributes a WHERE a.id = ov.attribute_id AND a.type = 'select'
     AND ov.value ? 'value' AND NOT ov.value ? 'option_code';
   ```
   Analogicznie: multiselect (goła lista / `{value:[...]}` → `{option_codes}`), price (`{value}` → `{amount}`; currency pozostaje NULL-em — eksport już to obsługuje), metric/asset/relation/reference (audyt + migracja jeśli zapytania diagnostyczne wykażą legacy wiersze). **Przed napisaniem migracji**: zapytania diagnostyczne na dev DB (count legacy wariantów per typ) — wyniki wkleić do PR jako uzasadnienie zakresu. `down()` może być no-op z komentarzem (restore z dumpa).
4. **Po migracji**: rebuild `attributes_indexed` dla wszystkich dotkniętych obiektów (przez `AttributesIndexedRebuilder` / istniejący async path `ObjectValuesChangedMessage` — wybrać i udokumentować; dla jednorazowej operacji dopuszczalny prosty skrypt/komenda iterująca z `clear()` per chunk 200 — reguła memory z CLAUDE.md) + `pim:search:reindex`.
5. **FE**: `unwrapAttributesIndexed()` w `apps/admin/src/lib/attributes-indexed.ts` rozszerzony o kanon: podnosi `option_code` / `option_codes` / `{amount,currency}` / `{value,unit}` / `asset_id` / `object_id` zgodnie z tym, co realnie wkłada `AttributesIndexedRebuilder`. Audyt konsumentów (listy, karta produktu, variants tab) — wartości selectów renderują się po migracji identycznie jak przed.
6. **Audyt read-paths BE**: po migracji `DocumentFlattener` i `VisibleWhenRuleEvaluator` mogą czytać wyłącznie kanon — wycofać wzorzec defensywny TAM, GDZIE migracja gwarantuje kanon (zostawić komentarz-odnośnik do ADR-0019); jeśli wycofanie ryzykowne, świadomie zostawić z komentarzem „tolerancja legacy do końca okna przejściowego D7" — decyzję opisać w PR.
7. **`ValueSerializer`**: fallback `AttributeType::Select => $payload['option_code'] ?? $payload['value']` (wzorzec #1271) na okres przejściowy — chroni środowiska, na których migracja jeszcze nie poszła, przed pustymi eksportami.
8. **`docs/api/jsonb-schemas.md`**: oznaczenie legacy-wariantów jako zmigrowane + data; sekcja zgodna ze stanem po migracji.
9. **Dump dev DB PRZED migracją** (krok 1 smoke testu) — to jest część zakresu, nie opcja.

## Poza zakresem tego ticketu
- Wspólny rdzeń walidacji/normalizacji i `ImportValueWriter` → IMP2-1.4 (ten ticket naprawia TYLKO istniejące writery: Upserter + GenerateVariants).
- Tryby importu i `ObjectResolver` → IMP2-1.3.
- Golden test konsumujący seedowane legacy shape'y → IMP2-1.5.
- Zmiany gramatyki kolumn eksportu (`code.locale.channel`) → IMP2-1.6 (fala B).
- Usunięcie fallbacku `{value}` z `ValueSerializer` (koniec okresu przejściowego) → osobny mini-ticket po etapie 1 (odnotować w IMP2-1.11).

## Kryteria akceptacji
- [ ] PHPUnit: test jednostkowy `wrapValue` per typ (min. select/multiselect/price/metric/asset/relation + scalar) — wartość skalarna z admina normalizuje się do kanonu.
- [ ] ApiTestCase: `PATCH` produktu z wartością selecta (goły kod opcji) → w bazie `object_values.value = {"option_code": ...}`; nieistniejący kod opcji → 422 (walidacja #1261 już nie jest omijana).
- [ ] ApiTestCase: eksport produktu z selectem zapisanym przez admina → komórka NIEPUSTA (bug żywy dziś — to jest kluczowa asercja ticketu).
- [ ] Test migracji: surowy INSERT legacy `{"value":"red"}` (select) → po `doctrine:migrations:migrate` wiersz ma `{"option_code":"red"}`; migracja idempotentna (drugie uruchomienie = 0 zmian).
- [ ] Generowanie wariantów stampuje osie w kanonie (test na `GenerateVariantsController`).
- [ ] FE: karta produktu i lista renderują wartości selectów po migracji (Playwright: produkt z selectem → wartość widoczna na liście i w formularzu).
- [ ] Na dev DB po migracji zapytanie diagnostyczne `SELECT count(*) ... WHERE a.type='select' AND ov.value ? 'value'` zwraca 0.
- [ ] Dump dev DB wykonany przed migracją, ścieżka pliku odnotowana w komentarzu issue.

## Jak zwalidować (smoke test po wykonaniu)
1. **Dump (PRZED migracją)**: `docker compose exec -T database pg_dump -U app -d app -Fc > backups/pre-imp2-1-2-$(date +%Y%m%d).dump` → plik > 0 B.
2. Diagnoza przed: `docker compose exec database psql -U app -d app -c "SELECT a.type, count(*) FROM object_values ov JOIN attributes a ON a.id=ov.attribute_id WHERE ov.value ? 'value' AND a.type IN ('select','multiselect','price') GROUP BY 1;"` → zanotuj liczby.
3. `docker compose exec api bin/console doctrine:migrations:migrate -n` → OK; powtórz zapytanie z kroku 2 → 0 wierszy dla selecta.
4. Rebuild + reindex: komenda rebuildu z zakresu pkt 4 + `docker compose exec api bin/console pim:search:reindex` → exit 0. Potem `docker compose restart api` (lekcja: FrankenPHP worker po cache:clear).
5. UI: `https://pim.localhost` (admin@demo.localhost / changeme) → otwórz produkt z atrybutem select (np. z demo seedu) → wartość widoczna w formularzu i na liście; DevTools Console bez czerwonych błędów.
6. W formularzu zmień wartość selecta → zapisz → Network: PATCH 200; `psql`: wiersz ma `option_code`.
7. Eksport: Eksporty → nowy eksport CSV z kolumną tego selecta → pobierz plik → komórka selecta NIEPUSTA (przed ticketem była pusta).
8. Wygeneruj warianty z osią select → `psql`: wiersze osi mają `{option_code}`.

## Zależności
Blokowany przez: IMP2-1.1 (kanon shape'ów w ADR-0019 musi być zamrożony).
Blokuje: IMP2-1.4, IMP2-1.5; odblokowuje też przyszłe UI `clear_if_empty` (D2: „do UI dopiero po domknięciu migracji D7").

## Referencje
- `Project Plan/UI/feature-imports-v2.md` §2.3 (diagnoza), §4.3 (fallback ValueSerializer), §4.4 D7, §5 wiersz 1.2, §9 pkt 4 (dump przed migracją).
- Issues: #1261 (walidacja opcji), #1271 (fallback price — wzorzec), #1130 (round-trip), #511 (historyczny bug envelope).
- Pliki: `ObjectAttributesUpserter.php`, `ValueSerializer.php`, `GenerateVariantsController.php`, `attributes-indexed.ts`, `DocumentFlattener.php`, `VisibleWhenRuleEvaluator.php`, `AttributesIndexedRebuilder.php`, `docs/api/jsonb-schemas.md`.

## Definition of Done
- PHPStan max zielony (`bin/console cache:warmup --env=dev` przed `composer phpstan`; `--memory-limit=1G` przy pełnym runie), php-cs-fixer przed commitem (husky pre-commit).
- PHPUnit ≥80% nowej logiki; przed testami Api/*: `bin/console cache:clear --env=test` (lekcja: Foundry ResetDatabase vs dev DB).
- ApiTestCase dla zmienionych zachowań endpointów (PATCH produktu, eksport) — patrz kryteria.
- Playwright dla widocznej zmiany UI (render selecta po migracji); admin typecheck z `NODE_OPTIONS=--max-old-space-size=4096`.
- Brak nowych endpointów → diff `docs/api-spec/v0.json` tylko jeśli serializacja się zmieniła (scope'ować diff do swojej zmiany — lekcja integer/number drift).
- Manual smoke wg sekcji „Jak zwalidować" z artefaktem dowodu w komentarzu zamykającym: output zapytań SQL przed/po + screenshot niepustej komórki selecta w wyeksportowanym pliku (CLOSED MEANS CLOSED).

---

## IMP2-1.3 — feat(import): ObjectResolver + realne tryby create/update/upsert (#1465)

**Labels:** backend, frontend, enhancement, epik-IMP2 · **Estymata:** 10–14 h · **Zależy od:** IMP2-1.1

## Po co to robimy (kontekst nietechniczny)
Dziś import umie tylko TWORZYĆ nowe produkty — a jednocześnie blokuje każdy wiersz, którego SKU już istnieje w bazie. Efekt: podstawowy scenariusz pracy operatora („wyeksportuj katalog, popraw ceny w Excelu, wgraj z powrotem") kończy się odrzuceniem 100% wierszy i zerem zmian. Po tym tickecie import ma trzy uczciwe tryby: **utwórz** (tylko nowe), **aktualizuj** (tylko istniejące), **utwórz-lub-aktualizuj** (domyślny) — a dopasowanie wiersza do istniejącego produktu działa po SKU albo po wskazanym identyfikatorze (np. EAN, jak w plikach Bosch operatora). UI przestaje kłamać: lista sesji pokazuje prawdziwy tryb zamiast przybitego na sztywno „UPDATE".

## Stan obecny
- **Silnik create-only**: `apps/api/src/Import/Application/Service/ImportObjectCreator.php` linia 63 — zawsze `new CatalogObject($objectType, $sku)`; żadna ścieżka kodu nie czyta trybu.
- **Enum dekoracyjny**: `apps/api/src/Import/Domain/Enum/ImportMode.php` ma 6 case'ów (ADD/UPDATE/UPSERT/MERGE/INCREMENT/DELETE), docblock przeczy kodowi. `apps/api/src/Import/Domain/Entity/ImportProfile.php` linia 155: `getMode()` robi `ImportMode::from($this->mode)` — **redukcja enuma bez migracji danych = ValueError przy hydracji każdego profilu z usuniętą wartością**. Kolumna `mode VARCHAR(16) DEFAULT 'UPDATE'` dodana w `apps/api/migrations/Version20260512000000.php`. Inne punkty hydracji: `apps/api/src/Import/Infrastructure/ApiPlatform/State/ImportProfileProcessor.php` (linie 92, 143 — `ImportMode::from`), `apps/api/src/Import/Presentation/Controller/ImportImportProfileController.php` (linia 99 — `tryFrom`).
- **Hardcode w UI-API**: `apps/api/src/Import/Presentation/Controller/ListImportSessionsController.php` linia 142: `'mode' => 'UPDATE'` przybite na sztywno. Encja `apps/api/src/Import/Domain/Entity/ImportSession.php` **nie ma pola mode** w ogóle.
- **Blokada re-importu**: `apps/api/src/Import/Domain/ValueObject/ValidationError.php` linie 33–36: `isRowBlocking()` zwraca true dla WSZYSTKIEGO poza `CategoryNotFound` → `DuplicateSkuInDb` (emitowany w `apps/api/src/Import/Application/Service/ImportValidationService.php` ~linia 217) blokuje wiersz. Re-import własnego eksportu do niepustego katalogu = 0 zmian.
- **Marker sesji**: `ImportObjectCreator` linia 64 — `assignImportSession()` wołane przy create (poprawnie); kontrakt „tylko created-by" (D11) nie jest nigdzie spisany ani testowany — rollback-delete sesji upsert skasowałby istniejący katalog klienta, gdyby update path kiedykolwiek stampował.
- **API/wizard**: `apps/api/src/Import/Presentation/Controller/StartImportController.php` — multipart bez pola `mode`; FE `apps/admin/src/features/imports/wizard/StepConfirm.tsx` (komponent `StepConfirmPlaceholder`) buduje FormData bez trybu. Primitive `apps/admin/src/features/imports/primitives/ModeBadge.tsx` istnieje i renderuje tryby.

## Zakres prac
1. **Redukcja enuma + migracja danych (D3)**: `ImportMode` = `Create='CREATE'`, `Update='UPDATE'`, `Upsert='UPSERT'`; **default `Upsert`** (zmiana defaultu w `ImportProfile` konstruktor/pole, `ImportProfileProcessor` linia 92). Migracja danych PRZED zmianą enuma w tym samym PR: `UPDATE import_profiles SET mode='CREATE' WHERE mode='ADD'; UPDATE import_profiles SET mode='UPSERT' WHERE mode IN ('MERGE','INCREMENT','DELETE');` (mapa: ADD→CREATE — najbliższa semantyka „tylko nowe"; MERGE/INCREMENT/DELETE→UPSERT — tryby nigdy nie działały, default jest bezpieczny; mapę udokumentować w docbloku migracji). Default kolumny `mode` zmienić na `'UPSERT'` + `<option name="default">` w mapowaniu ORM (lekcja: NOT NULL/default w ORM XML, bo test DB budowany z metadanych).
2. **`ImportSession.mode`**: nowe pole `mode VARCHAR(16) NOT NULL DEFAULT 'UPSERT'` (migracja + ORM default), settery/gettery; sesja zapisuje tryb wybrany na starcie (profil = tylko prefill). `ListImportSessionsController` linia 142 → `$session->getMode()->value` (analogicznie w serializacji `ImportShowPage`-endpointu, jeśli osobny kontroler).
3. **Klucz dopasowania per profil (D1)**: nowa kolumna `import_profiles.match_attribute_code VARCHAR NULL` (NULL = match po `objects.code`/SKU; wartość = code atrybutu typu `identifier`, np. `ean`) + walidacja przy zapisie profilu (atrybut istnieje i jest typu identifier) + pole w `ImportSession` (kopiowane na starcie, żeby sesja była samoopisująca). Ekspozycja w API profili (`ImportProfileProcessor` + DTO).
4. **`ObjectResolver`** (`apps/api/src/Import/Application/Service/ObjectResolver.php`): wejście: lista kluczy z chunka + tenant + ObjectType + konfiguracja klucza; wyjście: mapa `key → CatalogObject|null`. Match po `objects.code` (jedno zapytanie `WHERE code IN (...)`, tenant-scoped) lub po wartości atrybutu identifier (zapytanie po `object_values` z `value->>'value' IN (...)`). **Case-sensitive + trim** na wartości klucza (trim przy odczycie komórki, porównanie bez lower()). Decyzja per wiersz i tryb: `create` → istniejący = skip + warning; `update` → brak = skip + warning; `upsert` → odpowiednio create/update.
5. **Update path w `ImportRunHandler`/`ImportObjectCreator`**: dla istniejącego obiektu wartości zapisywane na NIM (lookup istniejącego `ObjectValue` per attribute+locale przez `findOneByScope` i `updateValue()` — **świadomie N+1 w tym tickecie**; prefetch per chunk i pełna walidacja wartości przejmuje `ImportValueWriter` w IMP2-1.4 — interfejs wywołania zaprojektować tak, żeby 1.4 podmienił implementację bez ruszania resolvera). Pusta komórka / niezmapowana kolumna = NIE RUSZAJ istniejącej wartości (D2 — `ImportObjectCreator` już skipuje `null/''`, zachować na update path). Kategoria: na update NIE nadpisujemy przypisań (multi-kategorie → IMP2-1.7).
6. **Pre-flight dedup w pliku (D1)**: duplikat klucza w pliku = pierwsze wystąpienie przetwarzane, kolejne SKIP + warning (dziś `DuplicateSkuInFile` blokuje) — nowy typ błędu/poziom w `ImportErrorType`/`ValidationError`; `DuplicateSkuInDb` przestaje być błędem blokującym (staje się informacją dla resolvera; w trybie `create` → skip + warning, nie fail sesji).
7. **Liczniki kubełków**: `ImportSession` dostaje `created_count`, `updated_count`, `skipped_count` (migracja + ORM default 0; `success_count`/`error_count` zostają dla kompatybilności) + inkrementacja w handlerze + serializacja w list/show. To karmi kubełek „Aktualizacje" z NUI-10 i asercje golden testu.
8. **Kontrakt D11 — `import_session_id` = created-by only**: `assignImportSession` wołane WYŁĄCZNIE na ścieżce create; test jednostkowy/integracyjny: upsert istniejącego obiektu NIE zmienia jego `import_session_id`.
9. **API**: `StartImportController` przyjmuje multipart `mode` (walidacja: jeden z CREATE/UPDATE/UPSERT; default: tryb profilu, a bez profilu UPSERT) i zapisuje na sesji.
10. **FE minimalnie**: w `StepConfirm.tsx` select trybu (3 opcje, default Upsert, stringi przez `t()`, klucze np. `imports.confirm.mode.upsert`) + dołączenie `mode` do FormData; `ModeBadge` na liście sesji czyta realny tryb z API. Pełny wizard v2 → etap 3 (IMP2-3.7).

## Poza zakresem tego ticketu
- Walidacja i normalizacja WARTOŚCI per typ + prefetch per chunk → IMP2-1.4 (`ImportValueWriter`); tu update path może być N+1 — to świadome.
- `clear_if_empty` (czyszczenie pustą komórką) → po D7, etap 3 (UI) — tu pusta komórka ZAWSZE „nie ruszaj".
- Multi-kategorie, status/enabled z kolumn → IMP2-1.7 (fala B); warianty/relacje → IMP2-1.8.
- Kubełki dry-run („utworzy N / zaktualizuje M" przed importem) → IMP2-3.6; ten ticket liczy kubełki PO imporcie.
- Rollback z undo-logiem dla updated → etap 2 (IMP2-2.4). Uwaga przejściowa: istniejący rollback (delete po `import_session_id`) pozostaje poprawny właśnie dzięki kontraktowi created-by-only.
- Gramatyka `code.locale.channel` → IMP2-1.6 (fala B).

## Kryteria akceptacji
- [ ] Migracja danych: profil z `mode='MERGE'` w bazie po migracji ma `UPSERT`; `GET /api/import-profiles` nie rzuca ValueError (test integracyjny z surowym INSERT-em legacy wartości).
- [ ] Tryb `upsert` (default): import pliku z 1 istniejącym SKU (zmieniona wartość) + 1 nowym SKU → `created_count=1`, `updated_count=1`, `error_count=0`; wartość istniejącego obiektu zaktualizowana, pozostałe wartości nietknięte.
- [ ] Tryb `create`: wiersz z istniejącym SKU → skip + warning (sesja `completed`, nie `failed`); `skipped_count=1`.
- [ ] Tryb `update`: wiersz z nieistniejącym SKU → skip + warning; nic nie utworzono.
- [ ] Match po identifier: profil z `match_attribute_code='ean'` → wiersz z EAN istniejącego produktu aktualizuje TEN produkt mimo innego/braku SKU w pliku (scenariusz `bosch-09-01-2026-param.csv`).
- [ ] Duplikat klucza w pliku: pierwszy wiersz przetworzony, drugi skip + warning (nie błąd blokujący sesji).
- [ ] Pusta komórka w zmapowanej kolumnie przy update NIE czyści istniejącej wartości (test).
- [ ] `import_session_id` obiektu aktualizowanego upsert-em pozostaje niezmieniony (test kontraktu D11).
- [ ] `GET /api/import-sessions` zwraca realny `mode` sesji (koniec hardcode 'UPDATE').
- [ ] FE: select trybu w kroku potwierdzenia wizarda; wybrany tryb widoczny po imporcie na liście sesji (`ModeBadge`); Playwright pokrywa wybór trybu.

## Jak zwalidować (smoke test po wykonaniu)
1. `docker compose exec api bin/console doctrine:migrations:migrate -n` → OK; `docker compose exec database psql -U app -d app -c "SELECT DISTINCT mode FROM import_profiles;"` → tylko CREATE/UPDATE/UPSERT.
2. UI `https://pim.localhost` (admin@demo.localhost / changeme): Eksporty → wyeksportuj kilka produktów do CSV.
3. W pliku zmień wartość jednej komórki istniejącego produktu i dopisz wiersz z nowym SKU; zapisz.
4. Importy → nowy import → wgraj plik, zmapuj kolumny, w kroku potwierdzenia wybierz tryb **Upsert** → uruchom. Network: `POST /api/import-sessions` zawiera `mode=UPSERT`, response 200/202.
5. Widok sesji: liczniki utworzone=1 / zaktualizowane=1 / błędy=0. Otwórz zaktualizowany produkt → nowa wartość widoczna; Console bez czerwonych błędów.
6. Powtórz import tego samego pliku w trybie **Create** → wszystkie wiersze skip + warning, zero nowych obiektów (sprawdź licznik produktów przed/po).
7. Benchmark operatora: `Zrodla/Importy przykładowe/bosch-09-01-2026-nazwy.csv` (2 kolumny: EAN + nazwa) — profil z match po `ean` (atrybut identifier musi istnieć na OT; jeśli brak — utwórz w Modelowaniu), tryb Update → mapping ręczny w wizardzie → sesja completed, `updated_count>0` dla wcześniej zaimportowanych produktów Bosch (pełne trio Bosch = gate etapu 1, tu wystarczy plik „nazwy").
8. `psql`: `SELECT import_session_id FROM objects WHERE code='<SKU aktualizowanego>'` → wartość sprzed importu (created-by only).

## Zależności
Blokowany przez: IMP2-1.1 (D1/D3/D11 zamrożone w ADR-0019).
Blokuje: IMP2-1.5 (golden test importuje w trybie upsert). Koordynacja: IMP2-1.4 podmienia zapis wartości na `ImportValueWriter`; IMP2-1.6a zmienia w `StartImportController` próg sync na wiersze — oba dotykają tych samych plików, mergować sekwencyjnie.

## Referencje
- `Project Plan/UI/feature-imports-v2.md` §2.1 (diagnoza create-only), §4.2 (ObjectResolver), §4.4 D1/D3/D11, §5 wiersz 1.3, §6 (trio Bosch).
- Issues: #498 (VIEW-IMP-02 — pochodzenie enuma), #1130 (round-trip), #1429 (kubełek „Aktualizacje" w NUI-10).
- Pliki: `ImportMode.php`, `ImportProfile.php`, `ImportSession.php`, `ImportObjectCreator.php`, `ImportRunHandler.php`, `ImportValidationService.php`, `ValidationError.php`, `ListImportSessionsController.php`, `StartImportController.php`, `ImportProfileProcessor.php`, `StepConfirm.tsx`, `ModeBadge.tsx`, migracja `Version20260512000000.php`.

## Definition of Done
- PHPStan max zielony (`cache:warmup --env=dev` przed `composer phpstan`, `--memory-limit=1G`), php-cs-fixer przed commitem.
- PHPUnit ≥80% nowej logiki (ObjectResolver, decyzje per tryb, dedup, kontrakt D11); `cache:clear --env=test` przed Api/*.
- ApiTestCase dla zmian endpointów: `POST /api/import-sessions` z `mode`, `GET /api/import-sessions` (realny mode), profile z `match_attribute_code`.
- Regeneracja `docs/api-spec/v0.json` (`cache:warmup` + `api:openapi:export`) — diff scope'owany do zmian importu; regeneracja shared-types jeśli dotknięte.
- Playwright dla widocznej zmiany UI (select trybu + ModeBadge); `NODE_OPTIONS=--max-old-space-size=4096` dla typecheck admin; nowe stringi przez `t()` z kluczami pl/en (po edycji locale: `docker compose restart admin`).
- Manual smoke wg sekcji „Jak zwalidować" z artefaktem dowodu w komentarzu zamykającym: JSON sesji z licznikami created/updated + screenshot listy sesji z realnym trybem (CLOSED MEANS CLOSED).

---

## IMP2-1.4 — refactor(catalog,import): wspólny rdzeń walidacji+normalizacji wartości + ImportValueWriter (#1466)

**Labels:** backend, refactor, epik-IMP2 · **Estymata:** 18–24 h · **Zależy od:** IMP2-1.1, IMP2-1.2

## Po co to robimy (kontekst nietechniczny)
W PIM są dziś dwie niezależne „bramy" zapisu wartości produktów: admin/API przechodzi przez pełną walidację (format e-maila, istnienie opcji selecta, unikalność identyfikatorów, poprawne przypisanie języka), a import buduje wartości sam i wkłada je do bazy z pominięciem tych wszystkich kontroli. Skutek: import potrafi zapisać nieistniejący kod opcji albo „wiszący" link do zasobu, a operator odkrywa to dopiero, gdy edycja produktu w adminie zwraca błąd — produkt wygląda na „zepsuty". Ten ticket wydziela jedną wspólną maszynerię walidacji i normalizacji, z której korzystają OBA wejścia. Różnica zostaje tylko w reakcji na błąd: admin odrzuca pojedynczy zapis komunikatem HTTP, import zbiera błędy do raportu i jedzie dalej — bez wywracania całej sesji.

## Stan obecny
- **`apps/api/src/Catalog/Application/ObjectAttributesUpserter.php`** (258 linii) — jedyne miejsce z pełną logiką zapisu: wrap/normalizacja envelope (po IMP2-1.2 Attribute-aware), required per `Attribute::isRequired` z wyjątkiem Boolean (#1350, linie 120–132), walidacja per-typ przez `AttributeValueValidator` dla `VALUE_VALIDATED_TYPES` (#1216/#1261, linie 56–64 i 138–148), pre-check 409 identifiera przez `IdentifierUniquenessValidator` (#1179, linie 154–164), routing primary-locale→global (#1148, linia 103: wartość dla primary locale ląduje na wierszu global) i channel scope (#1154, linia 116), zapis przez `findOneByScope` + `updateValue`/`changeProvenance`. **Rzuca HTTP-exceptions** (422/409) — nieużywalne w pętli batch importu.
- Walidatory: `apps/api/src/Catalog/Application/Validation/AttributeValueValidator.php` (+ `AttributeValueValidatorInterface`, katalog `TypeValidator/` z per-typ implementacjami), `apps/api/src/Catalog/Domain/Validator/IdentifierUniquenessValidator.php`.
- **Pułapka repo**: `apps/api/src/Catalog/Infrastructure/Doctrine/Repository/DoctrineObjectValueRepository.php` linie 85–89 — `save()` robi `persist` + **`flush()` per wartość**. Upserter woła to per atrybut (OK per-request); w imporcie 50k wierszy × N atrybutów = katastrofa. **Writer importu NIE może używać `save()`** — tylko `persist`, flush w chunku.
- **Import omija wszystko**: `apps/api/src/Import/Application/Service/ImportObjectCreator.php` — `buildValuePayload()` (linie 115–130) buduje envelope sam; `asset_id`/`object_id` zapisywane raw bez walidacji istnienia; `option_code`/`option_codes` bez sprawdzenia w `AttributeOption` (pozostałość IMP-17, #599/#603); brak pre-checku identifiera (duplikat wybucha dopiero na triggerze DB → `markFailed` całej sesji w środku batcha); brak reguły primary-locale→global (wartość `name.pl` przy primary locale `pl` ląduje w wierszu `locale='pl'` → niewidoczna w `attributes_indexed`, listach i Meilisearch).
- **Required hardcoded**: `apps/api/src/Import/Application/Service/ImportValidationService.php` linia 39: `REQUIRED_ATTRIBUTE_CODES = ['sku', 'name']` — custom OT bez atrybutu `name` nie przejdzie importu; prawdziwe required to `Attribute::isRequired` (globalne; `ObjectTypeAttribute` ma TYLKO `requiredForCompleteness` — nie mylić).
- **N+1 w trybie update**: po IMP2-1.3 update path robi `findOneByScope` per wartość — bez prefetchu istniejących `ObjectValue` per chunk tryb update nie przejdzie benchmarku.
- Batch infra: `apps/api/src/Shared/Application/AbstractBatchHandler.php` (`flushAndClear()`, `shouldFlush()`); `ImportRunHandler` już dziedziczy i flushuje co 200 wierszy.
- Wzorzec kontraktu cross-BC: `apps/api/src/Catalog/Contracts/Service/` (np. `AttributeCatalogReader.php`); Deptrac z IMP2-1.1 wymusza, żeby Import zależał TYLKO od `Catalog_Contracts` + `Shared`.

## Zakres prac
1. **Kontrakt w `Catalog\Contracts`**: interfejs (nazwa robocza `ValueWriteCoreInterface` w `App\Catalog\Contracts\Service\`) + DTO wyników: `ValueWriteResult` (zapisane/odrzucone per atrybut) i `ValueWriteIssue` (attributeCode, severity error/warning, message, machine-readable code błędu — mapowalny na `ImportErrorType`). Wejście: obiekt + payload `{code => raw}` + provenance + locale + channelId + opcje (np. skipIdentifierPrecheck=false).
2. **Implementacja `App\Catalog\Application\ValueWriteCore`** — wyekstrahowana z Upsertera logika: Attribute-aware normalizacja envelope (z IMP2-1.2), `isEmptyEnvelope`/required per `Attribute::isRequired` (Boolean exempt — zachować semantykę #1350), `AttributeValueValidator` per typ (w tym select/multiselect membership — to zamyka pozostałość IMP-17 dla ścieżki importu), `IdentifierUniquenessValidator` pre-check (jako Issue, nie exception), primary-locale→global, channel routing, upsert na istniejącym `ObjectValue` (podany z zewnątrz lub lookup) — **`persist` bez `flush`**, bez HTTP-exceptions, zwraca rezultaty.
3. **`ObjectAttributesUpserter` = cienki klient per-request**: woła rdzeń, mapuje Issues na dotychczasowe `UnprocessableEntityHttpException`/`ConflictHttpException` z TYMI SAMYMI komunikatami (regresja zero — istniejące ApiTestCase'y Upsertera muszą przejść bez modyfikacji asercji; flush nadal przez wywołujących/`save()` jak dziś — zachowanie per-request bez zmian). Konsumenci bez zmian interfejsu: `CreateCatalogObjectHandler`, `UpdateCatalogObjectHandler`, `BulkSetAttributeHandler`, `BackfillRequiredAttributesCommand`.
4. **`ImportValueWriter`** (`App\Import\Application\Service\ImportValueWriter`): batch-klient rdzenia — result-based (zbiera Issues do `ImportLog`-ów zamiast rzucać), persist-only, flush wyłącznie w chunku przez `AbstractBatchHandler` w `ImportRunHandler`. **Prefetch per chunk** (przed pętlą wierszy chunka, po `clear()`): (a) `AttributeOption` dla zmapowanych atrybutów select/multiselect, (b) kategorie po kodach z chunka, (c) SKU/identifiers (współpraca z `ObjectResolver` z 1.3), (d) **ISTNIEJĄCE `ObjectValue` dla par obiekt×atrybut z chunka — jedno zapytanie `WHERE object_id IN (...) AND attribute_id IN (...)`, indeksowane w pamięci po (object, attribute, locale, channel)** — rdzeń dostaje istniejący ObjectValue z mapy zamiast robić `findOneByScope` per wartość (eliminacja N+1 z 1.3). Identifier pre-check też z prefetchu + dedup w obrębie chunka.
5. **`ImportObjectCreator` odchudzony**: tworzy `CatalogObject` + przypisanie kategorii; CAŁY zapis wartości (create i update path) idzie przez `ImportValueWriter` — `buildValuePayload()`/`CompositeValueParser` wchłonięte do normalizacji rdzenia lub zostawione jako parser stringów CSV→raw (decyzja implementacyjna, opisać w PR; parsowanie `"20.99 EUR"`→`{amount,currency}` musi pozostać zgodne z eksporterem).
6. **Required z modelu**: `ImportValidationService` — `REQUIRED_ATTRIBUTE_CODES=['sku','name']` zastąpione przez `Attribute::isRequired` atrybutów zmapowanych kolumn + obecność klucza dopasowania (SKU/identifier zawsze wymagany jako klucz); custom OT bez `name` przechodzi import (test).
7. **Deptrac**: zdjęcie z baseline'u `skip_violations` (IMP2-1.1) wpisów, które ten refactor czyni zbędnymi (Import → Catalog Internals); `composer deptrac` zielony z mniejszym baseline'em.
8. **Wydajność**: smoke-run `pim:benchmark:bulk-import` (istniejąca komenda) przed/po — liczby do PR; pełny benchmark z asercją RAM → IMP2-2.6.

## Poza zakresem tego ticketu
- Tryby/resolver → IMP2-1.3 (ten ticket konsumuje jego decyzje create/update per wiersz).
- Izolacja błędu flusha / degradacja per-row / maszyna stanów severity → IMP2-1.9 (fala B); tu wystarczy, że błędy WALIDACJI nie wywracają sesji.
- Streaming readers / staged upload → etap 2 (IMP2-2.1/2.2).
- BulkContext ON + async rebuild `attributes_indexed` + Mercure per chunk → IMP2-2.6.
- Pass 2 relacji/wariantów (resolve `object_id` po code, `ObjectRelation`) → IMP2-1.8 — w tym tickecie relation/reference/asset walidowane co najwyżej na poziomie istnienia w tenancie (jeśli prefetch tani), inaczej zapis raw jak dziś z TODO w kodzie do 1.8.
- Per-OT required (zmiana modelu Catalog) — świadomie poza v2 (plan §7).

## Kryteria akceptacji
- [ ] Interfejs + DTO w `App\Catalog\Contracts\Service\`, implementacja w `App\Catalog\Application\` — `composer deptrac` zielony, baseline pomniejszony (diff deptrac.yaml w PR).
- [ ] WSZYSTKIE istniejące testy `ObjectAttributesUpserter`/endpointów PATCH produktu przechodzą bez zmiany asercji (regresja zero: te same kody 422/409 i komunikaty).
- [ ] Import wiersza z nieistniejącym `option_code` (select) → wiersz odrzucony z błędem w `import_logs`, sesja `completed` z `error_count=1` — NIE `failed` (dziś taki wpis ląduje w bazie bez walidacji).
- [ ] Import wiersza z nieistniejącym kodem opcji multiselect → jak wyżej (zamyka lukę IMP-17 dla importu).
- [ ] Import z duplikatem wartości identifiera vs DB → błąd wiersza w raporcie, sesja NIE markFailed (dziś: wybuch na triggerze DB w środku batcha).
- [ ] Wartość `name.pl` przy primary locale `pl` ląduje na wierszu global (`locale IS NULL`) i jest widoczna w `attributes_indexed` po imporcie (test integracyjny — dziś pada).
- [ ] Custom ObjectType BEZ atrybutu `name` importuje się poprawnie (required z modelu, nie hardcode).
- [ ] Test wydajnościowy/licznikowy: import chunka w trybie update wykonuje LIMITOWANĄ liczbę zapytań SQL (asercja licznika zapytań przez DBAL middleware/logger w teście — brak `findOneByScope` per wartość); `ImportValueWriter` nigdzie nie woła `DoctrineObjectValueRepository::save()` (guard: grep w code review + test architektoniczny PHPStan/Deptrac jeśli prosty).
- [ ] Flush wyłącznie na granicy chunka (`AbstractBatchHandler::flushAndClear`) — bez flush per wartość/wiersz.
- [ ] PHPUnit rdzenia: normalizacja+walidacja per typ (matryca 17 typów × poprawna/błędna wartość), routing locale/channel, required.

## Jak zwalidować (smoke test po wykonaniu)
1. `docker compose exec api sh -c "bin/console cache:clear --env=test && vendor/bin/phpunit --testsuite default --filter 'ValueWriteCore|ObjectAttributesUpserter|ImportValueWriter'"` → zielono.
2. UI `https://pim.localhost` (admin@demo.localhost / changeme): edytuj produkt w adminie — zapis selecta z poprawną opcją → 200; ręcznie (DevTools → edit and resend / curl z Bearer) wyślij PATCH z nieistniejącym `option_code` → 422 z tym samym komunikatem co przed refactorem.
3. Przygotuj CSV (3 wiersze): poprawny; z nieistniejącym kodem opcji selecta; z duplikatem EAN istniejącego produktu. Import przez wizard (tryb upsert) → sesja `completed`, liczniki: 1 sukces, 2 błędy; raport/`import_logs` zawiera oba błędy z numerami wierszy i nazwą kolumny.
4. `psql`: `SELECT count(*) FROM object_values ov JOIN attributes a ON a.id=ov.attribute_id WHERE a.type='select' AND NOT ov.value ? 'option_code';` → 0 nowych legacy wierszy po imporcie.
5. Import pliku z kolumną `name.pl` (primary locale pl) → produkt widoczny z nazwą na liście produktów i w wyszukiwarce (Meilisearch) — dowód reguły primary-locale→global.
6. `docker compose exec api bin/console pim:benchmark:bulk-import` → wynik porównywalny lub lepszy niż przed zmianą (liczby do komentarza zamykającego).

## Zależności
Blokowany przez: IMP2-1.1 (kontrakt + warstwy Deptrac), IMP2-1.2 (Attribute-aware normalizacja to fundament rdzenia).
Blokuje: IMP2-1.5. Koordynacja z IMP2-1.3: oba dotykają `ImportRunHandler`/`ImportObjectCreator` — mergować sekwencyjnie (1.3 → 1.4 lub odwrotnie, rebase drugiego).

## Referencje
- `Project Plan/UI/feature-imports-v2.md` §2.2 (diagnoza), §3 filar 2 (jeden writer), §4.2 (ValueWriteCore + ImportValueWriter), §5 wiersz 1.4.
- Issues: #1216, #1261 (walidacja per-typ), #1179 (identifier 409), #1148 (primary-locale→global), #1154 (channel scope), #1350 (required/Boolean), #599/#603 (IMP-17 multiselect — domknięcie dla importu).
- Pliki: `ObjectAttributesUpserter.php`, `AttributeValueValidator.php` (+ `TypeValidator/`), `IdentifierUniquenessValidator.php`, `DoctrineObjectValueRepository.php` (pułapka save), `ImportObjectCreator.php`, `ImportValidationService.php`, `ImportRunHandler.php`, `AbstractBatchHandler.php`, `apps/api/src/Catalog/Contracts/Service/` (wzorzec), `apps/api/deptrac.yaml`.

## Definition of Done
- PHPStan max zielony (`cache:warmup --env=dev` przed `composer phpstan`, `--memory-limit=1G`; pamiętaj o custom rule flush-bez-clear), php-cs-fixer przed commitem, `composer deptrac` zielony.
- PHPUnit ≥80% nowej logiki (rdzeń + writer); `cache:clear --env=test` przed Api/*; testy integracyjne na realnym Postgresie (no mocking).
- ApiTestCase: regresja endpointów Upsertera + nowe scenariusze importu z kryteriów.
- Bez zmian kontraktu API (refactor wewnętrzny) → `docs/api-spec/v0.json` bez diffu (zweryfikować); bez zmian UI → bez Playwright (chyba że raport błędów w UI się zmienił — wtedy dopisać).
- Manual smoke wg sekcji „Jak zwalidować" z artefaktem dowodu w komentarzu zamykającym: JSON sesji z błędami per wiersz + output zapytania z kroku 4 + liczby benchmarku (CLOSED MEANS CLOSED).

---

## IMP2-1.5 — test(import): golden round-trip test v0 (eksport → import → równość envelope) (#1467)

**Labels:** testing, backend, epik-IMP2 · **Estymata:** 4–6 h · **Zależy od:** IMP2-1.3, IMP2-1.4

## Po co to robimy (kontekst nietechniczny)
Najważniejsza obietnica całego epiku brzmi: „plik wyeksportowany z PIM musi się dać zaimportować z powrotem — i nic się przy tym nie zgubi ani nie zniekształci". Dziś tej obietnicy nie pilnuje ŻADEN test: istniejący test „round-trip" nie uruchamia eksportera, nie zapisuje nic do bazy i sprawdza jeden ręcznie napisany wiersz. Ten ticket buduje prawdziwego strażnika kontraktu: test, który realnie eksportuje katalog do CSV, realnie importuje ten plik z zapisem do bazy i porównuje wartości przed/po — dla wszystkich 17 typów atrybutów. Od tego momentu każda zmiana formatu eksportu albo silnika importu, która psuje round-trip, wywala CI — zanim zauważy ją operator.

## Stan obecny
- **`apps/api/tests/Api/Import/ImportRoundTripApiTest.php`** (111 linii, #1130) — DO ZASTĄPIENIA: woła `POST /api/import-sessions/validate-dry-run` na ręcznie sklejonym 1-wierszowym CSV (`writeRoundTripCsv()`), **nie uruchamia eksportera**, **bez persystencji**, **bez porównania wartości** — sprawdza tylko `error_count=0` dry-runu. Pokrywa 4 atrybuty (text/price/metric), z 17 typów persystencję ma przetestowaną tylko Text.
- Realny eksporter sync: `apps/api/src/Export/Application/Sync/SyncExportRunner::runToFile(ExportSession $session, string $targetPath, ?callable $onChunk): int` — pełny pipeline builder→writer, używany przez ścieżkę <100 wierszy.
- 17 typów: enum `apps/api/src/Catalog/Domain/AttributeType.php` (text, number, select, multiselect, date, boolean, asset, relation, price, metric, wysiwyg, datetime, reference, textarea, color, email, identifier).
- Wzorzec testów: `App\Tests\Api\Catalog\CatalogApiTestCase` (auth, tenant seed); istniejące testy Api/Export w `apps/api/tests/Api/Export/`.
- Po IMP2-1.3 import ma tryb upsert + liczniki created/updated/skipped; po IMP2-1.4 wartości idą przez wspólny rdzeń (walidacja + kanon + primary-locale→global).
- Reguły normalizacji porównania: ADR-0019 (IMP2-1.1), wersjonowane, minimalne.
- **Lekcja projektu (MEMORY)**: przed uruchamianiem testów Api/* — `bin/console cache:clear --env=test`, inaczej Foundry `ResetDatabase` potrafi wyczyścić dev DB.

## Zakres prac
1. **Zastąpienie `ImportRoundTripApiTest`** nowym testem golden v0 (ta sama lokalizacja: `apps/api/tests/Api/Import/ImportRoundTripApiTest.php`):
   - **Seed**: ObjectType Product + atrybuty WSZYSTKICH 17 typów `AttributeType` (z opcjami dla select/multiselect, assetem dla asset, drugim obiektem dla relation/reference) + 2–3 obiekty z wartościami **global i locale** (np. `name` localizable z wartością pl) + przypisanie kategorii + ustawiony status/enabled. Dodatkowo **seedowane legacy-shape'y sprzed migracji D7** (surowy INSERT/raw SQL: select jako `{"value":"red"}`, price jako `{"value":"99.99"}`) — dowód, że fallback eksportu (IMP2-1.2 pkt 7) je czyta i że po round-tripie w bazie ląduje już KANON.
   - **Eksport**: realny `SyncExportRunner::runToFile()` (lub endpoint `POST /api/exports/sessions` + pobranie pliku — wybrać prostszą stabilną ścieżkę i udokumentować) → CSV ze wszystkimi kolumnami atrybutów + `name.pl` + kategorią + status/enabled.
   - **Import**: `POST /api/import-sessions` (ścieżka inline ≤50 wierszy) w trybie **UPSERT** z mapowaniem 1:1 nagłówków → **persystencja, nie dry-run**.
   - **Asercja równości**: reload `object_values` zaimportowanych obiektów i porównanie envelope per atrybut×scope z wartościami źródłowymi, po minimalnej normalizacji wg reguł ADR-0019 v1 (helper `normalizeEnvelope()` w teście z komentarzem-linkiem do ADR; każda różnica poza listą = fail). Liczniki sesji: `created_count=0`, `updated_count=N` (wszystkie obiekty istniały), `error_count=0`.
2. **Scenariusz „edycja w Excelu"**: w wyeksportowanym CSV zmień programowo jedną wartość + dodaj wiersz z nowym SKU + wyczyść jedną komórkę → re-import upsert → asercje: zmieniona wartość zapisana, nowy obiekt utworzony (`created_count=1`), **wyczyszczona komórka NIE wyczyściła wartości w bazie** (D2), pozostałe wartości bajt-w-bajt nietknięte.
3. **Asercja statusu/enabled w v0**: kolumny `status`/`enabled` obecne w eksporcie; po upsercie istniejącego obiektu jego status w bazie NIEZMIENIONY (dzisiejsza semantyka „import nie rusza statusu" — jawny import statusu z kolumn rozszerzy matrycę w IMP2-1.7). Kategoria: pojedyncza kategoria per wiersz zachowana po round-tripie (multi-kategorie → 1.7).
4. **Matryca jako kontrakt rozszerzalny**: tabela typ×scope×wariant w docbloku klasy testowej z adnotacją, który ticket fali B dopisuje które wiersze (1.6 kanały, 1.7 multi-kategorie+status z kolumn, 1.8 warianty+relacje resolve, 1.10 XLSX+async). Struktura testu (data provider / helpery seed) przygotowana na te rozszerzenia.
5. **CI**: test w istniejącym suite Api (bez nowego workflow); jeśli czas runu >60 s — rozważyć grupę `@group golden` z osobnym krokiem, decyzja w PR.

## Poza zakresem tego ticketu
- Pełna matryca: XLSX, kanały (`code.channel`), warianty, relacje z resolve po code, multi-kategorie, testy async `ImportRunHandler`, test izolacji błędu wiersza → IMP2-1.10 (fala B).
- Naprawy znalezionych przez test bugów silnika WYKRACZAJĄCE poza drobne poprawki — jeśli golden v0 odkryje lukę w 1.2/1.3/1.4, bug wraca do tamtego zakresu (reopen/fix-PR), nie rozdyma tego ticketu. Test może tymczasowo NIE wejść do main przed fixem — golden test merguje się ZIELONY.
- Benchmarki RAM/wydajności → IMP2-2.6.
- Pliki benchmarkowe operatora (`Zrodla/Importy przykładowe`) — to manualna checklista (dane komercyjne, NIE commitujemy); zanonimizowane mini-fixtures → etap 3.

## Kryteria akceptacji
- [ ] Stary 111-liniowy test zastąpiony; nowy test uruchamia REALNY `SyncExportRunner` (w kodzie testu nie ma ręcznie pisanego CSV z danymi produktów).
- [ ] Import w teście persystuje (asercje na `object_values` po reloadzie, nie na response dry-runu).
- [ ] Pokryte wszystkie 17 wartości enuma `AttributeType` (asercja w teście: matryca seedu == `AttributeType::cases()` — nowy typ w przyszłości automatycznie wywali test z czytelnym komunikatem).
- [ ] Pokryte: wartość global + wartość locale (pl), kategoria, status/enabled (semantyka v0 jak w zakresie pkt 3), legacy-shape'y sprzed D7 (po round-tripie w bazie kanon).
- [ ] Scenariusz edycji: zmiana wartości + nowy wiersz + pusta komórka → liczniki i asercje z zakresu pkt 2 przechodzą.
- [ ] Normalizacja porównania zaimplementowana 1:1 wg listy z ADR-0019 v1 (komentarz z linkiem; żadnych nieudokumentowanych tolerancji).
- [ ] Test zielony w CI na czystym kontenerze (nie tylko lokalnie).
- [ ] Docblok z matrycą i przypisaniem rozszerzeń do ticketów fali B.

## Jak zwalidować (smoke test po wykonaniu)
1. `docker compose exec api sh -c "bin/console cache:clear --env=test && vendor/bin/phpunit apps-relative-path-or-filter --filter ImportRoundTripApiTest"` (z katalogu apps/api: `vendor/bin/phpunit --filter ImportRoundTripApiTest`) → zielono, czas runu odnotowany.
2. **Dowód, że test jest strażnikiem**: tymczasowo zepsuj kontrakt (np. w `ValueSerializer` zmień separator multiselect `|` na `;`) → test CZERWONY z czytelnym diffem envelope → cofnij zmianę, test zielony. (Ten krok = screenshot/output do komentarza zamykającego.)
3. CI: pełny pipeline PR-a zielony; w logach joba widać uruchomienie ImportRoundTripApiTest.
4. Sanity na żywym stacku (`https://pim.localhost`, admin@demo.localhost / changeme): ręcznie wykonaj mini round-trip (eksport 3 produktów → import upsert bez zmian → liczniki updated=3/errors=0) — zgodność zachowania testu z rzeczywistością.

## Zależności
Blokowany przez: IMP2-1.3 (tryb upsert + liczniki), IMP2-1.4 (writer z walidacją — bez niego asercje envelope nie mają sensu). Pośrednio wymaga IMP2-1.2 (legacy seedy testują fallback) i IMP2-1.1 (reguły normalizacji).
Blokuje: gate fali A („golden v0 zielony"); każdy ticket fali B (1.6–1.10) rozszerza tę matrycę w swoich kryteriach akceptacji.

## Referencje
- `Project Plan/UI/feature-imports-v2.md` §2.5 (brak strażnika), §3 filar 1 (plik jest API), §5 wiersz 1.5, §8 pkt 1 (kryterium całości).
- Issues: #1130 (poprzedni, niewystarczający round-trip — w komentarzu odnotować supersedence; pełny re-audit close-comentu → IMP2-1.11).
- Pliki: `apps/api/tests/Api/Import/ImportRoundTripApiTest.php` (zastępowany), `SyncExportRunner.php`, `ValueSerializer.php`, `AttributeType.php`, `CatalogApiTestCase`, ADR-0019 (reguły normalizacji).

## Definition of Done
- PHPStan max zielony także dla kodu testów (`cache:warmup --env=dev` przed `composer phpstan` — lekcja: CI łapie `mixed`-offset w testach, których stale lokalny run nie widzi), php-cs-fixer przed commitem.
- `bin/console cache:clear --env=test` przed każdym lokalnym runem Api/* (MEMORY: ochrona dev DB).
- Test integracyjny na realnym Postgresie (no mocking) — zgodnie z core principles.
- Bez zmian API/UI → bez regeneracji `docs/api-spec/v0.json`, bez Playwright.
- Manual smoke wg sekcji „Jak zwalidować" z artefaktem dowodu w komentarzu zamykającym: output zielonego runu + output CZERWONEGO runu z kroku 2 (dowód czujności strażnika) + liczniki sesji z kroku 4 (CLOSED MEANS CLOSED).

---

## IMP2-1.6a — chore(import): transport Messenger 'import' + worker w dev i prod (D8) (#1468)

**Labels:** infra, backend, epik-IMP2 · **Estymata:** 3–5 h · **Zależy od:** brak

## Po co to robimy (kontekst nietechniczny)
Duży import ma się wykonywać „w tle", a operator ma w tym czasie normalnie pracować. Dziś to fikcja: w środowisku deweloperskim nie ma żadnego procesu-robotnika, więc „asynchroniczny" import wykonuje się w całości WEWNĄTRZ żądania HTTP — duży plik blokuje przeglądarkę i może zostać ucięty przez timeout. Do tego decyzja „mały plik = od ręki, duży = w tle" zapada dziś na podstawie rozmiaru pliku w bajtach (50 KB), a nie liczby wierszy — plik z długimi opisami trafia do złej ścieżki. Po tym tickecie: import ma własną, dedykowaną kolejkę z robotnikiem działającym tak samo na dev i produkcji (zero dryfu dev→prod, zgodnie z filozofią projektu), a próg „od ręki" liczy realne wiersze. Zabezpieczamy też przyszłe długie importy: kolejka nie podejmie tego samego zadania drugi raz w trakcie, gdy pierwsze jeszcze trwa.

## Stan obecny
- **Dev/test wykonuje „async" in-band**: `apps/api/.env.dev` linia 10 i `apps/api/.env.test` linia 20: `MESSENGER_TRANSPORT_DSN=sync://`; `docker-compose.yml` (serwis `api`, ~linia 139) także `sync://`. W `docker-compose.yml` (dev) **nie ma żadnego serwisu workera** — `grep messenger docker-compose.yml` = 0 trafień.
- **Prod ma workera**: `docker-compose.prod.yml` serwis `worker` — `php bin/console messenger:consume async --memory-limit=256M --time-limit=3600 --failure-limit=5`, DSN `doctrine://default?queue_name=async&auto_setup=0`, wspólny `LOCK_DSN` redis z api (PROD-05).
- **Konfiguracja**: `apps/api/config/packages/messenger.yaml` — transporty `sync` / `async` (`%env(MESSENGER_TRANSPORT_DSN)%`) / `failed`; routing `App\Import\Domain\Message\ImportRunMessage: async`; **brak `redeliver_timeout`** → doctrine transport default **3600 s** — import trwający >1h zostanie RE-DELIVERED w trakcie działania (drugi worker zacznie ten sam import równolegle).
- **Próg sync po bajtach**: `apps/api/src/Import/Presentation/Controller/StartImportController.php` — `SYNC_THRESHOLD_ROWS = 50` (linia 54), ale porównanie `getFileSizeBytes() <= SYNC_THRESHOLD_ROWS * 1024` (linia 141) z komentarzem „<50 rows" — heurystyka bajtowa, nie wierszowa. Plik jest już zestagowany w MinIO przed tą decyzją (linie 122–135), a `ImportRowReader` (`apps/api/src/Import/Application/Service/ImportRowReader.php`) umie czytać wiersze z lokalnej ścieżki.
- Middleware Messengera już rebinduje TenantContext na async (`TenantContextRebindingMiddleware` w messenger.yaml) — to zostaje bez zmian.

## Zakres prac
1. **Transport `import`** w `apps/api/config/packages/messenger.yaml`: DSN wpisany wprost `doctrine://default?queue_name=import&auto_setup=0` (NIE przez `%env(MESSENGER_TRANSPORT_DSN)%` — dzięki temu topologia identyczna w dev i prod niezależnie od env; zgodne z zasadą single-origin/zero-drift), z `options.redeliver_timeout: 86400` (24 h > maksymalny realny czas importu; uzasadnienie i wartość zapisane w ADR-0019/komentarzu). Routing: `App\Import\Domain\Message\ImportRunMessage: import` (zamiast `async`). Zgodnie z konwencją w messenger.yaml — nowy async message class = wpis routingu + **dedykowany test asercji destynacji routingu** (wzorzec opisany w komentarzu pliku).
2. **Override testowy**: w konfiguracji test (np. `when@test` w messenger.yaml) transport `import` → `sync://` — testy ApiTestCase wykonują import in-band jak dotąd; testy async ścieżki przyjdą w IMP2-1.10.
3. **Worker w dev**: nowy serwis `worker` w `docker-compose.yml` — `php bin/console messenger:consume import --memory-limit=256M --time-limit=3600 --failure-limit=5 -vv`, ten sam obraz/wolumeny co `api`, `restart: unless-stopped`, `depends_on: database (healthy)`, env jak `api` (w tym `LOCK_DSN` — worker musi widzieć `BulkOperationLock` inline'owych importów). Konsumuje tylko `import` (transport `async` w dev to `sync://` — nie ma czego konsumować).
4. **Worker w prod**: `docker-compose.prod.yml` — istniejący serwis `worker` rozszerza command na `messenger:consume import async ...` (kolejność = priorytet: import przed async) ALBO dedykowany drugi serwis `import-worker` — wybrać wariant rozszerzenia istniejącego (mniejszy footprint RAM na VPS), udokumentować w PR. Uwaga na komentarz o liczbie połączeń Postgres/PgBouncer w nagłówku pliku — zaktualizować rachunek, jeśli dochodzi proces.
5. **Próg inline po WIERSZACH**: w `StartImportController` po zestagowaniu pliku policzyć realne wiersze przez `ImportRowReader` z early-exit (czytaj max 51 wierszy danych; ≤50 → inline `runHandler->run()`, >50 → dispatch). Stała `SYNC_THRESHOLD_ROWS=50` zostaje, znika mnożenie `*1024`; komentarz zaktualizowany. Koszt: jednorazowe częściowe parsowanie małego prefiksu pliku — akceptowalne (streaming readers → IMP2-2.1).
6. **Dokumentacja operacyjna**: krótka notka w komentarzach compose (jak podejrzeć kolejkę: `SELECT queue_name, count(*) FROM messenger_messages GROUP BY 1;`, jak zrestartować workera) + wpis w `agent/current_status.md`.

## Poza zakresem tego ticketu
- Checkpoint offsetu+fazy i resume świadomy redelivery → IMP2-2.3 (tu redelivery mitygowane wyłącznie wysokim `redeliver_timeout`).
- Pauza/cancel/resume → IMP2-2.3 (przyciski ukryte w etapie 0).
- Dry-run dwupoziomowy (pełny async dry-run) → IMP2-3.6 (transport `import` z tego ticketu będzie jego nośnikiem).
- GUC `app.current_tenant` w workerach (gotowość FORCE RLS) → IMP2-2.5.
- Streaming readers / limity rozmiaru pliku → IMP2-2.1 / IMP2-2.7.
- Mercure per chunk (dziś per wiersz) → IMP2-2.6.

## Kryteria akceptacji
- [ ] `messenger.yaml`: transport `import` (doctrine, `queue_name=import`, `redeliver_timeout=86400`), routing `ImportRunMessage → import`; test asercji routingu (wzorzec z komentarza w messenger.yaml) przechodzi.
- [ ] `docker compose up -d` na dev stawia serwis `worker`; `docker compose ps` pokazuje go `running`, a `docker compose logs worker` — konsumpcję transportu `import`.
- [ ] Import pliku >50 WIERSZY w dev: `POST /api/import-sessions` zwraca **202** szybko (bez przetwarzania in-band), wiersz pojawia się w `messenger_messages` z `queue_name='import'`, worker przetwarza, sesja przechodzi do `completed`, postęp widoczny w UI sesji.
- [ ] Import pliku ≤50 wierszy: response **200** z wynikami sync (inline path działa jak dotąd) — decyzja podjęta po LICZBIE WIERSZY: plik ~45 wierszy ale >50 KB (długie opisy) idzie INLINE (dziś poszedłby async — to jest test zmiany heurystyki), plik 60 krótkich wierszy <50 KB idzie ASYNC (dziś poszedłby inline).
- [ ] Testy CI (ApiTestCase importu) zielone bez workera — override `when@test` działa.
- [ ] Prod compose: `docker compose -f docker-compose.prod.yml config` parsuje się; worker konsumuje `import` (weryfikacja konfiguracyjna — pełny deploy prod poza zakresem dev smoke).
- [ ] PHPUnit dla logiki liczenia wierszy z early-exit (granice: 50/51, plik bez wierszy danych, sam nagłówek).

## Jak zwalidować (smoke test po wykonaniu)
1. `docker compose up -d --build worker && docker compose ps worker` → running; `docker compose logs -f worker` w drugim terminalu.
2. Przygotuj CSV z 60 wierszami (krótkie wartości, plik <50 KB — celowo poniżej starego progu bajtowego): nagłówek `sku;name` + 60 wierszy `SMOKE-{n};Produkt {n}`.
3. UI `https://pim.localhost` (admin@demo.localhost / changeme) → Importy → wizard → wgraj plik, zmapuj, uruchom. DevTools Network: `POST /api/import-sessions` → **202** w <2 s.
4. `docker compose exec database psql -U app -d app -c "SELECT queue_name, count(*) FROM messenger_messages GROUP BY 1;"` → (przejściowo) wiersz `import`; w logach workera widać przetwarzanie; po chwili widok sesji pokazuje `completed` z 60 sukcesami; Console bez czerwonych błędów.
5. Drugi plik: 40 wierszy, ale z bardzo długą kolumną opisu (>50 KB całość) → `POST /api/import-sessions` → **200** (inline, decyzja po wierszach, nie bajtach).
6. `docker compose exec api sh -c "bin/console cache:clear --env=test && vendor/bin/phpunit --filter 'Routing|StartImport'"` → zielono (test routingu + progu).
7. Restart odporności: `docker compose restart worker` w trakcie biegu importu z kroku 3 (powtórz z większym plikiem) → po restarcie worker podejmuje wiadomość, sesja kończy się bez duplikatów obiektów (lock + redeliver_timeout).

## Zależności
Blokowany przez: — (niezależny infra-ticket fali A; może iść równolegle z 1.2–1.4).
Blokuje: realne async smoke'i IMP2-1.5/1.10 na dev; IMP2-2.3 (pause/resume), IMP2-3.6 (async dry-run). Koordynacja: IMP2-1.3 też dotyka `StartImportController` — mergować sekwencyjnie.

## Referencje
- `Project Plan/UI/feature-imports-v2.md` §2.6 (infra: sync:// w dev, redeliver_timeout), §4.4 D8, §5 wiersz 1.6a.
- Pliki: `apps/api/config/packages/messenger.yaml`, `apps/api/.env.dev`, `apps/api/.env.test`, `docker-compose.yml`, `docker-compose.prod.yml` (serwis worker + komentarz o połączeniach PgBouncer), `StartImportController.php`, `ImportRowReader.php`, `ImportRunHandler.php` (lock PROD-05).
- Issues: #445 (IMP-04 — pochodzenie inline/async split).

## Definition of Done
- PHPStan max zielony (`cache:warmup --env=dev` + `--memory-limit=1G`), php-cs-fixer przed commitem.
- PHPUnit dla nowej logiki (próg wierszowy, test routingu transportu); `cache:clear --env=test` przed Api/*.
- Bez zmian kontraktu API (status code'y 200/202 już istnieją) → diff `docs/api-spec/v0.json` tylko jeśli OpenAPI się zmieniło; bez zmian UI → bez Playwright.
- `docker compose config` i `docker compose -f docker-compose.prod.yml config` przechodzą; ŻADNYCH operacji na wolumenach (zakaz `down -v` — MEMORY).
- Manual smoke wg sekcji „Jak zwalidować" z artefaktem dowodu w komentarzu zamykającym: response 202 + output zapytania o `messenger_messages` + fragment logów workera + JSON sesji `completed` (CLOSED MEANS CLOSED).

---

## IMP2-1.6 — feat(import,export): gramatyka kolumn code.locale.channel + zapis channelId (#1469)

**Labels:** backend, enhancement, epik-IMP2 · **Estymata:** 9–13 h · **Zależy od:** IMP2-1.1, IMP2-1.4, IMP2-1.5

## Po co to robimy (kontekst nietechniczny)

Gdy plik importu ma kolumnę z wartością dla konkretnego kanału sprzedaży (np. `price.shopify` — inna cena dla Shopify niż domyślna), importer dziś myli kanał z językiem: zapisuje wartość tak, jakby istniał język o nazwie „shopify". Taka wartość jest niewidoczna na karcie produktu, w listach i w eksporcie kanału — to cicha korupcja danych, której operator nie zauważy aż do reklamacji. Po tym tickecie kolumny językowe, kanałowe i kombinowane (`opis.pl.shopify`) są rozpoznawane jednoznacznie, import zapisuje wartość we właściwym „przedziale" (kanał), a eksport emituje dokładnie te nagłówki, które import rozumie. Dodatkowo eksport przestaje po cichu produkować puste kolumny, gdy kanał z profilu już nie istnieje — zamiast tego dostajemy czytelny błąd przed startem eksportu.

## Stan obecny

- `apps/api/src/Import/Domain/ColumnHeader.php` — parsuje KAŻDY suffix po kropce jako locale (`localeOf()`); własny docblock przyznaje: „a channel suffix would be read as a locale". Kolumna `description.shopify` → `ObjectValue(locale='shopify')` = cicha korupcja.
- `apps/api/src/Import/Application/Handler/ImportRunHandler.php` (`materialiseValues()`) buduje `ResolvedImportValue(attributeCode, locale, rawValue)` — **bez channel**; `apps/api/src/Import/Domain/ValueObject/ResolvedImportValue.php` nie ma pola channel.
- `apps/api/src/Catalog/Domain/Entity/ObjectValue.php` MA pole `channelId` (nullable Uuid, `changeChannelId()`) — import nigdy go nie ustawia.
- Konsumenci `ColumnHeader`: `ImportRunHandler`, `apps/api/src/Import/Application/Service/ImportValidationService.php`, `apps/api/src/Import/Application/Service/AutoMapper.php`.
- Eksport już dezambiguuje 1-segmentowy suffix: `apps/api/src/Export/Application/Builder/ColumnResolver.php` (`resolveOne($key, $channelCodes)`) — suffix będący kodem kanału sesji = kolumna kanałowa, inaczej locale. Notacja 3-segmentowa `code.locale.channel` jest jawnie odroczona (komentarz w pliku).
- `apps/api/src/Export/Application/Builder/ExportBuilder.php` — resolwuje kod kanału → UUID przez `ChannelResolverInterface::resolveId()` (`apps/api/src/Channel/Contracts/ChannelResolverInterface.php`); kanał, który się nie zresolwował, **degraduje do pustej komórki** w `cellFor()` (komentarz R-47) — po cichu.
- `ExportBuilder::cellFor()` robi lookup po dokładnym kluczu `code|locale|channelId` — kolumna `name.pl` daje pustą komórkę, gdy wartość jest zapisana globalnie (reguła primary-locale→global z `ObjectAttributesUpserter`). To luka #1146: eksport per-locale gubi wartości globalne.
- Tworzenie kanału: `apps/api/src/Channel/Application/Command/CreateChannel/CreateChannelHandler.php` sprawdza tylko duplikat kodu kanału — NIC nie zabrania utworzenia kanału o kodzie `en` / `pl` kolidującym z locale.
- Rejestry tenanta: locale — `apps/api/src/Channel/Domain/Entity/TenantLocale.php` (`isActive`) + `TenantLocaleRepositoryInterface` + `ActiveLocaleResolver`; kanały — `apps/api/src/Channel/Domain/Entity/Channel.php` + `ChannelRepositoryInterface`.
- Preflight eksportu: `apps/api/src/Export/Presentation/Controller/ExportPreflightController.php` — dziś tylko COUNT, bez walidacji kolumn.

## Zakres prac

1. **`ImportColumnGrammar`** (nowy serwis, `App\Import\Application\Service\ImportColumnGrammar` + VO wyniku np. `ParsedColumn {attributeCode, ?locale, ?channelCode}`): parsowanie `code` / `code.locale` / `code.channel` / `code.locale.channel` z rejestrami tenanta (aktywne `TenantLocale` + kody `Channel`). Reguły: brak suffixu = global; 1 suffix = locale jeśli w rejestrze locali, kanał jeśli w rejestrze kanałów; **kolizja kodów (suffix jest i locale, i kanałem)** = reguła precedencji wg ADR z IMP2-1.1 (default: locale wygrywa + warning w preflight/dry-run); suffix nieznany w żadnym rejestrze = błąd walidacji kolumny (NIE ciche locale); 2 suffixy = kolejność stała `locale.channel`, oba walidowane przeciw rejestrom.
2. **Wymiana `ColumnHeader` → `ImportColumnGrammar`** we wszystkich 3 konsumentach (`ImportRunHandler`, `ImportValidationService`, `AutoMapper`); usunięcie `ColumnHeader.php` (lub `@deprecated` z wpisem do usunięcia w IMP2-1.10); rozszerzenie `ResolvedImportValue` o `?string $channelCode` / `?Uuid $channelId`.
3. **Zapis `channelId` na `ObjectValue` w imporcie**: ścieżka zapisu przez `ImportValueWriter` (z IMP2-1.4) resolwuje kod kanału → UUID raz per sesja (nie per wiersz) i przekazuje do envelope/konstrukcji `ObjectValue`; prefetch istniejących `ObjectValue` per chunk (z IMP2-1.4) uwzględnia wymiar channel w kluczu `object×attribute×locale×channel`.
4. **Walidacja przy tworzeniu/edycji kanału**: w `CreateChannelHandler` (+ `ChannelProcessor`/`ChannelInput` w `apps/api/src/Channel/Infrastructure/ApiPlatform/`, oraz `UpdateChannelHandler` jeśli kod jest mutowalny) — odrzucenie (422, RFC 7807) kodu kanału kolidującego z jakimkolwiek kodem locale aktywnym dla tenanta (oraz odwrotnie: aktywacja locale o kodzie istniejącego kanału — analogiczny guard w `TenantLocaleController`, jeśli ADR 1.1 tak rozstrzyga).
5. **Egzekwowany charset kodów atrybutów**: weryfikacja, że walidacja kodu `Attribute` zabrania kropki (`[a-z0-9_]`) — jeśli nie jest egzekwowana przy tworzeniu atrybutu, dodać (gramatyka opiera się na „pierwsza kropka oddziela kod").
6. **Eksport — notacja `code.locale.channel`**: `ColumnResolver` parsuje 3 segmenty (z tymi samymi rejestrami/precedencją — wstrzyknięcie listy locali sesji lub tenanta), `ExportBuilder`/`PublicationColumnPlanner` emitują notację kombinowaną dla wartości mających locale+channel jednocześnie.
7. **Eksport — fan-out global→locale (#1146)**: kolumna `code.locale` przy braku wiersza locale-specific emituje wartość globalną jako fallback (zgodnie z regułą primary-locale→global z rdzenia IMP2-1.4 i regułami normalizacji golden testu z ADR 1.1). Test jednostkowy w `apps/api/tests/Unit/Export/Application/Builder/ExportBuilderTest.php`.
8. **Eksport — niezresolwowany kanał = błąd preflight (R-47)**: kolumna kanałowa, której kanał nie istnieje już w tenancie, kończy się błędem 422 w `ExportPreflightController` (i defensywnie wyjątkiem w `SyncExportRunner`), zamiast cichej pustej kolumny — chroni przed scenariuszem „pusty plik z błędu + `clear_if_empty` = skasowany katalog".
9. **Rozszerzenie golden testu** (z IMP2-1.5): wartości channel-scoped i locale+channel w matrycy round-trip — eksport → import → równość envelope włącznie z `channel_id`.

## Poza zakresem tego ticketu

- Warianty, relacje, galerie — IMP2-1.8.
- Multi-kategorie, status/enabled — IMP2-1.7.
- Pełna matryca testów (XLSX, async) — IMP2-1.10.
- UI kreatora z pickerami locale/channel per kolumna — etap 3 (IMP2-3.3); w tym tickecie wystarczy, że nagłówki z eksportu mapują się automatycznie.
- Streaming readers — IMP2-2.1.
- Migracja shape'ów JSONB — IMP2-1.2 (Fala A).

## Kryteria akceptacji

- [ ] Import pliku z kolumną `price.shopify` (kanał `shopify` istnieje w tenancie) tworzy `ObjectValue` z `channel_id` = UUID kanału i `locale IS NULL` — weryfikowalne SQL-em na `object_values`.
- [ ] Import kolumny `description.pl.shopify` tworzy `ObjectValue(locale='pl', channel_id=<uuid shopify>)`.
- [ ] Kolumna z suffixem nieznanym w żadnym rejestrze (np. `name.xx`) daje błąd walidacji kolumny w dry-run, NIE wiersz `locale='xx'`.
- [ ] Przy kolizji kodów (kanał `en` + locale `en`) zachowanie jest zgodne z regułą precedencji z ADR IMP2-1.1 i pokryte testem jednostkowym `ImportColumnGrammar`.
- [ ] `POST /api/channels` z kodem kolidującym z aktywnym locale (np. `pl`) zwraca 422 RFC 7807; test ApiTestCase.
- [ ] Eksport emituje `code.locale.channel` dla wartości locale+channel; `ColumnResolver` parsuje notację 3-segmentową (testy w `tests/Unit/Export/Application/Builder/ColumnResolverTest.php`).
- [ ] Eksport kolumny `name.pl` dla obiektu z wartością wyłącznie globalną emituje wartość globalną (fallback #1146) — test `ExportBuilderTest`.
- [ ] Preflight eksportu z kolumną kanału, którego nie da się zresolwować, zwraca 422 z czytelnym komunikatem (test w `tests/Api/Export/ExportPreflightApiTest.php`); zachowanie „cicha pusta kolumna" usunięte.
- [ ] Golden test z IMP2-1.5 rozszerzony o wartości channel-scoped przechodzi w CI.
- [ ] `ColumnHeader.php` nie jest już używany przez żaden kod produkcyjny (grep czysty).

## Jak zwalidować (smoke test po wykonaniu)

1. `docker compose exec api php bin/console cache:warmup --env=dev` (świeży kontener przed testami).
2. Zaloguj się: `TOKEN=$(curl -sk -X POST https://pim.localhost/api/auth/login -H 'Content-Type: application/json' -d '{"email":"admin@demo.localhost","password":"changeme"}' | jq -r .token)`.
3. Utwórz kanał testowy `shopify` (jeśli nie istnieje) przez `POST /api/channels`; następnie spróbuj utworzyć kanał o kodzie `pl` → oczekiwane **422** z komunikatem o kolizji z locale.
4. Przygotuj CSV: `sku,name,price.shopify` z 1 wierszem; `curl -sk -X POST https://pim.localhost/api/import-sessions -H "Authorization: Bearer $TOKEN" -F file=@test.csv -F target_object_type_id=<uuid-product-OT> -F 'mapping={"sku":"sku","name":"name","price.shopify":"price"}'` → 200/201, sesja success.
5. Sprawdź w DB: `docker compose exec db psql -U pim -c "SELECT locale, channel_id FROM object_values ov JOIN attributes a ON a.id=ov.attribute_id WHERE a.code='price' ORDER BY ov.created_at DESC LIMIT 1;"` → `locale=NULL`, `channel_id=<uuid shopify>` (NIE `locale='shopify'`).
6. Wyeksportuj ten produkt przez `POST /api/products/export` z kolumną `price.shopify` → w pliku wartość obecna; usuń kanał i powtórz preflight → oczekiwany błąd 422, nie pusta kolumna.
7. UI: https://pim.localhost → karta produktu → wartość ceny widoczna w scope kanału Shopify; konsola DevTools bez czerwonych errorów.

## Zależności

- Blokowany przez: IMP2-1.1 (ADR — precedencja, kanon), IMP2-1.4 (`ImportValueWriter` — ścieżka zapisu), IMP2-1.5 (golden test do rozszerzenia).
- Blokuje: IMP2-1.8 (pełna matryca z kanałami), IMP2-1.10 (golden pełna matryca).

## Referencje

- `Project Plan/UI/feature-imports-v2.md` §2.4 (tabela luk — wiersz „Kanały"), §3 filar 9, §4.2 (`ImportColumnGrammar`), §4.3, §5 Fala B wiersz 1.6; decyzje D11, D12.
- Issues: #1146 (fan-out global→locale), #1147 (kanały end-to-end), #1229 (kolumny per-channel w eksporcie), #1130/#1167 (round-trip v1).
- Kod: `apps/api/src/Import/Domain/ColumnHeader.php`, `apps/api/src/Export/Application/Builder/ColumnResolver.php`, `apps/api/src/Export/Application/Builder/ExportBuilder.php`, `apps/api/src/Catalog/Domain/Entity/ObjectValue.php`, `apps/api/src/Channel/Application/Command/CreateChannel/CreateChannelHandler.php`, `apps/api/src/Export/Presentation/Controller/ExportPreflightController.php`.

## Definition of Done

- [ ] PHPStan max zielony (`docker compose exec api php bin/console cache:warmup --env=dev` przed `composer phpstan`, `--memory-limit=1G`).
- [ ] php-cs-fixer przed commitem (husky i tak zablokuje niesformatowany PHP).
- [ ] PHPUnit ≥80% nowej logiki (`ImportColumnGrammar` w 100% — czysta logika parsowania).
- [ ] ApiTestCase: walidacja kodu kanału (422), preflight z martwym kanałem (422), import channel-scoped end-to-end; przed testami Api/* `cache:clear --env=test` (ochrona dev DB).
- [ ] Golden test rozszerzony i zielony w CI.
- [ ] Regeneracja `docs/api-spec/v0.json` jeśli zmienia się kontrakt API (`cache:warmup` + `api:openapi:export`); diff scope'owany do własnej zmiany (lekcja integer→number).
- [ ] Brak zmian UI → Playwright nie wymagany; jeśli dotknięty FE typecheck: `NODE_OPTIONS=--max-old-space-size=4096`.
- [ ] Manual smoke wg sekcji „Jak zwalidować" — artefakt dowodu (output psql + HTTP code/JSON z curl) w komentarzu zamykającym issue (CLOSED MEANS CLOSED).

---

## IMP2-1.7 — feat(import): multi-kategorie pipe-split + import status/enabled (#1470)

**Labels:** backend, frontend, enhancement, epik-IMP2 · **Estymata:** 4–6 h · **Zależy od:** IMP2-1.4, IMP2-1.5

## Po co to robimy (kontekst nietechniczny)

Eksport zapisuje wszystkie kategorie produktu w jednej komórce rozdzielone kreską (`elektronika|promocje`), ale import bierze z tej komórki tylko jedną kategorię — reszta przypisań po cichu ginie przy każdym round-tripie. Podobnie status publikacji (`draft`/`published`) i flaga włączenia produktu: eksport je zapisuje, ale import je ignoruje jako „kolumny systemowe". Efekt: po cyklu eksport → poprawka w Excelu → import produkty tracą kategorie i wszystkie wracają jako szkice. Po tym tickecie pełna lista kategorii oraz status/enabled wracają z pliku dokładnie tak, jak zostały wyeksportowane.

## Stan obecny

- Eksport: `apps/api/src/Export/Application/Builder/ExportBuilder.php` → `resolveCategories()` pipe-joinuje WIELE kodów kategorii (`ValueSerializer::MULTI_VALUE_GLUE = '|'`).
- Import: `apps/api/src/Import/Application/Handler/ImportRunHandler.php` → `extractCategoryCode()` zwraca pierwszą niepustą komórkę jako JEDEN string; `apps/api/src/Import/Application/Service/ImportObjectCreator.php` → `create(?string $categoryCode)` tworzy JEDNO `ObjectCategory(isPrimary: true, position: 0)`. Pozostałe kody z komórki giną bez śladu (nawet bez warninga).
- `apps/api/src/Import/Domain/SystemColumn.php` — `status` i `enabled` są w `HEADERS` = auto-skip; walidator nigdy ich nie flaguje, kreator nigdy nie zapisuje. Konsumenci `SystemColumn`: `ImportRunHandler`, `ImportValidationService`, `AutoMapper`.
- `apps/api/src/Import/Domain/ReservedMappingTarget.php` — ma tylko `SKIP` i `CATEGORY ('__category__')`; konwencja `__target__` mirrorowana w FE (`apps/api/src/Import/Domain/ReservedMappingTarget.php` docblock wskazuje `StepMapping.tsx → SKIP_VALUE / CATEGORY_VALUE`).
- `apps/api/src/Catalog/Domain/Entity/CatalogObject.php` — `transitionTo()` (statusy `draft|published|archived`, stałe `STATUS_*`), `changeEnabled(bool)`; eksport emituje `status` jako string i `enabled` jako `true`/`false` (`ValueSerializer::serializeScalar`).
- `apps/api/src/Catalog/Domain/Entity/ObjectCategory.php` — pola `isPrimary`, `position` gotowe na wiele przypisań.
- Walidacja kategorii: `ImportValidationService` emituje `CategoryNotFound` (Warning, nie blokuje) tylko dla pojedynczego kodu.

## Zakres prac

1. **Pipe-split kategorii**: `extractCategoryCode()` → `extractCategoryCodes(): list<string>` (split po `|`, trim, filtr pustych); ścieżka zapisu (po IMP2-1.4: `ObjectResolver` + `ImportValueWriter`, dla create także `ImportObjectCreator` dopóki istnieje) tworzy `ObjectCategory` per kod — pierwszy kod = `isPrimary: true`, `position` = kolejność w komórce. Prefetch kategorii per chunk (wzorzec z IMP2-1.4), nie 1 SELECT per kod.
2. **Walidacja per kod**: `ImportValidationService` emituje `CategoryNotFound` (Warning) osobno dla każdego niezresolwowanego kodu z listy; wiersz nadal się importuje z pozostałymi kategoriami.
3. **Polityka kolekcji wg D2** (tryby update/upsert z IMP2-1.3): kolumna `category` obecna z niepustą wartością → default **`replace`** (skasuj istniejące przypisania obiektu, wstaw z pliku), `append` opt-in per kolumna (flaga w `columnMapping` v2 / payloadzie sesji — format zgodny z ADR 1.1); **pusta komórka = nie ruszaj przypisań** (D2).
4. **`status`/`enabled` z jawnych kolumn**: usunięcie obu z `SystemColumn::HEADERS`; nowe stałe `ReservedMappingTarget::STATUS = '__status__'`, `ReservedMappingTarget::ENABLED = '__enabled__'` (+ `all()`); `AutoMapper` mapuje nagłówki `status`/`enabled` na nie automatycznie; zapis przez `CatalogObject::transitionTo()` / `changeEnabled()` — także na ścieżce update (obiekt istniejący).
5. **Walidacja dozwolonych wartości**: `status` ∈ {`draft`,`published`,`archived`} (dokładnie literały eksportu), `enabled` ∈ {`true`,`false`,`1`,`0`} — zła wartość = `InvalidValue` (Error, blokuje wiersz); pusta komórka = nie ruszaj (D2).
6. **FE — `StepMapping.tsx`** (`apps/admin/src/features/imports/wizard/StepMapping.tsx`): opcje „Status" i „Włączony/wyłączony" w comboboxie celów mapowania (konwencja `__status__`/`__enabled__` jak `CATEGORY_VALUE`); stringi przez `t()` (pl/en JSON + restart kontenera admin po edycji locale).
7. **Rozszerzenie golden testu** (z IMP2-1.5): obiekt z 2+ kategoriami (primary + position zachowane), `status='published'`, `enabled=false` — round-trip 1:1.

## Poza zakresem tego ticketu

- Tryby create/update/upsert same w sobie — IMP2-1.3 (Fala A); tu tylko polityka replace/append na ich ścieżce.
- `clear_if_empty` w UI — po domknięciu migracji D7 (decyzja D2), etap 3.
- Warianty/relacje/galerie — IMP2-1.8.
- Concat wielu kolumn → ścieżka kategorii (Bosch XLSX, 2-poziomowe kategorie w osobnych kolumnach) — transformacje IMP2-3.4.
- Import drzewa kategorii (struktura) — poza zakresem v2 (osobny epik importów strukturalnych, §7 planu).

## Kryteria akceptacji

- [ ] Import CSV z komórką `category` = `cat-a|cat-b|cat-c` tworzy 3 wiersze `object_categories`; `cat-a` ma `is_primary=true`, `position` odzwierciedla kolejność (test ApiTestCase rozszerzający `tests/Api/Import/StartImportApiTest.php`).
- [ ] Niezresolwowany kod w środku listy (`cat-a|GHOST|cat-c`) → wiersz importuje się z 2 kategoriami + warning `CategoryNotFound` z wartością `GHOST` w `import_logs`.
- [ ] Update istniejącego obiektu (tryb upsert) z kolumną `category` niepustą → przypisania zastąpione (replace); z `append` opt-in → dołożone bez duplikatów; z pustą komórką → przypisania nietknięte.
- [ ] `status`/`enabled` NIE są już w `SystemColumn::HEADERS`; import kolumn `status=published`, `enabled=false` ustawia pola na `objects` (weryfikowalne SQL-em); wartość `status=foo` → błąd wiersza `InvalidValue`.
- [ ] Kreator (StepMapping) pokazuje cele „Status" i „Enabled" w comboboxie; mapowanie działa end-to-end.
- [ ] Golden test rozszerzony (multi-kategorie + status + enabled) zielony w CI.

## Jak zwalidować (smoke test po wykonaniu)

1. `TOKEN=$(curl -sk -X POST https://pim.localhost/api/auth/login -H 'Content-Type: application/json' -d '{"email":"admin@demo.localhost","password":"changeme"}' | jq -r .token)`.
2. Przygotuj `test.csv`: nagłówek `sku,name,category,status,enabled`, wiersz `SMOKE-17,Produkt testowy,cat-a|cat-b,published,false` (kody kategorii istniejące w demo katalogu — sprawdź w UI Kategorie).
3. `curl -sk -X POST https://pim.localhost/api/import-sessions -H "Authorization: Bearer $TOKEN" -F file=@test.csv -F target_object_type_id=<uuid-product-OT> -F 'mapping={"sku":"sku","name":"name","category":"__category__","status":"__status__","enabled":"__enabled__"}'` → 200/201, status sesji `success`.
4. `docker compose exec db psql -U pim -c "SELECT oc.is_primary, oc.position, c.code FROM object_categories oc JOIN objects o ON o.id=oc.product_id JOIN objects c ON c.id=oc.category_id WHERE o.code='SMOKE-17' ORDER BY oc.position;"` → 2 wiersze, pierwszy primary.
5. `docker compose exec db psql -U pim -c "SELECT status, enabled FROM objects WHERE code='SMOKE-17';"` → `published`, `f`.
6. UI https://pim.localhost → produkt SMOKE-17: obie kategorie widoczne, status Published, przełącznik enabled wyłączony; konsola DevTools bez czerwonych errorów.
7. Round-trip: wyeksportuj SMOKE-17 (kolumny sku, category, status, enabled) → zaimportuj plik bez zmian → liczniki „zaktualizuje 0/pominie 1" (no-op diff po IMP2-1.3) lub brak zmian w DB.

## Zależności

- Blokowany przez: IMP2-1.4 (ścieżka zapisu + prefetch), IMP2-1.5 (golden test do rozszerzenia). Polityka replace/append zakłada tryby z IMP2-1.3 (Fala A — domknięta przed Falą B).
- Blokuje: IMP2-1.10 (golden pełna matryca).

## Referencje

- `Project Plan/UI/feature-imports-v2.md` §2.4 (wiersze „Kategorie", „Status"), §4.4 decyzja D2, §5 Fala B wiersz 1.7.
- Kod: `apps/api/src/Import/Domain/SystemColumn.php`, `apps/api/src/Import/Domain/ReservedMappingTarget.php`, `apps/api/src/Import/Application/Service/ImportObjectCreator.php`, `apps/api/src/Export/Application/Builder/ExportBuilder.php` (`resolveCategories`), `apps/api/src/Catalog/Domain/Entity/ObjectCategory.php`, `apps/admin/src/features/imports/wizard/StepMapping.tsx`.
- Powiązane: #1130/#1167 (round-trip v1), benchmark `Zrodla/Importy przykładowe/bosch-09-01-2026.xlsx` (kategorie wielopoziomowe — pełne wsparcie w 3.4).

## Definition of Done

- [ ] PHPStan max zielony (`cache:warmup --env=dev` + `--memory-limit=1G`).
- [ ] php-cs-fixer przed commitem.
- [ ] PHPUnit ≥80% nowej logiki; ApiTestCase dla ścieżek multi-kategorii i status/enabled (`cache:clear --env=test` przed Api/*).
- [ ] Playwright dla zmiany w StepMapping (nowe opcje comboboxa widoczne i mapowalne).
- [ ] FE typecheck z `NODE_OPTIONS=--max-old-space-size=4096`; nowe stringi przez `t()` w pl/en.
- [ ] Golden test zielony w CI.
- [ ] Manual smoke wg sekcji „Jak zwalidować" — artefakt dowodu (output psql + screenshot karty produktu) w komentarzu zamykającym (CLOSED MEANS CLOSED).

---

## IMP2-1.8 — feat(import,export): warianty parent_sku (two-pass) + relacje ObjectRelation + fan-out include_variants (#1471)

**Labels:** backend, enhancement, epik-IMP2 · **Estymata:** 14–20 h · **Zależy od:** IMP2-1.4, IMP2-1.6

## Po co to robimy (kontekst nietechniczny)

Trzy rzeczy giną dziś bezpowrotnie przy każdym cyklu eksport → import: (1) **hierarchia wariantów** — kolumna `parent_sku` jest w pliku eksportu, ale import ją ignoruje, więc warianty wracają jako osobne, niepowiązane produkty; (2) **relacje między produktami** (np. „akcesoria", „produkty powiązane") — import zapisuje je w starym, martwym formacie, przez co po imporcie są niewidoczne w UI; (3) **eksport wariantów w ogóle nie działa** — checkbox „uwzględnij warianty" istnieje, ale silnik go ignoruje i eksportuje tylko produkty główne. Po tym tickecie struktura rodzic–warianty, relacje i galerie zdjęć przeżywają pełny round-trip, a checkbox przestaje być fałszywą obietnicą.

## Stan obecny

- **parent_sku**: `apps/api/src/Import/Domain/SystemColumn.php` — `parent_sku` w `HEADERS` = auto-skip (docblock: „variant parent linking is out of MVP import scope"). Eksport emituje go jako built-in (`ExportBuilder::builtIn()` → `$object->getParent()?->getCode()`).
- **Relacje**: `apps/api/src/Import/Application/Service/ImportObjectCreator.php` → `buildValuePayload()`: `AttributeType::Relation/Reference → ['object_id' => $raw]` — surowy zapis do `ObjectValue` bez resolve i bez wiersza w `object_relations`. Kanon po ADR-014 to tabela `object_relations` (`apps/api/src/Catalog/Domain/Entity/ObjectRelation.php`, `ObjectRelationRepositoryInterface`) — ścieżki odczytu UI (MOD-07) czytają z niej, więc relacje z importu są **niewidoczne w UI**.
- **Eksport relacji**: `apps/api/src/Export/Application/Builder/ValueSerializer.php` → Relation/Reference emituje `object_id` (UUID) z `ObjectValue` — nie czyta `object_relations` i emituje UUID zamiast kodu (sprzeczne z D5: „relacje po code").
- **include_variants**: `apps/api/src/Export/Domain/Entity/ExportSession.php` ma `includeVariants` (default true), `SyncExportController` przyjmuje `include_variants` z payloadu — ale `apps/api/src/Export/Application/Sync/SyncExportRunner.php::resolveTargets()` nigdy nie dokłada wariantów; docblock `ExportBuilder` wprost: „Variant fan-out (...) caller materialises masters + variants" — żaden caller tego nie robi (sync i async `ExportJobHandler` używają tego samego runnera). Flaga = no-op.
- **variant_axes**: `apps/api/src/Catalog/Domain/Entity/CatalogObject.php` ma `variantAxes` (JSONB, get/set) — eksport nie ma takiej kolumny built-in, import jej nie ustawia.
- **Galerie**: `buildValuePayload()`: `AttributeType::Asset → ['asset_id' => $raw]` — bez splitu po `|` (eksport pipe-joinuje listy — `ValueSerializer::stringify()`), bez walidacji istnienia (`apps/api/src/Asset/Domain/Entity/Asset.php`).
- **GenerateVariants**: `apps/api/src/Catalog/Presentation/Controller/GenerateVariantsController.php` tworzy warianty; shape osi select naprawiany w IMP2-1.2 (D7).
- Otwarte tickety IMP-16: #598 i duplikat #602 — ten ticket je domyka (dedupe duplikatu w IMP2-1.11).

## Zakres prac

1. **Pass 2 (wzorzec Akeneo „dedicated step")** — nowy `App\Import\Application\Service\RelationImportStep` (nazwa z planu §4.2): w pass 1 (zapis obiektów) buforowane są w pamięci krotki `(childSku → parentSku)` oraz `(sourceSku, attributeCode, [targetCode,...])` — bufor in-memory jest OK do 200k wierszy (limit D10). Po zakończeniu pass 1 `ImportRunHandler` uruchamia pass 2: resolve po `objects.code` **tenant-scoped** i zapis w chunkach z `flushAndClear()` (kontrakt AbstractBatchHandler).
2. **Checkpoint z fazą**: checkpoint sesji (z IMP2-1.6a) rozszerzony o pole fazy (`values` / `links`) — redelivery Messengera wznawia we właściwej fazie, bez powtórnego zapisu obiektów.
3. **parent_sku w imporcie**: usunięcie z `SystemColumn::HEADERS`; cel mapowania (reserved target `__parent_sku__` lub built-in w gramatyce z IMP2-1.6 — rozstrzygnięcie spójne z ADR 1.1); pass 2 robi `CatalogObject::assignParent()`; rodzic nieznaleziony (w pliku ani w DB) = błąd wiersza wariantu (Error), nie abort sesji; guard na self-parent i cykl.
4. **Relacje → `ObjectRelation`**: komórki atrybutów Relation/Reference NIE zapisują już `ObjectValue {object_id}`; pass 2 tworzy `ObjectRelation(source, target, attribute, position wg kolejności w komórce)` z resolve targetów po `code` (pipe-split dla wielu); dedupe po unikalnej trójce (UNIQUE index z migracji ADR-014); niezresolwowany target = błąd/warning wg severity (spójnie z IMP2-1.9); **test izolacji cross-tenant: target o kodzie istniejącym tylko w innym tenancie → 0 dopasowań i błąd wiersza, nigdy link**.
5. **Eksport relacji po code (D5)**: `ExportBuilder` dla kolumn atrybutów Relation/Reference czyta `object_relations` (przez `ObjectRelationRepositoryInterface`) i emituje pipe-joined **kody** targetów zamiast UUID z `ObjectValue` — symetria z importem; aktualizacja `ValueSerializer`/`ExportBuilderTest`.
6. **variant_axes round-trip**: nowa kolumna built-in `variant_axes` (`ColumnResolver::BUILT_INS` + `ExportBuilder::builtIn()` — pipe-joined kody atrybutów osi z `getVariantAxes()`); import parsuje i wykonuje `setVariantAxes()` na obiektach-rodzicach z walidacją, że kody to istniejące atrybuty select (kontrakt GenerateVariants).
7. **Galerie**: pipe-split listy `asset_id` w komórce + walidacja istnienia assetów tenant-scoped (prefetch po ID per chunk — wzorzec IMP2-1.4); nieistniejący `asset_id` = błąd wiersza zamiast wiszącego ID w JSONB.
8. **Eksport `include_variants` fan-out**: `SyncExportRunner::resolveTargets()` przy `includeVariants=true` dokłada dzieci każdego mastera (zapytanie repo po `parent_id IN (...)`, tenant-scoped) w deterministycznej kolejności: master, potem jego warianty; przy `false` — jak dziś. Działa identycznie na ścieżce sync i async (`ExportJobHandler` używa tego samego runnera). **To ścieżka krytyczna golden testu wariantów.**
9. **Spójność z GenerateVariants po D7**: test integracyjny — wariant wygenerowany generatorem (osie select w kanonicznym shape z IMP2-1.2) eksportuje się z `parent_sku` + osiami i wraca importem 1:1.
10. **Rozszerzenie golden testu** (z IMP2-1.5): master + 2 warianty + relacja między dwoma produktami + galeria 2 assetów — eksport (include_variants=true) → import → identyczna struktura (parent, relacje w `object_relations`, asset_ids).

## Poza zakresem tego ticketu

- Pobieranie zdjęć z URL-i / resolve ścieżek na assety — IMP2-1.12 (`AssetUrlResolver`, IMP-18 #600/#604); tu wyłącznie istniejące `asset_id` same-tenant.
- ZIP ze zdjęciami — IMP2-1.13.
- Pauza/cancel/resume i pełny checkpoint offsetu — IMP2-2.3 (tu tylko pole fazy w checkpoincie z 1.6a).
- Undo-log dla relacji (`relation_created` op) — IMP2-2.4; w tym tickecie rollback created-objects (istniejący mechanizm) wystarcza, bo `import_session_id` = wyłącznie marker created-by (D11).
- Wiersz-locale i kolumna `_command` — poza v2 (§7 planu).

## Kryteria akceptacji

- [ ] Import pliku z kolumną `parent_sku` (warianty PO wierszu mastera oraz — drugi test — PRZED wierszem mastera) ustawia `objects.parent_id` poprawnie w obu przypadkach (two-pass niezależny od kolejności wierszy).
- [ ] `parent_sku` wskazujący nieistniejący SKU → błąd wiersza w `import_logs`, sesja kończy się `partial`, pozostałe wiersze zaimportowane.
- [ ] Import komórki atrybutu relation `REL-A|REL-B` tworzy 2 wiersze `object_relations` (position 0,1) i NIE tworzy `ObjectValue {object_id}`; relacje widoczne w UI na karcie produktu.
- [ ] Test izolacji: target istniejący wyłącznie w tenancie B przy imporcie do tenanta A → 0 linków + błąd wiersza (test integracyjny w `tests/Integration/Import/`).
- [ ] Eksport atrybutu relation emituje pipe-joined kody targetów z `object_relations` (test `ExportBuilderTest`).
- [ ] `POST /api/products/export` z `include_variants=true` emituje wiersze wariantów z wypełnionym `parent_sku` (master przed swoimi wariantami); z `false` — tylko mastery (test ApiTestCase).
- [ ] Kolumna `variant_axes` round-tripuje: eksport → import → `objects.variant_axes` identyczne.
- [ ] Galeria `id1|id2` → envelope z listą; nieistniejący `asset_id` → błąd wiersza, nic nie ląduje w JSONB.
- [ ] Wariant z GenerateVariants przechodzi round-trip 1:1 (test integracyjny).
- [ ] Golden test rozszerzony o warianty+relacje+galerie zielony w CI.
- [ ] Issue #598 (IMP-16) zamknięte z dowodem (link do testu + smoke).

## Jak zwalidować (smoke test po wykonaniu)

1. `TOKEN=$(curl -sk -X POST https://pim.localhost/api/auth/login -H 'Content-Type: application/json' -d '{"email":"admin@demo.localhost","password":"changeme"}' | jq -r .token)`.
2. W UI (https://pim.localhost) wybierz produkt z wariantami z demo katalogu (lub wygeneruj przez GenerateVariants) i produkt z relacją.
3. Eksport: `curl -sk -X POST https://pim.localhost/api/products/export -H "Authorization: Bearer $TOKEN" -H 'Content-Type: application/json' -d '{"format":"csv","target_scope":"all","include_variants":true,"selected_columns":["sku","parent_sku","variant_axes","name","<kod_atrybutu_relation>"]}' -o export.csv` → plik zawiera wiersze wariantów z `parent_sku`.
4. Wyczyść testowo (zmień SKU w pliku na nowe, np. prefix `RT-`) i zaimportuj `export.csv` przez `POST /api/import-sessions` z mapowaniem zawierającym `parent_sku` i atrybut relation → sesja `success`.
5. `docker compose exec db psql -U pim -c "SELECT o.code, p.code AS parent FROM objects o LEFT JOIN objects p ON p.id=o.parent_id WHERE o.code LIKE 'RT-%' ORDER BY o.code;"` → warianty mają rodzica.
6. `docker compose exec db psql -U pim -c "SELECT count(*) FROM object_relations r JOIN objects s ON s.id=r.source_id WHERE s.code LIKE 'RT-%';"` → relacje istnieją.
7. UI: karta zaimportowanego produktu — zakładka wariantów pokazuje dzieci, relacje widoczne; konsola DevTools bez czerwonych errorów.

## Zależności

- Blokowany przez: IMP2-1.4 (writer/prefetch), IMP2-1.6 (gramatyka kolumn — parent_sku/variant_axes jako built-iny mapowania). Pole fazy zakłada checkpoint z IMP2-1.6a (Fala A); spójność osi zakłada IMP2-1.2 (D7, Fala A).
- Blokuje: IMP2-1.10 (golden pełna matryca — ścieżka krytyczna wariantów).
- Zamyka: #598 (IMP-16; duplikat #602 zamykany w IMP2-1.11).

## Referencje

- `Project Plan/UI/feature-imports-v2.md` §2.4 (wiersze „Warianty", „Relacje", „Assety"), §4.2 (`RelationImportStep`), §4.3 (include_variants), §4.4 decyzje D5, D10, D11, §5 Fala B wiersz 1.8.
- ADR-014 (relacje → `object_relations`), issues #598/#602 (IMP-16), #894 (MOD-02).
- Kod: `apps/api/src/Import/Domain/SystemColumn.php`, `apps/api/src/Import/Application/Service/ImportObjectCreator.php`, `apps/api/src/Catalog/Domain/Entity/ObjectRelation.php`, `apps/api/src/Export/Application/Sync/SyncExportRunner.php`, `apps/api/src/Export/Application/Async/ExportJobHandler.php`, `apps/api/src/Export/Application/Builder/ExportBuilder.php`, `apps/api/src/Catalog/Presentation/Controller/GenerateVariantsController.php`.

## Definition of Done

- [ ] PHPStan max zielony (`cache:warmup --env=dev` + `--memory-limit=1G`).
- [ ] php-cs-fixer przed commitem.
- [ ] PHPUnit ≥80% nowej logiki (`RelationImportStep` — testy jednostkowe bufora i resolve); testy integracyjne na realnym Postgres (no-mocking rule).
- [ ] ApiTestCase dla fan-outu include_variants i importu relacji (`cache:clear --env=test` przed Api/*).
- [ ] Pamięć: pass 2 zapisuje w chunkach przez `flushAndClear()` (custom PHPStan rule flush-bez-clear musi przejść).
- [ ] Regeneracja `docs/api-spec/v0.json` jeśli kontrakt API się zmienia (nowa kolumna `variant_axes` w docs eksportu).
- [ ] Brak zmian UI → Playwright niewymagany (chyba że dotknięty StepMapping — wtedy test mapowania parent_sku).
- [ ] Manual smoke wg sekcji „Jak zwalidować" — artefakt dowodu (output psql + screenshot zakładki wariantów/relacji) w komentarzu zamykającym, także przy zamykaniu #598 (CLOSED MEANS CLOSED).

---

## IMP2-1.9 — feat(import): izolacja błędów per wiersz + naprawa maszyny stanów + semantyka severity (#1472)

**Labels:** backend, bug, enhancement, epik-IMP2 · **Estymata:** 6–8 h · **Zależy od:** IMP2-1.4

## Po co to robimy (kontekst nietechniczny)

Dziś jeden zepsuty wiersz potrafi położyć cały import: niespodziewany błąd przy zapisie paczki (np. duplikat identyfikatora wykryty dopiero przez bazę) oznacza całą sesję jako „failed", mimo że część danych już została zapisana — operator nie wie, co weszło, a co nie. Do tego ostrzeżenia (które z definicji nie powinny blokować) faktycznie blokują wiersze — przez co re-import własnego eksportu do niepustego katalogu odrzuca wszystko. Po tym tickecie: błąd jednego wiersza psuje tylko ten wiersz, sesja z częściowymi błędami kończy się uczciwym statusem „partial" z raportem co dokładnie pominięto, a ostrzeżenia ostrzegają zamiast blokować. Plik z „brudnymi" wierszami (np. wiersze-nagłówki sekcji wewnątrz danych) importuje się z pominięciem śmieci.

## Stan obecny

- `apps/api/src/Import/Domain/ValueObject/ValidationError.php` → `isRowBlocking()` zwraca `true` dla WSZYSTKIEGO poza `CategoryNotFound` — ignoruje pole `level`. Skutek: `DuplicateSkuInDb`, emitowany przez `ImportValidationService` jako `ImportLogLevel::Warning` (`apps/api/src/Import/Application/Service/ImportValidationService.php`, ~linie 214–218), BLOKUJE wiersz. To główny powód „re-import własnego eksportu = 0 zmian" (do czasu trybów z IMP2-1.3; po nich duplikat w DB to normalny kandydat na update, ale semantyka severity nadal musi być poprawna dla pozostałych warningów).
- `apps/api/src/Import/Application/Handler/ImportRunHandler.php` (~linie 237–244): `catch (Throwable)` → `markFailed()` CAŁEJ sesji — także gdy wyjątek poleciał z `flush()` w środku batcha (np. unikalność identifiera z triggera DB, SQLSTATE 23505), z częściowo zapisanymi wcześniejszymi batchami. Brak degradacji per-row, brak rozróżnienia błąd systemowy vs błąd danych.
- `apps/api/src/Import/Domain/Entity/ImportSession.php` → `markCompleted()` już ustawia `Partial` gdy `errorCount > 0` (enum `ImportSessionStatus::Partial` istnieje) — ale ścieżka wyjątku flusha nigdy tam nie dochodzi.
- `apps/api/src/Import/Domain/Enum/ImportLogLevel.php`: `Info|Warning|Error` — gotowa skala severity, nieużywana do blokowania.
- Brak setowych pre-checków duplikatów w chunku: walidator sprawdza duplikaty per wiersz 1–2 SELECT-ami (`skuSeenInFile` array), nie zna duplikatów identifierów w batchu vs DB → kolizja wybucha dopiero na DB.
- Otwarty bug #1455 (FK violation `objects_import_session_fk` przy inline commit, 500) — pokrewna klasa problemu „wyjątek flusha = wywalona sesja".
- Benchmark `Zrodla/Importy przykładowe/e-commerce.xlsx`: wiersze sekcji („POMPY CIEPŁA") WEWNĄTRZ danych — dziś taki wiersz może wywalić walidację typów zamiast zostać policzony jako błąd wiersza.

## Zakres prac

1. **Semantyka severity (redefinicja `isRowBlocking()`)**: blokowanie wynika z `level` zgodnie z ADR IMP2-1.1 — `Error` blokuje wiersz, `Warning`/`Info` NIE blokują (lądują w raporcie). Audit wszystkich miejsc emitujących `ValidationError` pod kątem poprawnego poziomu (`MissingRequired`/`InvalidType`/`InvalidValue` = Error; `CategoryNotFound`/`DuplicateSkuInFile` = Warning zgodnie z D1 „skip kolejnych wystąpień + warning"; `DuplicateSkuInDb` = Warning — po IMP2-1.3 w trybie upsert to w ogóle nie problem, w trybie create = skip+warning).
2. **Setowe pre-checki w chunku** (zamiast odkrycia kolizji na triggerze DB): przed flushem chunku jedno zapytanie zbiorcze sprawdza duplikaty `sku` i wartości atrybutów typu `identifier` — w pliku (bufor), w bieżącym batchu i vs DB (prefetch z IMP2-1.4); kolidujące wiersze dostają błąd/skip per D1 i NIE wchodzą do flusha.
3. **Degradacja do per-row commit**: gdy `flush()` chunku mimo wszystko rzuci niespodziewany wyjątek (DriverException itp.), handler NIE failuje sesji — retry chunku wiersz-po-wierszu (persist+flush per wiersz w nowej jednostce pracy po `clear()`); wiersz-winowajca dostaje `ImportLog` z Error i `errorCount++`, reszta chunku się zapisuje. Wyjątek powtarzający się systemowo (np. utrata połączenia z DB — heurystyka: błąd nie jest przypisywalny do wiersza) → dopiero wtedy `markFailed`.
4. **`partial` zamiast `failed`**: sesja z ≥1 zaimportowanym wierszem i ≥1 błędem kończy przez `markCompleted()` → `Partial` (istniejąca logika); `markFailed` zarezerwowany dla błędów systemowych (plik nieczytelny, brak tenanta, brak mapowania, utrata DB). Komunikat `error_message` przy partial wskazuje liczbę pominiętych wierszy i raport.
5. **Wiersz sekcji/śmieciowy = błąd wiersza, nie abort**: wiersz, który nie ma wartości w kolumnie SKU albo wybucha na materializacji wartości, liczony jako błąd wiersza (Error w `import_logs` z surową zawartością w `columnValue`) — pętla idzie dalej. Test na mini-fixture odtwarzającym strukturę `e-commerce.xlsx` (wiersz tekstowy w środku danych; danych komercyjnych NIE commitujemy — §6 planu).
6. **Testy**: jednostkowe nowej semantyki `isRowBlocking()`; integracyjne (realny Postgres) — duplikat identifiera w pliku → skip+warning bez wybuchu; wymuszony wyjątek flusha → degradacja per-row, sesja `partial`, poprawne liczniki `success_count`/`error_count` spójne z `import_logs`.

## Poza zakresem tego ticketu

- Tryby create/update/upsert i decyzja „duplikat w DB = update" — IMP2-1.3 (Fala A).
- Plik odrzutów do re-importu (surowe wiersze + przyczyny) — etap 3 (IMP2-3.6, dry-run v2).
- Pauza/cancel/resume + checkpoint offsetu — IMP2-2.3.
- Undo-log i rollback v2 — IMP2-2.4.
- Skip-rows jako reguła detekcji (wiersze sekcji wykrywane heurystyką) — IMP2-3.1; tutaj tylko gwarancja, że taki wiersz nie aborts.
- Naprawa #1455 jeśli okaże się osobnym defektem FK poza ścieżką flusha (wtedy link + osobny fix) — ale jeśli root cause to ta sama klasa, domykamy tutaj z dowodem.

## Kryteria akceptacji

- [ ] `ValidationError::isRowBlocking()` zwraca `true` wyłącznie dla `level === Error`; test jednostkowy pokrywa wszystkie kombinacje `ImportErrorType` × `ImportLogLevel`.
- [ ] Import pliku z 10 wierszami, z czego 2 błędne (zły typ, brak SKU): sesja kończy się `partial`, `success_count=8`, `error_count=2`, w `import_logs` dokładnie 2 wpisy Error z numerami wierszy.
- [ ] Duplikat SKU w pliku: pierwsze wystąpienie importuje się, kolejne = skip + Warning (D1); ZERO wyjątków DB.
- [ ] Duplikat wartości atrybutu identifier vs DB wykrywany pre-checkiem setowym przed flushem (test integracyjny z realnym Postgres) — sesja nie failuje, wiersz dostaje błąd.
- [ ] Wymuszony wyjątek flusha w środku batcha (test integracyjny) → degradacja per-row: pozostałe wiersze batcha zapisane, sesja `partial`, nie `failed`.
- [ ] Mini-fixture z wierszem sekcji w środku danych: import kończy się `partial`/`success` z błędem tylko dla tego wiersza.
- [ ] Warning (`CategoryNotFound`) na poprawnym wierszu nie zmniejsza `success_count` (wiersz się importuje).

## Jak zwalidować (smoke test po wykonaniu)

1. `TOKEN=$(curl -sk -X POST https://pim.localhost/api/auth/login -H 'Content-Type: application/json' -d '{"email":"admin@demo.localhost","password":"changeme"}' | jq -r .token)`.
2. Przygotuj `dirty.csv`: 5 wierszy poprawnych, 1 wiersz z pustym SKU, 1 wiersz „SEKCJA --- POMPY CIEPŁA" (tekst w pierwszej kolumnie, reszta pusta), 1 wiersz z duplikatem SKU wiersza 1.
3. `curl -sk -X POST https://pim.localhost/api/import-sessions -H "Authorization: Bearer $TOKEN" -F file=@dirty.csv -F target_object_type_id=<uuid-product-OT> -F 'mapping={"sku":"sku","name":"name"}'` → HTTP 200/201.
4. `GET /api/import-sessions/{id}` → `status: "partial"`, `success_count: 5`, `error_count: 2` (pusty SKU + wiersz sekcji), duplikat = skip+warning (wg D1 liczników z ADR).
5. Pobierz raport sesji (report.csv / `GET import_logs`) → każdy błędny wiersz ma numer wiersza i przyczynę.
6. Benchmark: zaimportuj `Zrodla/Importy przykładowe/e-commerce.xlsx` (mapowanie ręczne minimalne: SKU+nazwa) → sesja kończy się `partial` z błędami tylko na wierszach sekcji; UI sesji (https://pim.localhost) pokazuje liczniki spójne z raportem; konsola DevTools bez czerwonych errorów.
7. Negatywny: zatrzymaj kontener DB w trakcie dużego importu → sesja `failed` z komunikatem systemowym (markFailed nadal działa dla prawdziwych awarii).

## Zależności

- Blokowany przez: IMP2-1.4 (prefetch SKU/identifier per chunk wykorzystywany przez pre-checki setowe; result-based `ImportValueWriter`).
- Blokuje: IMP2-1.10 (test izolacji błędu wiersza w pełnej matrycy).

## Referencje

- `Project Plan/UI/feature-imports-v2.md` §2.1 (Warning blokuje), §2.2 (markFailed w środku batcha), §3 filary 5–6, §4.4 decyzja D1, §5 Fala B wiersz 1.9, §6 (benchmark `e-commerce.xlsx`).
- Issues: #1455 (FK violation przy inline commit — pokrewny), #1130/#1167 (round-trip v1).
- Kod: `apps/api/src/Import/Domain/ValueObject/ValidationError.php`, `apps/api/src/Import/Application/Handler/ImportRunHandler.php`, `apps/api/src/Import/Application/Service/ImportValidationService.php`, `apps/api/src/Import/Domain/Entity/ImportSession.php`, `apps/api/src/Import/Domain/Enum/ImportLogLevel.php`, `apps/api/src/Import/Domain/Enum/ImportSessionStatus.php`.

## Definition of Done

- [ ] PHPStan max zielony (`cache:warmup --env=dev` + `--memory-limit=1G`); reguła flush-bez-clear przechodzi dla ścieżki per-row.
- [ ] php-cs-fixer przed commitem.
- [ ] PHPUnit ≥80% nowej logiki; testy integracyjne na realnym Postgres (no-mocking), `cache:clear --env=test` przed Api/*.
- [ ] Brak zmian kontraktu API → bez regeneracji OpenAPI (jeśli liczniki/statusy w response się zmienią — regeneracja `docs/api-spec/v0.json`).
- [ ] Brak zmian UI → Playwright niewymagany.
- [ ] Manual smoke wg sekcji „Jak zwalidować" — artefakt dowodu (JSON sesji z licznikami + fragment raportu) w komentarzu zamykającym (CLOSED MEANS CLOSED).

---

## IMP2-1.10 — test(import): golden test pełna matryca + pierwsze testy ścieżki async (#1473)

**Labels:** testing, backend, epik-IMP2 · **Estymata:** 8–12 h · **Zależy od:** IMP2-1.5, IMP2-1.6, IMP2-1.7, IMP2-1.8, IMP2-1.9, IMP2-1.6a

## Po co to robimy (kontekst nietechniczny)

Round-trip („wyeksportuj → popraw w Excelu → zaimportuj z powrotem") to fundament całego modułu — ale dziś nic go nie pilnuje. Istniejący test „round-trip" nie uruchamia eksportera, sprawdza tylko walidację na sucho i tylko dla tekstu; ścieżka asynchroniczna (czyli ta, którą pójdzie każdy realny, większy plik) nie ma ANI JEDNEGO testu. Ten ticket domyka Falę B siatką bezpieczeństwa: automatyczny test w CI przegania pełny cykl eksport→import dla wszystkich typów danych, formatów (CSV i XLSX), kanałów, wariantów, relacji i wielu kategorii — żeby każda przyszła zmiana, która psuje round-trip, została wykryta zanim trafi do operatora.

## Stan obecny

- `apps/api/tests/Api/Import/ImportRoundTripApiTest.php` (#1130) — NIE uruchamia eksportera: dry-run ręcznie napisanego 1-wierszowego CSV (`POST /api/import-sessions/validate-dry-run`), bez persystencji, bez porównania wartości. Dryf formatu eksportu nieosłonięty.
- `apps/api/tests/Api/Import/StartImportApiTest.php` — persystencja przetestowana tylko dla atrybutów typu Text (z 17 typów `AttributeType`: text, number, select, multiselect, date, boolean, asset, relation, price, metric, wysiwyg, datetime, reference, textarea, color, email, identifier — `apps/api/src/Catalog/Domain/AttributeType.php`).
- `apps/api/src/Import/Application/Handler/ImportRunHandler.php` — **zero testów** ścieżki async (przez transport Messenger); w dev/test `MESSENGER_TRANSPORT_DSN=sync://` więc dotychczas wszystko wykonywało się in-band; dedykowany transport `import` + worker wchodzi w IMP2-1.6a (`apps/api/config/packages/messenger.yaml`).
- Golden test v0 (IMP2-1.5) pokrywa: realny `SyncExportRunner` → CSV → import z persystencją → równość envelope dla 17 typów + kategorie + status, global+locale, w tym seedowane legacy-shape'y. Fala B dołożyła swoje rozszerzenia per ticket (1.6 kanały, 1.7 multi-kategorie/status, 1.8 warianty/relacje) — ten ticket konsoliduje je w pełną matrycę i dokłada XLSX + async.
- `Attribute` ma flagi `isLocalizable`/`isScopable` (`apps/api/src/Catalog/Domain/Entity/Attribute.php`, linie ~54–56) — matryca scope'ów MUSI być wyprowadzona z tych reguł (atrybut nielokalizowalny nie ma wiersza per locale), a NIE jako kartezjan „17 typów × 4 scope'y".

## Zakres prac

1. **Golden test — pełna matryca** (rozszerzenie/konsolidacja testu z IMP2-1.5, np. `apps/api/tests/Api/Import/ImportExportGoldenTest.php`): eksport realnym `SyncExportRunner` → plik → import z persystencją → porównanie envelope po normalizacji (reguły wersjonowane w ADR IMP2-1.1, minimalne). Matryca: 17 typów × realne kombinacje scope wynikające z `isLocalizable`/`isScopable` (global; locale; channel; locale+channel — tylko dla atrybutów z odpowiednimi flagami) + multi-kategorie (primary+position) + status/enabled + warianty (`include_variants` fan-out, `parent_sku`, `variant_axes`) + relacje (`object_relations`) + galerie asset_ids + seedowane legacy-shape'y (kontrakt D7 z IMP2-1.2).
2. **Wariant XLSX**: ta sama matryca przegoniona przez `ExportFormat::Xlsx` → import XLSX (PhpSpreadsheet do czasu IMP2-2.1; test musi przeżyć wymianę readera). Parametryzacja przez data provider zamiast kopii testu.
3. **Pierwsze testy async `ImportRunHandler`**: test przez transport `import` z IMP2-1.6a (in-memory/test transport Messengera + jawne przetworzenie wiadomości), pokrywający: (a) plik >50 wierszy routuje do `ImportRunMessage` zamiast inline, (b) handler przetwarza sesję end-to-end z poprawnymi licznikami, (c) re-delivery po przerwaniu wznawia od checkpointu bez duplikatów obiektów (kontrakt checkpoint z 1.6a), (d) `BulkOperationInProgressException` → `RecoverableMessageHandlingException` (retry, nie dead-letter).
4. **Testy per-typ persystencji**: dla każdego z 17 `AttributeType` asercja zapisanego envelope w `object_values` po imporcie (dokładny shape wg `docs/api/jsonb-schemas.md` po D7) — domyka lukę „tylko Text".
5. **Test izolacji błędu wiersza** (kontrakt IMP2-1.9): plik z błędnym wierszem w środku → sesja `partial`, pozostałe wiersze zapisane, liczniki spójne z `import_logs`.
6. **Mini-fixtures zanonimizowane**: cechy strukturalne z benchmarków (§6 planu) potrzebne w tych testach odtwarzamy syntetycznie w `apps/api/tests/` — danych komercyjnych z `Zrodla/Importy przykładowe` NIE commitujemy.
7. **Stabilność CI**: golden test musi być deterministyczny (sortowanie wierszy/kolumn przed diffem, kontrola strefy czasowej dat) i mieścić się w budżecie czasowym CI; jeśli pełna matryca > ~60s, podział na osobne test-case'y per obszar.

## Poza zakresem tego ticketu

- Benchmarki wydajności 5k/50k z asercją RAM — IMP2-2.6.
- Testy streaming readers — IMP2-2.1.
- Testy plików benchmarkowych operatora przez kreator (gate etapu 3) — manualna checklista, nie CI.
- Testy media URL/ZIP — IMP2-1.12/1.13.
- Playwright dla wizarda v2 — etap 3 (IMP2-3.7).

## Kryteria akceptacji

- [ ] Golden test CSV: eksport→import→równość envelope dla pełnej matrycy (17 typów × scope z flag `isLocalizable`/`isScopable`) + kategorie + status/enabled + warianty + relacje + galerie — zielony w CI.
- [ ] Golden test XLSX: ta sama matryca przez format XLSX — zielony w CI.
- [ ] Test asercji per-typ: dla każdego z 17 `AttributeType` istnieje asercja dokładnego shape'u JSONB w `object_values` po imporcie.
- [ ] Testy async: ≥4 test-case'y `ImportRunHandler` przez transport `import` (routing >50 wierszy, end-to-end, re-delivery/checkpoint, recoverable na locku) — pierwsze w historii repo (dziś 0).
- [ ] Test izolacji błędu wiersza: sesja `partial` z poprawnymi licznikami.
- [ ] Matryca scope NIE jest kartezjanem — komentarz w teście wyjaśnia wyprowadzenie z reguł scope'owania.
- [ ] Żadnych plików z `Zrodla/Importy przykładowe` w repo (fixtures syntetyczne).
- [ ] Cały suite `tests/Api/Import/` + `tests/Unit/Import/` zielony lokalnie i w CI.

## Jak zwalidować (smoke test po wykonaniu)

1. `docker compose exec api php bin/console cache:clear --env=test` (ochrona dev DB — lekcja PHPUnit/Foundry).
2. `docker compose exec api vendor/bin/phpunit --filter ImportExportGolden` → wszystkie przypadki matrycy zielone; zanotuj liczbę asercji.
3. `docker compose exec api vendor/bin/phpunit --filter ImportRunHandler` → testy async zielone.
4. `docker compose exec api vendor/bin/phpunit tests/Api/Import tests/Unit/Import` → komplet zielony.
5. Mutacyjna próba kontraktu (ręczna, dowód siatki): tymczasowo zepsuj `ValueSerializer` (np. zamień separator `|` na `;`) → golden test MUSI zfailować; revert.
6. CI: pełny pipeline na branchu zielony; czas suite'u importowego w akceptowalnym budżecie (porównaj z main).
7. Live-stack sanity (gate etapu 1): eksport realnego katalogu demo na https://pim.localhost → edycja 3 wartości + 1 nowy wiersz + 1 pusta komórka w Excelu → import → poprawny diff w UI, brak czerwonych błędów w konsoli (artefakt: screenshot + liczniki sesji).

## Zależności

- Blokowany przez: IMP2-1.5 (golden v0), IMP2-1.6 (kanały), IMP2-1.7 (multi-kategorie/status), IMP2-1.8 (warianty/relacje), IMP2-1.9 (izolacja błędów), IMP2-1.6a (transport `import` dla testów async).
- Blokuje: gate etapu 1 (golden testy zielone w CI to warunek bramki) i pośrednio start etapu 2.

## Referencje

- `Project Plan/UI/feature-imports-v2.md` §2.5 (brak strażnika kontraktu), §5 Fala B wiersz 1.10, §8 kryterium 1 (golden round-trip w CI), §6 (zasada zanonimizowanych mini-fixtures).
- Issues: #1130 (test który nie strzegł kontraktu — precedens), #1167.
- Kod: `apps/api/tests/Api/Import/ImportRoundTripApiTest.php`, `apps/api/tests/Api/Import/StartImportApiTest.php`, `apps/api/src/Import/Application/Handler/ImportRunHandler.php`, `apps/api/src/Catalog/Domain/AttributeType.php`, `apps/api/src/Catalog/Domain/Entity/Attribute.php` (isLocalizable/isScopable), `apps/api/config/packages/messenger.yaml`, `docs/api/jsonb-schemas.md`.

## Definition of Done

- [ ] PHPStan max zielony także dla plików testowych (`cache:warmup --env=dev` przed runem — lekcja „green locally, red in CI"; `--memory-limit=1G`).
- [ ] php-cs-fixer przed commitem.
- [ ] Testy: to JEST ticket testowy — PHPUnit + ApiTestCase wyłącznie, bez Pest/Behat; testy integracyjne na realnym Postgres; `cache:clear --env=test` przed Api/*.
- [ ] Bez zmian API/UI → bez regeneracji OpenAPI i bez Playwright (jeśli jednak coś się zmieni — standardowe kroki).
- [ ] Manual smoke wg sekcji „Jak zwalidować" pkt 7 (live-stack round-trip) — artefakt dowodu (screenshot diffu + liczniki sesji + link do zielonego CI runu) w komentarzu zamykającym (CLOSED MEANS CLOSED).

---

## IMP2-1.11 — chore(import): higiena backlogu importów (#1474)

**Labels:** docs, epik-IMP2 · **Estymata:** 1–2 h · **Zależy od:** brak

## Po co to robimy (kontekst nietechniczny)

Backlog importów kłamie: osiem otwartych ticketów to w rzeczywistości cztery (każdy istnieje w dwóch identycznych kopiach), jeden z nich opisuje funkcję, która działa od miesięcy, a ticket „round-trip naprawiony" został zamknięty na podstawie testu, który nie testował round-tripu. Do tego komentarz w kodzie twierdzi, że silnik robi „upsert", podczas gdy umie tylko tworzyć nowe obiekty. Każda z tych nieścisłości kosztuje czas przy planowaniu i podważa zaufanie do tablicy. Ten ticket robi porządek: zamyka duplikaty i rzeczy zrobione, prostuje fałszywe komentarze i oznacza stary plan importów jako zastąpiony przez v2 — żeby każdy (operator i kolejne sesje agenta) widział prawdziwy stan.

## Stan obecny

- **Duplikaty**: #598/#599/#600/#601 (IMP-16..19) i #602/#603/#604/#605 (te same IMP-16..19 z dopiskiem „EXP-02 follow-up") — wszystkie 8 OPEN, treściowo identyczne pary (zweryfikowane 2026-06-12: tytuły i zakresy 1:1).
- **IMP-17 de-facto done**: pipe-split multiselect działa od PR #1167 — `apps/api/src/Import/Application/Service/ImportObjectCreator.php` → `multiSelectPayload()` splituje `|` na `option_codes`. Z zakresu IMP-17 została wyłącznie walidacja `option_codes` przeciw żywym `AttributeOption` — ta wchodzi w rdzeń walidatorów IMP2-1.4 (ekstrakcja z `ObjectAttributesUpserter`).
- **#1130 closed-but-broken**: zamknięty jako „round-trip naprawiony", ale strażnikiem był `apps/api/tests/Api/Import/ImportRoundTripApiTest.php`, który NIE uruchamia eksportera (dry-run ręcznego 1-wierszowego CSV, bez persystencji) — poziom dowodu nie odpowiadał claimowi. Precedens „closed means closed" wymaga sprostowania w historii issue.
- **Fałszywy docblock**: `apps/api/src/Import/Domain/Enum/ImportMode.php` (linie ~10–12): „The worker today always upserts; ADD / DELETE are reserved..." — fałsz: silnik jest create-only (`ImportObjectCreator` zawsze `new CatalogObject`, żadna ścieżka nie czyta `ImportProfile.mode`) do czasu IMP2-1.3. Dodatkowo `apps/api/src/Import/Presentation/Controller/ListImportSessionsController.php` (linia ~142) hardcoduje `'mode' => 'UPDATE'` w response (usuwane w IMP2-1.3 — tu tylko odnotowanie w issue-mapie).
- **Stary plan**: `Project Plan/UI/feature-imports.md` (spec v1, IMP-01..15 + VIEW-IMP-00..05) — bez banneru, że silnik jest superseded przez `feature-imports-v2.md`; ryzyko, że kolejna sesja agenta zaimplementuje coś wg v1.
- NUI-10/#1429 i NUI-11/#1430 — już CLOSED (completed); plan v2 §9 pkt 2 przewidywał ich zamknięcie jako superseded przy zleceniu 3.7 — stan faktyczny do odnotowania w mapie backlogu (bez akcji, tylko weryfikacja że nie ma trzeciej kopii zakresu w OPEN).

## Zakres prac

1. **Dedupe**: zamknięcie kompletu #602/#603/#604/#605 jako `duplicate` z komentarzem-linkiem do odpowiednika (#598/#599/#600/#601) i do mapy IMP2 (gdzie zakres jest realizowany).
2. **Zamknięcie IMP-17 (#599)** jako de-facto done: komentarz z dowodem — link do PR #1167 + wskazanie `ImportObjectCreator::multiSelectPayload()` + jawna notka: „pozostała walidacja option_codes → realizowana w IMP2-1.4 (link)". Zgodnie z CLOSED MEANS CLOSED dowód = link do kodu w main + smoke (komórka `red|blue` importuje się jako `{option_codes:[red,blue]}` — patrz sekcja walidacji).
3. **Mapowanie pozostałych**: komentarz w #598 („zakres przejmuje IMP2-1.8 — tam zostanie zamknięty z dowodem") i w #600 („zakres przejmuje IMP2-1.12"); weryfikacja #601 (IMP-19 multi-locale): suffix `.locale` działa od #1130/#1167 (`ColumnHeader::localeOf`), a gramatykę domyka IMP2-1.6 — jeśli zakres #601 jest w całości pokryty, zamknąć z dowodem; jeśli nie, komentarz-mapping do IMP2-1.6.
4. **Re-audit close-comentu #1130**: komentarz-sprostowanie w zamkniętym issue: zamknięcie nastąpiło na poziomie dry-run (test nie uruchamiał eksportera, nie persystował, nie porównywał wartości) — realny round-trip z persystencją dostarcza golden test IMP2-1.5, pełna matryca IMP2-1.10 (linki). Issue zostaje closed (zakres v1 historyczny), ale historia przestaje sugerować, że round-trip był wtedy dowiedziony.
5. **Korekta docblocka `ImportMode`**: opis zgodny z prawdą — „engine is create-only; mode is decorative until IMP2-1.3 lands; enum will be reduced to CREATE/UPDATE/UPSERT with data migration in IMP2-1.3" (komentarz po angielsku, zgodnie z konwencją kodu).
6. **Banner w `Project Plan/UI/feature-imports.md`**: na górze pliku sekcja „⚠️ SUPERSEDED — silnik importu przebudowywany wg `feature-imports-v2.md` (2026-06-12); sekcje silnika (IMP-01..15) historyczne, nie implementować wg tej wersji" + link do v2 i do mapy ticketów IMP2.
7. **Mapa backlogu w komentarzu epika**: jeden komentarz zbiorczy (w issue epika IMP2 lub w #598) z tabelą: stary ticket → status → następca IMP2-* (w tym notka o #1429/#1430 closed-completed i delcie przejętej przez 3.7).

## Poza zakresem tego ticketu

- Usunięcie hardcoded `mode='UPDATE'` z `ListImportSessionsController` i redukcja enum + migracja `import_profiles.mode` — IMP2-1.3 (kod, nie higiena).
- Walidacja `option_codes` przeciw `AttributeOption` — IMP2-1.4.
- Zamknięcie #598 (IMP-16) — następuje w IMP2-1.8 z dowodem.
- Zamknięcie #600 (IMP-18) — następuje w IMP2-1.12.
- Jakiekolwiek zmiany zachowania silnika — wyłącznie komentarze/dokumentacja/issues.

## Kryteria akceptacji

- [ ] #602, #603, #604, #605 zamknięte jako duplicate, każdy z komentarzem-linkiem do odpowiednika i mapy IMP2.
- [ ] #599 (IMP-17) zamknięty z dowodem (link PR #1167 + kod + wynik smoke) i notką o walidacji option_codes → IMP2-1.4.
- [ ] #598 i #600 mają komentarz-mapping do IMP2-1.8 / IMP2-1.12 (pozostają OPEN); #601 zamknięty z dowodem ALBO zmapowany do IMP2-1.6 (decyzja udokumentowana w komentarzu).
- [ ] #1130 ma komentarz-sprostowanie z linkami do IMP2-1.5/IMP2-1.10.
- [ ] Docblock `ImportMode.php` nie zawiera już zdania „worker today always upserts"; nowy opis odzwierciedla create-only + plan redukcji enum (PR z tą zmianą zmergowany).
- [ ] `Project Plan/UI/feature-imports.md` ma banner superseded na samej górze, z linkiem do `feature-imports-v2.md`.
- [ ] Komentarz-mapa backlogu opublikowany; liczba OPEN ticketów importowych ze starego kompletu spadła z 8 do max 3 (#598, #600, ewentualnie #601).

## Jak zwalidować (smoke test po wykonaniu)

1. `gh issue list --state open --search "IMP-1 in:title" --json number,title` → brak duplikatów; tylko #598, #600 (i ewentualnie #601) otwarte z kompletu IMP-16..19.
2. `gh issue view 599 --json state,comments` → CLOSED, ostatni komentarz zawiera link do PR #1167 i ścieżkę `ImportObjectCreator::multiSelectPayload`.
3. Dowód do zamknięcia #599 (jeśli nie ma świeżego): `TOKEN=$(curl -sk -X POST https://pim.localhost/api/auth/login -H 'Content-Type: application/json' -d '{"email":"admin@demo.localhost","password":"changeme"}' | jq -r .token)`; CSV z komórką multiselect `red|blue` → `POST /api/import-sessions` → `psql: SELECT value FROM object_values ...` → `{"option_codes": ["red", "blue"]}`; output do komentarza.
4. `gh issue view 1130 --json comments` → komentarz-sprostowanie obecny.
5. `git log -1 -- apps/api/src/Import/Domain/Enum/ImportMode.php` + podgląd pliku → docblock skorygowany; `head -15 "Project Plan/UI/feature-imports.md"` → banner widoczny.
6. Mapa backlogu: link do komentarza zbiorczego działa i wymienia wszystkie 8 starych ticketów + #1130 + #1429/#1430.

## Zależności

- Blokowany przez: nic (czysta higiena — można wykonać w dowolnym momencie Fali B; sprostowanie #1130 najlepiej linkować do już istniejącego golden testu IMP2-1.5).
- Blokuje: nic twardo; ułatwia IMP2-1.8 (czysty stan #598/#602) i planowanie etapu 2/3.

## Referencje

- `Project Plan/UI/feature-imports-v2.md` §2.1 (trzy wersje prawdy), §2.5 (#1130 nie strzeże kontraktu), §5 Fala B wiersz 1.11, stopka planu (banner w feature-imports.md).
- Issues: #598–#605, #1130, #1167 (PR), #1429, #1430.
- Kod/pliki: `apps/api/src/Import/Domain/Enum/ImportMode.php`, `apps/api/src/Import/Application/Service/ImportObjectCreator.php`, `apps/api/src/Import/Presentation/Controller/ListImportSessionsController.php`, `apps/api/tests/Api/Import/ImportRoundTripApiTest.php`, `Project Plan/UI/feature-imports.md`.

## Definition of Done

- [ ] Zmiany kodu ograniczone do komentarzy (docblock) — PHPStan max zielony, php-cs-fixer przed commitem; bez nowych testów (brak nowej logiki).
- [ ] Zmiana w `Project Plan/UI/feature-imports.md` scommitowana w tym samym PR (konwencja: dokumentacja planu w repo, po polsku).
- [ ] Wszystkie operacje `gh issue close` z artefaktem dowodu w komentarzu zamykającym (CLOSED MEANS CLOSED — dla #599 output psql z envelope; dla duplikatów wystarczy link do oryginału, bo to closure typu duplicate, nie completed).
- [ ] Brak zmian API/UI → bez OpenAPI regen i bez Playwright.
- [ ] `agent/current_status.md` zaktualizowany po wykonaniu (porządek backlogu odnotowany).

---

## IMP2-1.12 — feat(import,asset): pobieranie zdjęć z URL-i (media w imporcie, część 1) (#1475)

**Labels:** backend, enhancement, security, epik-IMP2 · **Estymata:** 8–12 h · **Zależy od:** IMP2-1.4

## Po co to robimy (kontekst nietechniczny)

Pliki od dostawców (np. Avapax) mają w kolumnach linki do zdjęć produktów. Dziś import te linki **psuje**: zapisuje surowy adres URL w miejscu, gdzie system oczekuje identyfikatora pliku — produkt wygląda na „zepsuty", zdjęcie nigdy się nie pojawia, a edycja w UI potem zwraca błędy. Po tym tickecie import sam pobierze zdjęcia z internetu, doda je do biblioteki multimediów (bez duplikatów — ten sam plik trafia do bazy tylko raz) i podepnie pod właściwe produkty. Liczniki „pobrano / nie udało się" na ekranie sesji importu zaczną pokazywać prawdę.

## Stan obecny

- **ZERO kodu pobierania zdjęć** — świadome odejście z v1 (IMP-04). Brak jakiegokolwiek handlera/serwisu HTTP-download w `apps/api/src/Import/`.
- `apps/api/src/Import/Application/Service/ImportObjectCreator.php` (linia ~126): `AttributeType::Asset => ['asset_id' => $raw]` — surowa zawartość komórki (czyli URL) ląduje w JSONB jako `asset_id`. To jest korupcja danych opisana w IMP-18 (#600/#604).
- `apps/api/src/Import/Domain/Entity/ImportSession.php`: pola `imagesDownloaded`/`imagesFailed` (linie 62–64) oraz metody `incrementImagesDownloaded()`/`incrementImagesFailed()` (linie ~210–218) istnieją, ale **nic ich nie woła** — martwe od dnia 1.
- `apps/api/src/Import/Domain/Enum/ImportImageSource.php` — enum `Http`/`Zip`/`None` istnieje, martwy (wizard trzyma `imageSource` tylko w localStorage — `apps/admin/src/features/imports/hooks/useImportWizard.ts` linia 41; `StepConfirm.tsx` nie wysyła go w FormData).
- `apps/api/src/Import/Domain/Enum/ImportErrorType.php` — case'y `ImageNotFound` i `ImageFormatUnsupported` istnieją od zawsze, **nigdy nie są emitowane**.
- Infrastruktura do reużycia istnieje: `apps/api/src/Asset/Application/AssetUploader.php` (sha256 `contentHash` + dedupe przez `AssetRepositoryInterface::findByContentHash()` + `DuplicateAssetException`), `apps/api/src/Asset/Domain/Entity/Asset.php` (pole `contentHash`, linia 67), `apps/api/src/Catalog/Application/ProductAssetLinkerService.php` + kontrakt `apps/api/src/Catalog/Contracts/Service/ProductAssetLinker.php` (`linkAssetsToProduct(Uuid $productId, array $assetIds)` → tabela `product_assets`), `apps/api/src/Shared/Application/AbstractBatchHandler.php`.

## Zakres prac

1. **`AssetUrlResolver`** (`apps/api/src/Import/Application/Service/Media/AssetUrlResolver.php`) — rozstrzyga zawartość komórki typu Asset: (a) UUID → istniejący Asset **tenant-scoped** (lookup przez repo z TenantFilter; UUID z innego tenanta = błąd walidacji, NIE zapis — reject cross-tenant per D5), (b) `http(s)://...` → job pobrania, (c) inne (gołe nazwy plików) → w tej części błąd `ImageNotFound` z komunikatem wskazującym na ZIP (IMP2-1.13) lub transformację prefix/suffix (IMP2-3.4).
2. **Split listy URL-i w jednej komórce**: separatory `|` (glue eksportera), `;`, `,`, białe znaki/nowe linie — każdy token zaczynający się od `http(s)://` to osobny job (benchmark: `products_export_20260209_201836.xls` ma listy URL-i w jednej komórce).
3. **`ImageDownloadMessage` + `ImageDownloadHandler`** (`apps/api/src/Import/Application/Handler/ImageDownloadHandler.php`, dziedziczy z `AbstractBatchHandler` — flush+clear w pętli, reguła PHPStan flush-bez-clear). Routing na transport `import` (z IMP2-1.6a) w `apps/api/config/packages/messenger.yaml`. Limity twarde: **concurrency cap ~10 równoległych pobrań** (Symfony HttpClient stream multiplexing), **timeout 30 s**, **max 3 redirecty**, **max 10 MB/plik**, **content-type sniff** (magic bytes, nie nagłówek serwera): akceptowane `jpg/jpeg/png/webp`, reszta → `ImageFormatUnsupported`.
4. **Guard SSRF** (koordynacja z IMP2-2.8): tylko schematy http/https; host rozwiązujący się na adres prywatny/loopback/link-local (RFC1918, 127.0.0.0/8, 169.254.0.0/16, ::1) → reject z `ImageNotFound` + log. 
5. **Dedupe po `content_hash`**: pobrany plik → sha256 → `findByContentHash()` → reuse istniejącego Assetu; nowy przez `AssetUploader::upload()` (złapać `DuplicateAssetException` → reuse). Cache URL→assetId w pamięci handlera (powtórzone URL-e między wierszami w batchu).
6. **Integracja z pipeline'em**: `ImportValueWriter` (IMP2-1.4) NIE zapisuje już surowego URL jako `asset_id` — joby medialne buforowane per chunk i dispatchowane po zapisie wierszy (faza `media` po `db_write`); handler po pobraniu zapisuje kanoniczny envelope `{asset_id}` (lista → shape galerii zgodny z IMP2-1.8 / `docs/api/jsonb-schemas.md`) z `provenance=import` i linkuje przez `ProductAssetLinkerService`. Sesja domyka się po ostatnim batchu mediów (licznik pending batches na sesji, dekrementowany; przy `imageSource=none`/braku URL-i faza pomijana — zachowanie jak dziś). Błędy mediów NIE failują sesji (warning; sesja kończy jako `success`/`partial`).
7. **Liczniki i błędy zaczynają działać**: `incrementImagesDownloaded()`/`incrementImagesFailed()` wołane per plik; błędy emitowane do `import_logs` jako `ImportErrorType::ImageNotFound` (HTTP ≥400/timeout/redirect-limit/SSRF) i `ImageFormatUnsupported` (zły format/oversize >10 MB) z URL-em i numerem wiersza w payloadzie.
8. **Testy**: unit `AssetUrlResolver` (UUID same-tenant / cross-tenant reject / split listy), unit handlera z mockiem HttpClient (sukces, 404, timeout, zły content-type, oversize, dedupe), integracyjny ApiTestCase: import CSV z kolumną URL → Asset utworzony, `product_assets` ma wpis, envelope `{asset_id}` poprawny, liczniki sesji > 0.

## Poza zakresem tego ticketu

- Zdjęcia z pliku ZIP i nazwy plików względne — **IMP2-1.13**.
- Transformacje prefix/suffix do budowy URL z gołej nazwy pliku (Tubądzin) — **IMP2-3.4**.
- Wybór arkusza XLSX i offset wiersza nagłówka w kreatorze — **IMP2-3.1** (smoke test poniżej obchodzi to ręcznym wycięciem arkusza).
- Walidacja istniejących `asset_id` + split pipe dla galerii w ścieżce nie-URL — **IMP2-1.8**.
- Generowanie miniatur — istniejący flow `AssetThumbnailsRequested` działa bez zmian.

## Kryteria akceptacji

- [ ] Import pliku z kolumną zmapowaną na atrybut typu Asset zawierającą URL **nie zapisuje** surowego URL w `object_values` (envelope zawiera wyłącznie poprawny UUID istniejącego Assetu).
- [ ] Pobrany plik tworzy rekord `Asset` z `contentHash`; drugi import tego samego URL **nie tworzy duplikatu** (reuse po hashu).
- [ ] Wpis w `product_assets` łączy produkt z Assetem (widoczny w UI produktu w zakładce mediów).
- [ ] Komórka z listą URL-i rozdzielona — N URL-i → N assetów podpiętych do produktu.
- [ ] `imagesDownloaded`/`imagesFailed` na `GET /api/import-sessions/{id}` pokazują realne liczby.
- [ ] Błędny URL (404) produkuje wpis `import_logs` typu `image_not_found`; plik .gif/.bmp → `image_format_unsupported`; w obu przypadkach sesja kończy się `success`/`partial`, nie `failed`.
- [ ] UUID assetu z innego tenanta jest odrzucany (test izolacji przechodzi).
- [ ] URL do hosta prywatnego (np. `http://127.0.0.1/x.png`) odrzucony bez wykonania requestu.
- [ ] PHPUnit: nowa logika pokryta ≥80%; ApiTestCase end-to-end zielony.

## Jak zwalidować (smoke test po wykonaniu)

1. `docker compose exec api php bin/console cache:clear --env=test` (lekcja: Foundry vs dev DB), potem `docker compose exec api php vendor/bin/phpunit --filter ImageDownload`.
2. Z benchmarku `Zrodla/Importy przykładowe/Avapax nowości 24.03.2026.xlsx` otwórz arkusz **Pliki** (4. arkusz; pełne URL-e `b2b.avapax.eu/...png`) i zapisz go jako osobny plik CSV; usuń nadmiarowy drugi wiersz nagłówka (sheet-picker i data-start-row to IMP2-3.1).
3. Zaloguj się na `https://pim.localhost` (admin@demo.localhost / changeme) → Importy → kreator: wgraj CSV, zmapuj kolumnę `kod` → SKU i kolumnę z URL-ami → atrybut typu Asset, uruchom import.
4. Na widoku sesji (`/integrations/imports/{id}`): liczniki zdjęć rosną; po zakończeniu `imagesDownloaded` > 0. DevTools Network: `GET /api/import-sessions/{id}` → 200 z licznikami; Console bez czerwonych błędów.
5. Otwórz dowolny zaimportowany produkt → zakładka mediów pokazuje pobrane zdjęcie; w bibliotece Assets plik istnieje raz (powtórzony URL nie zduplikował pliku).
6. Negatywny: CSV z URL-em `https://b2b.avapax.eu/nieistnieje.png` → sesja `partial`/`success` z `imagesFailed=1` i wpisem `image_not_found` w logu sesji.

## Zależności

- Blokowany przez: **IMP2-1.4** (punkt integracji to `ImportValueWriter`, nie legacy `ImportObjectCreator`). Korzysta z transportu `import` z **IMP2-1.6a** (wcześniej w fali A etapu 1).
- Blokuje: **IMP2-1.13** (ZIP reużywa resolvera, dedupe i liczników).

## Referencje

- `Project Plan/UI/feature-imports-v2.md` §5 etap 1 fala B (ticket 1.12), §4.4 decyzje D5 (assety same-tenant), D11, §6 (benchmark Avapax / products_export), §9 pkt 6 (media od razu w etapie 1).
- Issues: **#600**, **#604** (IMP-18 — ten ticket je zamyka), #598–605 (backlog v1), #1130.
- Kod: `apps/api/src/Import/Application/Service/ImportObjectCreator.php`, `apps/api/src/Asset/Application/AssetUploader.php`, `apps/api/src/Catalog/Application/ProductAssetLinkerService.php`, `apps/api/src/Shared/Application/AbstractBatchHandler.php`, `docs/api/jsonb-schemas.md`.

## Definition of Done

- [ ] PHPStan max zielony (`cache:warmup --env=dev` przed `composer phpstan`, `--memory-limit=1G`).
- [ ] PHPUnit ≥80% pokrycia nowej logiki; ApiTestCase dla ścieżki import-z-URL.
- [ ] php-cs-fixer przed commitem (husky i tak utnie commit).
- [ ] Bez zmian w publicznym API poza licznikami już obecnymi w response — jeśli response sesji się zmienia, regeneracja `docs/api-spec/v0.json` (`cache:warmup` + `api:openapi:export`).
- [ ] Manual smoke wg sekcji „Jak zwalidować" — **artefakt dowodu w komentarzu zamykającym** (HTTP code + JSON liczników + screenshot produktu ze zdjęciem). CLOSED MEANS CLOSED.
- [ ] Zamknięcie #600 i #604 z tym samym dowodem.

---

## IMP2-1.13 — feat(import,asset): zdjęcia z pliku ZIP (media w imporcie, część 2) (#1476)

**Labels:** backend, frontend, enhancement, security, epik-IMP2 · **Estymata:** 6–8 h · **Zależy od:** IMP2-1.12

## Po co to robimy (kontekst nietechniczny)

Nie każdy dostawca daje linki do zdjęć — często przysyła paczkę ZIP ze zdjęciami i plik Excela, w którym komórka zawiera tylko nazwę pliku (np. Tubądzin: `Albiano_598_598_F1`). Kreator importu **już dziś ma pole „dodaj ZIP"**, ale to atrapa: wybrany plik nigdy nie opuszcza przeglądarki. Po tym tickecie ZIP realnie wjedzie na serwer, zdjęcia zostaną z niego wyciągnięte i podpięte pod produkty po nazwie pliku — bez zapychania pamięci serwera nawet przy paczce 500 MB.

## Stan obecny

- `apps/admin/src/features/imports/hooks/useImportWizard.ts` — stan `zipFile: File | null` (linia 37); `persist()` go pomija, a **`StepConfirm.tsx` buduje FormData tylko z `file`, `target_object_type_id`, `mapping`, `encoding`, `delimiter`, `do_backup`** — ZIP nigdy nie jest wysyłany. Picker w `apps/admin/src/features/imports/wizard/StepSource.tsx` (linie ~341–344) to czysta atrapa.
- `apps/api/src/Import/Domain/Entity/ImportSession.php` — pola `zipFileName`/`zipFileSizeBytes` (linie 48–50) martwe (nigdy nie ustawiane poza defaultem null).
- `apps/api/src/Import/Presentation/Controller/StartImportController.php` — przyjmuje tylko jeden plik (`file`), streamuje go (fopen na pathname, linia ~123); brak obsługi drugiego pola multipart.
- `apps/api/src/Import/Domain/Enum/ImportImageSource.php` — case `Zip` istnieje, martwy.
- Po IMP2-1.12 istnieją: `AssetUrlResolver`, dedupe po `content_hash`, liczniki `imagesDownloaded/Failed`, błędy `ImageNotFound`/`ImageFormatUnsupported`, link przez `ProductAssetLinkerService`.
- Flysystem + MinIO skonfigurowane (`apps/api/config/packages/flysystem.yaml`, `league/flysystem-bundle` w `apps/api/composer.json`).

## Zakres prac

1. **Upload ZIP w wizardzie**: `StepConfirm.tsx` dokłada do FormData pole `zip_file` (oraz `image_source` z enumem `http|zip|none` — koniec martwego stanu); walidacja FE: rozszerzenie `.zip`, rozmiar ≤500 MB, czytelny komunikat błędu (i18n przez `t()`).
2. **`StartImportController`**: przyjmuje opcjonalny `zip_file` (multipart), zapisuje go **streamem** do MinIO przez Flysystem obok pliku danych (prefiks tenanta), ustawia `zipFileName`/`zipFileSizeBytes` + `imageSource` na sesji. Limit 500 MB egzekwowany serwerowo (413/422 z RFC 7807). Sprawdzić limit body w Caddy (koordynacja z IMP2-2.8) — jeśli niższy, podnieść w `Caddyfile` dla `/api/import-sessions`.
3. **`ZipImageExtractor`** (`apps/api/src/Import/Application/Service/Media/ZipImageExtractor.php`): otwiera archiwum przez `ZipArchive`, czyta **pojedyncze wpisy streamem** (`getStream()` per entry, kopiowanie do pliku tymczasowego chunkami) — NIGDY `extractTo()` całości ani wczytanie archiwum do RAM. Indeks nazw budowany raz: pełna ścieżka względna + basename, **case-insensitive**, z normalizacją Unicode **NFC/NFD** (polskie znaki w nazwach z macOS/Windows muszą się matchować).
4. **Sanityzacja nazw**: odrzucenie wpisów z `../`, ścieżek absolutnych, symlinków; **zip-bomb guard**: cap liczby wpisów, cap łącznego rozmiaru po dekompresji, cap współczynnika kompresji per wpis — przekroczenie = błąd systemowy sesji z czytelnym komunikatem (koordynacja z IMP2-2.8, żeby nie zdublować implementacji).
5. **Matching komórki**: gdy `imageSource=zip`, wartość komórki typu Asset = nazwa pliku w ZIP (dokładna nazwa lub ścieżka względna; case-insensitive). `AssetUrlResolver` (z IMP2-1.12) dostaje nową gałąź: lookup w indeksie ZIP → ekstrakcja → ta sama ścieżka co pobrany plik HTTP (sha256 → dedupe → `AssetUploader` → link `ProductAssetLinkerService` → envelope `{asset_id}`); brak pliku w ZIP → `ImageNotFound` (warning, nie failuje sesji). Content-type sniff jak w IMP2-1.12 (jpg/png/webp).
6. **Liczniki**: te same `imagesDownloaded`/`imagesFailed` (semantyka: „pozyskane z ZIP" wlicza się w downloaded).
7. **Cleanup**: po terminalnym statusie sesji ZIP usuwany z MinIO i pliki tymczasowe kasowane (finally w handlerze); porzucone ZIP-y łapie TTL staged plików (IMP2-2.2 — tu wystarczy delete-po-imporcie).
8. **Testy**: unit extractora (fixture mini-ZIP w testach: nazwa z polskimi znakami, podkatalog, plik nie-obrazek, wpis `../evil`), test zip-bomba (spreparowany wpis o dużym ratio), ApiTestCase: multipart start z ZIP → asset utworzony i podpięty, `zipFileName` ustawione.

## Poza zakresem tego ticketu

- Transformacje prefix/suffix budujące nazwę pliku z wartości komórki (Tubądzin ma w komórce nazwę bez rozszerzenia — pełny komfort da **IMP2-3.4**; tu wymagamy dokładnej nazwy pliku w komórce).
- Staged upload 1× dla pliku danych + TTL cleanup — **IMP2-2.2**.
- Pozostałe guardy plikowe (CSV injection, whitelist folder-probe) — **IMP2-2.8**.
- Pobieranie z URL — zrobione w **IMP2-1.12**.

## Kryteria akceptacji

- [ ] Wybranie ZIP w kreatorze skutkuje realnym uploadem (DevTools Network: multipart z `zip_file`; response 201).
- [ ] `GET /api/import-sessions/{id}` zwraca `zipFileName` i `zipFileSizeBytes` ≠ null dla sesji z ZIP-em.
- [ ] Komórka `zdjecie1.jpg` podpina plik `Zdjecie1.JPG` z podkatalogu ZIP (case-insensitive + ścieżki względne) — asset widoczny na produkcie.
- [ ] Nazwa z polskimi znakami (`żółć.png`) matchuje niezależnie od normalizacji NFC/NFD.
- [ ] Wpis `../etc/passwd` w ZIP jest odrzucony; spreparowany zip-bomb przerywa sesję czytelnym błędem systemowym (nie OOM).
- [ ] ZIP >500 MB odrzucony na FE i na API (422/413, RFC 7807).
- [ ] Brak pliku w ZIP → `image_not_ound`… → wpis `image_not_found` w `import_logs`, `imagesFailed`+1, sesja `partial`/`success`.
- [ ] Peak RAM procesu importu z ZIP ~100 MB nie rośnie o rozmiar archiwum (ekstrakcja streamem — asercja w teście lub pomiar `memory_get_peak_usage`).
- [ ] Po zakończeniu sesji ZIP nie wisi w MinIO (bucket sprawdzony), temp wyczyszczony.

## Jak zwalidować (smoke test po wykonaniu)

1. Przygotuj benchmark koncepcyjny: z `Zrodla/Importy przykładowe/Tubądzin MBL nowości 19.02.2026.xlsx` weź 3–5 wierszy do CSV (kolumny: kod, nazwa, zdjęcie) i w kolumnie zdjęcia wpisz **dokładne nazwy plików** (np. `Albiano_598_598_F1.jpg`); spakuj 3 dowolne JPG-i pod tymi nazwami do `test.zip` (transform prefix/suffix to IMP2-3.4 — tu dokładna nazwa wystarcza).
2. `https://pim.localhost` (admin@demo.localhost / changeme) → kreator importu: wgraj CSV + w sekcji źródła zdjęć wybierz ZIP i wgraj `test.zip`; zmapuj kolumny; start.
3. DevTools Network: POST `/api/import-sessions` zawiera część `zip_file`; response 201. Console bez czerwonych błędów.
4. Widok sesji: `imagesDownloaded=3`; produkty mają podpięte zdjęcia (otwórz jeden — miniatura się renderuje).
5. Negatywny: usuń jeden plik z ZIP i powtórz — `imagesFailed=1`, log `image_not_found` z nazwą brakującego pliku, sesja `partial`/`success`.
6. `docker compose exec api php vendor/bin/phpunit --filter ZipImage` — zielone (w tym test traversal i zip-bomb).

## Zależności

- Blokowany przez: **IMP2-1.12** (resolver, dedupe, liczniki, błędy — reużywane).
- Blokuje: pełen komfort benchmarku Tubądzin domyka **IMP2-3.4** (transformacje).

## Referencje

- `Project Plan/UI/feature-imports-v2.md` §5 etap 1 fala B (ticket 1.13), §2.7 (fałszywe affordancje: `zipFile` nie opuszcza przeglądarki), §4.4 D13 (limity plików), §6 (Tubądzin), §9 pkt 6.
- Issues powiązane: #598–605 (media w backlogu v1), #1429/#1430 (NUI-10/11 — UI kreatora, superseded przez etap 3).
- Kod: `apps/admin/src/features/imports/wizard/StepSource.tsx`, `apps/admin/src/features/imports/wizard/StepConfirm.tsx`, `apps/admin/src/features/imports/hooks/useImportWizard.ts`, `apps/api/src/Import/Presentation/Controller/StartImportController.php`, `apps/api/src/Import/Domain/Entity/ImportSession.php`, `apps/api/config/packages/flysystem.yaml`.

## Definition of Done

- [ ] PHPStan max zielony (`cache:warmup --env=dev` + `--memory-limit=1G`); Biome strict dla zmian FE.
- [ ] PHPUnit ≥80% nowej logiki; ApiTestCase dla multipart z ZIP.
- [ ] Playwright E2E: kreator z ZIP-em (widoczna zmiana UI).
- [ ] Typecheck admin z `NODE_OPTIONS=--max-old-space-size=4096`.
- [ ] Regeneracja `docs/api-spec/v0.json` (zmiana kontraktu POST /api/import-sessions): `cache:warmup` + `api:openapi:export`; diff scope'owany do tej zmiany (lekcja integer/number).
- [ ] php-cs-fixer przed commitem; stringi UI przez `t()` + restart kontenera admin po edycji locale JSON.
- [ ] Manual smoke wg sekcji „Jak zwalidować" — artefakt dowodu (screenshot produktu ze zdjęciem + JSON sesji) w komentarzu zamykającym. CLOSED MEANS CLOSED.

---

## IMP2-2.1 — refactor(import): streaming readers — openspout dla XLSX, league/csv stream dla CSV (#1477)

**Labels:** backend, refactor, epik-IMP2 · **Estymata:** 8–10 h · **Zależy od:** brak

## Po co to robimy (kontekst nietechniczny)
Dziś importer wczytuje CAŁY plik do pamięci — i to kilkukrotnie (raz przy podglądzie, raz przy walidacji, raz przy imporcie). Duży plik XLSX potrafi zabić proces serwera (OOM), zanim import w ogóle ruszy — także przy samym podglądzie w kreatorze. Po tym tickecie pliki są czytane strumieniowo, wiersz po wierszu, więc zużycie pamięci jest stałe niezależnie od rozmiaru pliku. To fundament wymagania „import nie może wywalać bazy/serwera".

## Stan obecny
- `apps/api/src/Import/Application/Service/ImportRowReader.php` — CSV przez `file_get_contents` (cały plik w RAM + druga kopia po iconv), XLSX przez PhpSpreadsheet `load()` (cały skoroszyt w RAM).
- `apps/api/src/Import/Application/Service/FileParserService.php` — parse-preview (request HTTP!) buduje tablicę WSZYSTKICH wierszy XLSX, żeby policzyć `totalRows` i wziąć 5 sampli.
- `openspout/openspout` i `league/csv` są już w `apps/api/composer.json` — to refaktor, nie nowa zależność.
- Makieta `Import-sesja.html` obiecuje „streaming chunk 5k · bez ładowania całego pliku do RAM" — obecnie fikcja.

## Zakres prac
1. `ImportRowReader`: XLSX przez openspout row-iterator (wybrany arkusz, wartości komórek rzutowane na string — Excel oddaje DateTime/float tam, gdzie oczekiwano stringa; benchmark `products_export_*.xls` ma floaty na EAN: `123456789.0`); CSV przez `league/csv` ze streamem (`Reader::createFromStream`) + iconv jako stream filter zamiast kopii w pamięci.
2. `FileParserService` (parse-preview): te same iteratory — pobiera nagłówki + N sampli + zlicza wiersze BEZ materializacji; dla XLSX `totalRows` z metadanych arkusza lub iteracji bez bufora.
3. Deduplikacja nagłówków na poziomie readera (duplikaty legalne — patrz D12; reader NIE może gubić kolumn przez nadpisanie klucza w assoc array; zwraca wiersze jako listy pozycyjne + osobno nagłówki).
4. Zachowanie `EncodingDetector`/`DelimiterDetector` (działają na próbce początku pliku, nie na całości).
5. Wyjątek D13: legacy `.xls` (PhpSpreadsheet, bez streamingu, limit 20 MB) — nie zamykać mu drogi w interfejsie readera (dispatch po rozszerzeniu); sam support .xls to IMP2-3.1.
6. Test pamięci: jednostkowy/integ. test czytający duży plik syntetyczny z asercją `memory_get_peak_usage` (próg w teście, np. <64 MB dla 100k wierszy CSV).

## Poza zakresem tego ticketu
- Staged upload 1× — IMP2-2.2. Benchmark RAM 50k jako gate CI — IMP2-2.6. Parametry detekcji (header offset, separatory, arkusz) — IMP2-3.1.

## Kryteria akceptacji
- [ ] `grep -rn "file_get_contents" apps/api/src/Import` → 0 trafień na ścieżce czytania danych.
- [ ] Parse-preview pliku `Zrodla/Importy przykładowe/GA_List.csv` (10 MB, 90k wierszy) odpowiada <5 s i nie podnosi pamięci workera o więcej niż ~50 MB.
- [ ] Wiersze z duplikatami nagłówków (benchmark `bosch-09-01-2026.csv`) zwracane bez utraty kolumn.
- [ ] Wartości XLSX zwracane jako stringi (test: komórka liczbowo wyglądająca jak EAN nie traci zer wiodących / nie ma `.0`).
- [ ] Istniejące testy Import (PHPUnit 114+) zielone bez zmian kontraktu.

## Jak zwalidować (smoke test po wykonaniu)
1. `docker compose exec api bash` → `php -d memory_limit=128M bin/console` + test komendą lub: upload `GA_List.csv` w kreatorze (krok 1) — podgląd działa, brak OOM.
2. Upload `bosch-09-01-2026.xlsx` — sample pokazują kody EAN bez `.0`.
3. `docker compose exec api composer phpstan && bin/phpunit tests/Unit/Import tests/Api/Import` — zielone (pamiętaj: `cache:clear --env=test` przed Api/*).

## Zależności
Blokowany przez: —. Blokuje: IMP2-2.2, IMP2-2.6, IMP2-3.1.

## Referencje
Plan: §2.6, §5 ETAP 2 (2.1), D13. Lekcja Akeneo PIM-10167 (XLSX type coercion). Benchmarki: GA_List.csv, bosch-09-01-2026.csv/xlsx, products_export_*.xls.

## Definition of Done
PHPStan max zielony; PHPUnit ≥80% nowej logiki + test pamięci; smoke wg walidacji z artefaktem (czas+RAM parse-preview GA_List) w komentarzu zamykającym.

---

## IMP2-2.2 — feat(import): staged upload — plik wgrywany raz i reużywany w preview/dry-run/start (#1478)

**Labels:** backend, frontend, enhancement, epik-IMP2 · **Estymata:** 5–7 h · **Zależy od:** IMP2-2.1

## Po co to robimy (kontekst nietechniczny)
Kreator importu wysyła ten sam plik na serwer TRZY razy: przy podglądzie, przy walidacji i przy starcie. Przy pliku 50 MB to 150 MB transferu i trzykrotne parsowanie — a każda zmiana mapowania odpala walidację od nowa, bez chwili zwłoki. Po tym tickecie plik wgrywany jest raz, dostaje identyfikator i kolejne kroki tylko się do niego odwołują. Kreator robi się szybszy, a serwer przestaje mielić te same bajty.

## Stan obecny
- FE wysyła multipart 3×: `StepMapping.tsx` (parse-preview), `StepValidation.tsx` (validate-dry-run — re-trigger przy KAŻDEJ zmianie mapping/encoding bez debounce, useEffect), `StepConfirm.tsx` (start).
- BE: `ParsePreviewController` jest stateless (plik → temp → wynik → unlink); `StartImportController` dopiero na końcu staguje plik do MinIO `{tenant}/{session}/{file}` (flysystem `imports.storage`).

## Zakres prac
1. BE: `POST /api/import-sessions/parse-preview` dodatkowo staguje plik w MinIO pod kluczem `{tenant}/staged/{uuid}/{filename}` i zwraca `staged_file_id`; nowa tabela `import_staged_files` (id, tenant_id NOT NULL + ORM default, user_id, file_name, size, storage_key, created_at) — RLS-ready (polityka w tym tickecie).
2. `validate-dry-run` i `POST /api/import-sessions` przyjmują `staged_file_id` ZAMIAST pliku (back-compat: multipart nadal akceptowany); ownership check (user/tenant) przy odczycie.
3. TTL 24h: komenda `pim:import:purge-staged` (cron/scheduler) usuwająca przeterminowane pliki + wiersze; cleaner tenant-scoped (nie iteruje cross-tenant bez kontekstu — usuwanie po kluczach z tabeli).
4. FE: `useImportWizard` trzyma `stagedFileId`; kroki 2–4 wysyłają identyfikator; debounce dry-run 500 ms; zmiana pliku = nowy staged_file_id.
5. OpenAPI snapshot regen (nowe pola endpointów).

## Poza zakresem tego ticketu
- Chunked/resumable upload dla >100 MB — poza MVP (limit D10).
- Dwupoziomowy dry-run — IMP2-3.6.

## Kryteria akceptacji
- [ ] Pełny przebieg kreatora wysyła plik dokładnie 1× (DevTools Network: 1 multipart, potem JSON-y ze `staged_file_id`).
- [ ] Dry-run po zmianie mappingu odpala się raz po 500 ms ciszy (debounce), nie przy każdym kliknięciu.
- [ ] `staged_file_id` innego użytkownika/tenanta → 404 (test ApiTestCase, cross-tenant=0).
- [ ] Komenda purge usuwa pliki starsze niż 24h (test integracyjny z podstawionym czasem).
- [ ] `docs/api-spec/v0.json` zaktualizowany.

## Jak zwalidować (smoke test po wykonaniu)
1. Kreator: wgraj `Zrodla/Importy przykładowe/bosch-09-01-2026.csv`; przejdź kroki do startu obserwując Network — plik leci raz.
2. Zmieniaj mapowanie kilka razy szybko — walidacja odpala się dopiero po pauzie.
3. `docker compose exec api bin/console pim:import:purge-staged --dry-run` — lista do skasowania pusta/zgodna.

## Zależności
Blokowany przez: IMP2-2.1. Blokuje: IMP2-3.6 (pełny dry-run async czyta staged file).

## Referencje
Plan: §2.6, §5 ETAP 2 (2.2), filar 7. Kod: StepMapping/StepValidation/StepConfirm, ParsePreviewController, StartImportController, flysystem.yaml.

## Definition of Done
PHPStan max + PHPUnit (≥80% nowej logiki) + ApiTestCase nowych pól + Biome/tsc zielone; OpenAPI regen; smoke z dowodem (screenshot Network 1×upload).

---

## IMP2-2.3 — feat(import): realna pauza/wznowienie/anulowanie + checkpoint odporny na crash (#1479)

**Labels:** backend, frontend, enhancement, epik-IMP2 · **Estymata:** 7–9 h · **Zależy od:** IMP2-1.6a, IMP2-0.3

## Po co to robimy (kontekst nietechniczny)
Przy imporcie kilkudziesięciu tysięcy wierszy operator musi mieć realny hamulec: pauzę (np. „serwer jest potrzebny do czegoś innego"), wznowienie od miejsca zatrzymania i anulowanie. Dziś przyciski są ukryte (IMP2-0.3), bo silnik ich nie honorował. Dodatkowo: jeśli worker padnie w połowie, import musi dać się bezpiecznie wznowić — bez duplikowania już zapisanych wierszy. Po tym tickecie pauza/wznów/anuluj działają naprawdę, a sesja pamięta, dokąd doszła.

## Stan obecny
- `ImportRunHandler` — pętla bez sprawdzania statusu między chunkami; pauza w trakcie → `markCompleted()` rzuca `LogicException` → FAILED.
- `ImportSessionStateController` — endpointy pause/resume/cancel zmieniają tylko status.
- `BulkOperationLock` — TTL 1h, komentarz obiecuje heartbeat, którego NIE ma w pętli; import >1h straci lock.
- Transport doctrine (po IMP2-1.6a): `redeliver_timeout` — wznowienie musi być odporne na re-delivery tej samej wiadomości.
- Wzorzec poll-statusu istnieje w `ExportJobHandler` (surowy SQL co chunk → `ExportCancelledException`).

## Zakres prac
1. `ImportRunHandler`: między chunkami surowy SQL status-check (wzorem ExportJobHandler): `paused` → graceful stop (flush dotychczasowego chunka, zapis checkpointu, release lock, koniec handlera BEZ markCompleted); `cancelled` → jak wyżej + status terminalny cancelled.
2. Checkpoint na `ImportSession`: kolumny `checkpoint_offset` (ostatni scommitowany wiersz) + `checkpoint_phase` (`pass1`/`pass2` — koordynacja z IMP2-1.8) + liczniki utrwalane przy każdym checkpoint.
3. Resume: `POST /{id}/resume` re-dispatchuje `ImportRunMessage`; handler zaczyna od `checkpoint_offset+1` (reader przewija strumień); idempotencja liczników: created/updated/skipped przeliczane z `import_logs`/checkpointu, nie inkrementowane podwójnie; koordynacja z undo-logiem (IMP2-2.4): first-write-wins per (session, object_value) — powtórzony chunk nie nadpisuje before-values.
4. Redelivery-awareness: handler na starcie sprawdza status sesji (running+żywy checkpoint = kontynuacja od checkpointu, nie od zera); `redeliver_timeout` zweryfikowany > realny czas chunka.
5. Lock TTL renewal: `$lock->refresh()` co chunk (Symfony Lock wspiera refresh dla store z TTL).
6. Naprawa maszyny stanów: przejścia paused→running, running→cancelled legalne bez LogicException (`ImportSessionStatus::ensureTransitionable`).
7. FE: przywrócenie przycisków (flaga z IMP2-0.3 → true), stany przycisków wg statusu, Playwright: pauza → status paused → resume → success.

## Poza zakresem tego ticketu
- Undo-log/rollback — IMP2-2.4. Dwupoziomowy dry-run — IMP2-3.6.

## Kryteria akceptacji
- [ ] Pauza w trakcie importu 5k wierszy: handler kończy chunk i staje; sesja `paused`; wiersze zapisane ≤ offset checkpointu.
- [ ] Resume kontynuuje od checkpointu: po zakończeniu `success_count` == liczba wierszy pliku (bez duplikatów — weryfikacja COUNT w DB).
- [ ] Cancel w trakcie → status `cancelled`, lock zwolniony, brak LogicException w logach.
- [ ] Kill workera w trakcie (docker restart) + ponowny consume → import dochodzi do końca bez zduplikowanych obiektów (test integracyjny lub udokumentowany smoke).
- [ ] Import trwający >TTL locka nie traci locka (refresh co chunk — test jednostkowy na wywołanie refresh).

## Jak zwalidować (smoke test po wykonaniu)
1. Przygotuj CSV ~5k wierszy (skrypt z GA_List.csv — pierwsze 5k). Uruchom import async.
2. W trakcie: klik „Pauza" → karta sesji pokazuje paused; SQL: `SELECT status, checkpoint_offset FROM import_sessions WHERE id=...`.
3. „Wznów" → import kończy się success; `SELECT count(*) FROM objects WHERE import_session_id=...` == liczba wierszy.
4. Drugi przebieg: w trakcie importu `docker compose restart api` (lub worker) → po powrocie workera import dochodzi do końca, liczniki poprawne.

## Zależności
Blokowany przez: IMP2-1.6a, IMP2-0.3. Blokuje: IMP2-3.7 (UI sesji v2 pokazuje pauzę).

## Referencje
Plan: §5 ETAP 2 (2.3), D8/D11. Kod: ImportRunHandler, ImportSessionStateController, ExportJobHandler (wzorzec), BulkOperationLock.

## Definition of Done
PHPStan max + PHPUnit/ApiTestCase + Playwright pauza/resume zielone; smoke z dowodem (zrzuty statusów + COUNT) w komentarzu zamykającym.

---

## IMP2-2.4 — feat(import): undo-log operacji + rollback v2 (delete created + replay updated, rebuild indeksów) (#1480)

**Labels:** backend, enhancement, epik-IMP2 · **Estymata:** 12–18 h · **Zależy od:** IMP2-1.3, IMP2-1.4

## Po co to robimy (kontekst nietechniczny)
„Wycofaj import" musi mówić prawdę. Dziś cofa tylko produkty UTWORZONE przez import (kasuje je), ale jeśli import coś NADPISAŁ w istniejących produktach — stare wartości przepadają bezpowrotnie. Po wprowadzeniu trybu „aktualizuj" (IMP2-1.3) to jest niedopuszczalne: operator wgrywający zły plik cenowy musi móc wrócić do stanu sprzed importu. Po tym tickecie rollback cofa i utworzenia, i nadpisania — a podgląd przed wycofaniem mówi dokładnie, co się stanie. Dodatkowo naprawiamy istniejący błąd: po obecnym rollbacku skasowane produkty wciąż straszą w wyszukiwarce („duchy" w Meilisearch).

## Stan obecny
- `apps/api/src/Import/Application/Service/ImportRollbackService.php` — hard DELETE przez DBAL po `objects.import_session_id` (transakcja: object_values → objects); omija listenery Doctrine → **Meilisearch nie jest czyszczony (ghost-documents już dziś)**, attributes_indexed nieodświeżane; assety nieruszane (świadome).
- Kontrakt D11 (po IMP2-1.3): `import_session_id` stampowany TYLKO na created — bez tego rollback sesji upsert skasowałby istniejący katalog klienta.
- FK przy DELETE obiektu: object_values/object_categories/object_relations/product_assets — ON DELETE CASCADE; objects.parent_id — SET NULL (osierocenie pre-istniejących wariantów możliwe).
- Okno rollbacku: `rollback_until` = completedAt+24h.

## Zakres prac
1. Tabela `import_undo_log` (encja + migracja): id, tenant_id NOT NULL (+ `<option name="default">` w ORM XML — lekcja: test DB z metadanych), import_session FK (ON DELETE CASCADE), object_id, operation enum (`value_overwritten`,`value_created`,`category_set`,`relation_created`,`object_field_changed`), payload JSONB (before-envelope / before-state), created_at; indeks (session, object_id); polityka RLS.
2. Zapis do undo-logu w `ImportValueWriter`/pass2 (tylko tryb update/upsert na PRE-ISTNIEJĄCYCH obiektach): nadpisanie wartości → before-envelope; NOWA wartość na istniejącym obiekcie → tombstone `value_created` (rollback = DELETE tej wartości); zmiany kategorii (replace), relacji (pass2), pól obiektu (status/enabled/parent) → before-state. First-write-wins per (session, object_value) — idempotencja przy resume (IMP2-2.3).
3. Konfigurowalny próg: undo-log wyłączany dla sesji > N wierszy (parametr, default włączony do 200k) — decyzja zapisana w sesji i komunikowana w UI przed startem.
4. Rollback v2 w `ImportRollbackService`: jedna transakcja pod `BulkOperationLock`: (a) DELETE created (jak dziś, po import_session_id), (b) replay undo-logu dla updated z guardem — jeśli wartość po imporcie zmieniona ręcznie (provenance != import lub updated_at > completed_at sesji) → pomiń + zapisz w raporcie rollbacku, (c) po obu: rebuild attributes_indexed + completeness (dispatch ObjectValuesChangedMessage dla affected ids) + reindex zmienionych / DELETE skasowanych z Meilisearch (fix ghost-docs także dla starej ścieżki), (d) raport rollbacku zapisany na sesji (JSONB: counts + pominięcia).
5. Preview: `GET /api/import-sessions/{id}/rollback-preview` → {created_to_delete, updated_to_restore, manual_edits_to_skip, orphaned_variants (children z parent SET NULL), auto_created_options (D6 — NIE są cofane, tylko raportowane)}; FE: modal rollbacku pokazuje preview przed potwierdzeniem.
6. Retencja: purge undo-logu po zamknięciu okna rollbacku (komenda/cron wspólny z IMP2-2.2 purge lub osobny).

## Poza zakresem tego ticketu
- Rollback assetów pobranych przez media (świadomie — jak v1, assety zostają w DAM).
- Pauza/resume — IMP2-2.3 (koordynacja first-write-wins opisana tam i tu).

## Kryteria akceptacji
- [ ] Scenariusz upsert: import nadpisuje 10 wartości i tworzy 5 obiektów → rollback przywraca 10 starych wartości (SQL diff przed/po) i kasuje 5 obiektów.
- [ ] Wartość zmieniona RĘCZNIE po imporcie nie jest cofana; pojawia się w raporcie pominięć.
- [ ] Po rollbacku: Meilisearch nie zwraca skasowanych obiektów (search smoke), attributes_indexed zaktualizowane, completeness przeliczone.
- [ ] Preview zwraca poprawne kubełki (test ApiTestCase na seedzie: created+updated+manual edit).
- [ ] Undo-log ma tenant_id NOT NULL; test izolacji cross-tenant=0.
- [ ] Purge usuwa wpisy po oknie rollbacku.

## Jak zwalidować (smoke test po wykonaniu)
1. Wyeksportuj 20 produktów; w Excelu zmień ceny 10, dodaj 5 nowych wierszy; import upsert.
2. Ręcznie zmień w UI jedną z nadpisanych cen.
3. Rollback-preview → widzisz 5 do skasowania / 10 do przywrócenia / 1 pominięcie.
4. Rollback → ceny wróciły (poza ręcznie zmienioną), nowe produkty zniknęły także z wyszukiwarki (sprawdź pole search w katalogu).

## Zależności
Blokowany przez: IMP2-1.3, IMP2-1.4. Blokuje: IMP2-3.7 (modal preview), koordynacja z IMP2-2.3.

## Referencje
Plan: §5 ETAP 2 (2.4), filar 10, D6/D11. Kod: ImportRollbackService, ObjectValue (provenance/updated_at), RebuildAttributesIndexedHandler. Research: reverse-delta (sekcja crossSystem planu).

## Definition of Done
PHPStan max + PHPUnit ≥80% + ApiTestCase preview/rollback + migracje czyste; OpenAPI regen; smoke wg walidacji z artefaktem (SQL before/after + screenshot preview) — CLOSED MEANS CLOSED.

---

## IMP2-2.5 — feat(import,identity): tenant_id na import_logs + GUC app.current_tenant w workerach (RLS-ready) (#1481)

**Labels:** backend, security, epik-IMP2 · **Estymata:** 3–5 h · **Zależy od:** brak

## Po co to robimy (kontekst nietechniczny)
Izolacja danych między przyszłymi klientami (tenantami) ma w PIM dwie warstwy: filtr w aplikacji i polityki w samej bazie (RLS). Tabela logów importu nie ma kolumny tenant_id (chroni ją tylko powiązanie z sesją), a procesy w tle nie ustawiają w bazie identyfikatora tenanta — więc gdy zaostrzymy polityki bazy (FORCE RLS przed pierwszym multi-tenant wdrożeniem), importy w tle przestałyby działać. Ten ticket domyka obie luki zawczasu.

## Stan obecny
- `import_logs` BEZ tenant_id — izolacja wyłącznie przez FK do `import_sessions` (luka pod RLS; zidentyfikowana w audycie).
- `apps/api/src/Identity/Infrastructure/Doctrine/RlsContextListener.php` — `set_config('app.current_tenant', ...)` TYLKO na kernel.request; workery Messenger nie ustawiają GUC. UWAGA ścisłość: `TenantContextRebindingMiddleware` poprawnie rebinduje PHP TenantContext (TenantFilter działa w workerach) — luka dotyczy WYŁĄCZNIE polityk RLS Postgresa.
- Polityki RLS włączone migracją Version20260518170000 (ENABLE, bez FORCE).

## Zakres prac
1. Migracja: `ALTER TABLE import_logs ADD tenant_id UUID` + backfill z `import_sessions.tenant_id` po FK + `SET NOT NULL`; ORM XML z `<option name="default">`? — nie: kolumna wypełniana w kodzie (ImportLog tworzony zawsze w kontekście sesji) + joined default w ORM zgodnie z lekcją NOT NULL (test DB z metadanych — dodać default lub ustawiać w konstruktorze encji; wybrać konstruktor: ImportLog dostaje tenant z sesji).
2. Polityka RLS na `import_logs` (wzorem istniejących polityk z Version20260518170000).
3. Nowy middleware Messenger (po `TenantContextRebindingMiddleware`): `set_config('app.current_tenant', <uuid>, false)` na połączeniu DBAL przy starcie obsługi wiadomości tenant-aware; czyszczenie po zakończeniu (set_config local w transakcji lub reset).
4. Test integracyjny: handler w workerze widzi GUC ustawiony (SELECT current_setting('app.current_tenant')); test izolacji import_logs cross-tenant=0 (wzorzec ImportTenantIsolationTest).
5. Audyt checklisty: wpis do `TenantAuditCommand` allowlist/coverage jeśli dotyczy.

## Poza zakresem tego ticketu
- FORCE RLS (decyzja przy pilotach, RBAC Phase 2 #654 follow-up). Polityki dla `import_undo_log`/`import_staged_files` — w ich ticketach (IMP2-2.4/2.2).

## Kryteria akceptacji
- [ ] `\d import_logs` pokazuje tenant_id NOT NULL + policy RLS.
- [ ] Backfill: 0 wierszy z NULL po migracji (asercja w migracji lub teście).
- [ ] Test: w handlerze Messenger `current_setting('app.current_tenant')` == tenant wiadomości.
- [ ] ImportTenantIsolationTest rozszerzony o import_logs — cross-read 0.

## Jak zwalidować (smoke test po wykonaniu)
1. `docker compose exec api bin/console doctrine:migrations:migrate` na dev — czysto.
2. Uruchom import async; w psql: `SELECT tenant_id FROM import_logs ORDER BY created_at DESC LIMIT 5` — wypełnione.
3. `bin/phpunit tests/Integration/Import` — zielone.

## Zależności
Blokowany przez: —. Blokuje: przyszłe FORCE RLS; koordynacja z IMP2-2.2/2.4 (ich tabele rodzą się z polityką od razu).

## Referencje
Plan: §2.6, §5 ETAP 2 (2.5). Lekcje: ORM NOT NULL default; RBAC ticket #654 (RLS). Kod: RlsContextListener, TenantContextRebindingMiddleware, Version20260518170000.

## Definition of Done
PHPStan max + PHPUnit/Integration zielone; migracja idempotentna na kopii dev DB; dowód (psql output) w komentarzu zamykającym.

---

## IMP2-2.6 — perf(import,export): bulk-path wydajność + benchmark RAM jako test (#1482)

**Labels:** backend, frontend, testing, enhancement, epik-IMP2 · **Estymata:** 10–14 h · **Zależy od:** IMP2-1.4, IMP2-2.1

## Po co to robimy (kontekst nietechniczny)
Duży import (50 tys. wierszy) dziś działa wielokrotnie wolniej niż musi i potrafi zabić proces roboczy przez brak pamięci. Przy każdej porcji zapisu system od razu, synchronicznie przelicza indeksy każdego produktu (zamiast zrobić to raz, w tle), a dla KAŻDEGO wiersza wysyła osobne powiadomienie do przeglądarki — 50 tys. wierszy to 50 tys. requestów HTTP do huba powiadomień. Eksport ma bliźniaczy problem: ładuje cały katalog do pamięci naraz. Po tym tickecie import i eksport dużych plików będą działać w stałej, niskiej pamięci (limit twardo pilnowany testem w CI), pasek postępu będzie aktualizował się płynnie co ~1–2%, a wiersze, w których nic się nie zmieniło, będą pomijane bez zbędnych zapisów do bazy.

## Stan obecny
- `apps/api/src/Import/Application/Handler/ImportRunHandler.php` — **NIE używa `BulkContext`** (brak importu klasy, brak wywołań `setBulk()`). Sama infrastruktura bulk-path ISTNIEJE i jest gotowa: `apps/api/src/Catalog/Application/BulkContext.php` (request-scoped toggle z `ResetInterface`), `apps/api/src/Catalog/Infrastructure/Doctrine/EventListener/AttributesIndexedSyncListener.php` jest BulkContext-aware (sprawdza `isBulk()`), `apps/api/src/Search/Application/CatalogIndexSubscriber.php` też (short-circuit w każdej metodzie gdy `isBulk()`). Skutek: każdy `flushAndClear()` co 200 wierszy w imporcie odpala synchroniczny rebuild `attributes_indexed` + completeness + indeksację Meilisearch per obiekt — wbrew regule architektury „async dla >1000" (CLAUDE.md, reguła 4).
- `apps/api/src/Catalog/Application/Message/ObjectValuesChangedMessage.php` + `apps/api/src/Catalog/Application/Handler/RebuildAttributesIndexedHandler.php` — asynchroniczny rebuild istnieje i jest routowany na transport `async` (`apps/api/config/packages/messenger.yaml` linia 70). Import go nie dispatchuje.
- Batch reindex Meilisearch istnieje: `apps/api/src/Search/Application/BulkCatalogObjectIndexer.php` (iteracja + clear co 200, push do Meili w paczkach po 500) oraz adapter kolejki `apps/api/src/Search/Application/CatalogBulkReindexQueue.php` za kontraktem `apps/api/src/Catalog/Application/Reindex/BulkReindexQueueInterface.php`. Import nie używa żadnego z nich.
- `apps/api/src/Import/Application/Service/ImportProgressPublisher.php` — metoda `rowProcessed()` wywoływana w `ImportRunHandler::run()` **per wiersz** (linia ~205): 50k wierszy = 50k POST-ów do huba Mercure. `progress()` idzie tylko przy flushu (co 200), ale per-row `rowProcessed` dominuje ruch.
- FE: `apps/admin/src/features/imports/hooks/useImportProgress.ts` — konsumuje eventy `row_processed`/`error` do live-logu (NUI-11) i `progress` do paska.
- Brak compare-values diff: każdy wiersz pliku = zapis do `object_values`, nawet gdy wartość identyczna z DB (re-import własnego eksportu po IMP2-1.3/1.4 wygeneruje N bezsensownych UPDATE-ów + rebuildów).
- `apps/api/src/Export/Application/Sync/SyncExportRunner.php` — `resolveTargets()` zwraca `list<CatalogObject>` i **materializuje CAŁY zbiór** (`findByObjectType` dla scope All, `findByIds` dla Selected/Filter); `resolveTargetCount()` robi `\count($this->resolveTargets(...))`, czyli ładuje wszystkie encje tylko po to, żeby je policzyć; `runToFile()` iteruje po pełnej tablicy bez `EntityManager::clear()` per chunk.
- Wzorzec benchmarku istnieje: `apps/api/src/Benchmark/BulkImportBenchmarkCommand.php` (`pim:benchmark:bulk-import`, próg `MEMORY_THRESHOLD_BYTES = 256 MiB`) — ale to komenda CLI z syntetycznymi insertami `CatalogObject`, nie test przebiegu importu i nie jest bramką CI.
- Limit pamięci workera: `apps/api/frankenphp/php.ini` → `memory_limit = 256M` — przekroczenie = twardy OOM procesu.

## Zakres prac
1. **BulkContext ON w imporcie**: w `ImportRunHandler::run()` ustaw `BulkContext::setBulk(true, $session->getId())` na początku przebiegu i **zawsze** zresetuj w `finally` (worker Messenger żyje między wiadomościami — wyciek flagi zatruje kolejne joby). Zbieraj `id` dotkniętych `CatalogObject` per chunk (created + updated z `ImportValueWriter`/`ObjectResolver` z IMP2-1.3/1.4).
2. **Async rebuild**: po każdym `flushAndClear()` dispatch `ObjectValuesChangedMessage` z id-kami chunka (transport `async`/`import`) → `RebuildAttributesIndexedHandler` przelicza `attributes_indexed` + completeness w tle. Udokumentuj w docblocku świadomy skutek: listy/szukajka widzą nowe wartości dopiero po przebiegu rebuildów (eventual consistency, zgodnie z regułą „async dla >1000").
3. **Batch reindex Meilisearch**: id-ki chunka trafiają do `BulkReindexQueueInterface` (push w paczkach — adapter już batchuje po 500) ALBO po zakończeniu przebiegu dispatch jednego rebuildu przez `BulkCatalogObjectIndexer` — wybierz mniejszy koszt, ale kryterium twarde: **zero per-row wywołań HTTP do Meili podczas importu** i komplet dokumentów po przebiegu. Uwaga: `CatalogIndexFlushSubscriber` drenuje kolektor na `kernel.terminate` — w workerze Messenger trzeba drenować na evencie workera (np. `WorkerMessageHandledEvent`) lub jawnie na końcu `run()`.
4. **Mercure per chunk**: usuń per-row `rowProcessed()` ze ścieżki batchowej; publikuj `progress()` co chunk, nie częściej niż co ~1–2% całości (`max(batchSize, ceil(totalRows/100))` wierszy). Event `error` zostaje per błędny wiersz (błędów jest mało, a live-log ich potrzebuje). Ścieżka inline (≤50 wierszy, D8) może zachować `rowProcessed`. Dostosuj `apps/admin/src/features/imports/hooks/useImportProgress.ts`: live-log buduje się z eventów `error` + snapshotów `progress` (pokaż `processed_rows`/`current_sku`), typy zaktualizowane.
5. **Compare-values diff przed zapisem**: w `ImportValueWriter` (IMP2-1.4 — prefetch istniejących `ObjectValue` per chunk już jest w jego kontrakcie) porównuj nowy envelope z istniejącym: identyczna wartość + identyczny provenance → **skip** (bez UPDATE, bez wpisu w undo-logu, wiersz liczony jako `skipped`); identyczna wartość ale **inny provenance ≠ no-op** — zapisujemy, żeby audyt odzwierciedlał przejęcie wartości przez import (rozstrzygnięcie z ADR IMP2-1.1, §4.4/2.6 planu). Licznik `skipped` widoczny w podsumowaniu sesji.
6. **`SyncExportRunner` — iteracja + clear**: (a) `resolveTargetCount()` liczy przez `COUNT(*)` SQL (bez ładowania encji); (b) `runToFile()` dla scope All iteruje `Query::toIterable()` + `EntityManager::clear()` co 200–500 wierszy (nowa metoda repo, np. `iterateByObjectType()` w `CatalogObjectRepositoryInterface`), dla Selected/Filter ładuje encje w chunkach id-ków zamiast jednego `findByIds()` na cały zbiór. Zachowaj kontrakt `onChunk` (progress co `PROGRESS_CHUNK`).
7. **Benchmark RAM jako test CI**: nowy test (np. `apps/api/tests/Integration/Import/ImportRunMemoryBenchmarkTest.php`, `#[Group('import-benchmark')]`), wzorem `BulkImportBenchmarkCommand`: generuje syntetyczny CSV (sku + ~5 kolumn atrybutów mieszanych typów) na 5 000 i 50 000 wierszy, przepuszcza przez `ImportRunHandler::run()` na testowym Postgresie i asercja `memory_get_peak_usage(true) < 256 MiB` + poprawne liczniki sesji. Analogiczna asercja dla eksportu 50k obiektów przez `SyncExportRunner::runToFile()`. Osobny krok CI uruchamiający grupę (gate etapu 2: benchmark 50k w CI). Danych komercyjnych NIE commitujemy — fixtures syntetyczne.

## Poza zakresem tego ticketu
- Prefetch walidacji (1–2 SELECT per wiersz w `ImportValidationService`) — to kontrakt `ImportValueWriter`/`RowValidator` z **IMP2-1.4**.
- Streaming readerów plików (openspout/league\csv) — **IMP2-2.1** (zależność).
- Transport `import` + worker w dev/prod compose, checkpoint redelivery — **IMP2-1.6a** (etap 1, zakładamy zmergowane).
- Obsługa `OptimisticLockException` w `RebuildAttributesIndexedHandler` i locki bulk-edit — **IMP2-2.9**.
- Limity rozmiaru/wierszy pliku i rate limit — **IMP2-2.7**.
- Undo-log i rollback v2 — **IMP2-2.4**.

## Kryteria akceptacji
- [ ] `ImportRunHandler` ustawia `BulkContext` na czas przebiegu i resetuje w `finally`; test integracyjny dowodzi, że podczas importu >200 wierszy `AttributesIndexedSyncListener` i `CatalogIndexSubscriber` NIE wykonują pracy per flush (np. spy/licznik), a `ObjectValuesChangedMessage` jest dispatchowany z id-kami chunków.
- [ ] Po asynchronicznym imporcie N wierszy: `attributes_indexed` przeliczone dla wszystkich N obiektów (po przetworzeniu kolejki), dokumenty w Meilisearch obecne, zero per-row wywołań Meili w trakcie importu.
- [ ] Import 50k wierszy publikuje ≤ ~100 eventów `progress` do Mercure (zamiast 50k `row_processed`); eventy `error` nadal per błędny wiersz; live-log w UI sesji działa (zasilany z `error` + `progress`).
- [ ] Re-import niezmienionego własnego eksportu: 100% wierszy w kubełku `skipped`, zero UPDATE na `object_values` (asercja w teście); ta sama wartość z innym provenance → zapis (test jednostkowy diffa).
- [ ] `SyncExportRunner::resolveTargetCount()` nie ładuje encji (asercja na SQL lub na pamięć); eksport 50k obiektów < 256 MiB peak.
- [ ] Test `#[Group('import-benchmark')]`: import 5k i 50k wierszy z asercją `memory_get_peak_usage(true) < 256 MiB` — zielony lokalnie i w CI (osobny krok pipeline'u).
- [ ] PHPStan max zielony; istniejące testy Import/Export zielone.

## Jak zwalidować (smoke test po wykonaniu)
1. `docker compose exec api php bin/console cache:clear --env=test` (obowiązkowo przed testami Api/* — ochrona dev DB), potem `docker compose exec api vendor/bin/phpunit --group import-benchmark` → zielone, output raportuje peak <256 MiB dla 5k i 50k.
2. Zaloguj się na `https://pim.localhost` (admin@demo.localhost / changeme). Wejdź w Importy → kreator → wgraj plik `Zrodla/Importy przykładowe/GA_List.csv` (10 MB, ~90k wierszy — stress klasy B; mapowanie minimalne, np. pierwsza kolumna → sku). Uruchom import.
3. W trakcie: `docker stats --no-stream` na kontenerze workera importu (po IMP2-1.6a) co kilkanaście sekund → RSS stabilnie poniżej 256 MB, bez trendu rosnącego.
4. DevTools → Network → EventStream `/.well-known/mercure`: eventy `progress` przychodzą co ~1–2% (NIE per wiersz); pasek postępu w widoku sesji rośnie płynnie; Console bez czerwonych błędów.
5. Po imporcie: lista produktów pokazuje zaimportowane wartości (po przetworzeniu rebuildów — odśwież po chwili), wyszukiwarka (Meilisearch) znajduje próbkę SKU z pliku.
6. Eksport całego katalogu (scope All) z widoku eksportów → plik się generuje, `docker stats` na api bez skoku pamięci.
7. Re-import świeżo wyeksportowanego pliku bez zmian → podsumowanie sesji pokazuje `skipped` ≈ 100% wierszy, `updated` ≈ 0.

## Zależności
- Blokowany przez: **IMP2-1.4** (ImportValueWriter z prefetchem istniejących ObjectValues — na nim wisi diff), **IMP2-2.1** (streaming readers — bez nich benchmark RAM mierzy parser, nie ścieżkę zapisu). Zakłada zmergowane **IMP2-1.6a** (worker `import` w dev).
- Blokuje: **IMP2-2.9** (macierz równoległości buduje na bulk-path), gate etapu 2.

## Referencje
- `Project Plan/UI/feature-imports-v2.md` §2.6 (diagnoza), §3 filar 8, §4.3 (SyncExportRunner → ticket 2.6), §5 ETAP 2 poz. 2.6, §8 pkt 4 (benchmark jako kryterium całości).
- Decyzje: D8 (sync/async), rozstrzygnięcie provenance-diff w ADR IMP2-1.1.
- Kod: `apps/api/src/Import/Application/Handler/ImportRunHandler.php`, `apps/api/src/Catalog/Application/BulkContext.php`, `apps/api/src/Catalog/Application/Handler/RebuildAttributesIndexedHandler.php`, `apps/api/src/Search/Application/BulkCatalogObjectIndexer.php`, `apps/api/src/Search/Application/CatalogBulkReindexQueue.php`, `apps/api/src/Import/Application/Service/ImportProgressPublisher.php`, `apps/api/src/Export/Application/Sync/SyncExportRunner.php`, `apps/api/src/Benchmark/BulkImportBenchmarkCommand.php`, `apps/admin/src/features/imports/hooks/useImportProgress.ts`.
- Issues: #445 (IMP-04 handler), #1130 (round-trip), CLAUDE.md sekcja „Memory management — FrankenPHP worker mode".

## Definition of Done
- PHPStan max zielony (`cache:warmup --env=dev` przed `composer phpstan`, `--memory-limit=1G`); php-cs-fixer przed commitem.
- PHPUnit ≥80% nowej logiki (diff compare-values, chunked progress, iteracja eksportu); test benchmark `import-benchmark` w CI jako osobny krok.
- FE: `NODE_OPTIONS=--max-old-space-size=4096` dla typecheck/build admin; Playwright dla zmiany w widoku sesji (live-log + pasek postępu) jeśli zachowanie widoczne się zmienia.
- Brak zmian kontraktu API REST → regeneracja `docs/api-spec/v0.json` tylko jeśli payload Mercure/serializacja sesji się zmieni.
- Manual smoke wg sekcji „Jak zwalidować" z artefaktem dowodu w komentarzu zamykającym (output benchmarku + zrzut `docker stats` + screenshot sesji GA_List) — CLOSED MEANS CLOSED.

---

## IMP2-2.7 — feat(import): limity i guardraile (D10) + streamowany raport CSV (#1483)

**Labels:** backend, enhancement, epik-IMP2 · **Estymata:** 3–5 h · **Zależy od:** IMP2-2.1

## Po co to robimy (kontekst nietechniczny)
Dziś nic nie powstrzymuje użytkownika przed wrzuceniem pliku, który zatka system: brak limitu liczby wierszy, brak limitu wielkości pliku po stronie aplikacji (tylko surowy limit PHP, który ucina żądanie bez czytelnego komunikatu) i brak ogranicznika częstotliwości — można odpalić import za importem. Dodatkowo pobranie raportu błędów z dużej sesji ładuje do pamięci do 100 tys. rekordów naraz. Po tym tickecie: zbyt duży plik dostanie jasny komunikat „plik przekracza limit X" zamiast tajemniczego błędu, tenant ma konfigurowalne limity, opcjonalny próg „przerwij import, jeśli błędów jest więcej niż N%" chroni przed wsypaniem śmieci do katalogu, a raport CSV streamuje się bez obciążania serwera.

## Stan obecny
- **Brak limitów aplikacyjnych**: `apps/api/src/Import/Presentation/Controller/StartImportController.php` i `apps/api/src/Import/Presentation/Controller/ParsePreviewController.php` nie sprawdzają ani rozmiaru pliku, ani liczby wierszy. Jedyny realny limit to `apps/api/frankenphp/php.ini`: `upload_max_filesize = 50M`, `post_max_size = 56M` — czyli **poniżej** defaultu D10 (100 MB), a przekroczenie kończy się pustym `$_FILES` / niezrozumiałym 400, nie czytelnym RFC 7807.
- **Brak progu Allowed-Errors**: `apps/api/src/Import/Application/Handler/ImportRunHandler.php` liczy `error_count`, ale nigdy nie przerywa przebiegu — import z 99% błędnych wierszy doleci do końca. `apps/api/src/Import/Domain/Entity/ImportProfile.php` nie ma pola na próg (pola: name, code, mode, columnMapping, locale, encoding, delimiter, imageSource, customValidationRules...).
- **Brak rate limitu sesji importu**: `apps/api/config/packages/framework.yaml` sekcja `rate_limiter` ma `auth_login`, `agent_run`, `backup_trigger` (1/h/tenant), limiter syncu integracji — nic dla `POST /api/import-sessions`.
- **Raport CSV nie streamuje**: `apps/api/src/Import/Presentation/Controller/ImportReportCsvController.php` mimo docblocka „The streamed response keeps the worker memory footprint flat" buduje całość w `php://temp`, robi `stream_get_contents()` do stringa i zwraca zwykły `Response`; `ImportLogRepositoryInterface::findBySession()` (`apps/api/src/Import/Domain/Repository/ImportLogRepositoryInterface.php`) zwraca tablicę **encji** z limitem `100_000` — do 100k obiektów `ImportLog` w RAM.
- Per-tenant storage limitów nie istnieje — encja `apps/api/src/Shared/Domain/Tenant.php` nie ma pola konfiguracyjnego na limity importu (ma `enabledLocales`, `primaryLocale`, plan, status).

## Zakres prac
1. **Limity per tenant (D10)**: migracja Doctrine — nullable kolumny `import_max_file_size_bytes BIGINT NULL` i `import_max_rows INT NULL` na `tenants` (+ ORM XML; nullable, więc bez pułapki NOT NULL default). `NULL` = użyj defaultów z parametrów kontenera: `import.max_file_size_bytes` (default 100 MB) i `import.max_rows` (default 200 000), nadpisywalne env (`IMPORT_MAX_FILE_SIZE_BYTES`, `IMPORT_MAX_ROWS`). Nowy serwis `ImportLimits` (Import\Application) z metodami `maxFileSizeBytes(Tenant)` / `maxRows(Tenant)`.
2. **Egzekwowanie limitu rozmiaru**: w `ParsePreviewController` i `StartImportController` — plik > limitu → 422 RFC 7807 z komunikatem zawierającym limit i rozmiar pliku (klucz i18n po stronie FE). Podnieś `upload_max_filesize`/`post_max_size` w `apps/api/frankenphp/php.ini` do 128M/132M, żeby default 100 MB był osiągalny (spójny limit edge w Caddy robi **IMP2-2.8** — skoordynować wartości w PR).
3. **Egzekwowanie limitu wierszy**: licznik w pętli streamingu (po IMP2-2.1 readery iterują wiersz po wierszu, więc nie trzeba czytać całego pliku z góry): przekroczenie `maxRows` w preview → 422 z licznikiem; w przebiegu (`ImportRunHandler`) → sesja kończy jako `failed` z czytelnym `error_message` (guard na wypadek ominięcia preview).
4. **Allowed-Errors próg % per profil (wzorzec Magento, default OFF)**: migracja — nullable `allowed_errors_percent SMALLINT NULL` na `import_profiles` + pole w `ImportProfile` + przyjęcie w payloadzie zapisu profilu. W `ImportRunHandler`: gdy próg ustawiony i `error_count / processed > próg` (sprawdzane per chunk, po min. 100 przetworzonych wierszach), przebieg przerywa się — sesja `failed` z komunikatem „Przekroczono próg błędów X% (Y błędów na Z wierszy)". Komunikowany przed startem: serializacja profilu zwraca próg, a krok potwierdzenia w wizardzie pokazuje go w podsumowaniu (minimalna zmiana w `apps/admin/src/features/imports/wizard/StepConfirm.tsx` — wiersz w karcie podsumowania; pełny UI progu w kreatorze v2 = etap 3).
5. **Rate limit sesji importu per tenant**: nowy limiter `import_session_start` w `framework.yaml` (proponowany default: `sliding_window`, 20/h per tenant — wartość w parametrze, udokumentowana w komentarzu). Konsumpcja w `StartImportController` przed utworzeniem sesji; przekroczenie → 429 z `Retry-After` (wzorzec z `apps/api/src/Backup/Presentation/Controller/TriggerBackupController.php`).
6. **Raport CSV przez `StreamedResponse`**: przepisz `ImportReportCsvController` na `Symfony\Component\HttpFoundation\StreamedResponse` + nowa metoda repo `iterateBySession()` (Doctrine `toIterable()` + `$em->clear()` co ~500 rekordów) bez sztucznego limitu 100k; `fputcsv` bezpośrednio do `php://output`. Usuń kłamliwy fragment docblocka.
7. **Testy**: ApiTestCase — 422 dla pliku > limit, 422 dla > maxRows w preview, 429 po wyczerpaniu limitera (w teście można obniżyć limit przez config testowy), abort sesji po przekroczeniu progu błędów, raport CSV dla sesji z >1000 logów (asercja nagłówków + liczby linii). Unit dla `ImportLimits` (fallback tenant → default).

## Poza zakresem tego ticketu
- Spójny limit body w Caddy + RFC 7807 na poziomie edge — **IMP2-2.8** (skoordynować liczby).
- Streaming readerów — **IMP2-2.1** (zależność).
- Pełny UI konfiguracji limitów tenanta i progu błędów w kreatorze — etap 3 (**IMP2-3.7**, wizard v2); tu tylko odczyt/zapis przez API + wiersz w podsumowaniu.
- Plik odrzutów do re-importu — **IMP2-3.6** (dry-run v2).
- Rate limity backupu (istnieją) i harmonogramów — **IMP2-4.1**.

## Kryteria akceptacji
- [ ] `POST /api/import-sessions/parse-preview` (i start) z plikiem > `import_max_file_size_bytes` zwraca 422 RFC 7807 z limitem w treści (test ApiTestCase).
- [ ] Plik z liczbą wierszy > `import_max_rows` → 422 w preview; w starcie sesja kończy jako `failed` z czytelnym `error_message` (test).
- [ ] Kolumny `tenants.import_max_file_size_bytes` / `import_max_rows` działają jako override (test: tenant z limitem 10 wierszy odrzuca 11-wierszowy plik, default tenant nie).
- [ ] Profil z `allowed_errors_percent = 10`: import, w którym >10% wierszy ma błędy blokujące, zostaje przerwany ze statusem `failed` i komunikatem z licznikami; profil bez progu (default) zachowuje dzisiejsze zachowanie partial (test).
- [ ] 21. `POST /api/import-sessions` w ciągu godziny dla tego samego tenanta → 429 z `Retry-After` (test z obniżonym limitem).
- [ ] `GET /api/import-sessions/{id}/report.csv` zwraca `StreamedResponse`; sesja z >1000 wpisów `import_logs` streamuje pełen raport (bez limitu 100k), pamięć płaska (brak `stream_get_contents` w kontrolerze).
- [ ] PHPStan max zielony; regeneracja `docs/api-spec/v0.json` (nowe pola profilu/tenanta w API).

## Jak zwalidować (smoke test po wykonaniu)
1. `docker compose exec api php bin/console doctrine:migrations:migrate -n` → migracje przechodzą.
2. Zaloguj się na `https://pim.localhost` (admin@demo.localhost / changeme), pobierz token: `TOKEN=$(curl -sk https://pim.localhost/api/auth/login -H 'Content-Type: application/json' -d '{"email":"admin@demo.localhost","password":"changeme"}' | jq -r .token)`.
3. Wygeneruj plik 110 MB: `mkfile -n 110m /tmp/big.csv` (albo `dd`), potem `curl -sk -X POST https://pim.localhost/api/import-sessions -H "Authorization: Bearer $TOKEN" -F file=@/tmp/big.csv -F target_object_type_id=<uuid-product-OT> -F 'mapping={}' -o - -w '%{http_code}'` → `422` + JSON RFC 7807 z limitem (nie ucięte połączenie, nie 500).
4. Ustaw tenantowi `import_max_rows = 5` (SQL: `docker compose exec db psql -U pim -c "UPDATE tenants SET import_max_rows=5 WHERE code='demo'"`), wgraj w kreatorze CSV z 10 wierszami → preview pokazuje czytelny błąd limitu. Przywróć `NULL`.
5. W pętli wywołaj start importu małego pliku >20× w godzinę (albo obniż limit env i zrestartuj api) → 429 z `Retry-After`.
6. Sesja z błędami (np. `Zrodla/Importy przykładowe/bosch-09-01-2026.csv` ze złym mapowaniem): pobierz raport z UI sesji → plik CSV kompletny; `docker stats --no-stream` na api w trakcie — bez skoku RAM.
7. DevTools Console bez czerwonych błędów przy krokach 4 i 6.

## Zależności
- Blokowany przez: **IMP2-2.1** (limit wierszy liczony na streamie, nie po full-load).
- Blokuje: gate etapu 2; koordynacja wartości limitów z **IMP2-2.8** (Caddy).

## Referencje
- `Project Plan/UI/feature-imports-v2.md` §4.4 **D10** (100 MB / 200k, Allowed-Errors default OFF), §5 ETAP 2 poz. 2.7, §2.6 (raport CSV do RAM).
- Kod: `apps/api/src/Import/Presentation/Controller/StartImportController.php`, `apps/api/src/Import/Presentation/Controller/ParsePreviewController.php`, `apps/api/src/Import/Presentation/Controller/ImportReportCsvController.php`, `apps/api/src/Import/Domain/Repository/ImportLogRepositoryInterface.php`, `apps/api/src/Import/Domain/Entity/ImportProfile.php`, `apps/api/config/packages/framework.yaml` (rate_limiter), `apps/api/frankenphp/php.ini`, wzorzec 429: `apps/api/src/Backup/Presentation/Controller/TriggerBackupController.php`.
- Issues: #446 (IMP-05 raport), #445 (IMP-04). Lekcja ORM NOT NULL default — memory `feedback_orm_notnull_needs_default` (tu kolumny nullable, więc bezpiecznie).

## Definition of Done
- PHPStan max zielony (`cache:warmup --env=dev` + `--memory-limit=1G`); php-cs-fixer przed commitem.
- PHPUnit ≥80% nowej logiki; ApiTestCase dla wszystkich nowych zachowań endpointów (422/429/abort/stream); `cache:clear --env=test` przed uruchomieniem Api/*.
- Regeneracja `docs/api-spec/v0.json` (`cache:warmup` + `api:openapi:export`) — diff scope'owany do zmian tego ticketu.
- FE typecheck z `NODE_OPTIONS=--max-old-space-size=4096`; zmiana w StepConfirm objęta istniejącym/krótkim Playwrightem jeśli widoczna.
- Manual smoke wg sekcji „Jak zwalidować" z artefaktem dowodu (kody HTTP + body z curl, screenshot komunikatu w preview) w komentarzu zamykającym — CLOSED MEANS CLOSED.

---

## IMP2-2.8 — security(import,export): bezpieczeństwo plików — zip-bomb, folder-probe, CSV injection, limit body (#1484)

**Labels:** backend, security, infra, epik-IMP2 · **Estymata:** 4–6 h · **Zależy od:** brak

## Po co to robimy (kontekst nietechniczny)
Moduł importu przyjmuje pliki od użytkowników, a moduł eksportu produkuje pliki otwierane w Excelu — obie ścieżki mają dziś znane klasy podatności. Spreparowany XLSX (zip-bomba) może rozdąć się w pamięci i ubić serwer. Health-check źródła typu „folder" przyjmuje dowolną ścieżkę i grzecznie raportuje, czy istnieje i ile ma plików — to gotowe narzędzie do skanowania wnętrza kontenera. Wyeksportowany CSV z komórką zaczynającą się od `=` wykona formułę po otwarciu w Excelu u klienta (CSV injection). Po tym tickecie każda z tych dziur jest zamknięta, a za duże uploady dostają czytelny błąd zamiast zerwanego połączenia.

## Stan obecny
- **Zip-bomb**: `apps/api/src/Import/Application/Service/FileParserService.php` i `apps/api/src/Import/Application/Service/ImportRowReader.php` ładują XLSX przez `PhpOffice\PhpSpreadsheet\IOFactory` bez żadnej inspekcji archiwum (ratio kompresji, liczba wpisów, rozmiar po dekompresji). `memory_limit = 256M` (`apps/api/frankenphp/php.ini`) — bomba = OOM workera i 500. Po IMP2-2.1 czytanie idzie przez openspout, ale inspekcji archiwum nadal nikt nie robi.
- **Folder-probe**: `apps/api/src/Import/Application/Service/HealthCheck/FolderHealthCheckDriver.php` — `probe()` bierze `ImportSource.path` jak leci: `is_dir($path)` / `is_readable($path)` / `scandir($path)` i zwraca komunikaty różnicujące („is not a directory" vs „is not readable" vs liczba plików) → enumeracja dowolnych katalogów kontenera (np. `/etc`, `/var/www`) przez `POST .../test-connection` (`apps/api/src/Import/Presentation/Controller/TestImportSourceConnectionController.php`).
- **CSV injection przy eksporcie**: `apps/api/src/Export/Infrastructure/Writer/CsvStreamWriter.php` pisze wartości przez `fputcsv` bez sanityzacji prefiksów formuł (`=`, `+`, `-`, `@`). `XlsxStreamWriter.php` (openspout) zapisuje komórki jako typ string — formuły nie są wykonywane, ale do potwierdzenia w teście. Importowane dane NIE są nigdzie mutowane — i tak ma zostać.
- **Limit body na edge**: `docker/caddy/Caddyfile` nie ma żadnego `request_body max_size` — za duży upload ubija dopiero PHP (`post_max_size = 56M`), co objawia się uciętym połączeniem / pustym `$_FILES`, bez RFC 7807.

## Zakres prac
1. **`XlsxArchiveGuard`** (nowy serwis, `Import\Application\Service`): przed jakimkolwiek parsowaniem XLSX otwiera plik przez `ZipArchive` i czyta **central directory bez dekompresji**: (a) liczba wpisów > 1 000 → reject; (b) suma `uncompressedSize` > limit (parametr, default 2 GB) → reject; (c) ratio `uncompressedSize/compressedSize` archiwum > 200 **i jednocześnie** suma po dekompresji > 512 MB → reject. Progi celowo koniunkcyjne i konfigurowalne parametrami kontenera — legalne pliki z tysiącami powtórzonych wartości kompresują się ekstremalnie dobrze i NIE mogą wpadać w false-positive (przetestuj na benchmarkach!). Reject = 422 RFC 7807 z komunikatem typu „Plik wygląda na uszkodzony lub potencjalnie niebezpieczny (nadmierny współczynnik kompresji). Przekonwertuj plik do CSV i spróbuj ponownie" — **nie 500**. Wpinka w `ParsePreviewController` i `StartImportController` (oraz w przepływ StagedFile, gdy IMP2-2.2 zmerguje — dodać guard w jednym wspólnym miejscu stagingu, jeśli kolejność na to pozwoli).
2. **Whitelist ścieżek dla `FolderHealthCheckDriver`**: parametr `import.source_base_path` (env `IMPORT_SOURCE_BASE_PATH`, default np. `/var/pim/import-sources`). Walidacja: `realpath()` ścieżki musi istnieć wewnątrz base path (containment check odporny na `..` i symlinki — porównanie po realpath obu stron). Ścieżka poza whitelistą → `HealthCheckResult(Error, 'Path is outside the allowed import sources directory.')` — jeden, niezróżnicowany komunikat (bez zdradzania, czy katalog istnieje). Walidacja także przy zapisie `ImportSource.path` (422), nie tylko przy probe.
3. **Sanityzacja CSV injection przy EKSPORCIE** (OWASP, advisory GHSA-2xhg-w2g5-w95x): w `CsvStreamWriter::writeRow()` każda komórka zaczynająca się od `=`, `+`, `-`, `@` (oraz `\t`, `\r` wg OWASP) dostaje prefiks **tab** (`\t`). Wyłącznie przy eksporcie — **NIGDY nie mutujemy danych przy imporcie** (zero zmian w Import BC). Dla `XlsxStreamWriter` — test potwierdzający, że wartości lądują jako string-cells (formuła nie wykonuje się), bez zmian kodu jeśli tak jest. UWAGA dla round-tripu: default-trim transformu importu (D1/etap 3) zdejmuje wiodący tab przy ponownym imporcie — dopisz tę regułę do reguł normalizacji golden testu w ADR IMP2-1.1 (komentarz w ADR/teście, koordynacja z IMP2-1.5).
4. **Spójny limit body w Caddy**: w `docker/caddy/Caddyfile` dodaj `request_body { max_size 150MB }` w bloku `handle /api*` (wartość = limit aplikacyjny D10 100 MB + zapas na multipart + nagłówki; skoordynowana z `php.ini` i limitami z IMP2-2.7 — w PR zostaw komentarz wiążący trzy miejsca: Caddy > php.ini > limit aplikacyjny, tak żeby to APLIKACJA odrzucała typowy za duży plik czytelnym RFC 7807 z IMP2-2.7, a Caddy był tylko bezpiecznikiem na ekstremalne payloady). Dokumentujący komentarz w Caddyfile.
5. **Testy**: unit `XlsxArchiveGuard` — wygenerowana w teście mini zip-bomba (mały plik, niski próg przez parametry testowe) → reject; legalny XLSX z 5k powtórzonych wartości → pass. Unit driver folder: ścieżki `/etc`, `../../etc`, symlink poza base → error bez szczegółów; ścieżka w base → dotychczasowa logika. Unit writer CSV: `=SUM(A1)`, `+48123`, `-5`, `@x` → prefiks tab w pliku wynikowym; zwykłe wartości nietknięte. ApiTestCase: upload spreparowanego XLSX → 422 RFC 7807.

## Poza zakresem tego ticketu
- Limity rozmiaru/liczby wierszy pliku i rate limit sesji (aplikacyjne, D10) — **IMP2-2.7** (tu tylko bezpiecznik edge w Caddy).
- Streaming readerów XLSX — **IMP2-2.1** (guard działa niezależnie od silnika parsowania).
- StagedFile / pojedynczy upload — **IMP2-2.2** (guard ma być wpięty tak, żeby przeniesienie do stagingu było trywialne).
- Realne drivery SFTP/HTTP dla źródeł — **IMP2-4.2**; test-connection stub dla innych typów zostaje.
- Sanityzacja na ścieżce IMPORTU — celowo NIE istnieje (mutowałaby dane klienta).

## Kryteria akceptacji
- [ ] Upload spreparowanego XLSX (zip-bomba) do parse-preview i do startu importu zwraca 422 RFC 7807 z komunikatem sugerującym konwersję do CSV; worker nie przekracza memory_limit (test + manual).
- [ ] Benchmarkowe XLSX z `Zrodla/Importy przykładowe` (`bosch-09-01-2026.xlsx`, `Avapax nowości 24.03.2026.xlsx`, `Annex E - Filter supplier product data.xlsx`) przechodzą guard bez false-positive (manual checklist — pliki komercyjne, nie commitujemy).
- [ ] `FolderHealthCheckDriver` z ścieżką spoza `IMPORT_SOURCE_BASE_PATH` (w tym `..` i symlink) zwraca błąd jednolitym komunikatem bez ujawniania istnienia/zawartości; ścieżka w obrębie base działa jak dotychczas (testy unit).
- [ ] Zapis `ImportSource` ze ścieżką poza base → 422 (ApiTestCase).
- [ ] Eksport CSV produktu z wartością `=HYPERLINK(...)` daje komórkę z prefiksem `\t` (test + manual w Excel/Numbers: formuła się NIE wykonuje); XLSX-eksport tej samej wartości nie wykonuje formuły (test typu komórki).
- [ ] Wartości w bazie po imporcie pliku zawierającego `=...` pozostają BEZ prefiksu (asercja braku mutacji przy imporcie).
- [ ] `docker/caddy/Caddyfile` ma `request_body max_size` dla `/api*` z komentarzem wiążącym łańcuch limitów; request > limitu Caddy dostaje 413 z edge.
- [ ] PHPStan max zielony; wszystkie istniejące testy Import/Export zielone.

## Jak zwalidować (smoke test po wykonaniu)
1. Wygeneruj zip-bombę testową: `python3 -c "import zipfile; z=zipfile.ZipFile('/tmp/bomb.xlsx','w',zipfile.ZIP_DEFLATED); z.writestr('xl/worksheets/sheet1.xml','A'*500_000_000); z.close()"`. Zaloguj się (`https://pim.localhost`, admin@demo.localhost / changeme), pobierz token i wyślij: `curl -sk -X POST https://pim.localhost/api/import-sessions/parse-preview -H "Authorization: Bearer $TOKEN" -F file=@/tmp/bomb.xlsx -w '\n%{http_code}'` → `422` + RFC 7807 (sprawdź `docker stats` — bez skoku RAM, bez restartu api).
2. Wgraj w kreatorze `Zrodla/Importy przykładowe/bosch-09-01-2026.xlsx` i `Annex E - Filter supplier product data.xlsx` → preview działa normalnie (brak false-positive).
3. Utwórz źródło typu folder ze ścieżką `/etc` (Ustawienia importów → źródła) i kliknij test połączenia → błąd „outside the allowed...", bez liczby plików; ścieżka w obrębie `IMPORT_SOURCE_BASE_PATH` → OK/Warn jak dotąd.
4. Ustaw produktowi wartość tekstową `=2+2` (edycja w adminie), wyeksportuj CSV, otwórz w Excelu/Numbers → komórka pokazuje tekst, nie `4`. `xxd export.csv | grep -A1 '='` → widoczny `\t` przed `=`.
5. Zaimportuj z powrotem ten CSV → wartość w UI produktu nadal `=2+2` (bez taba — trim transformu; bez podwójnej mutacji).
6. `curl -sk -X POST https://pim.localhost/api/import-sessions -H "Authorization: Bearer $TOKEN" -F file=@/tmp/200mb.bin -w '%{http_code}'` (plik 200 MB) → 413 z Caddy (nie zerwane połączenie bez odpowiedzi).
7. DevTools Console bez czerwonych błędów przy krokach 2–4.

## Zależności
- Blokowany przez: — (samodzielny; koordynacja wartości limitów z **IMP2-2.7**, reguła trim w golden test z **IMP2-1.1/1.5**).
- Blokuje: gate etapu 2 (bezpieczeństwo plików przed pilotami).

## Referencje
- `Project Plan/UI/feature-imports-v2.md` §5 ETAP 2 poz. 2.8, §2.6 (RAM/parsery), §4.4 D10.
- OWASP CSV Injection (https://owasp.org/www-community/attacks/CSV_Injection), Symfony advisory GHSA-2xhg-w2g5-w95x.
- Kod: `apps/api/src/Import/Application/Service/FileParserService.php`, `apps/api/src/Import/Application/Service/ImportRowReader.php`, `apps/api/src/Import/Application/Service/HealthCheck/FolderHealthCheckDriver.php`, `apps/api/src/Import/Presentation/Controller/TestImportSourceConnectionController.php`, `apps/api/src/Export/Infrastructure/Writer/CsvStreamWriter.php`, `apps/api/src/Export/Infrastructure/Writer/XlsxStreamWriter.php`, `docker/caddy/Caddyfile`, `apps/api/frankenphp/php.ini`.
- Issues: #500 (VIEW-IMP-03 — wprowadził folder driver), #1130.

## Definition of Done
- PHPStan max zielony; php-cs-fixer przed commitem; PHPUnit ≥80% nowej logiki (guard, containment, sanitizer); ApiTestCase dla 422 zip-bomby i 422 ścieżki źródła (`cache:clear --env=test` przed Api/*).
- Brak zmian kształtu API → bez regeneracji OpenAPI (chyba że RFC 7807 payload dodaje pola — wtedy regeneracja + scoped diff).
- Semgrep/audit bez nowych findings (`composer audit`).
- Manual smoke wg sekcji „Jak zwalidować" z artefaktem dowodu w komentarzu zamykającym (kody HTTP + body, screenshot komórki w Excelu, wynik probe `/etc`) — CLOSED MEANS CLOSED.

---

## IMP2-2.9 — fix(catalog): macierz równoległości — BulkOperationLock dla bulk-edit + OptimisticLock per-id (D11) (#1485)

**Labels:** backend, bug, docs, epik-IMP2 · **Estymata:** 4–6 h · **Zależy od:** IMP2-2.6

## Po co to robimy (kontekst nietechniczny)
Gdy w tym samym czasie trwa import i ktoś odpali masową edycję produktów (albo dwóch operatorów pracuje równolegle), system dziś nie ma spójnych reguł, kto wygrywa i co jest bezpieczne. Masowa edycja z listy produktów (do 5000 sztuk naraz) w ogóle nie respektuje blokady „jedna operacja masowa na tenant". Z kolei przeliczanie indeksów w tle, gdy zderzy się z równoległą edycją produktu w UI, wywala CAŁĄ paczkę przeliczeń zamiast ponowić jeden konfliktowy produkt. Po tym tickecie operacje masowe są skoordynowane jedną blokadą, konflikty wersji obsługiwane punktowo, oczekiwanie na blokadę ma sensowną politykę ponowień, a reguły współbieżności są spisane w jednym dokumencie.

## Stan obecny
- `apps/api/src/Shared/Application/BulkOperationLock.php` — lock Symfony `bulk-op:{tenantId}`, TTL 3600 s, non-blocking acquire. Używają go **TYLKO**: `apps/api/src/Import/Application/Handler/ImportRunHandler.php` i `apps/api/src/Shared/Infrastructure/Maintenance/DatabaseResetCommand.php` (zweryfikowane grep).
- `apps/api/src/Catalog/Presentation/Controller/BulkEditController.php` (UI-02.3 #293) — synchroniczny bulk edit `POST /api/products/bulk-edit`, `MAX_IDS = 5000`, operacje `toggle_enabled` / `set_attribute_value`. **Nie bierze `BulkOperationLock` ani nie ustawia `BulkContext`** — może orać po katalogu równolegle z trwającym importem.
- Nowsze handlery bulk ops `apps/api/src/Catalog/Application/Bulk/*.php` (`BulkSetAttributeHandler`, `BulkMultiAttributeEditHandler`, `BulkAddCategoryHandler`, `BulkIncrementNumericHandler`, `BulkRollbackHandler`) — są BulkContext-aware (VIEW-35), ale również NIE biorą `BulkOperationLock`.
- `apps/api/src/Catalog/Application/Handler/RebuildAttributesIndexedHandler.php` — pętla po `objectIds` z `flushAndClear()`; **brak obsługi `OptimisticLockException`**. `CatalogObject` ma optimistic locking: `apps/api/src/Catalog/Infrastructure/Doctrine/Orm/Mapping/CatalogObject.orm.xml` linia 128 — `<field name="version" ... version="true">`. Konflikt z równoległą edycją w UI (bump `objects.version` między load a flush) = wyjątek wywala cały batch → retry/dead-letter WSZYSTKICH id-ków, w tym już przeliczonych.
- Polityka retry: `apps/api/config/packages/messenger.yaml` — **brak sekcji `retry_strategy`** (defaulty Symfony: 3 retry, delay 1 s, multiplier 2). `ImportRunHandler` przy kontencji locka rzuca `RecoverableMessageHandlingException` — po 3 szybkich retry (łącznie ~7 s) wiadomość ląduje w transporcie `failed`, a `ImportSession` zostaje `pending` na zawsze, mimo że blokujący job mógł trwać minuty.
- Kontrakt D11 (`import_session_id` = wyłącznie marker created-by, last-writer-wins na `object_values`, guard undo-logu) nie jest nigdzie spisany jako macierz konfliktów.

## Zakres prac
1. **`BulkEditController` pod `BulkOperationLock`**: non-blocking acquire na początku `bulkEdit()`; kolizja → `BulkOperationInProgressException` przetłumaczony na **409 Conflict** RFC 7807 (wzorzec ze `StartImportController`); release w `finally`. FE list bulk-edit pokaże istniejącą obsługę błędu (sprawdź, że komunikat 409 jest czytelny — jeśli FE łyka błąd, minimalny fix komunikatu).
2. **Audyt lock-coverage handlerów `Catalog\Application\Bulk\*`**: każdy z 5 handlerów albo dostaje acquire/release `BulkOperationLock` (ten sam wzorzec), albo świadome uzasadnienie w macierzy (pkt 5), dlaczego nie (np. małe paczki z wizarda). Decyzję podejmij według realnego wolumenu — default: dodaj lock, skoro to operacje masowe na tych samych `object_values`.
3. **`RebuildAttributesIndexedHandler` — `OptimisticLockException` per-id**: złap wyjątek wokół pojedynczego `rebuild($object)` + flush; na konflikt: `$em->clear()`, świeży `find()` tego samego id i ponowienie (max 3 próby); po wyczerpaniu — `$logger->warning()` z id + **continue** (reszta batcha się przelicza; jeden nieprzeliczony obiekt to mniejsze zło niż dead-letter całej paczki — wartość i tak przeliczy następny event tego obiektu). Test integracyjny symulujący konflikt (bump `version` w równoległym połączeniu DBAL między load a flush).
4. **Polityka retry/delay dla kontencji locka**: jawna sekcja `retry_strategy` dla transportu async/`import` w `messenger.yaml`: `max_retries: 5`, `delay: 30000` (30 s), `multiplier: 2`, `max_delay: 300000` — ~16 min łącznego oczekiwania pokrywa realny czas trzymania locka przez import. **Dead-letter guard**: gdy `ImportRunMessage` mimo retry ląduje w `failed` (listener na `WorkerMessageFailedEvent` z `willRetry=false`), powiązana `ImportSession` przechodzi w `failed` z czytelnym `error_message` („Nie udało się uzyskać blokady operacji masowych — inna operacja trwała zbyt długo. Uruchom import ponownie.") — sesja nie wisi wiecznie w `pending`. Test routing/retry konfiguracji.
5. **Dokumentacja macierzy konfliktów (D11)**: nowy `docs/architecture/concurrency-matrix.md` — tabela: import × bulk-edit (controller + handlery Bulk/*) × edycja pojedyncza w UI × rebuild async × rollback × backup/db-reset; dla każdej pary: mechanizm (lock / optimistic version / last-writer-wins / brak konfliktu) i zachowanie użytkownika (409 / retry / kolejka). Zapisz kontrakty: `objects.import_session_id` = WYŁĄCZNIE marker „utworzony przez sesję" (stampowany tylko na create; rollback-delete nie może dotykać obiektów istniejących przed sesją), last-writer-wins na `object_values` + guard undo-logu (provenance/updated_at — implementacja w IMP2-2.4, tu kontrakt). Link z ADR Import v2 (IMP2-1.1) i z `docs/adr/README.md` jeśli indeksuje.

## Poza zakresem tego ticketu
- Implementacja undo-logu i guardu provenance/updated_at przy rollbacku — **IMP2-2.4** (tu tylko spisany kontrakt).
- BulkContext/wydajność bulk-path importu — **IMP2-2.6** (zależność).
- Pause/cancel/resume i checkpoint — **IMP2-2.3**.
- Lock TTL renewal dla importów >1h — **IMP2-2.3** (lock TTL 3600 s zostaje).
- Przeniesienie `BulkEditController` na async Messenger — poza epikiem (świadomie sync do 5000 id).

## Kryteria akceptacji
- [ ] ApiTestCase: `POST /api/products/bulk-edit` podczas trzymanego locka tenanta zwraca **409** RFC 7807; po zwolnieniu locka działa normalnie; lock zwalniany także przy wyjątku w trakcie (finally).
- [ ] Każdy handler `Catalog\Application\Bulk\*` ma lock ALBO wpis w macierzy z uzasadnieniem (review checklist w PR).
- [ ] Test integracyjny: `RebuildAttributesIndexedHandler` z batchem 3 id, gdzie środkowy dostaje konflikt wersji → pozostałe 2 przeliczone, konfliktowy ponowiony (a po wyczerpaniu prób zalogowany i pominięty), wiadomość NIE trafia do `failed`.
- [ ] `messenger.yaml` ma jawny `retry_strategy` (5 prób, 30 s start, multiplier 2) + test asercji konfiguracji; kontencja locka importu nie dead-letteruje przed ~10 min oczekiwania.
- [ ] Listener dead-letter: sztucznie wyczerpane retry `ImportRunMessage` → `ImportSession.status = failed` + czytelny `error_message` (test).
- [ ] `docs/architecture/concurrency-matrix.md` istnieje, pokrywa wszystkie pary z zakresu pkt 5 i kontrakty D11; ADR IMP2-1.1 linkuje.
- [ ] PHPStan max zielony; istniejące testy bulk/import zielone.

## Jak zwalidować (smoke test po wykonaniu)
1. `docker compose exec api php bin/console cache:clear --env=test && docker compose exec api vendor/bin/phpunit --filter 'BulkEdit|RebuildAttributesIndexed|ImportRun'` → zielone.
2. Zaloguj się na `https://pim.localhost` (admin@demo.localhost / changeme). Uruchom duży import (np. CSV 5k wierszy — może być syntetyczny albo `Zrodla/Importy przykładowe/GA_List.csv` dla dłuższego okna locka).
3. W trakcie trwania importu: lista produktów → zaznacz kilka → masowa edycja (np. toggle enabled) → DevTools Network: response **409** z czytelnym komunikatem JSON (nie 500, nie cicha połowiczna edycja); UI pokazuje błąd, Console bez czerwonych wyjątków nieobsłużonych.
4. Po zakończeniu importu powtórz bulk-edit → 200, zmiany widoczne.
5. Konflikt wersji: otwórz produkt w dwóch kartach; w jednej zapisz zmianę atrybutu w momencie gdy worker przelicza indeksy po imporcie (alternatywnie: test integracyjny z pkt 3 kryteriów jako dowód) → `docker compose logs worker | grep -i optimistic` pokazuje warning z retry per-id, `docker compose exec api php bin/console messenger:failed:show` NIE zawiera `ObjectValuesChangedMessage`.
6. Dead-letter guard: zatrzymaj na chwilę zwalnianie locka (np. drugi długi import) i odpal trzeci import; po wyczerpaniu retry (można obniżyć delaye w env dev) sesja w UI ma status `failed` z komunikatem o blokadzie — nie wisi w `pending`.
7. Otwórz `docs/architecture/concurrency-matrix.md` w przeglądzie PR — recenzja tabeli przez operatora.

## Zależności
- Blokowany przez: **IMP2-2.6** (bulk-path i dispatch rebuildów muszą już działać, żeby testować realne kolizje).
- Blokuje: **IMP2-2.4** korzysta ze spisanych kontraktów (może iść równolegle po ustaleniu macierzy); gate etapu 2 (smoke równoległości).

## Referencje
- `Project Plan/UI/feature-imports-v2.md` §4.4 **D11**, §5 ETAP 2 poz. 2.9, §2.6.
- Kod: `apps/api/src/Catalog/Presentation/Controller/BulkEditController.php`, `apps/api/src/Shared/Application/BulkOperationLock.php`, `apps/api/src/Shared/Application/BulkOperationInProgressException.php`, `apps/api/src/Catalog/Application/Handler/RebuildAttributesIndexedHandler.php`, `apps/api/src/Catalog/Application/Bulk/` (5 handlerów), `apps/api/src/Catalog/Infrastructure/Doctrine/Orm/Mapping/CatalogObject.orm.xml` (version), `apps/api/config/packages/messenger.yaml`, `apps/api/src/Import/Application/Handler/ImportRunHandler.php`, `apps/api/src/Shared/Infrastructure/Maintenance/DatabaseResetCommand.php`.
- Issues: #293 (UI-02.3 bulk edit), #445 (IMP-04), PROD-05 (lock).

## Definition of Done
- PHPStan max zielony; php-cs-fixer przed commitem; PHPUnit ≥80% nowej logiki; ApiTestCase dla 409 bulk-edit; testy integracyjne na realnym Postgresie (no-mock policy).
- Bez zmian kształtu API poza nowym kodem błędu 409 → regeneracja `docs/api-spec/v0.json` jeśli opis odpowiedzi wchodzi do OpenAPI (scoped diff).
- `docs/architecture/concurrency-matrix.md` w tym samym PR.
- Manual smoke wg sekcji „Jak zwalidować" z artefaktem dowodu w komentarzu zamykającym (HTTP 409 body z DevTools, log warning per-id, screenshot sesji failed z komunikatem o locku) — CLOSED MEANS CLOSED.

---

## IMP2-2.10 — feat(import,backup): spięcie do_backup z modułem Backup i sesją importu (#1486)

**Labels:** backend, frontend, enhancement, epik-IMP2 · **Estymata:** 2–3 h · **Zależy od:** brak

## Po co to robimy (kontekst nietechniczny)
W kreatorze importu jest checkbox „Utwórz pełen backup bazy danych (pgBackRest)". Backup faktycznie się wykonuje (moduł Backup działa), ale system w żaden sposób nie wiąże go z sesją importu: backend ignoruje przesłaną flagę, a w widoku sesji nigdzie nie widać, czy i jaki backup został zrobiony przed importem. Jeśli operator po nieudanym imporcie chce wrócić do snapshotu, musi sam zgadywać, który backup był „tym przed importem". Po tym tickecie sesja importu zapamiętuje swój backup, widok sesji go pokazuje, a backend pilnuje, żeby import z zaznaczonym backupem nie ruszył bez ukończonego snapshotu. To także likwidacja jednej z „fałszywych affordancji" z sekcji 2.7 planu.

## Stan obecny
- FE wysyła flagę: `apps/admin/src/features/imports/wizard/StepConfirm.tsx` linia 52 — `formData.set('do_backup', state.doBackup ? '1' : '0')`. CTA „Uruchom import" jest już dziś gate'owane: `canRun` wymaga `backupStatus === 'completed'` gdy checkbox zaznaczony (linia 37).
- `apps/admin/src/features/imports/components/BackupTriggerCheckbox.tsx` — po zaznaczeniu robi własny `POST /api/backups` (`triggered_by_action: 'pre_import'`) i polluje `GET /api/backups/{id}` co 5 s do stanu terminalnego; **id utworzonego backupu zostaje wyłącznie w lokalnym stanie komponentu** (`backupId`), nigdy nie trafia do requestu startu importu.
- Backend ignoruje flagę: `apps/api/src/Import/Presentation/Controller/StartImportController.php` — docblock wprost: „`do_backup` — boolean, optional (forwarded to IMP-06 in a follow-up)"; w kodzie ZERO odczytów `do_backup`.
- `apps/api/src/Import/Domain/Entity/ImportSession.php` — pole `private ?Backup $backupSnapshot` (linia 74) z getterem/setterem (243–248); **`setBackupSnapshot()` nie ma ani jednego wywołania w `apps/api/src`** (zweryfikowane grep) — martwe pole schematu (sekcja 2.7 planu).
- Moduł Backup ISTNIEJE i działa: `apps/api/src/Backup/Presentation/Controller/TriggerBackupController.php` (`POST /api/backups`, 202, dispatch `BackupSnapshotMessage`, RBAC `backup:write`, rate limiter `backup_trigger` = sliding window **1/h/tenant** w `apps/api/config/packages/framework.yaml` linie 63–66), `GetBackupController` (`GET /api/backups/{id}`), encja `Backup` ze state machine `BackupStatus` (pending/running/completed/failed) i `BackupTriggerAction` (manual/pre_import/scheduled).
- Widok sesji nie pokazuje backupu: `apps/api/src/Import/Presentation/Controller/GetImportSessionController.php` serializuje id/status/liczniki/daty — bez `backup`; FE `apps/admin/src/features/imports/show/ImportShowPage.tsx` nie ma sekcji backupu.

## Zakres prac
1. **FE — przekazanie backup_id**: `BackupTriggerCheckbox` wystawia id ukończonego backupu do rodzica (nowy prop `onBackupCreated(id)` lub rozszerzenie `onStatusChange`); `StepConfirm` trzyma `backupId` w stanie i przy submit dodaje `formData.set('backup_id', backupId)` gdy `do_backup=1`. Orkiestracja zostaje po stronie FE jak dziś (decyzja per plan: „FE-gate jak dziś + zapis backupSnapshot na sesji" — bez backendowego łańcucha stanów; uzasadnienie: moduł Backup już ma własny async flow i polling, a CTA i tak czeka na `completed`).
2. **Backend — walidacja i zapis**: `StartImportController` czyta `do_backup` + `backup_id`: (a) `do_backup=1` bez `backup_id` → 422 RFC 7807 („Backup был requested, ale nie przekazano backup_id ukończonego backupu" — komunikat po polsku); (b) `backup_id` wskazujący backup nieistniejący / innego tenanta → 404 (bez ujawniania istnienia); (c) backup w stanie innym niż `completed` → 422 z aktualnym statusem; (d) poprawny → `$session->setBackupSnapshot($backup)` przed zapisem sesji. Usuń kłamliwy fragment docblocka „forwarded in a follow-up". `do_backup=0`/brak — bez zmian zachowania.
3. **Widoczność w sesji**: `GetImportSessionController` (i `ListImportSessionsController`, jeśli lista ma kolumnę/badge) serializuje `backup: {id, status, started_at} | null`; FE `ImportShowPage.tsx` pokazuje w nagłówku/metadanych sesji wiersz „Backup przed importem: ✅ <data> (id)" lub „—" (i18n przez `t()`, klucze pl/en).
4. **Kolizja z harmonogramami — odnotowanie (UWAGA z planu)**: rate limit `backup_trigger` 1/h/tenant koliduje z przyszłymi importami z harmonogramu (IMP2-4.1) — backup pre-import pozostaje funkcją **wyłącznie importów manualnych z wizarda**. Dodaj komentarz przy limiterze w `framework.yaml` + akapit w `docs/architecture/concurrency-matrix.md` (po IMP2-2.9) lub w ADR IMP2-1.1, żeby IMP2-4.1 tego nie zassał automatycznie. Drugi import w ciągu godziny z zaznaczonym backupem dostanie 429 z `/api/backups` — FE już to obsługuje (komunikat + odznaczenie checkboxa); upewnij się, że komunikat jest zrozumiały („Limit: 1 backup na godzinę — możesz kontynuować import bez backupu albo poczekać").
5. **Testy**: ApiTestCase dla wariantów (a)–(d) z pkt 2 (w teście backup tworzony bezpośrednio przez repozytorium z wymuszonym statusem — bez realnego pgBackRest); asercja, że `GET /api/import-sessions/{id}` zwraca obiekt `backup` po linkowaniu; test izolacji tenantów (backup obcego tenanta → 404). Playwright: przebieg wizarda z odznaczonym checkboxem (bez regresji) + widoczność sekcji backupu w widoku sesji (mock/seed stanu).

## Poza zakresem tego ticketu
- Backendowa orkiestracja „najpierw backup, potem auto-start importu" (state machine łącząca oba moduły) — świadomie NIE; FE-gate wystarcza, decyzja odnotowana wyżej.
- Restore/rollback z backupu pgBackRest w UI — osobny obszar (moduł Backup / runbook); rollback sesji importu robi **IMP2-2.4** (undo-log), niezależnie od snapshotu.
- Backup przy importach z harmonogramu — **IMP2-4.1** (z odnotowaną kolizją rate limitu).
- Pozostałe martwe pola schematu sesji (`imagesDownloaded/Failed` → IMP2-1.12, `zipFileName` → IMP2-1.13, `notifyChannels` → IMP2-4.4).
- E-mail po imporcie (checkbox placebo w StepConfirm) — **IMP2-4.4**.

## Kryteria akceptacji
- [ ] `POST /api/import-sessions` z `do_backup=1` i `backup_id` ukończonego backupu tworzy sesję z wypełnionym `backup_snapshot_id` (asercja w DB/odpowiedzi) — test ApiTestCase.
- [ ] `do_backup=1` bez `backup_id` → 422; `backup_id` w stanie `pending`/`running`/`failed` → 422 ze statusem w treści; backup innego tenanta lub nieistniejący → 404 — testy ApiTestCase.
- [ ] `do_backup=0` — zachowanie identyczne jak dziś (test regresji: sesja bez backupu startuje normalnie).
- [ ] `GET /api/import-sessions/{id}` zwraca `backup: {id, status, started_at}` dla zlinkowanej sesji i `null` dla pozostałych; regeneracja `docs/api-spec/v0.json` obejmuje nowe pole.
- [ ] W UI widoku sesji (`/integrations/imports/{id}`) widać informację o backupie przed importem (data + status) dla sesji z backupem — Playwright.
- [ ] Docblock `StartImportController` nie zawiera już „forwarded in a follow-up"; komentarz o kolizji rate-limitu z harmonogramami dodany przy limiterze.
- [ ] PHPStan max zielony; typecheck admin zielony.

## Jak zwalidować (smoke test po wykonaniu)
1. Zaloguj się na `https://pim.localhost` (admin@demo.localhost / changeme). Kreator importu → mały CSV (np. 3 wiersze sku/name) → krok potwierdzenia → zaznacz „Utwórz pełen backup".
2. Obserwuj: checkbox POST-uje `/api/backups` (DevTools Network: 202), progress poll co 5 s, po `completed` CTA „Uruchom import" się odblokowuje. Kliknij start → w Network: `POST /api/import-sessions` zawiera w multipart `do_backup=1` **i** `backup_id=<uuid>`; response 200/202.
3. Widok sesji `/integrations/imports/{id}` → widoczny wiersz „Backup przed importem" z datą/statusem; `curl -sk https://pim.localhost/api/import-sessions/<id> -H "Authorization: Bearer $TOKEN" | jq .backup` → obiekt z id/status `completed`.
4. Negatyw: `curl -sk -X POST https://pim.localhost/api/import-sessions -H "Authorization: Bearer $TOKEN" -F file=@/tmp/mini.csv -F target_object_type_id=<uuid> -F 'mapping={"sku":"sku"}' -F do_backup=1 -w '\n%{http_code}'` (bez backup_id) → `422` RFC 7807.
5. Drugi przebieg z backupem w ciągu godziny → checkbox pokazuje czytelny komunikat o limicie 1/h (429 z `/api/backups`), import można uruchomić bez backupu; Console bez czerwonych błędów we wszystkich krokach.
6. Import bez checkboxa → sesja działa jak dotychczas, `backup: null` w API.

## Zależności
- Blokowany przez: — (moduł Backup i wizard istnieją; niezależny od reszty etapu 2).
- Blokuje: **IMP2-4.1** (musi respektować decyzję „backup tylko dla manualnych"); usuwa pozycję `do_backup` z listy fałszywych affordancji (§2.7) przed gate'em etapu 2.

## Referencje
- `Project Plan/UI/feature-imports-v2.md` §2.7 (fałszywe affordancje — `do_backup` ignorowany), §3 filar 12 („zero fałszywych affordancji"), §5 ETAP 2 poz. 2.10 (+ UWAGA o kolizji rate limitu z 4.1).
- Kod: `apps/admin/src/features/imports/wizard/StepConfirm.tsx`, `apps/admin/src/features/imports/components/BackupTriggerCheckbox.tsx`, `apps/api/src/Import/Presentation/Controller/StartImportController.php`, `apps/api/src/Import/Domain/Entity/ImportSession.php` (backupSnapshot), `apps/api/src/Import/Presentation/Controller/GetImportSessionController.php`, `apps/api/src/Backup/Presentation/Controller/TriggerBackupController.php`, `apps/api/src/Backup/Presentation/Controller/GetBackupController.php`, `apps/api/src/Backup/Domain/Entity/Backup.php`, `apps/api/config/packages/framework.yaml` (backup_trigger), `apps/admin/src/features/imports/show/ImportShowPage.tsx`.
- Issues: #447 (IMP-06 backup), #445 (IMP-04 start controller).

## Definition of Done
- PHPStan max zielony; php-cs-fixer przed commitem; PHPUnit ≥80% nowej logiki; ApiTestCase dla wszystkich wariantów walidacji (`cache:clear --env=test` przed Api/*).
- Regeneracja `docs/api-spec/v0.json` (`cache:warmup` + `api:openapi:export`) — diff scoped do pola `backup`.
- FE: typecheck/build z `NODE_OPTIONS=--max-old-space-size=4096`; Playwright dla widocznej zmiany w widoku sesji; i18n przez `t()` (pl/en), po edycji locali `docker compose restart admin`.
- Manual smoke wg sekcji „Jak zwalidować" z artefaktem dowodu w komentarzu zamykającym (request multipart z backup_id z DevTools, `jq .backup` output, screenshot widoku sesji) — CLOSED MEANS CLOSED.

---

## IMP2-3.1 — feat(import): detekcja rozszerzona pliku — header offset, wiersz startu danych, arkusz, separatory, .xls (#1487)

**Labels:** backend, frontend, enhancement, epik-IMP2 · **Estymata:** 9–13 h · **Zależy od:** IMP2-2.1

## Po co to robimy (kontekst nietechniczny)
Pliki od dostawców rzadko wyglądają „książkowo": Kospel ma tytuł NAD nagłówkami, Avapax/Tubądzin mają DWA wiersze nagłówka (kod techniczny + opis po polsku) i dane dopiero od trzeciego wiersza, e-commerce.xlsx ma wiersze-sekcje w środku danych, Annex E używa niemieckiego zapisu liczb („1.000 µm" to tysiąc, nie jeden), a products_export to stary format .xls. Dziś kreator zakłada „nagłówek = wiersz 1, dane od wiersza 2, liczby po polsku" — i na takich plikach się wykłada. Po tym tickecie kreator wykrywa strukturę pliku i pozwala ją ręcznie poprawić, zanim cokolwiek zostanie zaimportowane.

## Stan obecny
- `FileParserService` — nagłówek zawsze wiersz 1, dane od 2; XLSX zawsze pierwszy arkusz (`had_multiple_sheets` zwracane, ale FE ignoruje — `StepUpload.tsx`).
- `DelimiterDetector` (kandydaci `;`, `,`, tab, `|`), `EncodingDetector` (BOM/UTF-8/CP1250/ISO-8859-2) — działają, zostają.
- Brak: header-row offset, data-start-row, skip-rows, para separatorów liczb, format dat, quote char, wybór arkusza, wsparcie `.xls`.
- Parsowanie liczb: `is_numeric` z zamianą przecinka (`ImportValidationService`/`CompositeValueParser`) — bez konceptu separatora tysięcy.

## Zakres prac
1. Parametry parsowania (BE, parse-preview + dry-run + run; persystowane w sesji i profilu): `sheet` (nazwa/indeks), `header_row` (default auto-heurystyka), `data_start_row` (default header_row+1), `skip_row_pattern`/lista skip-rows (wiersze sekcji: heurystyka „≤2 niepuste komórki" + ręczne odznaczenie w preview), `decimal_separator` + `thousands_separator` (pary `, .` / `. ,` / auto), `date_format` (lista: ISO, DD.MM.YYYY, MM/DD/YYYY…), `quote_char`.
2. Heurystyka header-row: wiersz o najwyższym odsetku niepustych, nie-numerycznych, unikalnych komórek w pierwszych 10 wierszach; zwracana z confidence; niska pewność (benchmark all-doctors) → UI wymusza ręczny wybór, bez crasha.
3. Wybór arkusza XLSX: parse-preview zwraca listę arkuszy (nazwa + liczba wierszy), parametr `sheet` respektowany przez wszystkie kroki (D14: jeden arkusz per sesja).
4. Legacy `.xls` (D13): akceptacja rozszerzenia, czytanie przez PhpSpreadsheet Xls reader (bez streamingu), limit 20 MB; **test obowiązkowo na `Zrodla/Importy przykładowe/products_export_20260209_201836.xls`** (niestandardowy CDF — jeśli PhpSpreadsheet go nie czyta, czytelny komunikat „zapisz jako .xlsx" zamiast 500); `.xlsm`/`.xlsb` nadal reject.
5. Parser liczb z parą separatorów (używany przez walidację + transformacje IMP2-3.4): „10,200" przy (`,` dec / `.` tys) = 10.2; „1.000" = 1000.
6. FE (krok Wykrywanie wg makiety Import-nowy.html): podgląd pierwszych ~20 wierszy z zaznaczonym header/data-start/skip-rows, selecty arkusza/separatorów/formatu dat z auto-wykrytymi wartościami i override.
7. OpenAPI regen.

## Poza zakresem tego ticketu
- Transformacje wartości — IMP2-3.4. Mapping UI — IMP2-3.3. Pełny wizard 6 kroków — IMP2-3.7 (tu można rozszerzyć istniejący StepUpload o nowe parametry, docelowy layout w 3.7).

## Kryteria akceptacji
- [ ] `CENNIK_KOSPEL_01-07-2023v2 (1).xlsx`: heurystyka wskazuje header_row=2 (tytuł „POMPY CIEPŁA" w w.1); preview pokazuje poprawne nagłówki (Kod EAN, Kod produktu…).
- [ ] `Avapax nowości 24.03.2026.xlsx`: header_row=1, data_start_row=3 (wiersz 2 = opisowy nagłówek pomijany); arkusz wybieralny (3D/Serie/Ceny/Pliki).
- [ ] `e-commerce.xlsx`: wiersz sekcji w środku danych oznaczony do pominięcia (auto lub ręcznie) — dry-run nie zgłasza go jako błędnego produktu.
- [ ] `Annex E…xlsx`: przy ustawieniu separatorów niemieckich wartość „10,200" parsuje się jako 10.2 (test jednostkowy parsera + sample w preview).
- [ ] `products_export_20260209_201836.xls`: parse-preview działa LUB zwraca czytelny komunikat konwersji (zależnie od wyniku testu PhpSpreadsheet na tym pliku — wynik udokumentowany w tickecie przy wykonaniu).
- [ ] `all-doctors-details.xlsx`: niska pewność heurystyki → UI prosi o ręczny wybór; brak 500.

## Jak zwalidować (smoke test po wykonaniu)
1. W kreatorze wgraj kolejno 5 plików z kryteriów akceptacji; dla każdego sprawdź zachowanie jak wyżej (screenshoty).
2. `bin/phpunit tests/Unit/Import` — testy heurystyki i parsera liczb zielone.

## Zależności
Blokowany przez: IMP2-2.1. Blokuje: IMP2-3.3, IMP2-3.4, IMP2-3.7.

## Referencje
Plan: §5 ETAP 3 (3.1), §6 benchmarki, D13/D14. Makieta: Import-nowy.html (krok Wykrywanie).

## Definition of Done
PHPStan max + PHPUnit ≥80% nowej logiki + ApiTestCase parametrów + Biome/tsc zielone; OpenAPI regen; smoke na 5 benchmarkach z artefaktami.

---

## IMP2-3.2 — feat(catalog): endpoint import-schema — schemat importu generowany z ObjectType (#1488)

**Labels:** backend, enhancement, epik-IMP2 · **Estymata:** 4–6 h · **Zależy od:** IMP2-1.4

## Po co to robimy (kontekst nietechniczny)
Kreator importu, walidacja i generator szablonu muszą wiedzieć to samo: jakie atrybuty ma wybrany typ obiektu, które są wymagane, jakie mają typy i dozwolone wartości. Zamiast trzech osobnych źródeł prawdy robimy jeden endpoint, który zwraca „schemat importu" wyliczony z modelu danych (wzorzec Flatfile Blueprint). Dzięki temu dropdowny mapowania, reguły walidacji i szablon XLSX zawsze mówią to samo.

## Stan obecny
- Mapping pobiera atrybuty przez generyczny `useList('attributes', pageSize 200)` BEZ filtra po ObjectType (`StepMapping.tsx` — komentarz twierdzi inaczej).
- Walidacja czyta atrybuty per-code w handlerze; słownik aliasów w `config/imports/mapping_dictionary.yaml` (`MappingDictionaryService`).
- Brak jednego endpointu agregującego: typy, required, opcje selectów, is_localizable/is_scopable, validation_rules, aliasy.

## Zakres prac
1. `GET /api/object-types/{id}/import-schema` (custom controller, `#[RequiresPermission('imports:run')]`): JSON z listą atrybutów OT (przez `object_type_attributes`): code, label (i18n), type (17 typów), required (z `Attribute::isRequired` — globalny), unique (identifier), is_localizable, is_scopable, validation_rules (passthrough JSONB), options (dla select/multiselect: code+label z `AttributeOption`), aliases (z mapping_dictionary + przyszłe per-attribute), system_targets (status, enabled, parent_sku, __category__) + aktywne locale i kanały tenanta (dla pickerów wymiarów).
2. Cache odpowiedzi (cache.app, TTL 5 min, inwalidacja niekonieczna w MVP — TTL wystarczy; klucz per tenant+OT).
3. Konsument nr 1 od razu: AutoMapper przyjmuje schemat zamiast osobnych lookupów (przygotowanie pod IMP2-3.3).
4. Regeneracja OpenAPI + `packages/shared-types`.

## Poza zakresem tego ticketu
- UI mapowania — IMP2-3.3. Generator szablonu — IMP2-3.8. Aliasy edytowalne per-tenant — poza MVP (plan §7).

## Kryteria akceptacji
- [ ] `curl -H "Authorization: Bearer …" https://pim.localhost/api/object-types/{product-id}/import-schema` zwraca 200 z atrybutami, opcjami selectów, locale i kanałami tenanta.
- [ ] Atrybut spoza OT nie występuje w odpowiedzi (test ApiTestCase na 2 OT).
- [ ] Cross-tenant OT id → 404.
- [ ] `docs/api-spec/v0.json` + shared-types zregenerowane.

## Jak zwalidować (smoke test po wykonaniu)
1. curl jak wyżej dla OT product i jednego custom OT; porównaj listę z Modelowaniem w UI.
2. `bin/phpunit tests/Api` (import-schema) zielone.

## Zależności
Blokowany przez: IMP2-1.4 (kontrakt walidacji). Blokuje: IMP2-3.3, IMP2-3.8.

## Referencje
Plan: §5 ETAP 3 (3.2), filar 5 (schema-first). Research: Flatfile Blueprint.

## Definition of Done
PHPStan max + ApiTestCase + OpenAPI/shared-types regen; smoke curl z dowodem.

---

## IMP2-3.3 — feat(import,admin): Mapping UI v2 — mapping po indeksie kolumny, multi-kolumny, wymiary, bulk-create atrybutów (#1489)

**Labels:** backend, frontend, enhancement, epik-IMP2 · **Estymata:** 12–17 h · **Zależy od:** IMP2-3.1, IMP2-3.2

## Po co to robimy (kontekst nietechniczny)
Realne pliki dostawców łamią założenie „każda kolumna ma unikalną nazwę": Bosch ma kolumny BEZ nagłówka i z powtórzonymi nagłówkami, Avapax ma osiem kolumn „foto" do jednej galerii. Dzisiejsze mapowanie (słownik nazwa→atrybut) gubi takie kolumny po cichu. Po tym tickecie każda kolumna jest identyfikowana pozycją (A, B, C…), kilka kolumn można zmapować na jeden atrybut-listę, kolumnom można przypisać język i kanał, a brakujące atrybuty można założyć hurtowo jednym oknem — zamiast skakać dwadzieścia razy do Modelowania.

## Stan obecny
- Format mappingu: płaski dict `header→attribute_code` (`ImportSession.columnMapping`, `ImportProfile.columnMapping`, FE `useImportWizard.mapping`) — duplikat nagłówka NADPISUJE klucz; puste nagłówki nieobsługiwane.
- `StepMapping.tsx`: Combobox z `useList('attributes', pageSize 200)` bez filtra po OT; jeden sample value; brak wymiarów locale/channel; CTA „+ Stwórz atrybut" = deep-link 1:1 do Modelowania z persist/restore.
- AutoMapper: exact/fuzzy po słowniku; sufiksy locale tylko dotted (`name.pl`); brak wzorców `(pl)`, `_En`, `[pol]`, `(DE)`.

## Zakres prac
1. **Format columnMapping v2 (D12, D9)**: lista wpisów `[{column_index, header_display, target, locale?, channel?, policy?: {clear_if_empty?, collection_mode?: replace|append}}]`; `target` ∈ {attribute_code, `__category__`, `__skip__`, `status`, `enabled`, `parent_sku`}; wersjonowanie (`mapping_version: 2`) + czytnik back-compat v1 (header→code) dla istniejących sesji/profili; BE: `ImportRunHandler`/`ImportValidationService`/`AutoMapper` operują na indeksach (reader z IMP2-2.1 zwraca wiersze pozycyjnie).
2. **Multi-kolumny → jeden atrybut**: wiele wpisów z tym samym target dla multiselect/asset-galerii = append wartości (kolejność = kolejność kolumn); walidacja: ten sam target na atrybucie skalarnym = błąd konfiguracji.
3. **Auto-map v2**: wzorce sufiksów locale w nagłówkach: `name (pl)`, `Name_En`, `name[pol]`, `Materialkurztext (DE)` → sugestia (atrybut, locale) z mapowaniem kodów języków (en/De/pol→pl?) wg rejestru locali tenanta; konsumuje import-schema (IMP2-3.2) zamiast osobnych lookupów; strip jednostek z nagłówków przy matchu („Cena netto [zł]" → cena netto).
4. **UI**: tabela kolumn wg indeksu (litera kolumny + header_display + 3 sample values), Combobox atrybutów ze schematu (pełna lista OT, wyszukiwanie, bez capa 200), pickery locale/channel per kolumna (tylko dla is_localizable/is_scopable), wybór ObjectType w kreatorze (koniec auto-locka na product), confidence badges z akcją „zatwierdź wszystkie pewne".
5. **Bulk „utwórz atrybuty z niezmapowanych kolumn"**: modal z multi-select niezmapowanych kolumn; per kolumna: proponowany kod (slug z nagłówka), typ zgadywany z sampli (number/boolean/date/select przy ≤10 unikalnych/text), edycja przed zatwierdzeniem; POST batch do istniejących endpointów atrybutów + auto-attach do OT + auto-mapowanie kolumn; deep-link do Modelowania zostaje jako alternatywa.
6. OpenAPI + shared-types regen (nowy format mapping w API).

## Poza zakresem tego ticketu
- Transformacje wartości — IMP2-3.4. Zapis/aplikowanie profili — IMP2-3.5. Layout 6-krokowy — IMP2-3.7 (ten ticket modernizuje istniejący krok mapowania; 3.7 przenosi go do nowego wizardu).

## Kryteria akceptacji
- [ ] `bosch-09-01-2026.csv` (duplikaty + puste nagłówki): wszystkie kolumny widoczne w tabeli mapowania, każdą można zmapować niezależnie; dry-run nie gubi żadnej.
- [ ] `Avapax …xlsx` arkusz „Pliki": 8 kolumn foto zmapowanych na jeden atrybut galerii (multi-value append) — po imporcie obiekt ma listę wartości w kolejności kolumn.
- [ ] `products_export_*.xls` (po IMP2-3.1): nagłówki „Opis (pl)"/„Opis (en)" dostają sugestię atrybut+locale.
- [ ] `Tubądzin…xlsx`: kolumny cecha:15–21 → bulk-create zakłada atrybuty i mapuje je w jednym przebiegu (≤2 min klikania).
- [ ] Profil/sesja z mappingiem v1 nadal działa (test back-compat).
- [ ] Wybór OT w kreatorze zmienia listę atrybutów (test: custom OT bez atrybutu name działa — required wg schematu, nie hardcode).

## Jak zwalidować (smoke test po wykonaniu)
1. Kreator + `bosch-09-01-2026.csv`: zmapuj ręcznie kolumny bez nagłówka; dry-run; sprawdź że liczba kolumn = liczba w pliku.
2. Avapax arkusz Pliki: zmapuj foto×8 → galeria; import 2 wierszy; karta produktu pokazuje zdjęcia w kolejności (wymaga IMP2-1.12 dla realnych plików — bez niego wartości asset bez pobrania, OK do walidacji mapowania).
3. Tubądzin: bulk-create cech; sprawdź w Modelowaniu że atrybuty powstały z sensownymi typami.

## Zależności
Blokowany przez: IMP2-3.1, IMP2-3.2. Blokuje: IMP2-3.4, IMP2-3.5, IMP2-3.7.

## Referencje
Plan: §5 ETAP 3 (3.3), D9/D12, §6 benchmarki. Kod: StepMapping.tsx, AutoMapper, ImportSession.columnMapping.

## Definition of Done
PHPStan max + PHPUnit (format v2 + back-compat) + ApiTestCase + Biome/tsc + Playwright kroku mapowania zielone; OpenAPI/shared-types regen; smoke na 3 benchmarkach z artefaktami.

---

## IMP2-3.4 — feat(import): TransformPipeline — deklaratywne transformacje wartości per kolumna (#1490)

**Labels:** backend, frontend, enhancement, epik-IMP2 · **Estymata:** 10–14 h · **Zależy od:** IMP2-3.3

## Po co to robimy (kontekst nietechniczny)
Plik dostawcy prawie nigdy nie ma wartości w formacie, którego chce PIM: ceny z przecinkami, „---" zamiast pustego pola, opisy w JSON-ie, kategorie rozbite na dwie kolumny, nazwy plików zdjęć bez adresu serwera. Zamiast kazać użytkownikowi poprawiać plik w Excelu, kreator dostaje proste „przepisy" na kolumnę: przytnij, podziel, zamień, sklej, wyciągnij. Przepisy zapisują się w profilu, więc kolejny plik od tego samego dostawcy przechodzi bez pracy.

## Stan obecny
- Brak transformacji: jedyna „transformacja" to CompositeValueParser (price/metric) i pipe-split multiselect na sztywno w `ImportObjectCreator`/writerze.
- Parametry separatorów liczb/dat — z IMP2-3.1 (sesja/profil).
- Mapping v2 (IMP2-3.3) ma już miejsce na konfigurację per kolumna.

## Zakres prac
1. Model: `transforms: [{op, params}]` per wpis mappingu v2 (JSONB w sesji/profilu); wykonywane w kolejności PRZED walidacją (kolejność ma znaczenie — najpierw split, potem value-map; lekcja Akeneo).
2. Operatory MVP (enum + walidacja parametrów): `trim` (default ON globalnie), `split` (separator → wartości multi), `find_replace` (max 10 par, plain/regex), `value_map` (słownik wartość źródłowa → option_code; case-insensitive opt), `number_format`/`date_format` (z parametrami z IMP2-3.1), `concat` (źródła: inne kolumny po indeksie + literały; use-case ścieżka kategorii Bosch poz2+poz3 → `__category__`), `null_markers` (lista: `---`, `N/D`, `brak`, `n/a` → null; default globalna lista edytowalna), `json_extract` (klucz, np. `pl` z `{"pl":…,"en":…}` — benchmark products_export), `constant` (stała wartość) i `sheet_name` (nazwa arkusza jako wartość — benchmark Annex E: arkusz=rodzina → kategoria), `prefix`/`suffix` (use-case Tubądzin: nazwa pliku → URL CDN).
3. Wykonanie w BE (`TransformPipeline` service) na ścieżce dry-run i run; FE: edytor przepisów per kolumna (lista kroków + parametry) z **podglądem na żywo na samplach** (przed → po) — endpoint preview transformacji na próbce (reuse parse-preview sampli).
4. Jawna lista operatorów POZA zakresem (w body PR/docs): change-case, clean-html, regex-extract, AI-mapping — Faza 2.
5. OpenAPI + shared-types regen.

## Poza zakresem tego ticketu
- Zapamiętywanie value_map między profilami (mapping memory robi IMP2-3.5 — value_map jest częścią profilu).
- Computed attributes przy eksporcie — inny moduł.

## Kryteria akceptacji
- [ ] `products_export_*.xls`: kolumna „Informacje bezpieczeństwa" (JSON-in-cell) z `json_extract(pl)` daje czysty tekst PL w dry-run preview; `---` znika przez null_markers.
- [ ] `bosch-09-01-2026.xlsx`: `concat(Kategoria_poz2, ' / ', Kategoria_poz3)` → `__category__` przypisuje kategorię (lub czytelny błąd CategoryNotFound).
- [ ] `Annex E`: operator `sheet_name` → stała wartość kolumny kategorii per arkusz.
- [ ] `Tubądzin`: `prefix('https://cdn…/')` + `suffix('.jpg')` na kolumnie FOTO daje poprawny URL (walidacja z IMP2-1.12).
- [ ] Kolejność operatorów respektowana (test: split przed value_map).
- [ ] Preview przed→po widoczny w UI dla każdego kroku przepisu.

## Jak zwalidować (smoke test po wykonaniu)
1. Kreator + products_export: skonfiguruj json_extract + null_markers; dry-run pokazuje przetworzone wartości; zaimportuj 3 wiersze; karta produktu ma czysty opis PL.
2. Annex E: dwa arkusze z różnymi rodzinami → obiekt z arkusza X ma kategorię X.

## Zależności
Blokowany przez: IMP2-3.3. Blokuje: IMP2-3.5, IMP2-3.6, IMP2-3.7.

## Referencje
Plan: §5 ETAP 3 (3.4), §6 benchmarki. Research: Akeneo Tailored operations, Pimcore pipeline, OneSchema autofix.

## Definition of Done
PHPStan max + PHPUnit operatorów (każdy operator ≥1 test) + ApiTestCase + Biome/tsc zielone; OpenAPI regen; smoke na 3 benchmarkach z artefaktami.

---

## IMP2-3.5 — feat(import,admin): profile v2 + mapping memory — zapis i aplikowanie pełnej konfiguracji przebiegu (#1491)

**Labels:** backend, frontend, enhancement, epik-IMP2 · **Estymata:** 6–8 h · **Zależy od:** IMP2-3.3, IMP2-3.4

## Po co to robimy (kontekst nietechniczny)
Dostawcy przysyłają cyklicznie pliki o tej samej strukturze. Użytkownik powinien raz skonfigurować import (mapowanie, transformacje, tryb), zapisać to jako profil — i przy następnym pliku dostać wszystko wypełnione automatycznie. Dziś pola „zapisz jako profil" i „użyj profilu" w kreatorze są atrapami: nic nie zapisują i nic nie wczytują. Po tym tickecie profile naprawdę działają, a kreator sam rozpoznaje znany format pliku po nagłówkach.

## Stan obecny
- `useImportWizard.ts`: `profileId` i `saveAsProfileName` to czysty stan lokalny — `StepConfirm.tsx` ich NIE wysyła; wybrany profil NIE prefilluje mappingu (zawsze świeży auto-map); `StartImportController` akceptuje `profile_id` (touchLastUsed), ale FE go nie wysyła.
- `ImportProfile` ma: columnMapping (v1), locale, encoding, delimiter, mode, imageSource… — brak: parametrów detekcji z IMP2-3.1, transformacji z IMP2-3.4, formatu v2.
- Decyzja D9: profile **per-user** (operator odrzucił tenant-shared).

## Zakres prac
1. Rozszerzenie `ImportProfile` o pełną konfigurację przebiegu: `mapping_version: 2` + wpisy v2 (z transforms i politykami), parametry detekcji (sheet, header_row, data_start_row, separatory, date_format, quote), tryb (D3), media source; migracja: istniejące profile dostają `mapping_version: 1` i działają przez czytnik back-compat (IMP2-3.3).
2. Zapis profilu z kreatora: krok podsumowania ma działające „Zapisz jako profil" (POST /api/import-profiles z całą konfiguracją) i „Aktualizuj profil" gdy przebieg startował z profilu (PATCH) — wzorzec exports `use-run-export.ts` saveProfile/updateProfile.
3. Aplikowanie profilu: wybór profilu w kroku 1 prefilluje WSZYSTKIE kroki (detekcja, mapping, transformacje, tryb); `profile_id` wysyłany w POST /api/import-sessions (touchLastUsed już jest).
4. Mapping memory: `header_signature` = sha256 posortowanych nagłówków (po normalizacji) zapisywany na profilu; parse-preview zwraca signature; kreator przy zgodności podpowiada „Wykryto format profilu X — zastosować?" (per-user, D9).
5. OpenAPI + shared-types regen.

## Poza zakresem tego ticketu
- Współdzielenie profili w tenancie — odrzucone (D9). Eksport/import profilu JSON — działa (envelope v1.0); rozszerzyć envelope o nowe pola w tym tickecie (bump schemaVersion na 2.0 z czytnikiem 1.x).

## Kryteria akceptacji
- [ ] Pełny przebieg: konfiguracja + „Zapisz jako profil" → profil widoczny w tabie Profile z kompletem ustawień (GET pokazuje mapping v2 + transforms + detekcję).
- [ ] Nowa sesja z wyborem tego profilu: wszystkie kroki prefilled (mapping, transformacje, tryb, separatory) — zero ręcznego klikania do dry-run.
- [ ] Drugi upload pliku o tych samych nagłówkach BEZ wyboru profilu → kreator proponuje profil (banner), akceptacja = prefill.
- [ ] `POST /api/import-sessions` zawiera profile_id; `last_used_at` profilu się aktualizuje.
- [ ] Profil użytkownika A niewidoczny dla B (istniejący voter — test regresji).
- [ ] Stary profil (v1) nadal aplikowalny (back-compat test).

## Jak zwalidować (smoke test po wykonaniu)
1. Skonfiguruj import `bosch-09-01-2026.csv` (mapping + transformy), zapisz profil „Bosch IdoSell".
2. Wgraj `bosch-09-01-2026-nazwy.csv`? — NIE (inne nagłówki); wgraj ponownie pierwszy plik: banner „Wykryto format…", zastosuj, dry-run bez ręcznej konfiguracji.
3. Sprawdź touchLastUsed: kolumna „ostatnio użyty" w tabie Profile.

## Zależności
Blokowany przez: IMP2-3.3, IMP2-3.4. Blokuje: IMP2-3.7, IMP2-4.1 (harmonogram używa profilu).

## Referencje
Plan: §5 ETAP 3 (3.5), D9, filar 11. Research: OneSchema mapping memory. Kod: useImportWizard, StepConfirm, ImportProfile, use-run-export.ts (wzorzec).

## Definition of Done
PHPStan max + PHPUnit + ApiTestCase (zapis/aplikowanie/signature/back-compat) + Biome/tsc + Playwright zapisu profilu zielone; OpenAPI regen; smoke z artefaktem.

---

## IMP2-3.6 — feat(import): dry-run v2 — dwupoziomowy, kubełki utworzy/zaktualizuje/wyczyści, plik odrzutów (#1492)

**Labels:** backend, frontend, enhancement, epik-IMP2 · **Estymata:** 8–10 h · **Zależy od:** IMP2-1.3, IMP2-3.4, IMP2-2.2

## Po co to robimy (kontekst nietechniczny)
Przed naciśnięciem „Importuj" użytkownik musi wiedzieć, co się stanie: ile produktów POWSTANIE, ile zostanie ZAKTUALIZOWANYCH, ile pominiętych, a ile ma błędy — i, co najważniejsze, czy import przypadkiem nie WYCZYŚCI istniejących danych pustymi komórkami. Dziś podgląd zna tylko „OK/błąd" i potrafi zamulić serwer na dużym pliku (liczy wszystko w jednym żądaniu). Po tym tickecie podgląd jest natychmiastowy na próbce, pełen wynik liczy się w tle, a błędne wiersze można pobrać jako plik, poprawić i wgrać ponownie.

## Stan obecny
- `ValidateDryRunController` — synchroniczny, cały plik w jednym requeście HTTP (po IMP2-2.1 streamowo, ale nadal in-band), wynik: total/success/error + top_errors (cap 100); ZERO kubełków create/update; brak pliku odrzutów.
- Po IMP2-1.3 silnik zna decyzję create/update/skip per wiersz; po IMP2-3.4 wartości przechodzą transformacje; po IMP2-2.2 plik jest staged.
- Makieta Import-nowy.html: krok Podgląd z kubełkami „Gotowe do dodania / Aktualizacje / Błędy".

## Zakres prac
1. Poziom 1 (sync, w kreatorze): dry-run na próbce pierwszych ~1000 wierszy (parametr) ze staged file — odpowiedź <2 s: kubełki szacunkowe (created/updated/skipped/errors/cleared) + lista błędów próbki; wyraźna etykieta „wynik z próbki N wierszy".
2. Poziom 2 (async, opcjonalny przyciskiem „Sprawdź cały plik"): `ImportRunMessage` z flagą `dryRun: true` na tym samym handlerze/transporcie — pełny przebieg walidacji + decyzji BEZ zapisu (writer w trybie collect-only); wynik na sesji dry-run (status, kubełki, logi) + Mercure progress; wynik prezentowany w kreatorze.
3. Kubełek „WYCZYŚCI": dla kolumn z `clear_if_empty` policz wartości istniejące, które zostaną skasowane; próg ostrzegawczy: >20% niepustych → wymagane explicit confirm (czerwony banner) — guard D2/R-47.
4. Diff dla update (sample): dla ~20 pierwszych aktualizacji pokaż per wiersz: atrybut, stara → nowa wartość.
5. Plik odrzutów: `GET /api/import-sessions/{id}/rejected.csv` — surowe wiersze z błędami + kolumna `_errors` (przyczyny); format wgrywalny z powrotem (pętla self-healing; kolumna _errors auto-skip przy imporcie).
6. Wyróżnienie nieoczekiwanych liczb: duży kubełek „utworzy" przy trybie update/upsert = żółty banner („Spodziewałeś się aktualizacji? Sprawdź klucz dopasowania") — anty-footgun literówki w SKU.
7. OpenAPI regen; FE: ekran podglądu wg makiety (kubełki + tabela błędów + akcje).

## Poza zakresem tego ticketu
- Layout 6-krokowy — IMP2-3.7. Edycja inline błędów w gridzie — poza MVP (plan §7; plik odrzutów to MVP-ścieżka naprawy).

## Kryteria akceptacji
- [ ] Próbka 1000 wierszy z `GA_List.csv` odpowiada <2 s; pełny dry-run 90k idzie async z progressem.
- [ ] Round-trip-edycja: eksport 50 produktów → zmiana 10 + 5 nowych wierszy → dry-run pokazuje 5/10/0/0 (created/updated/skipped/errors).
- [ ] Kolumna z clear_if_empty czyszcząca >20% wartości wymaga dodatkowego potwierdzenia (test UI + API flaga confirm).
- [ ] rejected.csv: wgranie go po poprawkach importuje wcześniej odrzucone wiersze (smoke pętli self-healing).
- [ ] Kubełki sync-próbki i async-pełnego zgodne na pliku ≤1000 wierszy (test spójności).

## Jak zwalidować (smoke test po wykonaniu)
1. Eksport → edycja w Excelu → kreator: kubełki zgodne z edycją (screenshot).
2. „Sprawdź cały plik" na GA_List.csv → progress, wynik async.
3. Pobierz rejected.csv z importu z błędami, popraw 1 wiersz, wgraj — wiersz przechodzi.

## Zależności
Blokowany przez: IMP2-1.3, IMP2-3.4, IMP2-2.2. Blokuje: IMP2-3.7.

## Referencje
Plan: §5 ETAP 3 (3.6), D2/D8, filar 5. Makieta: Import-nowy.html (Podgląd). Research: Matrixify dry-run, Akeneo invalid items, Magento Check Data.

## Definition of Done
PHPStan max + PHPUnit + ApiTestCase (oba poziomy, rejected.csv) + Biome/tsc zielone; OpenAPI regen; smoke z artefaktami (screenshoty kubełków, plik odrzutów).

---

## IMP2-3.7 — feat(admin/imports): wizard 6 kroków + widok sesji v2 + hub delta (absorbuje NUI-10/NUI-11) (#1493)

**Labels:** frontend, backend, enhancement, UI, epik-IMP2 · **Estymata:** 30–44 h · **Zależy od:** IMP2-3.1, IMP2-3.2, IMP2-3.3, IMP2-3.4, IMP2-3.5, IMP2-3.6, IMP2-2.3

## Po co to robimy (kontekst nietechniczny)
To jest „twarz" całej przebudowy: nowy kreator importu według zaprojektowanych makiet (6 kroków: Źródło → Wykrywanie → Mapowanie → Reguły → Podgląd → Start) i nowy widok trwającej sesji (fazy, log na żywo, raporty) — tym razem podpięte do silnika, który NAPRAWDĘ umie aktualizować produkty, pauzować i wycofywać. Dotychczasowe makiety pokazywały rzeczy, których backend nie umiał; po etapach 1–2 umie — więc UI może wreszcie mówić prawdę.

## Stan obecny
- Wizard 4-krokowy w legacy layoucie: `apps/admin/src/features/imports/wizard/*` pod `/integrations/imports/new` (App.tsx trzyma importy w IntegrationsLayout — komentarz przy EXR-08).
- Makiety: `Zrodla/Front_Claude_Design/NOWY UI/PIM-nowoczesny/Import-nowy.html` (wizard) i `Import-sesja.html` (sesja: pipeline 6 faz, live log, taby).
- Otwarte tickety NUI-10 (#1429 — wizard v2 „na istniejącym backendzie") i NUI-11 (#1430 — sesja v2): **ten ticket je POKRYWA I ZASTĘPUJE** (decyzja operatora §9.2 planu) — przy zleceniu wykonania zamknąć #1429/#1430 jako superseded z komentarzem-linkiem. Hub NUI-09 (#1428) jest w toku osobno — tu tylko delta.
- Znane bugi FE do domknięcia tutaj (o ile nie zamknięte wcześniej): stale suggestions cache + setState-in-render (StepMapping/StepUpload), brak refetchInterval listy sesji (LiveSessionCard zamrożona), hardcoded PL bez t() (RollbackButton dialog, SummaryRow w StepConfirm, BackupTriggerCheckbox, FileDropzone), martwy kod (ImportsListView, ImportProfilesPlaceholder, ImportProfileManager Sheet), brak hinta o re-uploadzie po powrocie z Modelowania, FE ignoruje had_multiple_sheets.
- Playwright: 3 testy importów w test.fixme „Pending #799" (regresja step-gatingu).

## Zakres prac
1. **Wizard 6 kroków** wg Import-nowy.html, na komponentach ui-v2 (wzorzec exports EXR): Źródło (upload/staged + wybór profilu z auto-detekcją signature z IMP2-3.5 + przyszłe źródła jako sloty), Wykrywanie (parametry z IMP2-3.1 + preview 20 wierszy), Mapowanie (IMP2-3.3), Reguły (tryb D3 + klucz dopasowania D1 + polityki komórek D2 + transformacje IMP2-3.4), Podgląd (dry-run v2 IMP2-3.6), Start (podsumowanie + backup checkbox + uruchom); stan na Context+useReducer (wzorzec exports wizard-store — deterministyczny reset), persist/restore deep-linku do Modelowania zachowany + hint o re-uploadzie.
2. **Widok sesji v2** wg Import-sesja.html: pipeline faz z `phase_timestamps` (IMP2-4.4 dostarcza kolumnę — tu fallback na statusy gdy brak), live log (Mercure, bufor ~200 wpisów + link do pełnego raportu), taby Podsumowanie / Błędy & warnings / Per-row report (paginowane z import_logs), akcje: pauza/wznów/anuluj (IMP2-2.3), rollback z preview (IMP2-2.4), pobierz raport/odrzuty.
3. **Hub delta**: jeśli NUI-09 zmergowany — dopasowanie liczników/statusów do nowego silnika (tryby w HistoryRow zamiast hardcoded UPDATE, kubełki sesji); jeśli nie — wyjście importów z IntegrationsLayout do shellu v2 w tym tickecie.
4. **Sprzątanie**: wszystkie bugi FE z listy „Stan obecny"; i18n pl+en kompletne dla nowych ekranów.
5. **Playwright E2E na realnym backendzie** (bez route.fulfill — lekcja UI-02): happy path 6 kroków z małym CSV, dry-run z kubełkami, pauza/resume, rollback preview; naprawa/odblokowanie 3 fixme #799 lub ich zastąpienie nowymi specami.
6. **Karta prawdy** (z IMP2-1.1) aktualizowana na koniec: każdy element UI ma działający odpowiednik BE.

## Poza zakresem tego ticketu
- Nowe funkcje silnika (wszystko w etapach 1–2). Harmonogramy/źródła realne — IMP2-4.1/4.2 (kroki UI mają sloty, nie obietnice). Inbox/dzwoneczek — IMP2-4.4.

## Kryteria akceptacji
- [ ] Pełny przebieg 6 kroków na `bosch-09-01-2026.csv` (klasa A) kończy się importem; wszystkie kroki zgodne z makietą (review wizualny operatora).
- [ ] Kubełek „Aktualizacje" w Podglądzie pokazuje realne liczby (round-trip-edycja smoke).
- [ ] Widok sesji: fazy + live log + per-row report działają na imporcie ≥5k wierszy; pauza/wznów/rollback dostępne i działające.
- [ ] Tryb w historii sesji = realny tryb sesji (koniec hardcoded „UPDATE").
- [ ] `grep -rn "defaultValue.*[ąęłóśżź]" apps/admin/src/features/imports` → stringi przez t(); pl.json+en.json kompletne.
- [ ] Playwright: nowe specy zielone NA REALNYM backendzie; 0 testów fixme dla importów.
- [ ] Martwe komponenty usunięte (ImportsListView itd.) — bundle bez nieosiągalnego kodu importów.
- [ ] #1429 i #1430 zamknięte jako superseded (komentarz z linkiem do tego ticketu) — dopiero przy wykonaniu.

## Jak zwalidować (smoke test po wykonaniu)
1. Pełen przebieg kreatora na 2 plikach klasy A (bosch CSV + Avapax XLSX z arkuszem i data-start-row) — artefakty: screenshoty każdego kroku.
2. Import 5k wierszy: obserwuj fazy i live log; pauza → wznów; po zakończeniu rollback z preview.
3. Konsola DevTools czysta na wszystkich ekranach.

## Zależności
Blokowany przez: IMP2-3.1–3.6, IMP2-2.3. Powiązane: IMP2-2.4 (preview rollbacku), IMP2-4.4 (phase_timestamps — fallback obsłużony).

## Referencje
Plan: §5 ETAP 3 (3.7), §9.2. Makiety: Import-nowy.html, Import-sesja.html. Tickety zastępowane: #1429 (NUI-10), #1430 (NUI-11). Lekcje: UI-02 (smoke na realnym BE), #799.

## Definition of Done
PHPStan/Biome/tsc + pełny Playwright suite na realnym BE zielone; i18n complete; smoke wg walidacji z kompletem screenshotów; CLOSED MEANS CLOSED dla tego ticketu ORAZ zamykanych #1429/#1430.

---

## IMP2-3.8 — feat(import): generator szablonu importu XLSX z ObjectType (#1494)

**Labels:** backend, frontend, enhancement, epik-IMP2 · **Estymata:** 3–5 h · **Zależy od:** IMP2-3.2

## Po co to robimy (kontekst nietechniczny)
Najtańszy błąd importu to ten, który nie powstanie. Zamiast tłumaczyć dostawcy/koleżance „jakie kolumny ma mieć plik", użytkownik klika „Pobierz szablon" i dostaje gotowy XLSX: poprawne nagłówki dla wybranego typu obiektu, zaznaczone pola wymagane, przykładowy wiersz i listy rozwijane dla pól słownikowych (Excel sam pilnuje dozwolonych wartości). Wypełniony szablon wchodzi do kreatora bez mapowania.

## Stan obecny
- Brak czegokolwiek podobnego. Import-schema (IMP2-3.2) dostarcza komplet danych. PhpSpreadsheet w composer.json (potrzebny do zapisu z data validation — openspout nie wspiera walidacji komórek).

## Zakres prac
1. `GET /api/object-types/{id}/import-template?locale=pl` (`#[RequiresPermission('imports:run')]`): XLSX z (a) wierszem nagłówków = kody atrybutów (konwencja gramatyki: `code`, `code.locale` dla localizable wg aktywnych locale), (b) drugim wierszem opisowym (label + help z i18n JSONB; oznaczenie wymaganych „*"; wiersz ignorowany przy imporcie dzięki data_start_row=3 w metadanych szablonu), (c) wierszem przykładowym, (d) Excel data validation: listy opcji dla select/multiselect, format liczbowy dla number/price, (e) zamrożony wiersz nagłówka.
2. Wygenerowany szablon przechodzi przez kreator BEZ ręcznego mapowania (nagłówki = kody → auto-map 100%; data_start_row=3 wykrywany).
3. Przycisk „Pobierz szablon" w kroku Źródło kreatora (per wybrany OT) — fetch+blob z Bearer (wzorzec IMP2-0.1).
4. OpenAPI regen.

## Poza zakresem tego ticketu
- Szablony CSV (XLSX wystarcza; CSV nie ma walidacji komórek). Self-validating makra — nie.

## Kryteria akceptacji
- [ ] Szablon dla product otwiera się w Excelu: nagłówki, opisy, przykład, dropdowny dla selectów działają.
- [ ] Wypełniony szablon (2 wiersze) → kreator: auto-map 100% kolumn, dry-run 2 OK, import przechodzi.
- [ ] Custom OT: szablon zawiera tylko atrybuty tego OT.
- [ ] Endpoint ma test ApiTestCase (200, content-type, cross-tenant 404).

## Jak zwalidować (smoke test po wykonaniu)
1. Pobierz szablon z kreatora, wypełnij 2 wiersze (w tym select z dropdownu), wgraj — import end-to-end bez dotykania mapowania.

## Zależności
Blokowany przez: IMP2-3.2. Blokuje: —.

## Referencje
Plan: §5 ETAP 3 (3.8). Research: OneSchema self-validating template.

## Definition of Done
PHPStan max + ApiTestCase + Biome/tsc zielone; OpenAPI regen; smoke z artefaktem (szablon + screenshot auto-map 100%).

---

## IMP2-4.1 — feat(import): harmonogramy realne (cron daemon) (#1495)

**Labels:** backend, infra, enhancement, epik-IMP2 · **Estymata:** 6–8 h · **Zależy od:** IMP2-1.6a, IMP2-4.2

## Po co to robimy (kontekst nietechniczny)

W module importu można dziś skonfigurować harmonogram („pobieraj cennik co noc o 3:00") i kliknąć „Uruchom teraz" — ale to teatr: system odnotowuje „uruchomienie" w tabelce i **nic więcej się nie dzieje, nigdy**. Wpis wisi wiecznie jako „oczekujący", żaden import nie startuje. Po tym tickecie harmonogramy zaczną naprawdę odpalać importy: o zaplanowanej porze (także nadrabiając zaległości po restarcie serwera), a „Uruchom teraz" wykona prawdziwy import z podpiętego źródła i profilu, z widocznym wynikiem i czasem trwania.

## Stan obecny

- `apps/api/src/Import/Application/Service/ScheduleDispatcherService.php` — `runNow()` tworzy **tylko** `ImportScheduleRun` ze statusem `Pending`, woła `recordRun()` + `computeNextRun()` i kończy. Docblock wprost: „the actual import-session creation [is left] to the integration the follow-up ticket wires up". Ten follow-up to ten ticket.
- Cron tick **nie istnieje**: `symfony/scheduler` nie ma w `apps/api/composer.json`; żaden proces nie sprawdza `nextRun`.
- `apps/api/src/Import/Domain/Entity/ImportScheduleRun.php` — pole `sessionId` (linia 31) **nigdy nie wypełniane**; `durationMs` (linia 27) martwe; statusy `Pending/Success/Warning/Error` w `apps/api/src/Import/Domain/Enum/ScheduleRunStatus.php` — nic nie przechodzi z Pending dalej.
- `apps/api/src/Import/Domain/Entity/ImportSchedule.php` — relacje `source`/`profile` (linie 33–35) i flaga `enabled` (linia 54) istnieją; pola `notifyChannels`/`notifyConfig` (linie 65–68) **martwe**.
- Endpoint `POST /api/import-schedules/{id}/run-now` istnieje (`apps/api/src/Import/Presentation/Controller/ScheduleStateController.php`, linia ~62).
- `apps/api/src/Import/Application/Service/CronExpressionParser.php` — walidacja + `nextRun()` działają.
- Po IMP2-1.6a istnieje transport Messenger `import` + worker w dev i prod compose; po IMP2-4.2 istnieją drivery źródeł (SFTP/HTTP/folder) zdolne pobrać plik.

## Zakres prac

1. **`composer require symfony/scheduler`** + provider `ImportScheduleTick` (`apps/api/src/Import/Application/Scheduler/`): `RecurringMessage` co 60 s dispatchuje `ImportScheduleTickMessage`; konsumpcja przez workera z IMP2-1.6a (dopisać transport `scheduler_*` do komendy `messenger:consume` w `docker-compose.yml` i `docker-compose.prod.yml`).
2. **`ImportScheduleTickHandler`**: pobiera harmonogramy `enabled=true AND nextRun <= NOW()` (per tenant, z ustawieniem TenantContext — wzorzec istniejących handlerów async). **Catch-up po downtime (wzorzec Pimcore execute-cron)**: zaległy harmonogram odpala się dokładnie RAZ niezależnie od liczby pominiętych okien, po czym `computeNextRun(from: now)`. Idempotencja: guard na „run already in-flight" (nie odpalaj drugiego, gdy poprzedni `Pending/Running`).
3. **`ScheduleRunner`** — wspólny serwis dla ticku i `runNow()`: (a) pobiera plik ze `source` przez driver z IMP2-4.2 (source `folder/sftp/http`); (b) tworzy **realną `ImportSession`** z `profile` harmonogramu (mapping, ObjectType, mode); (c) dispatchuje `ImportRunMessage` na transport `import`; (d) wypełnia `ImportScheduleRun.sessionId`. Harmonogram bez `source` lub bez `profile` → run kończy jako `Error` z czytelnym komunikatem (i 422 dla run-now) — zero udawania.
4. **Domknięcie runa**: po terminalnym statusie sesji (listener na zakończenie `ImportRunHandler` / event sesji) `ScheduleRun` przechodzi `Pending → Success/Warning/Error` (Warning dla sesji `partial`), `durationMs` = startedAt→completedAt; `ImportSchedule.recordRun()` aktualizowany realnym wynikiem.
5. **Hook notyfikacji wyniku**: emit `ImportScheduleRunCompleted` (event/message) niosący run + sessionId + status — **konsument dostarcza IMP2-4.4**; tu wystarczy emisja + log. `notifyChannels`/`notifyConfig` pozostają polami konfiguracyjnymi czytanymi w 4.4.
6. **FE delta (minimalna)**: lista runów harmonogramu pokazuje link do utworzonej sesji (sessionId już w response) — bez przebudowy widoków (pełne UI to etap 3).
7. **Testy**: unit `ScheduleRunner` (happy path, brak source, brak profile, guard in-flight), unit catch-up (nextRun w przeszłości ×3 okna → 1 run), ApiTestCase `run-now` → 200 + run z `sessionId` ≠ null i sesja istnieje.

## Poza zakresem tego ticketu

- Drivery źródeł i polling wg `pollIntervalSec` — **IMP2-4.2** (ten ticket konsumuje jego API).
- Wysyłka e-maili / dzwoneczek / metryki — **IMP2-4.4** (tu tylko emit hooka).
- Przebudowa widoków harmonogramów (NUI) — etap 3 (**IMP2-3.7**).
- Wieloarkuszowe sesje (D14) — Faza 2.

## Kryteria akceptacji

- [ ] `POST /api/import-schedules/{id}/run-now` dla harmonogramu z source+profile zwraca 200, a `ImportScheduleRun.sessionId` wskazuje istniejącą `ImportSession`, która realnie przetwarza plik.
- [ ] Run-now dla harmonogramu bez source/profile zwraca 422 (RFC 7807) z komunikatem, run NIE wisi jako wieczny `pending`.
- [ ] Harmonogram z `nextRun` w przeszłości odpala się w ≤60 s od startu workera (catch-up po downtime) i odpala się dokładnie raz.
- [ ] Po zakończeniu importu run ma status `success`/`warning`/`error` zgodny ze statusem sesji oraz wypełnione `durationMs`.
- [ ] Drugi tick w trakcie działającego importu NIE tworzy drugiego runa (guard in-flight — test).
- [ ] Event `ImportScheduleRunCompleted` emitowany (asercja w teście) — punkt zaczepienia dla IMP2-4.4.
- [ ] Worker w dev compose konsumuje transport schedulera (`docker compose logs worker` pokazuje ticki).

## Jak zwalidować (smoke test po wykonaniu)

1. `docker compose up -d && docker compose logs -f worker` — w logu co minutę tick schedulera.
2. Na `https://pim.localhost` (admin@demo.localhost / changeme): utwórz źródło typu `folder` wskazujące na whitelistowany katalog (patrz IMP2-4.2) z wrzuconym małym CSV; utwórz profil mapowania; utwórz harmonogram z tym źródłem+profilem i cronem `*/2 * * * *`.
3. Kliknij **Uruchom teraz** → DevTools Network: 200; w liście runów pojawia się wpis z linkiem do sesji; sesja w Importach przechodzi `pending → running → success`, produkty z CSV są w katalogu.
4. Poczekaj ≤2 min → cron tick tworzy kolejny run automatycznie (bez klikania).
5. Test downtime: `docker compose stop worker`, odczekaj aż minie okno crona, `docker compose start worker` → zaległy run odpala się raz w ciągu minuty.
6. `curl -H "Authorization: Bearer $TOKEN" https://pim.localhost/api/import-schedules/{id} | jq '.lastRunStatus, .nextRun'` — wartości realne, niepuste.

## Zależności

- Blokowany przez: **IMP2-1.6a** (transport `import` + worker), **IMP2-4.2** (driver źródła do pobrania pliku).
- Blokuje: **IMP2-4.4** (konsumuje hook `ImportScheduleRunCompleted` + `notifyChannels`).

## Referencje

- `Project Plan/UI/feature-imports-v2.md` §5 etap 4 (ticket 4.1), §2.7 (run-now = wieczny pending), §4.4 D8.
- Kod: `apps/api/src/Import/Application/Service/ScheduleDispatcherService.php`, `apps/api/src/Import/Presentation/Controller/ScheduleStateController.php`, `apps/api/src/Import/Domain/Entity/ImportSchedule.php`, `apps/api/src/Import/Domain/Entity/ImportScheduleRun.php`, `apps/api/src/Import/Application/Service/CronExpressionParser.php`, `apps/api/config/packages/messenger.yaml`, `docker-compose.yml`, `docker-compose.prod.yml`.
- Issues: #502 (VIEW-IMP-04, gdzie powstał stub), #598–605.

## Definition of Done

- [ ] PHPStan max zielony (`cache:warmup --env=dev` + `--memory-limit=1G`).
- [ ] PHPUnit ≥80% nowej logiki; ApiTestCase dla run-now (happy + 422).
- [ ] Regeneracja `docs/api-spec/v0.json` jeśli response run-now/runs się zmienia (`cache:warmup` + `api:openapi:export`).
- [ ] php-cs-fixer przed commitem; nowy pakiet `symfony/scheduler` w najnowszej stabilnej wersji, lockfile ścisły.
- [ ] Manual smoke wg sekcji „Jak zwalidować" — artefakt dowodu (JSON runa z sessionId + screenshot sesji `success` + log ticku workera) w komentarzu zamykającym. CLOSED MEANS CLOSED.

---

## IMP2-4.2 — feat(import): źródła realne lub uczciwe (driver SFTP/HTTP, polling, health „off” zamiast fałszywego „ok”) (#1496)

**Labels:** backend, frontend, security, enhancement, epik-IMP2 · **Estymata:** 8–12 h · **Zależy od:** IMP2-1.6a

## Po co to robimy (kontekst nietechniczny)

Ekran „Źródła importu" pozwala dodać serwer SFTP czy adres HTTP i pokazuje zieloną kropkę „połączenie OK" — ale ta kropka **kłamie**: dla wszystkiego poza lokalnym folderem test połączenia zawsze zwraca „ok", niczego nie sprawdzając. Żadne źródło też samo z siebie nie pobiera plików. Po tym tickecie: SFTP i HTTP będą naprawdę testowane i odpytywane cyklicznie (nowy plik u dostawcy = automatyczny import), typy bez prawdziwej obsługi dostaną szary badge „nieaktywne" zamiast fałszywej zieleni, a probe folderu przestanie pozwalać na podglądanie dowolnych katalogów serwera (dziś każdy może enumerować system plików kontenera).

## Stan obecny

- **Polling daemon nie istnieje** — nic nie czyta `pollIntervalSec`.
- `apps/api/src/Import/Application/Service/HealthCheck/FolderHealthCheckDriver.php` — jedyny realny driver; **UWAGA security**: przyjmuje DOWOLNY `path`, robi `is_dir`/`scandir` i zwraca liczbę plików oraz komunikaty z pełną ścieżką → enumeracja katalogów kontenera przez API.
- `apps/api/src/Import/Application/Service/HealthCheck/StubHealthCheckDriver.php` — dla `sftp/ftp/http/webhook/api/upload` zwraca **zawsze `Ok`** („Probe … will be implemented with the polling daemon follow-up") → mylące zielone kropki w UI.
- `apps/api/src/Import/Domain/Entity/ImportSource.php` — pola `path` (52), `filePattern` (54), `pollIntervalSec` (58) istnieją; `lastPickupAt` (68) i `files24h` (70) **martwe**; relacja `profile` (37) istnieje — gotowy hak pod autotrigger.
- `apps/api/src/Import/Domain/Enum/ImportSourceHealth.php` ma już case `Off`; `ImportSourceType`: `Sftp/Ftp/Http/Folder/Webhook/Api/Upload`.
- `apps/api/src/Import/Domain/Entity/ImportSourceLog.php` (eventType/severity/payload) — gotowy magazyn na historię pickupów.
- `apps/api/src/Import/Presentation/Controller/TestImportSourceConnectionController.php` + `HealthCheckService` — orkiestracja driverów działa.
- W `apps/api/composer.json` brak adaptera SFTP (`league/flysystem-sftp-v3`); jest `league/flysystem-bundle`.

## Zakres prac

1. **Kontrakt `SourceDriverInterface`** (`apps/api/src/Import/Application/Service/Source/`): `listFiles(ImportSource): list<RemoteFile>` (nazwa, rozmiar, mtime) + `fetch(ImportSource, RemoteFile): stream`. Implementacje: **`FolderSourceDriver`** (lokalny katalog), **`SftpSourceDriver`** (`composer require league/flysystem-sftp-v3`; host/port/user/hasło lub klucz z configu źródła — sekrety NIE w kodzie), **`HttpSourceDriver`** (Symfony HttpClient, GET pojedynczego URL; `Last-Modified`/`ETag` zapamiętywane w `ImportSourceLog` do wykrywania nowości; timeout 30 s, max 3 redirecty).
2. **Realne health-check drivery** dla `sftp` (connect + listing katalogu) i `http` (HEAD/GET range z kodem odpowiedzi) — zastępują stub. **`StubHealthCheckDriver` znika**; typy bez drivera (`ftp`, `webhook`, `api`, `upload`) → `HealthCheckService` zwraca `ImportSourceHealth::Off` z komunikatem „driver not implemented". FE: szary badge dla `off` (mapowanie koloru w komponencie listy źródeł w `apps/admin/src/features/imports/sources/`), tooltip wyjaśniający — **koniec fałszywych zielonych kropek**.
3. **Whitelist katalogów dla folder probe i pickup** (koordynacja z IMP2-2.8, implementacja TUTAJ, 2.8 tylko weryfikuje): parametr `import_source_folder_roots` (env `IMPORT_SOURCE_FOLDER_ROOTS`, default np. `/data/import-inbox`), realpath-check że `path` jest wewnątrz roota; poza whitelistą → health `Error` „path outside allowed roots" **bez** ujawniania zawartości; komunikaty błędów bez echo pełnych ścieżek systemowych.
4. **Polling**: tick schedulera (współdzielony z IMP2-4.1; jeśli 4.2 wykonywany pierwszy — własny `RecurringMessage` co 60 s, 4.1 go reużyje) wybiera źródła z `pollIntervalSec` due → `listFiles()` → filtr `filePattern` (fnmatch, np. `*.csv`) → pliki nowe vs historia pickupów w `ImportSourceLog` (klucz: nazwa+mtime/rozmiar) → dla każdego nowego pliku **autotrigger ImportSession** z `source.profile` (mapping/OT/mode) + dispatch `ImportRunMessage` na transport `import`. Źródło bez profilu → log `warn` „no profile assigned, file skipped", health `Warn` — zero cichego ignorowania.
5. **Ad-hoc run**: `POST /api/import-sources/{id}/run` (nowy endpoint, `#[RequiresPermission]` analogicznie do test-connection) — wykonuje natychmiast cykl pickup dla jednego źródła; response: lista utworzonych sesji (może być pusta z powodem `no_new_files`).
6. **Telemetria źródła zaczyna działać**: `lastPickupAt` ustawiane przy każdym pickupie; `files24h` = liczba plików pobranych w ostatnich 24 h (agregat z `ImportSourceLog` przy odczycie lub licznik odświeżany przy pickupie); oba widoczne w istniejącym API źródeł.
7. **Testy**: unit driverów (SFTP mockowany przez adapter in-memory Flysystem — zasada „mock tylko zewnętrzne API"; HTTP przez MockHttpClient), unit whitelisty (path traversal `/data/import-inbox/../../etc` → reject), unit pollingu (nowy plik → 1 sesja; ten sam plik → 0), ApiTestCase: `POST .../run` + test-connection dla `ftp` zwraca `off`.

## Poza zakresem tego ticketu

- Harmonogramy cron i `ScheduleRun` — **IMP2-4.1** (konsumuje drivery z tego ticketu).
- Driver FTP, webhook ingest, API pull — świadomie NIE robimy (health `off`); wrócą przy realnym żądaniu pilota.
- Skip identycznych plików po treści (content-hash) — **IMP2-4.3**.
- Pozostałe guardy plikowe (zip-bomb, CSV injection, limit body Caddy) — **IMP2-2.8**.
- Notyfikacje o wyniku autotriggera — **IMP2-4.4**.

## Kryteria akceptacji

- [ ] Test połączenia dla źródła `sftp` z błędnym hasłem zwraca `error` (nie `ok`); z poprawnym — `ok` z czasem odpowiedzi.
- [ ] Test połączenia dla `ftp`/`webhook`/`api` zwraca `off`; UI pokazuje szary badge (screenshot w dowodzie).
- [ ] Folder probe z `path` spoza `IMPORT_SOURCE_FOLDER_ROOTS` zwraca `error` bez listingu i bez pełnej ścieżki w komunikacie; probe wewnątrz whitelisty działa jak dotąd.
- [ ] Wrzucenie nowego pliku pasującego do `filePattern` do folderu źródła z profilem skutkuje automatyczną sesją importu w ≤(pollIntervalSec+60) s.
- [ ] Ten sam plik NIE triggeruje drugiej sesji przy kolejnym ticku.
- [ ] `POST /api/import-sources/{id}/run` zwraca 200 z listą sesji; dla braku nowych plików — pustą listę z powodem.
- [ ] `lastPickupAt` i `files24h` na API źródła niepuste po pickupie.
- [ ] Źródło bez profilu loguje `warn` i nie tworzy sesji (test).

## Jak zwalidować (smoke test po wykonaniu)

1. `docker compose up -d`; przygotuj katalog whitelistowany (np. zamontowany `/data/import-inbox` — dopisany do compose w tym tickecie) i wrzuć tam mały CSV (możesz użyć 3 wierszy z `Zrodla/Importy przykładowe/bosch-09-01-2026.csv` zapisanych jako UTF-8 CSV).
2. `https://pim.localhost` (admin@demo.localhost / changeme) → Importy → Źródła: utwórz źródło `folder` z path wewnątrz whitelisty, `filePattern=*.csv`, `pollIntervalSec=60`, przypnij profil. Kliknij „Testuj połączenie" → zielony `ok` z liczbą plików.
3. Utwórz drugie źródło `folder` z `path=/etc` → test połączenia zwraca `error` „path outside allowed roots" (DevTools Network: response bez listingu katalogu).
4. Utwórz źródło `ftp` → badge **szary `off`**, nie zielony.
5. `curl -X POST -H "Authorization: Bearer $TOKEN" https://pim.localhost/api/import-sources/{id}/run` → 200, JSON z utworzoną sesją; sesja widoczna w Importach i kończy się `success`.
6. Wrzuć kolejny plik do katalogu i odczekaj ~2 min → sesja powstaje sama (worker log: pickup). `curl .../api/import-sources/{id}` → `lastPickupAt` świeże, `files24h ≥ 2`.
7. (Jeśli masz serwer SFTP pod ręką: `docker run -p 2222:22 atmoz/sftp foo:pass:::upload` i powtórz kroki 2/5 dla typu sftp.)

## Zależności

- Blokowany przez: **IMP2-1.6a** (transport `import` + worker w dev compose — autotrigger dispatchuje async).
- Blokuje: **IMP2-4.1** (ScheduleRunner pobiera plik driverem z tego ticketu).
- Koordynacja: **IMP2-2.8** (whitelist zaimplementowana tu; 2.8 nie dubluje), **IMP2-4.3** (skip-unchanged dla feedów z tych źródeł).

## Referencje

- `Project Plan/UI/feature-imports-v2.md` §5 etap 4 (ticket 4.2), §2.7 (test-connection stub zawsze „ok", martwe `lastPickupAt/files24h`), §4.4 D8.
- Kod: `apps/api/src/Import/Application/Service/HealthCheck/FolderHealthCheckDriver.php`, `apps/api/src/Import/Application/Service/HealthCheck/StubHealthCheckDriver.php`, `apps/api/src/Import/Application/Service/HealthCheckService.php`, `apps/api/src/Import/Presentation/Controller/TestImportSourceConnectionController.php`, `apps/api/src/Import/Domain/Entity/ImportSource.php`, `apps/api/src/Import/Domain/Entity/ImportSourceLog.php`, `apps/admin/src/features/imports/sources/`.
- Issues: #500 (VIEW-IMP-03, gdzie powstał stub), #598–605.

## Definition of Done

- [ ] PHPStan max zielony (`cache:warmup --env=dev` + `--memory-limit=1G`); Biome strict dla FE.
- [ ] PHPUnit ≥80% nowej logiki; ApiTestCase dla `POST .../run` i test-connection `off`.
- [ ] Playwright dla widocznej zmiany badge'a `off` na liście źródeł.
- [ ] Typecheck admin z `NODE_OPTIONS=--max-old-space-size=4096`.
- [ ] Regeneracja `docs/api-spec/v0.json` (nowy endpoint `run`): `cache:warmup` + `api:openapi:export`.
- [ ] php-cs-fixer przed commitem; `league/flysystem-sftp-v3` w najnowszej stabilnej, lockfile ścisły; sekrety źródeł poza kodem.
- [ ] Manual smoke wg sekcji „Jak zwalidować" — artefakt dowodu (JSON test-connection error/off + screenshot szarego badge'a + JSON sesji z autotriggera) w komentarzu zamykającym. CLOSED MEANS CLOSED.

---

## IMP2-4.3 — feat(import): content-hash skip-unchanged dla feedów cyklicznych (#1497)

**Labels:** backend, enhancement, testing, epik-IMP2 · **Estymata:** 6–8 h · **Zależy od:** IMP2-1.4, IMP2-2.6

## Po co to robimy (kontekst nietechniczny)

Dostawcy przysyłają co noc ten sam plik z cennikiem, w którym realnie zmieniło się kilkanaście pozycji z dziesiątek tysięcy. Bez tego ticketu każdy nocny import mieli od nowa wszystkie wiersze — godziny pracy serwera na zapisanie tego, co już jest w bazie. Po tym tickecie system zapamięta „odcisk palca" każdego zaimportowanego obiektu i przy kolejnym przebiegu **pominie wiersze, w których nic się nie zmieniło** — drugi import tego samego pliku będzie wielokrotnie szybszy. Z bezpiecznikiem: gdy zmienią się reguły kompletności lub schemat typu obiektu, skip się nie aktywuje (inaczej „zamroziłby" przeliczanie kompletności), a operator zawsze może wymusić pełny przebieg.

## Stan obecny

- Mechanizm skip-unchanged **nie istnieje** — żadnego hashowania treści wiersza nigdzie w `apps/api/src/Import/`.
- Po IMP2-1.4 istnieje `ImportValueWriter` z prefetchem istniejących `ObjectValues` per chunk; po IMP2-2.6 istnieje compare-values diff (skip no-op na poziomie pojedynczej wartości) — to jest poziom 2; ten ticket dodaje tańszy poziom 1 (skip całego wiersza bez prefetchu wartości).
- `apps/api/src/Catalog/Domain/Entity/ObjectType.php` — `schemaVersion` (linia 139, bumpowany przez `apps/api/src/Catalog/Infrastructure/Doctrine/Listener/ObjectTypeSchemaVersionBumper.php`) oraz `completenessRules` JSONB (linia 67) istnieją — gotowe składniki fingerprninta schematu.
- `apps/api/src/Catalog/Application/Handler/RebuildAttributesIndexedHandler.php` — istnieje async rebuild; zmiana `completeness_rules` dziś **nie dispatchuje** przeliczenia (mini-zakres pkt 6).
- Benchmark: `Zrodla/Importy przykładowe/GA_List.csv` — 10 MB, 90k wierszy (klasa B w §6 planu).

## Zakres prac

1. **`RowContentHasher`** (`apps/api/src/Import/Application/Service/RowContentHasher.php`): sha256 **kanonicznego JSON-a zmapowanych wartości wiersza** — payload gotowy do writera PO mapowaniu i transformacjach (atrybut→envelope, posortowane klucze, stabilna serializacja float/bool), włącznie z kolumnami systemowymi (kategorie, status/enabled) i scope (locale/channel). Hash liczy się z tego, co import CHCE zapisać — nie z surowej linii pliku (różna kolejność kolumn w pliku nie psuje skipu).
2. **Fingerprint schematu**: `schema_fingerprint = sha256(objectType.schemaVersion . ':' . canonical_json(completenessRules))`. Bez tego skip zamroziłby completeness po zmianie reguł — wiersz „niezmieniony" nigdy nie przeszedłby przez writer i rebuild.
3. **Tabela `import_object_content_hashes`** (migracja Doctrine, Import BC): `tenant_id UUID NOT NULL` (z ORM default — lekcja NOT NULL/test DB), `object_id UUID` (PK razem z tenant), `content_hash`, `schema_fingerprint`, `last_import_session_id`, `updated_at`. RLS-ready (polityka jak pozostałe tabele Import po IMP2-2.5).
4. **Skip w pipeline** (przed `ImportValueWriter`, po `ObjectResolver` z IMP2-1.3): dla trybu `update`/`upsert`, gdy obiekt zmatchowany i `(content_hash, schema_fingerprint)` z tabeli równe obliczonym → wiersz pominięty w całości: bez prefetchu wartości, bez zapisów, bez undo-logu, bez rebuildu; licznik `skipped`++ (kubełek „pominie" w dry-run i raporcie). Po udanym zapisie wiersza — upsert hasha (w tym samym flushu chunku). Dla trybu `create` skip nie dotyczy (nowe obiekty).
5. **„Wymuś pełny przebieg"**: flaga `forceFullRun` w payloadzie `POST /api/import-sessions` (+ checkbox w kroku potwierdzenia wizarda — minimalny FE) — bypassuje **oba** poziomy skipu: content-hash (poziom 1) ORAZ compare-values diff z IMP2-2.6 (poziom 2). Persistowana na sesji, widoczna w response.
6. **Mini-zakres (wymóg planu)**: zmiana `completeness_rules` na ObjectType dispatchuje async przeliczenie completeness/`attributes_indexed` dla obiektów tego typu (listener → istniejący rebuild handler; BulkContext path dla >1000 obiektów). Dzięki temu fingerprint + rebuild domykają spójność.
7. **Testy**: unit hashera (stabilność: te same dane w innej kolejności kolumn → ten sam hash; zmiana jednej wartości → inny hash), integracyjny: import → re-import tego samego pliku → `skipped == totalRows`, zero nowych wpisów undo-logu; zmiana `completeness_rules` → trzeci przebieg NIE skipuje (fingerprint się zmienił) i rebuild został zdispatchowany; `forceFullRun=true` → zero skipów. **Benchmark jako test** (rozszerzenie `apps/api/src/Benchmark/BulkImportBenchmarkCommand.php` lub osobny case): drugi przebieg identycznego pliku ≥5× szybszy od pierwszego przy 50k+ wierszy.

## Poza zakresem tego ticketu

- Compare-values diff per wartość (poziom 2) — **IMP2-2.6** (zależność; semantyka provenance ≠ no-op rozstrzygnięta w ADR IMP2-1.1).
- Skip na poziomie CAŁEGO PLIKU (hash pliku w źródle przed parsowaniem) — świadomie nie teraz; wraca przy realnym feedzie, jeśli poziom wierszy nie wystarczy.
- Import strukturalny słownika atrybutów z GA_List (merytoryczna zawartość tego pliku) — osobny epik „configuration as code" (§7 planu).
- Polling źródeł, które dostarczają feedy — **IMP2-4.2**.

## Kryteria akceptacji

- [ ] Drugi import identycznego pliku: `skipped == liczba wierszy`, `created == 0`, `updated == 0`, brak wpisów w undo-logu i brak dispatchy rebuild `attributes_indexed` dla skipniętych obiektów.
- [ ] Zmiana JEDNEJ komórki w pliku → dokładnie 1 wiersz przetworzony, reszta skipnięta.
- [ ] Zmiana `completeness_rules` ObjectType unieważnia skip (trzeci przebieg przetwarza wiersze) ORAZ dispatchuje przeliczenie completeness (test asercji na messengerze).
- [ ] Bump `schemaVersion` (np. zmiana definicji atrybutu) unieważnia skip.
- [ ] `forceFullRun=true` w POST /api/import-sessions wyłącza skip poziomu 1 i 2 (test).
- [ ] Dry-run pokazuje skipnięte wiersze w kubełku „pominie".
- [ ] Tabela hashy ma `tenant_id NOT NULL` z ORM default; cross-tenant odczyt hashy niemożliwy (test izolacji).
- [ ] Benchmark: drugi przebieg ≥5× szybszy (asercja w teście/benchmark commandzie, wynik w dowodzie).

## Jak zwalidować (smoke test po wykonaniu)

1. `docker compose exec api php bin/console cache:clear --env=test` → `vendor/bin/phpunit --filter ContentHash` — zielone.
2. Benchmark na realnym pliku: `Zrodla/Importy przykładowe/GA_List.csv` (90k wierszy; merytorycznie to słownik atrybutów — do benchmarku zmapuj 2–3 kolumny tekstowe na atrybuty testowego ObjectType). Na `https://pim.localhost` (admin@demo.localhost / changeme) zaimportuj plik przez kreator; zanotuj czas trwania sesji (widok sesji / `completedAt - startedAt`).
3. Zaimportuj ten sam plik drugi raz tym samym profilem → sesja kończy się **wielokrotnie szybciej** (oczekiwane ≥5×), liczniki: `skipped ≈ 90k`, `created/updated = 0`.
4. Zmień jedną wartość w jednej linii pliku, import trzeci raz → dokładnie 1 wiersz `updated`, reszta `skipped`.
5. Zmień reguły kompletności na ObjectType (Modelowanie → completeness), import czwarty raz → wiersze przetwarzane (nie skipnięte), completeness przeliczona (badge na produktach się aktualizuje).
6. Powtórz krok 3 z zaznaczonym „Wymuś pełny przebieg" → zero skipów; DevTools Network: payload zawiera `forceFullRun: true`, response 201.

## Zależności

- Blokowany przez: **IMP2-1.4** (hash liczony z payloadu writera; punkt wpięcia przed `ImportValueWriter`), **IMP2-2.6** (flaga force bypassuje też compare-values diff; wspólna semantyka no-op z ADR).
- Blokuje: efektywność feedów cyklicznych z **IMP2-4.1**/**IMP2-4.2** (nocne harmonogramy korzystają ze skipu automatycznie).

## Referencje

- `Project Plan/UI/feature-imports-v2.md` §5 etap 4 (ticket 4.3), §3 filar 8, §4.4 D11, §6 (GA_List jako benchmark klasy B), §8 pkt 4 (benchmark RAM/CI).
- Kod: `apps/api/src/Catalog/Domain/Entity/ObjectType.php` (schemaVersion/completenessRules), `apps/api/src/Catalog/Infrastructure/Doctrine/Listener/ObjectTypeSchemaVersionBumper.php`, `apps/api/src/Catalog/Application/Handler/RebuildAttributesIndexedHandler.php`, `apps/api/src/Benchmark/BulkImportBenchmarkCommand.php`.
- Issues: #598–605 (backlog v1), #1130 (round-trip — skip nie może go psuć: golden test po tym tickecie nadal zielony).

## Definition of Done

- [ ] PHPStan max zielony (`cache:warmup --env=dev` + `--memory-limit=1G`).
- [ ] PHPUnit ≥80% nowej logiki; testy integracyjne na realnym Postgresie (bez mocków DB).
- [ ] Migracja Doctrine z ORM default dla `tenant_id` (lekcja: test DB budowany z metadanych ORM).
- [ ] Regeneracja `docs/api-spec/v0.json` (nowe pole `forceFullRun`): `cache:warmup` + `api:openapi:export`.
- [ ] Golden test round-trip (IMP2-1.5/1.10) nadal zielony po włączeniu skipu.
- [ ] php-cs-fixer przed commitem; checkbox FE przez `t()`; typecheck admin z `NODE_OPTIONS=--max-old-space-size=4096`.
- [ ] Manual smoke wg sekcji „Jak zwalidować" — artefakt dowodu (czasy obu przebiegów + liczniki skipped + screenshot) w komentarzu zamykającym. CLOSED MEANS CLOSED.

---

## IMP2-4.4 — feat(import,admin): notyfikacje końca importu + telemetria (#1498)

**Labels:** backend, frontend, infra, enhancement, epik-IMP2 · **Estymata:** 6–8 h · **Zależy od:** IMP2-1.6a

## Po co to robimy (kontekst nietechniczny)

Długi import (kilkadziesiąt tysięcy wierszy) potrafi trwać kilkanaście minut — operator nie będzie siedział i patrzył na pasek postępu. Dziś, jeśli odejdzie od ekranu importu, **nie dowie się, że import się skończył** (ani że się wywalił): checkbox „powiadom mailem" w kreatorze to atrapa, dzwoneczek powiadomień w aplikacji nie wie nic o importach (choć dla eksportów już działa). Po tym tickecie: koniec długiego importu przyjdzie mailem, każdy koniec importu wpadnie do dzwoneczka w aplikacji niezależnie od tego, gdzie operator akurat jest, a na ekranie sesji będzie widać znaczniki czasu poszczególnych faz (pobranie pliku → parsowanie → walidacja → zapis → media → koniec). Do tego metryki Prometheus, żeby dało się alertować na wolne/wysypujące się importy.

## Stan obecny

- **E-mail**: checkbox `emailNotification` w `apps/admin/src/features/imports/hooks/useImportWizard.ts` to placebo — `StepConfirm.tsx` nie wysyła go w FormData; backend nie ma żadnej wysyłki maila o imporcie. Infrastruktura Mailer istnieje po PR #790: `symfony/mailer` w `apps/api/composer.json`, `apps/api/config/packages/mailer.yaml`, wzorce użycia `MailerInterface` w `apps/api/src/Identity/Application/InvitationService.php` / `PasswordResetService.php`; w dev Mailpit (`docker-compose.yml`, serwis `mailpit` linia ~360).
- **Dzwoneczek**: `apps/admin/src/features/exports/hooks/ExportsLiveBridge.tsx` — render-less bridge (terminalne eventy → `useNotificationsInboxOptional()` z `@/layout/notifications-context` + invalidate cache), zamontowany w `apps/admin/src/layout/AppLayout.tsx`. **Dla importów odpowiednika nie ma** — jest tylko per-sesyjny `apps/admin/src/features/imports/hooks/useImportProgress.ts` (działa wyłącznie na otwartym widoku sesji; topic hardcoded `https://pim.localhost`, linia 74 — fix w etapie 0).
- **Backend publikuje już topic per-user**: `apps/api/src/Import/Application/Service/ImportProgressPublisher.php` — topiki `{base}/imports/{sessionId}` i `{base}/imports/user/{userId}` (linie 86–87), eventy `progress` / `row_processed` / `error` / `completed`.
- **Metryki**: wzorzec istnieje w `apps/api/src/Shared/Infrastructure/Metrics/` (`RbacMetricsRegistry` + `MetricsController` — Prometheus exposition format). Zero metryk importu.
- **Fazy**: `ImportSession` ma tylko `startedAt`/`completedAt`; makieta `Zrodla/Front_Claude_Design/NOWY UI/PIM-nowoczesny/Import-sesja.html` pokazuje timeline 6 faz („Plik pobrany" → „Encoding wykryto" → „Parsing" → „Walidacja" → „Zapis DB" → „Zakończono"). Kolumny `phase_timestamps` nie ma.

## Zakres prac

1. **`phase_timestamps` JSONB na `import_sessions`** (migracja, ORM default `'{}'`): klucze `file_fetched`, `parsed`, `validated`, `db_write_started`, `media_started`, `finished` (ISO 8601), zapisywane przez `ImportRunHandler` (i media handler z IMP2-1.12) na granicach faz; eksponowane w `GET /api/import-sessions/{id}` (konsumpcję wizualną robi widok sesji etapu 3 / IMP2-3.7 — tu wystarczy API + proste wypisanie w istniejącym widoku sesji jeśli tanie).
2. **E-mail po długim imporcie**: listener terminalnego statusu sesji (`success`/`partial`/`failed`): jeżeli runtime (`completedAt - startedAt`) **> 5 min** LUB sesja ma włączoną notyfikację — wyślij e-mail przez `MailerInterface` (Twig template: nazwa pliku, OT, liczniki created/updated/skipped/errors/images, link do sesji, status). Odbiorca: e-mail użytkownika sesji; dla sesji z harmonogramu (IMP2-4.1) honoruj `ImportSchedule.notifyChannels`/`notifyConfig` (kanał `email` + lista adresów) — hook `ImportScheduleRunCompleted` z 4.1; jeśli 4.1 jeszcze nie merged, listener działa na samych sesjach (brak twardej zależności). Checkbox `emailNotification` z wizarda **zaczyna działać**: przesyłany w FormData, persystowany na sesji (kolumna `email_notification` bool, ta sama migracja) — koniec placebo.
3. **`ImportsLiveBridge`** (`apps/admin/src/features/imports/hooks/ImportsLiveBridge.tsx`) — render-less, wzorzec 1:1 z `ExportsLiveBridge.tsx`: hook `useImportSessionsStream` subskrybujący topic per-user `…/imports/user/{userId}` przez `/.well-known/mercure` (topic z konfiguracji — po fixie etapu 0, nie hardcode); eventy `completed` (status terminalny) → wpis do inbox dzwoneczka (success/partial=warning/failed=error, body z licznikami, `href: /integrations/imports/{id}`) + invalidate cache listy sesji (React Query — wzorzec useQuery, nie useEffect+jsonFetch); dedupe po `sessionId:status` (seenRef). Montaż w `apps/admin/src/layout/AppLayout.tsx` obok `ExportsLiveBridge`. Klucze i18n w `pl/`+`en/`.
4. **Metryki Prometheus** (`apps/api/src/Shared/Infrastructure/Metrics/ImportMetricsRegistry.php`, wpięte do `MetricsController` jak Rbac): `pim_import_sessions_total{status}`, `pim_import_rows_processed_total`, `pim_import_rows_skipped_total`, `pim_import_errors_total{type}`, `pim_import_rows_per_second` (gauge per ostatnia sesja), `pim_import_worker_memory_bytes` (gauge, `memory_get_usage(true)` na granicach chunków `ImportRunHandler`). Aktualizacja z handlera per chunk (nie per wiersz — lekcja Mercure z §2.6).
5. **Testy**: unit listenera maila (>5 min → mail; <5 min bez checkboxa → brak; checkbox → mail; profil mailera `null://` w testach łapany przez asercje na transporcie), test serializacji `phase_timestamps` w response sesji (ApiTestCase), test rejestru metryk (format exposition), test FE bridge'a (vitest/Playwright: event completed → wpis w inbox).

## Poza zakresem tego ticketu

- Pełny widok sesji v2 z wizualnym pipeline'em 6 faz wg makiety — **IMP2-3.7** (tu tylko dane w API).
- Naprawa hardcoded `pim.localhost` w topicach Mercure — **etap 0** (quick-win b); bridge konsumuje konfigurowalny topic.
- Notyfikacje webhook/Slack z `notifyChannels` — tylko kanał `email`; reszta wartości enum jawnie ignorowana z logiem `info` (zero fałszywych affordancji — UI nie oferuje innych kanałów).
- Grafana dashboard / alerty na metrykach — infra Fazy 1 (monitoring full stack).
- Tworzenie sesji z harmonogramu — **IMP2-4.1**.

## Kryteria akceptacji

- [ ] Import trwający >5 min wysyła e-mail z licznikami i linkiem (w dev widoczny w Mailpit).
- [ ] Import <5 min z zaznaczonym checkboxem „powiadom mailem" wysyła e-mail; bez checkboxa — nie wysyła (checkbox realnie przesyłany w FormData — DevTools).
- [ ] Zakończenie importu, gdy operator jest na INNEJ stronie aplikacji (np. Dashboard), dodaje wpis do dzwoneczka z linkiem do sesji; klik prowadzi na widok sesji.
- [ ] Wpis w dzwoneczku nie duplikuje się przy re-evencie (dedupe po sessionId:status).
- [ ] Lista sesji importów odświeża się automatycznie po zakończeniu (invalidate cache — bez ręcznego F5).
- [ ] `GET /api/import-sessions/{id}` zawiera `phaseTimestamps` z ≥4 wypełnionymi fazami po zakończonym imporcie; znaczniki rosnące chronologicznie.
- [ ] `GET /api/metrics` zawiera `pim_import_sessions_total`, `pim_import_rows_processed_total`, `pim_import_worker_memory_bytes` z niezerowymi wartościami po imporcie.
- [ ] Zero czerwonych błędów w konsoli przy aktywnym bridge'u; PHPUnit/vitest zielone.

## Jak zwalidować (smoke test po wykonaniu)

1. `docker compose up -d` (worker z IMP2-1.6a aktywny). Na `https://pim.localhost` (admin@demo.localhost / changeme) uruchom import średniego pliku przez kreator z zaznaczonym „powiadom mailem" — np. `Zrodla/Importy przykładowe/bosch-09-01-2026.csv` (TAB+quotes; jeśli kreator sprzed IMP2-3.x ma problem z formatem, użyj dowolnego CSV z eksportu własnego katalogu).
2. Przejdź na Dashboard (opuść widok sesji). Po zakończeniu importu: dzwoneczek dostaje badge; wpis z licznikami; klik → widok sesji. Console (DevTools) bez czerwonych błędów.
3. Otwórz `http://localhost:8025` (Mailpit) → e-mail „Import zakończony" z licznikami i linkiem; screenshot do dowodu.
4. `curl -H "Authorization: Bearer $TOKEN" https://pim.localhost/api/import-sessions/{id} | jq '.phaseTimestamps'` → obiekt z fazami w kolejności chronologicznej.
5. `curl -s -H "Authorization: Bearer $TOKEN" https://pim.localhost/api/metrics | grep pim_import_` → metryki z wartościami > 0.
6. Uruchom drugi import i sprawdź, że lista sesji (otwarta w drugiej karcie) odświeżyła się sama po zakończeniu.

## Zależności

- Blokowany przez: **IMP2-1.6a** (realny async worker — bez niego „koniec importu w tle" nie istnieje w dev).
- Miękka integracja: **IMP2-4.1** (hook `ImportScheduleRunCompleted` + `notifyChannels` dla sesji z harmonogramów — jeśli 4.1 niezmergowane, część schedule'owa za feature-guardem), **IMP2-1.12** (faza `media` w `phase_timestamps`), etap 0 quick-win (konfigurowalne topiki Mercure).

## Referencje

- `Project Plan/UI/feature-imports-v2.md` §5 etap 4 (ticket 4.4), §2.6 (Mercure per chunk, nie per wiersz), §2.7 (checkbox e-mail = placebo), §3 filar 12.
- Makieta: `Zrodla/Front_Claude_Design/NOWY UI/PIM-nowoczesny/Import-sesja.html` (timeline 6 faz).
- Wzorce kodu: `apps/admin/src/features/exports/hooks/ExportsLiveBridge.tsx`, `apps/admin/src/layout/AppLayout.tsx`, `apps/api/src/Import/Application/Service/ImportProgressPublisher.php`, `apps/api/src/Shared/Infrastructure/Metrics/RbacMetricsRegistry.php` + `MetricsController.php`, `apps/api/src/Identity/Application/InvitationService.php` (wzorzec MailerInterface), PR #790 (Mailer infra).
- Issues: #598–605, #1429/#1430 (widok sesji — superseded przez etap 3).

## Definition of Done

- [ ] PHPStan max zielony (`cache:warmup --env=dev` + `--memory-limit=1G`); Biome strict.
- [ ] PHPUnit ≥80% nowej logiki; ApiTestCase dla `phaseTimestamps` w response.
- [ ] Playwright E2E dla dzwoneczka (event → wpis w inbox) — widoczna zmiana UI.
- [ ] Typecheck admin z `NODE_OPTIONS=--max-old-space-size=4096`; i18n przez `t()` + restart kontenera admin po edycji locale JSON.
- [ ] Regeneracja `docs/api-spec/v0.json` (nowe pola sesji): `cache:warmup` + `api:openapi:export`; diff scope'owany do zmiany.
- [ ] Migracja z ORM default dla nowych kolumn (lekcja NOT NULL/test DB).
- [ ] php-cs-fixer przed commitem.
- [ ] Manual smoke wg sekcji „Jak zwalidować" — artefakt dowodu (screenshot Mailpit + screenshot dzwoneczka + JSON phaseTimestamps + grep metryk) w komentarzu zamykającym. CLOSED MEANS CLOSED.

---
