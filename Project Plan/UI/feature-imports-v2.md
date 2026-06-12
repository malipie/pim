# Feature — Import v2: przebudowa modułu importu (round-trip + kreator + odporność)

## Status: 🟢 ZAAKCEPTOWANY (2026-06-12) — tickety rozpisane: issues **#1460–#1498**, epic **#1499**, backlog `feature-imports-v2-tickets.md`

> Następca `feature-imports.md` (v1, IMP-01..15 + VIEW-IMP-00..05). Plan oparty na:
> (a) pełnym audycie kodu Import/Export/Catalog BC + FE + testów (2026-06-12, 10 agentów),
> (b) researchu best practices: Akeneo (job profiles, Tailored Import), Pimcore (Data Importer),
> Flatfile/OneSchema (import UX), Matrixify/Magento (round-trip, partial update), wzorce inżynieryjne
> (staging, content-hash, reverse-delta rollback),
> (c) adwersaryjnym przeglądzie draftu przez 3 niezależnych krytyków (architekt / pragmatyk /
> integralność danych) — wszystkie twierdzenia diagnozy zweryfikowane w kodzie,
> (d) analizie 14 plików benchmarkowych operatora z `Zrodla/Importy przykładowe` (2026-06-12) —
> sekcja 6; cel operatora: „te pliki po odpowiednim mapowaniu mają się importować do PIM".
> Po akceptacji: rozpisanie na tickety GitHub per etap.

---

## 1. Wymagania operatora (2026-06-12)

1. **Round-trip jest baseline'em i nie podlega negocjacji**: plik wyeksportowany z wieloma/wszystkimi
   atrybutami musi się dać zaimportować z powrotem. „To musi po prostu działać."
2. **Import dowolnych plików klienta** (CSV/XLSX na start) przez przyjazny, krokowy kreator mapowania.
3. **Walidatory i odporność** — import nie może wywalać bazy ani zostawiać śmieci.
4. **UI bazuje na istniejących makietach i widokach** (NUI-09/10/11, makiety `Import-nowy.html`,
   `Import-sesja.html`).

## 2. Diagnoza — dlaczego dziś nie działa

### 2.1 Bloker główny: silnik jest CREATE-ONLY

- `ImportObjectCreator` **zawsze** robi `new CatalogObject`; żadna ścieżka kodu nie czyta
  `ImportProfile.mode` — enum `ImportMode` (ADD/UPDATE/UPSERT/MERGE/INCREMENT/DELETE) jest dekoracyjny.
- `DuplicateSkuInDb` ma poziom *Warning*, ale `ValidationError::isRowBlocking()` zwraca `true` dla
  wszystkiego poza `CategoryNotFound` → **re-import własnego eksportu do niepustego katalogu odrzuca
  100% wierszy**. Scenariusz „eksport → poprawka w Excelu → import" daje 0 zmian.
- Trzy wersje „prawdy" w repo: docblock `ImportMode` mówi „worker always upserts", spec v1 mówi
  „ADD-only", kod robi create + blokadę. `ListImportSessionsController` hardcoduje `mode='UPDATE'`.
  Makieta NUI-10 pokazuje kubełek „Aktualizacje", którego silnik nie umie wyprodukować.

### 2.2 Import omija centralny writer katalogu

`ObjectAttributesUpserter` (ścieżka admin/API/bulk-edit) ma: walidację per-typ (email/color/identifier/
select/multiselect z żywych `AttributeOption`), required per `Attribute::isRequired` (globalny — uwaga:
required per ObjectType NIE istnieje w modelu, junction ma tylko `requiredForCompleteness`), pre-check
409 dla identifier, normalizację primary-locale→global, routing channel scope. **Import tego wszystkiego
nie ma** — buduje envelope sam i persystuje `ObjectValue` bezpośrednio. Skutki:

- nieistniejące `option_code`, wiszące `asset_id`/`object_id`, niezwalidowane identifiery lądują w JSONB
  (potem edycja w UI dostaje 422 — operator widzi „zepsute" produkty),
- duplikat identifiera wybucha dopiero na triggerze DB → **markFailed całej sesji w środku batcha**,
  z częściowo zapisanymi danymi,
- required hardcoded `['sku','name']` — custom OT bez atrybutu `name` w ogóle nie przejdzie importu,
- wartość `name.pl` przy primary locale `pl` ląduje w wierszu `locale='pl'` zamiast global → niewidoczna
  w `attributes_indexed`, listach i Meilisearch.

### 2.3 Rozjazd shape'ów JSONB — koroduje round-trip JUŻ DZIŚ (eskalacja po przeglądzie)

Dwa „kanony" żyją równolegle: admin (`ObjectAttributesUpserter::wrapValue`) zapisuje select jako
`{value:'red'}` (ślepe wrapowanie skalara), a eksport (`ValueSerializer`) czyta **wyłącznie**
`{option_code}` — **selecty wpisane w adminie już dziś eksportują się jako puste komórki** i omijają
walidację #1261. `GenerateVariantsController` stampuje osie select jako `{value:x}`. Price ma legacy
`{value}` vs kanoniczne `{amount,currency}`. `docs/api/jsonb-schemas.md` (authoritative wg CLAUDE.md)
jest sprzeczny z kodem. FE (`unwrapAttributesIndexed`) podnosi tylko klucz `.value`. Golden test na
świeżych fixtures niczego z tego nie wykryje — **migracja kanonu shape'ów to warunek wstępny
round-tripu, nie „nice to have"** (ticket 1.2).

### 2.4 Luki round-trip poza silnikiem zapisu

| Obszar | Eksport emituje | Import robi |
|---|---|---|
| Kanały | `code.channel` (np. `price.shopify`), planner ADR-0018 | każdy suffix parsowany jako **locale** → `ObjectValue(locale='shopify')` — cicha korupcja |
| Warianty | `parent_sku` built-in; `include_variants` to **no-op** (żaden caller nie fan-outuje wariantów) | `parent_sku` w SystemColumn = **auto-skip**, hierarchia ginie |
| Kategorie | pipe-joined **wiele** kodów `cat1\|cat2` | jedna kategoria per wiersz, reszta ginie |
| Status | `status`, `enabled` | auto-skip — stan publikacji nie wraca |
| Relacje | `object_id` (UUID) z ObjectValue | zapis raw bez resolve; **nie tworzy `ObjectRelation`** (kanon ADR-014) → relacje niewidoczne w UI |
| Assety | `asset_id` (galerie pipe-joined) | zapis raw bez walidacji; pipe **nie jest splitowany** |

### 2.5 Brak strażnika kontraktu

`ImportRoundTripApiTest` (#1130) **nie uruchamia eksportera** — dry-run ręcznie napisanego
1-wierszowego CSV, bez persystencji, bez porównania wartości. Dryf formatu eksportu nie jest strzeżony
żadnym testem. Ścieżka async (`ImportRunHandler`) ma **zero** testów. Persystencja per typ atrybutu
przetestowana tylko dla Text (z 17 typów).

### 2.6 Odporność i wydajność

- **RAM**: CSV przez `file_get_contents` (+ kopia iconv), XLSX przez PhpSpreadsheet full-load — także
  w `parse-preview` w requeście HTTP. Makieta obiecuje „streaming chunk 5k", kod ładuje wszystko.
  Eksport ma analogiczny problem: `SyncExportRunner::resolveTargets()` materializuje cały zbiór
  obiektów (hard cap 500k!) bez `clear()` per chunk.
- **Plik uploadowany 3×** w wizardzie (preview, dry-run, start); dry-run re-trigger bez debounce.
- `ImportRunHandler` **nie wchodzi w BulkContext** (sam listener jest BulkContext-aware) → synchroniczny
  rebuild `attributes_indexed` + completeness przy każdym flushu batcha, wbrew regule „async dla >1000".
- Mercure `rowProcessed` publikowany **per wiersz** (50k wierszy = 50k POST-ów do huba).
- Walidacja: 1–2 SELECT-y per wiersz; brak prefetchu (w tym istniejących ObjectValues dla trybu update).
- `import_logs` bez `tenant_id`; workery Messenger nie ustawiają **GUC** `app.current_tenant`
  (TenantContext per middleware działa — luka dotyczy wyłącznie polityk RLS; FORCE RLS wywali async
  import); raport CSV ładuje do 100k encji do RAM mimo komentarza „streamed".
- **Infra**: w dev/test `MESSENGER_TRANSPORT_DSN=sync://`, worker `messenger:consume` istnieje tylko
  w `docker-compose.prod.yml` — „async" w dev wykonuje się in-band w requeście HTTP; doctrine transport
  ma `redeliver_timeout` 3600s → import >1h zostanie re-delivered w trakcie działania.

### 2.7 Fałszywe affordancje (każda albo zacznie działać, albo zniknie)

pauza/cancel/resume (worker nie sprawdza statusu — pauza kończy się FAILED przez LogicException),
`do_backup` (ignorowany przez kontroler), checkbox e-mail (placebo), `profileId`/`saveAsProfileName`/
`locale`/`zipFile`/`imageSource` (nie opuszczają przeglądarki), zdjęcia HTTP/ZIP (zero kodu),
run-now harmonogramu (wieczny `ScheduleRun pending`, nie tworzy sesji), test-connection SFTP/FTP/HTTP
(stub zawsze „ok"), `report.csv` i eksport profilu przez `<a href>` bez Bearer (→ 401), hardcoded
`https://pim.localhost` w topicach Mercure, martwe pola schematu (`imagesDownloaded/Failed`,
`zipFileName`, `backupSnapshot`, `customValidationRules`, `notifyChannels`, `lastPickupAt/files24h`,
`ImportScheduleRun.sessionId`).

---

## 3. Filary projektu v2 (wnioski z researchu)

1. **Plik jest API dla nie-programisty** (Matrixify): format eksportu = format importu, 1:1, strzeżony
   golden testem. Eksport→edycja→import to podstawowy workflow, nie corner case.
2. **Jeden writer, jedna walidacja**: wspólny rdzeń (normalizacja envelope per typ + walidatory +
   reguła primary-locale→global) w `Catalog\Application`, wystawiony przez kontrakt; konsumowany przez
   `ObjectAttributesUpserter` (per-request, HTTP-exceptions) i nowy `ImportValueWriter` (result-based,
   persist-only, flush w chunku). Granica egzekwowana w Deptrac (warstwy Import/Export dziś w ogóle
   nie istnieją w `deptrac.yaml` — to się zmienia).
3. **UPSERT po konfigurowalnym kluczu**: match po identifier per profil (default `objects.code`/SKU;
   opcjonalnie atrybut typu identifier, np. EAN). Tryby realne: `create` / `update` / `upsert`
   (default `upsert`). MERGE/INCREMENT/DELETE — usuwamy z enum i UI (z migracją istniejących wierszy!).
   Dry-run pokazuje jawnie: *utworzy N / zaktualizuje M / pominie K / błędy E* — nieoczekiwane N to
   czerwona flaga (literówka w SKU = cichy create).
4. **Trzy stany komórki**: kolumna nieobecna = nie ruszaj; komórka pusta = **nie ruszaj (default)**,
   czyszczenie tylko opt-in `clear_if_empty` per kolumna (z progiem ostrzegawczym w dry-run); dla
   kolekcji polityka `append`/`replace` per kolumna. Kontrakt w `docs/api/jsonb-schemas.md`.
5. **Walidacja schema-first, wszystkie błędy wiersza naraz**: schemat importu generowany z `ObjectType`
   + `validation_rules`; severity `error`/`warning`/`info` — **warning nie blokuje**; partial import
   default; błędne wiersze → **plik odrzutów** (surowe wiersze + przyczyny) do poprawy i re-importu.
6. **Izolacja błędu wiersza od batcha**: setowy pre-check w chunku (duplikaty sku/identifier w pliku,
   w batchu i vs DB), degradacja do per-row commit przy niespodziewanym błędzie flusha. Sesja failuje
   tylko na błędach systemowych.
7. **Streaming end-to-end + jeden upload**: openspout (już w composer.json) + league/csv stream;
   plik stagowany w MinIO raz (`staged_file_id`), reuse w dry-run i run; TTL na porzucone sesje.
8. **Bulk-path zapisu**: BulkContext + async rebuild `attributes_indexed`, batch reindex Meilisearch,
   Mercure per chunk, compare-values diff (skip no-op — z poprawnym traktowaniem provenance i zmian
   schematu, patrz 2.6/4.5).
9. **Jednoznaczna gramatyka kolumn**: `code` / `code.locale` / `code.channel` / `code.locale.channel`,
   dezambiguacja przez rejestr locali i kanałów tenanta + **reguła precedencji przy kolizji kodów**
   (kanał `en` vs locale `en`) + walidacja zabraniająca tworzenia kanału o kodzie kolidującym z locale.
   Symetryczna zmiana po stronie eksportu; egzekwowany charset kodów atrybutów (bez kropek).
10. **Provenance + rollback z prawdą**: `provenance=import` + wypełniane `provenance_meta`. Twardy
    kontrakt: **`import_session_id` na obiekcie znaczy wyłącznie „utworzony przez sesję"** (stampowany
    tylko na `new CatalogObject`) — inaczej rollback-delete sesji upsert skasowałby istniejący katalog
    klienta. Obiekty aktualizowane śledzone w undo-logu. Rollback sesji upsert = delete(created)
    + replay(updated) w jednej transakcji, z preview obu kubełków; po rollbacku ZAWSZE rebuild
    indeksów + reindex/delete Meilisearch (już dziś delete-rollback zostawia ghost-documents w Meili).
11. **Mapping memory**: sygnatura nagłówków → auto-podpowiedź zapisanego profilu. Profile współdzielone
    w tenancie, gated RBAC, z wersjonowanym formatem i migracją istniejących.
12. **Zero fałszywych affordancji**: każdy element z sekcji 2.7 albo zaczyna działać, albo znika —
    poczynając od etapu 0 (quick-winy), nie od etapu 3.

---

## 4. Architektura docelowa

### 4.1 Przepływ

```
Upload (1×, staged MinIO, TTL 24h)
  → Detekcja (encoding/delimiter/quote/sheet/header-row + sample)
  → Mapowanie (auto-map: słownik+Levenshtein+aliasy; gramatyka code.locale.channel;
               profil/mapping-memory; transformacje per kolumna)
  → Dry-run DWUPOZIOMOWY (sync na próbce ~1000 wierszy w wizardzie; pełny jako async run
               z flagą dryRun — wyniki przez Mercure + plik odrzutów)
               kubełki: utworzy / zaktualizuje / pominie / błędy / WYCZYŚCI (próg ostrzegawczy)
  → Run (async Messenger, osobny transport `import` z workerem w dev i prod; chunk 200:
         pre-check setowy → ImportValueWriter → flushAndClear; BulkContext ON;
         checkpoint offsetu + fazy, świadomy redelivery; pass 2: relacje + warianty)
  → Raport (created/updated/skipped/errors; pełny CSV streamowany; plik odrzutów)
  → Rollback (created: delete + rebuild; updated: undo-log replay z guardem provenance/updated_at;
              zawsze: rebuild attributes_indexed + completeness + reindex/delete Meili)
```

Ścieżka inline (sync) **zostaje** dla małych plików — próg po WIERSZACH (≤50), nie bajtach — żeby dev
bez workera i testy działały bez tarcia.

### 4.2 Komponenty nowe / przebudowane

| Komponent | Rola | Los istniejącego kodu |
|---|---|---|
| `Catalog\Application\ValueWriteCore` (nazwa robocza) | współdzielony rdzeń: normalizacja envelope per typ (Attribute-aware), walidatory per-typ, identifier pre-check, primary-locale→global | wyekstrahowany z `ObjectAttributesUpserter`; Upserter staje się jego cienkim klientem |
| `ImportValueWriter` | batch-klient rdzenia: result-based (zbiera błędy zamiast rzucać HTTP-exceptions), persist-only + flush w chunku, prefetch AttributeOption/kategorii/SKU **+ istniejących ObjectValues** per chunk | zastępuje logikę envelope w `ImportObjectCreator` |
| `ObjectResolver` | match po kluczu profilu, decyzja create/update/skip per tryb | nowy |
| `ImportColumnGrammar` | parsowanie `code[.locale][.channel]` z rejestrem tenanta + precedencją | zastępuje `ColumnHeader` |
| `RowValidator` v2 | wszystkie błędy wiersza naraz, severity, walidacja per 17 typów | przebudowa `ImportValidationService` |
| `StagedFile` | jeden upload, reuse między krokami, TTL cleanup, tenant-prefiks w MinIO + tenant_id | nowy |
| Streaming readers | openspout XLSX + league/csv stream, header-row offset, sheet select, wartości jako stringi | zastępuje `ImportRowReader`/`FileParserService` full-load |
| `ImportUndoLog` | **log operacji** (value_overwritten / value_created / category_set / relation_created / object_field_changed), nie tylko before-envelopes; tenant_id NOT NULL + RLS + retencja (purge po zamknięciu okna rollbacku) | nowy; rozszerza `ImportRollbackService` |
| `TransformPipeline` | deklaratywne operacje per kolumna (JSONB w profilu) | nowy (etap 3) |
| Pass 2: `RelationImportStep` | relacje (`ObjectRelation`) + parent_sku po zapisaniu obiektów; bufor krotek in-memory (OK do 200k), checkpoint rejestruje fazę | nowy |

### 4.3 Zmiany po stronie EKSPORTU (round-trip to kontrakt dwustronny — każda ma ticket)

- notacja `code.locale.channel` + reguła precedencji — **ticket 1.6**,
- weryfikacja fan-outu global→locale (#1146) — **ticket 1.6**,
- `include_variants` przestaje być no-opem (fan-out wierszy wariantów z `parent_sku`) — **ticket 1.8**
  (ścieżka krytyczna golden testu wariantów),
- fallback `{value}` w `ValueSerializer` dla select na okres przejściowy migracji shape'ów — **ticket 1.2**,
- kolumna kanału, której nie da się zresolwować = **błąd preflight eksportu**, nie cicha pusta kolumna
  (R-47 ochrona przed `clear_if_empty` na pliku z błędu) — **ticket 1.6**,
- `SyncExportRunner`: iteracja + `clear()` per chunk zamiast materializacji całego zbioru — **ticket 2.6**,
- sanityzacja CSV injection przy eksporcie — **ticket 2.8**.

### 4.4 Decyzje kontraktowe (proponowane defaulty)

| # | Decyzja | Rekomendacja |
|---|---|---|
| D1 | Klucz dopasowania | `objects.code` (SKU) default; opcjonalnie atrybut typu `identifier` per profil. Case-sensitive + trim. Duplikat w pliku: skip kolejnych wystąpień + warning (pre-flight, nie constraint DB) |
| D2 | Pusta komórka | nie ruszaj; `clear_if_empty` opt-in per kolumna **(do UI dopiero po domknięciu migracji D7** — legacy select eksportuje '' mimo istniejącej wartości**)**; próg ostrzegawczy w dry-run (>20% niepustych wartości do skasowania = explicit confirm); kolekcje: `replace` default przy obecnej kolumnie, `append` opt-in |
| D3 | Tryby | `upsert` default; `create`/`update` wybieralne; MERGE/INCREMENT/DELETE usunięte z enum **+ migracja danych** (`UPDATE import_profiles SET mode=...`) — inaczej hydracja Doctrine rzuci ValueError |
| D4 | Round-trip scope (baseline) | wartości 17 typów + multi-kategorie + status/enabled + warianty + relacje + asset_id (same-tenant). **Importy strukturalne OUT** — osobny epik |
| D5 | Cross-tenant/środowisko | relacje i select po `code` (resolve), assety same-tenant po `asset_id` (URL/path-resolve = etap media) |
| D6 | Select: nieznany option_code | reject z błędem; auto-create opcji opt-in per profil (raport „utworzono N opcji"; rollback ich NIE usuwa — komunikowane w preview) |
| D7 | Legacy shape'y JSONB | **dedykowany ticket 1.2 na początku etapu 1**: kanon w ADR, Attribute-aware `wrapValue`, migracja SQL + rebuild + reindex, FE `unwrapAttributesIndexed` + audyt read-paths, fallback w ValueSerializer, golden test z seedowanymi legacy-shape'ami |
| D8 | Sync/async | osobny transport `import` (doctrine, queue_name) **+ worker w docker-compose.yml dev i prod** + `redeliver_timeout` > max czas importu; inline sync zostaje dla ≤50 wierszy; dry-run dwupoziomowy (sync próbka ~1000 / pełny async) |
| D9 | Profile | **per-user (decyzja operatora 2026-06-12** — tenant-shared odroczone, „na razie nie ma sensu"**)**; `columnMapping` v2 wersjonowany + czytnik back-compat dla istniejących profili |
| D10 | Limity | max 100 MB / 200k wierszy per plik (konfig per tenant); Allowed-Errors próg % opcjonalny per profil (default off) |
| D11 | Równoległość | `import_session_id` = wyłącznie marker created-by; last-writer-wins na `object_values` udokumentowane + guard undo-logu (provenance/updated_at); `BulkEditController` też akwiruje `BulkOperationLock`; `RebuildAttributesIndexedHandler` łapie `OptimisticLockException` per-id (nie wali batcha); undo-log idempotentny przy resume (first-write-wins per session+value) |
| D12 | Tożsamość kolumny | mapping kluczowany **indeksem kolumny** (+ nazwa jako display/auto-match); duplikaty i puste nagłówki legalne (benchmark Bosch/Avapax); wiele kolumn może wskazywać ten sam atrybut multi-value (append) |
| D13 | Legacy `.xls` | wspierany read-only przez PhpSpreadsheet Xls, limit 20 MB, bez streamingu (jawny wyjątek od 2.1); pliki z uszkodzonym CDF testowane na benchmarku `products_export_*.xls`; `.xlsm`/`.xlsb` nadal reject z komunikatem „zapisz jako .xlsx" |
| D14 | Multi-sheet | MVP: wybór JEDNEGO arkusza per sesja + profil per arkusz (Avapax = 4 przebiegi, Annex E = 15); wieloarkuszowa sesja „import całego skoroszytu" → Faza 2 (po pierwszym realnym żądaniu) |

---

## 5. Etapy realizacji

### ETAP 0 — Quick-winy (natychmiast, przed etapem 1) — ~3–5 h

Tryb SKILL-BUG-FIX-TICKET, bugi widoczne dziś:
(a) `report.csv` + eksport profilu przez fetch z Authorization + blob (wzorzec z exports),
(b) topic Mercure z konfiguracji zamiast hardcoded `pim.localhost` (FE hook + default DI publisherów),
(c) ukrycie przycisków pauza/resume/cancel do czasu ticketu 2.3 (dziś AKTYWNIE SZKODLIWE — pauza
kończy sesję jako FAILED). Zasada „zero fałszywych affordancji" stosowana od razu.

### ETAP 1 — Round-trip baseline + media (non-negotiable gate) — ~105–150 h

Cel: **golden test przechodzi**. Podzielony na dwie fale, żeby pierwszy testowalny rezultat był
wcześnie (golden v0 po fali A, ~50–65 h), a nie po całym etapie.

**Fala A — silnik (golden v0):**

| # | Ticket (robocze) | Zakres | Est. |
|---|---|---|---|
| 1.1 | ADR Import v2 | tryby, klucz matcha, semantyka komórek (D1–D11), gramatyka kolumn z precedencją, **kanon shape'ów JSONB**, umiejscowienie writera (Catalog\Application + Contracts) + **warstwy Import/Export w deptrac.yaml**, limity dry-run, reguły normalizacji golden testu (wersjonowane, minimalne — docelowo byte-equality); aktualizacja `jsonb-schemas.md` + karta prawdy dla NUI-10 | 5–7 h |
| 1.2 | **Migracja legacy shape'ów JSONB (D7)** | kanon per typ; Attribute-aware `wrapValue` w Upserterze; fix GenerateVariants (osie select `{option_code}`); migracja SQL `object_values` + obowiązkowy rebuild `attributes_indexed` + reindex Meili; FE `unwrapAttributesIndexed` + audyt read-paths (DocumentFlattener/VisibleWhenRuleEvaluator czytają oba shape'y — wycofać wzorzec defensywny); fallback `{value}` w ValueSerializer (select) na okres przejściowy. **Wymaga dump dev DB przed migracją** | 10–16 h |
| 1.3 | `ObjectResolver` + tryby create/update/upsert | realny upsert; wybór trybu w API+wizardzie+profilu; pre-flight dedup identyfikatorów w pliku; kontrakt `import_session_id` = created-by only (D11); usunięcie hardcoded `mode='UPDATE'`; redukcja enum **+ migracja `import_profiles.mode`** | 10–14 h |
| 1.4 | Ekstrakcja rdzenia + `ImportValueWriter` | wyciągnięcie współdzielonego rdzenia z `ObjectAttributesUpserter` (normalizacja + AttributeValueValidator + IdentifierUniquenessValidator + primary-locale→global) do Catalog\Application za kontraktem; `ImportValueWriter` result-based, persist-only, flush w chunku; prefetch AttributeOption/kategorii/SKU **+ istniejących ObjectValues per chunk** (jedno zapytanie object×attribute×locale×channel); required per `Attribute::isRequired` zamiast hardcoded | 18–24 h |
| 1.5 | **Golden test v0** | realny `SyncExportRunner` → CSV → import z persystencją → równość envelope: wartości (17 typów, w tym seedowane legacy-shape'y) + kategorie + status, global+locale. Od tego ticketu każdy kolejny rozszerza matrycę w swoich kryteriach akceptacji | 4–6 h |
| 1.6a | Infra async | transport `import` (doctrine, queue_name) + serwis workera w `docker-compose.yml` (dev) i prod; `redeliver_timeout` > max czas importu; checkpoint świadomy redelivery; inline ≤50 wierszy (po wierszach, nie bajtach) | 3–5 h |

**Fala B — pełna matryca:**

| # | Ticket | Zakres | Est. |
|---|---|---|---|
| 1.6 | Gramatyka kolumn + kanały | `ImportColumnGrammar` z rejestrem + precedencją; zapis `channelId` na ObjectValue; eksport: `code.locale.channel`, weryfikacja fan-out #1146, niezresolwowany kanał = błąd preflight (R-47); walidacja kodu kanału vs locale przy tworzeniu kanału | 9–13 h |
| 1.7 | Multi-kategorie + status/enabled | pipe-split kategorii, primary+position; import `status`/`enabled` z jawnych kolumn (out z SystemColumn) | 4–6 h |
| 1.8 | Warianty + relacje (pass 2) + fan-out eksportu | parent_sku two-pass (bufor in-memory, checkpoint z fazą), variant_axes; `ObjectRelation` (ADR-014) z resolve po code (tenant-scoped, test izolacji); galerie: split pipe asset_ids + walidacja istnienia (tenant-scoped); **eksport: `include_variants` fan-out wierszy wariantów** | 14–20 h |
| 1.9 | Izolacja błędów + maszyna stanów | setowe pre-checki w chunku; degradacja per-row przy błędzie flusha; partial zamiast markFailed; semantyka severity (warning nie blokuje) | 6–8 h |
| 1.10 | Golden test — pełna matryca | XLSX + kanały + warianty + relacje + multi-kategorie; matryca wyprowadzona z realnych reguł scope'owania (nie kartezjan „17×4"); testy async `ImportRunHandler`; testy per-typ persystencji; test izolacji błędu wiersza | 8–12 h |
| 1.11 | Higiena backlogu | dedupe #598–601 vs #602–605, zamknięcie de-facto-done IMP-17, **re-audit close-comentu #1130** (precedens closed-but-broken), korekta docblocków/spec v1 | 1–2 h |
| 1.12 | Media: URL download *(przeniesione z etapu 4 — decyzja operatora 2026-06-12)* | async worker pobierania zdjęć z URL-i w kolumnach (concurrency cap, timeout, redirect limit, content-type sniff, dedupe po `content_hash`), `AssetUrlResolver` (IMP-18 #600/#604: URL/ścieżka → asset, tenant-scoped), liczniki `imagesDownloaded/Failed` zaczynają działać; obsługa list URL w jednej komórce (split) | 8–12 h |
| 1.13 | Media: ZIP *(przeniesione z etapu 4)* | upload ZIP w wizardzie (realny — dziś `zipFile` nie opuszcza przeglądarki), ekstrakcja streamem (nie do RAM), ścieżki względne w kolumnach, case-insensitive match, sanityzacja nazw, limit 500 MB | 6–8 h |

**Gate etapu 1**: golden testy zielone w CI + live-stack smoke (eksport realnego katalogu → edycja
3 wartości + 1 nowy wiersz + 1 pusta komórka w Excelu → import → poprawny diff w UI, brak czerwonych
błędów w konsoli) **z artefaktem dowodu w issue** (HTTP code + body / screenshot — CLOSED MEANS CLOSED).
Dogfooding wstępny na benchmarkach (decyzja operatora: pliki z sekcji 6 zastępują eksport IdoSell):
trio Bosch — `bosch-09-01-2026.csv` (create), `bosch-…-nazwy.csv` (UPDATE po EAN), `bosch-…-param.csv`
(UPDATE parametrów, mapping ręczny w istniejącym wizardzie). Pełna suite klasy A = gate etapu 3.

### ETAP 2 — Odporność i ochrona bazy — ~58–80 h

| # | Ticket | Zakres | Est. |
|---|---|---|---|
| 2.1 | Streaming readers | openspout XLSX row-iterator + league/csv stream (też w parse-preview!); deduplikacja nagłówków; XLSX wartości jako stringi (lekcja Akeneo PIM-10167) | 8–10 h |
| 2.2 | Staged upload 1× | `staged_file_id` z parse-preview (tenant-prefiks MinIO + tenant_id + RLS), reuse w dry-run/run; TTL cleanup (nie cross-tenant); debounce dry-run w FE | 5–7 h |
| 2.3 | Pause/cancel/resume realne | poll statusu między chunkami; graceful stop; checkpoint offsetu+fazy w sesji; resume idempotentny (liczniki z import_logs, undo-log first-write-wins); lock TTL renewal (import >1h); naprawa LogicException; odsłonięcie przycisków z etapu 0 | 7–9 h |
| 2.4 | Undo-log + rollback v2 | log operacji (value_overwritten/value_created/category_set/relation_created/object_field_changed), tenant_id NOT NULL + RLS + ORM default; rollback sesji upsert = delete(created)+replay(updated) w transakcji pod BulkOperationLock; w OBU trybach: rebuild attributes_indexed + completeness + reindex/**delete** Meili (fix istniejących ghost-docs); preview zakresu (oba kubełki + osierocone warianty parent SET NULL + nieusuwalne auto-opcje D6); retencja: purge po zamknięciu okna; guard provenance/updated_at (pomija ręczne edycje po imporcie, z raportem) | 12–18 h |
| 2.5 | RLS + tenant | `tenant_id` na `import_logs` (z backfill + ORM default — lekcja NOT NULL); GUC `app.current_tenant` w workerach Messenger (gotowość FORCE RLS) | 3–5 h |
| 2.6 | Bulk-path wydajność + benchmark | BulkContext ON + async rebuild + batch reindex Meili; Mercure per chunk; compare-values diff (decyzja: równa wartość z innym provenance ≠ no-op dla audytu — rozstrzygnięcie w ADR); `SyncExportRunner` iteracja+clear; **benchmark 5k/50k z asercją RAM <256 MB jako test** | 10–14 h |
| 2.7 | Limity i guardraile | max rows/size per profil/tenant; rate limit sesji; raport CSV przez StreamedResponse | 3–5 h |
| 2.8 | Security plików | zip-bomb guard XLSX; whitelist ścieżek folder-probe; CSV injection sanityzacja w eksporcie; spójny limit body w Caddy | 4–6 h |
| 2.9 | Concurrency matrix | `BulkEditController` pod BulkOperationLock; `RebuildAttributesIndexedHandler` z obsługą OptimisticLockException per-id; polityka retry/delay dla kontencji locka async (dead-letter guard); dokumentacja macierzy konfliktów (D11) | 4–6 h |
| 2.10 | Backup: `do_backup` | spiąć checkbox z modułem Backup (rekomendacja — koszt mały, moduł istnieje) | 2–3 h |

**Gate etapu 2**: benchmark 50k < 256 MB RAM w CI + smoke pauza/resume/rollback-upsert na żywym imporcie.

### ETAP 3 — Kreator importu dowolnych plików (NUI-09/10/11 na realnym silniku) — ~82–120 h

NUI-10 i NUI-11 zostają **wycofane z backlogu marathonu NUI i wchłonięte przez ten etap** (aktualizacja
`plan-epik-NUI-retrofit-ui-v2.md`); NUI-09 (hub, w toku, ortogonalny do silnika) merguje się jak jest —
w 3.7 zostaje tylko delta liczników/statusów. ~30–44 h z tego etapu to budżet przeniesiony z marathonu
NUI, nie nowy koszt.

| # | Ticket | Zakres | Est. |
|---|---|---|---|
| 3.1 | Detekcja rozszerzona | header-row offset (heurystyka + override) **+ osobny „wiersz startu danych"** (drugi, opisowy wiersz nagłówka — Avapax/Tubądzin) **+ wiersze sekcji wewnątrz danych do pominięcia** (e-commerce.xlsx); wybór arkusza XLSX; **para separatorów decimal+thousands** (formaty niemieckie „10,200 KG" / „1.000 µm" — Annex E); format dat; quote char; **legacy .xls** (PhpSpreadsheet Xls fallback ≤20 MB — test obowiązkowo na `products_export_*.xls`, który ma niestandardowy CDF) | 9–13 h |
| 3.2 | Schemat importu z OT | `GET /api/object-types/{id}/import-schema` (typy, constraints, required, opcje, aliasy); regeneracja OpenAPI + shared-types w DoD | 4–6 h |
| 3.3 | Mapping UI v2 | **mapping kluczowany INDEKSEM kolumny, nie nazwą nagłówka** (duplikaty `foto`×8 i puste nagłówki — Bosch/Avapax; format columnMapping v2 z D9); **wiele kolumn → jeden atrybut multi-value** (append; galerie, serie); combobox filtrowany po OT bez capa 200; sample values; wymiar locale/channel per kolumna + **wzorce sufiksów locale w auto-map** (`(pl)`, `_En`, `[pol]`, `(DE)`); confidence z hurtowym zatwierdzaniem; wybór ObjectType; **bulk „utwórz atrybuty z niezmapowanych kolumn"** (typ zgadywany z próbek, multi-select zamiast 20× deep-link) | 12–17 h |
| 3.4 | Transformacje per kolumna (MVP) | pipeline JSONB: trim (default), split, find&replace, value-map→option_code, format liczb/dat, concat (w tym **wiele kolumn → ścieżka kategorii**), **lista null-markerów** (`---`, `N/D`, `brak`…), **json_extract(key)** dla JSON-in-cell (edito: `{"pl":…,"en":…}`), **źródło „stała wartość" / „nazwa arkusza"** (Annex E: arkusz = rodzina produktowa); preview wyniku na próbkach; reszta operatorów jawnie SKIP | 10–14 h |
| 3.5 | Profile v2 + mapping memory | zapis profilu z wizarda (działający!), aplikowanie profilu (prefill), header-signature → auto-podpowiedź (per-user, D9), wersjonowanie + migracja formatu | 6–8 h |
| 3.6 | Dry-run v2 | dwupoziomowy (sync próbka / pełny async); kubełki utworzy/zaktualizuje/pominie/błędy + **wyczyści** (próg ostrzegawczy D2) + diff wartości dla update; plik odrzutów do re-importu | 8–10 h |
| 3.7 | Wizard 6 kroków + sesja v2 (eks-NUI-10/11) + hub delta | widoki na nowym silniku wg makiet; karta prawdy z 1.1; pozostałe FE fixy (stale suggestions, refetch listy sesji, i18n hardcoded PL, martwy kod) | 30–44 h |
| 3.8 | Template generator | `GET .../import-template?locale=` — XLSX z labelami, przykładem, walidacją Excela dla selectów, required | 3–5 h |

**Gate etapu 3 (benchmark-driven)**: pliki klasy A z suite benchmarków (sekcja 6) importują się
przez kreator na pim.localhost bez ręcznego pre-processingu, z artefaktem dowodu per plik
+ pełny dogfooding IdoSell bez pre-processingu.

### ETAP 4 — Operacjonalizacja (feedy, notyfikacje) — ~26–36 h — **na żądanie / po pierwszym pilocie**

*(media 4.3/4.4 przeniesione do etapu 1 jako 1.12/1.13 — decyzja operatora 2026-06-12)*

| # | Ticket | Zakres | Est. |
|---|---|---|---|
| 4.1 | Harmonogramy realne | cron tick (Symfony Scheduler), run-now → realna ImportSession, `ScheduleRun.sessionId`, notyfikacja wyniku | 6–8 h |
| 4.2 | Źródła realne lub uczciwe | driver SFTP/HTTP przez Flysystem + polling + ad-hoc run; typy bez drivera → health `off` | 8–12 h |
| 4.3 | Content-hash skip-unchanged | hash kanonicznego JSONa zmapowanych wartości **+ fingerprint schematu** (schema_version + hash completeness_rules — inaczej skip zamrozi completeness po zmianie reguł); „wymuś pełny przebieg" bypassuje oba poziomy skipu; mini-ticket: zmiana completeness_rules dispatchuje rebuild | 6–8 h |
| 4.4 | Notyfikacje + telemetria | email po imporcie >5 min (mailer z PR #790), inbox/dzwoneczek (à la ExportsLiveBridge), metryki Prometheus, phase_timestamps | 6–8 h |

### Podsumowanie kosztów

| Pakiet | Zakres | Est. |
|---|---|---|
| **Rdzeń** (etapy 0+1+2) | „import działa, nie kłamie i nie wywala bazy" + media | **~166–235 h** |
| Etap 3 | kreator dowolnych plików + UI NUI (w tym ~30–44 h przeniesione z budżetu marathonu NUI) + benchmarki klasy A | ~82–120 h |
| Etap 4 | feedy/notyfikacje — na żądanie | ~26–36 h |
| **Razem** | | ~274–391 h (realnie nowego budżetu: ~244–347 h) |

Kolejność: 0 → 1A → 1B → 2 → 3 → 4. Ticket 3.7 może ruszyć równolegle z etapem 2 **tylko** po
zamrożeniu w 1.1 karty prawdy, semantyki komórek (D2) i kubełków dry-run; tickety 2.2/2.3 dotykają
tych samych ekranów — ownership po stronie 3.7.

---

## 6. Suite benchmarków operatora — `Zrodla/Importy przykładowe` (2026-06-12)

14 realnych plików od dostawców/z systemów operatora. Cel: **każdy plik klasy A da się zaimportować
przez kreator po odpowiednim mapowaniu** (gate etapu 3). Klasy: **A** = musi się importować end-to-end;
**B** = wydajność/odporność (parser nie może paść, sensowna degradacja); **C** = stress-test detekcji
(graceful degradation, pełne wsparcie poza zakresem).

| Plik | Klasa | Co testuje (unikalne cechy) | Pokrycie |
|---|---|---|---|
| `bosch-09-01-2026.csv` | A | format IdoSell: nagłówki XPath `/description/name[pol]`, TAB+quotes, **duplikaty i PUSTE nagłówki**, kolumny bez nagłówka | 3.3 (mapping po indeksie), 3.1 |
| `bosch-…-param.csv` | A | **UPDATE po EAN** (match po atrybucie identifier, nie SKU); nagłówki = polskie nazwy atrybutów; `N/D` jako null; zakresy „3,3-10,2" | 1.3 (D1), 3.3, 3.4 (null-markery) |
| `bosch-…-nazwy.csv` | A | minimalny partial update: 2 kolumny (EAN + nazwa) — tylko obecne kolumny dotykane | 1.3 + D2 (partial update) |
| `bosch-09-01-2026.xlsx` | A | te same dane jako XLSX, kolumny bez nagłówków, kategorie 2-poziomowe w osobnych kolumnach | 3.1, 3.4 (concat→kategoria) |
| `Avapax nowości…xlsx` / `Tubądzin MBL…xlsx` | A | szablon hurtowni: **2 wiersze nagłówka** (techniczny `cecha:15` + opisowy), dane od w. 3; **4 arkusze o tych samych produktach** (atrybuty/serie/ceny/pliki — import per arkusz z osobnym profilem, match po `kod`); `foto`×8 → **multi-value append**; zdjęcia jako nazwy plików bez URL (transform prefix); jednostki `m2`/`szt.`; EAN-y | 3.1 (data-start-row, sheet picker), 3.3 (indeks, multi-col), 3.4, 4.3 (media) |
| `CENNIK_KOSPEL…xlsx` | A | **wiersz tytułu NAD nagłówkiem**; wartości wieloliniowe w komórkach; EAN z trailing spaces; jednostki w nagłówkach `[zł]`/`(mm)`; ceny bez waluty w komórce | 3.1 (header offset), 3.4 (trim), automap (strip jednostek) |
| `e-commerce.xlsx` | A | nagłówek w w. 1, ale **wiersze sekcji WEWNĄTRZ danych** („POMPY CIEPŁA") — skip-row reguła lub raport per wiersz | 3.1, 1.9 (wiersz błędny ≠ abort) |
| `Format danych.xlsx` | A | książki (ISBN jako identifier), arkusz `Kategorie` = **drzewo kategorii w kolumnach-wcięciach** (import struktur — out of scope, ręcznie), arkusz legacy | 3.1, D1 (identifier=ISBN); drzewo → epik struktur |
| `products_export_…xls` | A | **legacy .xls (BIFF) z niestandardowym CDF** (xlrd wymaga ignore-corruption!); 59 kolumn; locale jako `(pl)/(en)/(ru)/(de)`; **JSON-in-cell** `{"pl":…}`; `---` jako null; Excelowe floaty na kodach (`123456789.0`); listy URL zdjęć w jednej komórce | 3.1 (.xls), 3.3 (sufiksy locale), 3.4 (json_extract, null-markery), 2.1 (wartości jako stringi) |
| `Annex E…xlsx` (677 KB) | A− | **15 arkuszy = rodziny produktowe** (różne zestawy kolumn per arkusz; nazwa arkusza → kategoria/stała); niemieckie formaty: „10,200 KG" (przecinek dziesiętny), „1.000 µm" (kropka tysięcy!); composite value+unit; DE/EN nazwy w 2 kolumnach | 3.1 (separatory!), 3.4 (stała z arkusza), import per arkusz |
| `GA_List.csv` (10 MB, 90k wierszy) | B | wydajność parse/dry-run/preview na dużym pliku; kolumny `_En`/`_De`; to **słownik atrybutów** (schema), nie produkty — merytorycznie materiał na import strukturalny (osobny epik) | 2.1/2.6 (benchmark), 3.3 (sufiksy) |
| `990.csv` | B | semicolon, wszystkie pola quoted, escaped quotes `""…""`, bardzo długie teksty; **język jako KOLUMNA wartości** (LangNo) — wariant row-locale poza zakresem | 2.1 (parser robustness) |
| `all-doctors-details.xlsx` | C | nagłówki wielowierszowe scalane grupami + **wiersze kontynuacji** rekordu + puste wiersze — heurystyka header-row musi zgłosić niską pewność i dać ręczny wybór; scalanie kontynuacji POZA zakresem | 3.1 (graceful) |

Wnioski wbudowane w plan (delta po analizie): mapping po **indeksie kolumny** zamiast nazwy nagłówka
(D12), wiele kolumn → jeden atrybut multi-value, osobny wiersz startu danych + skip-rows, para
separatorów decimal/thousands, lista null-markerów, `json_extract`, źródło „stała wartość/nazwa
arkusza", wsparcie legacy `.xls` (D13), wzorce sufiksów locale w auto-map, bulk-create atrybutów
z niezmapowanych kolumn. Trio Bosch potwierdza decyzje D1 (match po identifier/EAN) i D2 (partial
update). Pliki zostają w `Zrodla/Importy przykładowe` jako manualna checklista benchmarków (dane
komercyjne — NIE commitujemy ich do testów); do testów automatycznych powstają zanonimizowane
mini-fixtures odtwarzające każdą cechę strukturalną z tabeli.

---

## 7. Co świadomie POZA zakresem v2

- **Importy strukturalne** (module_schema / attributes_groups / categories jako import) — osobny epik
  „configuration as code" (przyda się przy migracjach z PIMCore).
- **Required per ObjectType** — dziś required jest globalne na `Attribute::isRequired`; per-OT wymaga
  zmiany modelu Catalog (junction + migracja + UI modelowania + Upserter) — osobny ticket Catalog,
  jeśli operator potrzebuje.
- **AI auto-mapping** — słownik+Levenshtein+aliasy wystarczą; LLM-mapping to tool agent layer (Faza 2).
- **Kolumna `_command` per wiersz** (Matrixify) — tryb per sesję wystarcza.
- **Tryb REPLACE** (destrukcyjny) — celowo nie istnieje.
- **Formaty JSON/XML** — architektura readerów gotowa; dowozimy przy pierwszym realnym feedzie.
- **Staging table COPY** — dopiero gdy benchmark pokaże, że streaming + chunked ORM nie wystarcza.
- **Język jako kolumna wartości** (row-locale, `990.csv` LangNo) — wariant spotykany w feedach
  TecDoc; obejście: filtr wierszy per import. Pełne wsparcie → Faza 2.
- **Scalanie wierszy kontynuacji** (`all-doctors-details.xlsx`) — heurystyka header-row musi jedynie
  zgłosić niską pewność i oddać ręczny wybór; sklejanie rekordów wielowierszowych poza zakresem.
- **Import drzewa kategorii z arkusza** (`Format danych.xlsx` → `Kategorie` w kolumnach-wcięciach)
  — należy do epiku importów strukturalnych, razem z GA_List jako importem słownika atrybutów.
- **Wieloarkuszowa sesja importu** (cały skoroszyt na raz) — D14, Faza 2; MVP = przebieg per arkusz.

## 8. Kryteria akceptacji całości (CLOSED MEANS CLOSED)

1. **Golden round-trip** w CI: eksport→import→równość envelope (reguły normalizacji wersjonowane
   w ADR, minimalne) dla wszystkich typów × realne kombinacje scope (z reguł scope'owania, nie
   kartezjan) × CSV/XLSX + kategorie/warianty/relacje/status + seedowane legacy shape'y.
2. **Live-stack smoke z artefaktem dowodu** w issue-close: eksport realnego katalogu → edycja w Excelu
   (zmiana wartości, nowy wiersz, pusta komórka) → import → poprawny diff w UI, zero czerwonych
   błędów w konsoli.
3. **Dogfooding**: pliki benchmarkowe z sekcji 6 (decyzja operatora 2026-06-12 — zastępują eksport
   IdoSell z US-IMP-005); wstępnie trio Bosch na gate etapu 1, pełna klasa A na gate etapu 3.
4. **Benchmark**: 50k wierszy < 256 MB RAM workera, bez OOM, linearny progres — jako test w CI.
5. **Zero fałszywych affordancji**: przegląd sekcji 2.7 — każda pozycja działa albo nie istnieje.
6. **Benchmark suite**: każdy plik klasy A z sekcji 6 zaimportowany przez kreator na pim.localhost
   (artefakt dowodu per plik: liczniki sesji + screenshot produktów); pliki klasy B przechodzą
   parse/dry-run bez OOM; plik klasy C dostaje czytelną degradację zamiast crasha.

## 9. Decyzje operatora (2026-06-12 — plan ZAAKCEPTOWANY)

1. **Zakres baseline (D4)**: warianty + relacje **w etapie 1B** — potwierdzone.
2. **Kolejność wykonania**: operator decyduje przy zlecaniu; **wszystkie tickety (etapy 0–4)
   rozpisane z góry** w GitHub. Tickety NUI-10/#1429 i NUI-11/#1430 pokrywają się zakresem z 3.7 —
   przy zleceniu 3.7 stare tickety NUI zostaną zamknięte jako superseded (do tego czasu zostają open).
3. **Dogfooding**: pliki benchmarkowe z `Zrodla/Importy przykładowe` zastępują eksport IdoSell.
4. **Migracja D7 (legacy JSONB)**: zaakceptowana — dump dev DB przed operacją (polityka recovery).
5. **Profile**: zostają **per-user** — tenant-shared odrzucone („na razie nie ma sensu").
6. **Media**: **od razu w etapie 1** — tickety 1.12 (URL download) i 1.13 (ZIP).
7. **Klasyfikacja benchmarków A/B/C**: zatwierdzona bez zmian.
8. **Budżet**: zaakceptowany — kryterium nadrzędne: „ma być dobrze zrobione". Wymóg operatora dla
   ticketów: szczegółowy opis z sekcją nietechniczną, jednoznaczny zakres, możliwość walidacji
   i przetestowania ticket-po-tickecie.

---

*Plik wersjonowany w `Project Plan/UI/`. Po akceptacji: tickety GitHub per etap (etap 0 + 1A w pierwszej
kolejności), aktualizacja `feature-imports.md` (banner „silnik superseded by v2"), aktualizacja
`plan-epik-NUI-retrofit-ui-v2.md` (NUI-10/11 → import v2 etap 3), korekta NUI-10 (karta prawdy wg ADR 1.1).*
