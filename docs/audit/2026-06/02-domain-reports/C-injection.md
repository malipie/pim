# Domena C — Injection i walidacja wejścia

Audyt adwersarski PIM, 2026-06-16. Postawa: każdy ścieżka traktowana jako podatna
dopóki empirycznie nie udowodniono inaczej. Każdy finding ma dowód (plik:linia,
cytat, output komendy lub odpowiedź serwisu).

## Metodyka — co i jak sprawdzono

### 1. SQL Injection (FilterDSL, ltree, JSONB path)
- Przeczytano `FilterDslResolver.php` w całości (kompilator DSL → SQL). Ustalono, że
  buduje **parameter-free SQL przez konkatenację literałów** (komentarz w kodzie sam
  to przyznaje, linia 295-299: *„builds parameter-free SQL by inlining literal values…
  Future VIEW-10 will switch to PDO-bound parameters for safety"*).
- Prześledzono WSZYSTKICH konsumentów `toCountSql()` → wykonanie raw SQL:
  - `SyncExportRunner::resolveFilter` (`apps/api/src/Export/Application/Sync/SyncExportRunner.php:395`) — `fetchAllAssociative`
  - `SmartFilterPresetController::resolveCounts` (`:346`) — `executeQuery`
  - `ExportPreflightController::countFilter` (`:213-219`) — `fetchOne`, **DSL prosto z payloadu requestu, bez `validate()`**
- Empiryczna weryfikacja escapowania: skrypt PHP w kontenerze uruchomił `toCountSql`
  na 11 adwersarskich DSL-ach (apostrofy, backslash, IN-injection, attr-injection,
  unicode). Wygenerowany SQL zweryfikowano `EXPLAIN`-em na żywej bazie.
- Sprawdzono konfigurację Postgresa: `standard_conforming_strings=on`,
  `backslash_quote=safe_encoding`, server 16.13 — escapowanie `''` jest poprawne dla
  string literals w tym trybie.
- `is_numeric` edge cases (`scalarLiteral` emituje liczby UNQUOTED) — przetestowano 15
  wariantów; żaden niebezpieczny.
- ltree: przeczytano `MoveCategoryService`, `CategoryUsageController`,
  `CategoryDeleteGuard`, `CheckSchemaDriftHandler` — wszystkie ltree i path queries
  używają **bound params** `CAST(:path AS ltree)`.

### 2. Import plików (zip-bomb, path traversal, MIME, limity)
- `XlsxArchiveGuard` (metadata-only zip-bomb guard), `ZipImageExtractor`
  (`isUnsafePath` przeciw zip-slip), `FolderPathGuard` (realpath containment),
  `StartImportController`/`FileParserService` (limity, detekcja formatu).
- Detekcja formatu: **extension-based** (`.xlsx`/`.csv`), nie magic-byte. Zmitygowane
  przez XlsxArchiveGuard (wymusza poprawny ZIP) + parser OpenSpout (failuje na śmieci).
- Limity: `DEFAULT_MAX_ROWS=200_000`, `DEFAULT_MAX_FILE_BYTES=100MB`,
  `MAX_ZIP_BYTES=500MB`, image `MAX_BYTES=10MB`. Obecne i sensowne.

### 3. Eksport — formula injection
- Przeczytano `CsvStreamWriter::neutraliseFormula` i `XlsxStreamWriter::neutraliseFormula`.
- Empiryczny test: zapisano CSV z 8 adwersarskimi komórkami (`=cmd|calc`, `+`, `-`,
  `@SUM`, `=HYPERLINK`, leading-space). Potwierdzono prefiks TAB na triggerach.

### 4. XSS
- `rg dangerouslySetInnerHTML|innerHTML|v-html` w `apps/admin/src` → **jeden** trafienie
  (`wysiwyg-editor.tsx:125`), opakowane `DOMPurify.sanitize`.
- Serwerowa sanityzacja wysiwyg: `WysiwygValidator` — tylko `is_string` + `max_length`,
  **bez sanityzacji HTML** (świadoma decyzja, polega na DOMPurify na froncie).

### 5. SSRF
- `SsrfGuard` (pre-filter), wiring `import.ssrf_safe_http_client` =
  `NoPrivateNetworkHttpClient` wrapping `@http_client`, wstrzyknięty do
  `ImageDownloadHandler` (`services.yaml:242-249`). Backstop poprawny per lesson-memory.
- `ImageDownloadHandler`: guard wołany (`:237`), MAX_BYTES mid-stream (`:268`),
  MAX_REDIRECTS=3, timeout 30s.

### 6. Deserializacja
- `rg 'unserialize\(|eval\(|create_function|call_user_func|new \$|\$\$'` w `apps/api/src`
  → **0 trafień** w kodzie aplikacji. Brak dynamicznych nazw klas z inputu.

### 7. Walidacja JSONB envelope
- Przeczytano `ValueWriteCore`, `ObjectAttributesUpserter`, `ObjectValue` entity,
  `ValueSerializer` (eksport).
- Empiryczny test `ValueWriteCore::normalise` na 8 adwersarskich wartościach.

## Czego NIE dało się sprawdzić (luki audytu)
- **Meili injection end-to-end przez HTTP**: lokalny dataset ma rozjazd tenantów —
  zalogowany admin@demo (`019ebfbb-…7d36`) NIE ma zaindeksowanych docs (są pod
  `019ed034-…`). Każdy search dla admina zwraca 0 hits niezależnie od filtra, więc
  pozytywne potwierdzenie cross-read przez sam endpoint było niemożliwe. Dowód
  przeprowadzono na poziomie **dokładnie zrekonstruowanego wyrażenia filtra**
  (kod serwisu 1:1) uruchomionego na żywym Meili — patrz finding C-1.
- **Semgrep**: raw/semgrep.json = 0 findings, ale przebieg miał 2 TIMEOUT + 2 SYNTAX
  ERROR (niepełne pokrycie 2 plików admina) — nie traktowano jako autorytet, cały audyt
  manualny.
- **Integration webhooks SSRF** (`WebhookDeliveryClient` w ApiConfigurator) — pobieżnie,
  nie zweryfikowano czy używa NoPrivateNetwork. Poza głównym scope domeny (import URL),
  ale wymaga osobnego sprawdzenia.
- **Reszta readerów JSONB** (Meilisearch indexer `DocumentFlattener`, integracje
  Shopify/BaseLinker) — nie sprawdzono jak reagują na non-canonical envelope zapisany
  via finding C-3. ValueSerializer (eksport) jest odporny; inne readery nie audytowane.

---

## FINDINGS

### C-1 [CRITICAL] Meili filter injection przez niewalidowany klucz filtra → cross-tenant read

**Lokalizacja:** `apps/api/src/Search/Application/CatalogSearchService.php:81-93`
(reachable z `SearchController::run` `:124-150` przez `?filter[KEY]=VAL`,
oraz `BulkSelectionController`).

**Dowód kodu:**
```php
foreach ($filters as $key => $value) {
    ...
    $extraFilters[] = \sprintf('%s = "%s"', $key, addslashes((string) $value));
}
```
`$key` pochodzi z `?filter[<KEY>]` (`SearchController` linia 124 `$request->query->all('filter')`)
i jest wstawiany do wyrażenia filtra Meili **bez żadnej walidacji ani whitelistu**
(tylko `$facets` są filtrowane do `filterableAttributes`, linia 121; klucze `$filters` nie).
Wartość przez `addslashes`, ale to nie pomaga — dziurą jest klucz.

**Dowód empiryczny (żywy Meili, indeks `objects`, 689 docs):**
Zrekonstruowano DOKŁADNIE wyrażenie które buduje serwis (kod 1:1, linie 75-107) dla
atakującego w pustym tenancie `0000…` z kluczem `parentId IS NULL OR tenantId` i
wartością = tenant-ofiara `019ed034-…`:
```
tenantId = "00000000-0000-0000-0000-000000000000" AND kind = "product"
  AND parentId IS NULL OR tenantId = "019ed034-ff70-7bb8-8309-5afb97e9ec38"
```
Uruchomione na żywym Meili: `estimatedTotalHits = 1` (dokumenty INNEGO tenanta),
podczas gdy czysty scope `tenantId = "0000…" AND kind = "product"` → `0`. Operator
`OR` przekazany przez klucz znosi `AND`-scoping tenanta — **naruszenie izolacji
multi-tenant**.

**Scenariusz ataku:** Użytkownik tenanta A wysyła `GET /api/search/products?
filter[parentId IS NULL OR tenantId]=<id-tenanta-B>`. Klucz dociera niezmieniony do
serwisu (potwierdzone: endpoint zwraca HTTP 200, `processingTimeMs:3` = Meili
ewaluował wyrażenie), które buduje filtr łamiący scope → odczyt produktów tenanta B
(SKU, atrybuty, completeness — wszystko co w indeksie). To eskaluje do enumeracji
danych konkurencji w SaaS. Endpoint chroniony tylko `ROLE_USER` + `products:view`.

**Rekomendacja:** Whitelist kluczy filtra przeciw `filterableAttributes` (tak jak
robione dla `$facets`) i odrzucać wszystko spoza listy 400 BadRequest. Dodatkowo —
nie budować wyrażeń Meili przez `sprintf` z surowym kluczem; mapować klucz → znana
nazwa pola. `tenantId` filter powinien być wymuszony jako osobny, nie-mieszalny scope.

**Estymacja:** M (4-8h: whitelist + testy + przegląd BulkSelectionController).

---

### C-2 [MEDIUM] FilterDSL → SQL przez konkatenację literałów (bezpieczne TYLKO przy standard_conforming_strings=on)

**Lokalizacja:** `apps/api/src/Catalog/Application/Filter/FilterDslResolver.php`
(`literal()` `:637`, `scalarLiteral()` `:656`, `likeLiteral()` `:670`, `safeIdent()` `:627`);
wykonawcy: `SyncExportRunner.php:395`, `SmartFilterPresetController.php:346`,
`ExportPreflightController.php:219`.

**Dowód kodu:** kompilator buduje SQL przez konkatenację, escapując ręcznie:
```php
private function literal(mixed $value): string {
    ...
    if (\is_string($value)) { return "'".str_replace("'", "''", $value)."'"; }
}
```
Komentarz `:295-299` przyznaje: *„builds parameter-free SQL by inlining literal values…
Future VIEW-10 will switch to PDO-bound parameters for safety; for VIEW-09 the DSL flow
is admin-only"*. Nie jest już admin-only — `ExportPreflightController::countFilter`
(`:213`) bierze DSL **prosto z payloadu** (`parseFilter` → `$payload['filter']`),
woła `toCountSql` BEZ `validate()` i wstawia inline (linia 219).

**Dowód empiryczny — escaping TRZYMA przy obecnej konfiguracji:**
Probe na żywej bazie: wartość `x' OR '1'='1` skompilowała się do
`… = 'x'' OR ''1''=''1'` którą Postgres `EXPLAIN` interpretuje jako POJEDYNCZY literał
(`Filter: … = 'x'' OR ''1''=''1'::text`), nie injection. Backslash nie ucieka z literału
(`backslash_quote=safe_encoding`, `standard_conforming_strings=on`). `attr` injection →
`NULL` (blokuje `safeIdent` regex `^[a-zA-Z0-9_\-]+$`). `is_numeric` gate: tylko czyste
liczby emitowane unquoted, żaden wariant z `;`/quote/spacja-keyword nie przechodzi.

**Scenariusz awarii/ataku:** Dziś NIE wykonalny SQLi. ALE: (a) bezpieczeństwo zależy od
ustawienia serwera Postgres (`standard_conforming_strings=on` — default od 9.1, lecz
gdyby ktoś zmienił na `off`, escaping `''` przecieka przez `\'`); (b) brak parametryzacji
to długoterminowy dług — każda nowa gałąź kompilatora (nowy operator, nowa funkcja JSONB)
może wprowadzić dziurę bez ochrony bind-params; (c) `ExportPreflightController` pomija
`validate()`, więc tylko escaping `literal()` stoi między payloadem a SQL.

**Rekomendacja:** Przejść na PDO bind params (zapowiedziane „VIEW-10") albo minimum
egzekwować `standard_conforming_strings=on` w bootstrapie połączenia + dodać test
regresyjny tej konfiguracji. Wymusić `validate()` w `ExportPreflightController::countFilter`
przed `toCountSql`.

**Estymacja:** L (8-16h pełna parametryzacja) / S (1-2h: enforce setting + validate()).

---

### C-3 [MEDIUM] ValueWriteCore nie egzekwuje kontraktu envelope dla 12/17 typów — dowolny śmieć ląduje w object_values.value

**Lokalizacja:** `apps/api/src/Catalog/Application/ValueWriteCore.php:34-65` (`normalise`,
`VALUE_VALIDATED_TYPES`), `ObjectValue.php:74-90` (entity = dumb container, brak walidacji).

**Dowód kodu:** Tylko 5 typów jest format-walidowanych:
```php
private const array VALUE_VALIDATED_TYPES = [
    AttributeType::Email, AttributeType::Color, AttributeType::Identifier,
    AttributeType::Select, AttributeType::Multiselect,
];
```
`normalise()` dla dowolnego array tylko stringifikuje klucze i przepuszcza przez
`canonicalise` — **nie odrzuca nieznanych kluczy ani nie-skalarnych value**. Typy
text/textarea/wysiwyg/number/date/datetime/boolean/price/metric/asset/relation/
reference NIE mają walidacji formatu w `formatViolations`.

**Dowód empiryczny (`ValueWriteCore::normalise` w kontenerze):**
```
[text z zagniezdzonym obiektem]  normalised = {"value":{"deep":{"x":1}}}
[text z dodatkowymi smieci-keys] normalised = {"value":"ok","evil":"<script>alert(1)</script>","__proto__":"x"}
[number z stringiem nie-num]     normalised = {"value":"abc OR 1=1"}
[boolean z obiektem]             normalised = {"value":{"a":"b"}}
[price garbage]                  normalised = {"amount":"lol","currency":["x"]}
[wysiwyg z HTML/script]          normalised = {"value":"<img src=x onerror=alert(1)>"}
```
Wszystko przechodzi verbatim. Kontrakt z `docs/api/jsonb-schemas.md` (§6: text →
`{"value": <scalar>}`, price → `{amount, currency}`) NIE jest egzekwowany przy zapisie.

**Scenariusz awarii:** Klient API zapisuje przez `PATCH /api/products/{id}` value
niezgodne z kanonem (np. `number` = `"abc OR 1=1"`, price.amount = `"lol"`, dodatkowe
klucze). Readery muszą bronić się defensywnie. `ValueSerializer` (eksport) JEST odporny
(`stringify` coerces array→pipe, object→''), ale niezaudytowane readery (Meili
`DocumentFlattener`, integracje Shopify/BaseLinker, completeness calc) mogą się wykrzaczyć
lub produkować śmieci propagowane do kanałów sprzedaży. To też wektor stored-XSS dla
wysiwyg (patrz C-4).

**Rekomendacja:** Rozszerzyć walidację per-type na wszystkie typy (number → numeric,
boolean → bool, price → {amount:number, currency:ISO}) i odrzucać nieznane klucze
envelope (`additionalProperties: false` z jsonb-schemas.md). Per §4 cross-cutting w
docs: *„Future (po Fazie 1): JSON Schema validation w Symfony Validator constraints"* —
to jest ten dług.

**Estymacja:** M (4-8h: dopisać walidatory dla pozostałych typów + odrzucanie extra keys).

---

### C-4 [MEDIUM] Brak serwerowej sanityzacji HTML wysiwyg — XSS broniony WYŁĄCZNIE przez DOMPurify na froncie

**Lokalizacja:** `apps/api/src/Catalog/Application/Validation/TypeValidator/WysiwygValidator.php:26-52`
(tylko `is_string` + `max_length`), render: `apps/admin/src/components/catalog/wysiwyg-editor.tsx:125,205`.

**Dowód kodu (serwer):** `WysiwygValidator` docblock: *„HTML sanitisation is enforced at
render time on the frontend (DOMPurify…) — keeping the backend agnostic"*. Zapisany HTML
to surowy string — potwierdzone empirycznie w C-3: `{"value":"<img src=x onerror=alert(1)>"}`
ląduje w bazie bez zmian.

**Dowód kodu (front — dziś bezpieczny):** Jedyny `dangerouslySetInnerHTML` w całym
`apps/admin/src` jest sanityzowany:
```tsx
dangerouslySetInnerHTML={{ __html: DOMPurify.sanitize(value || '') }}
```
Plus `htmlToSlate` sanityzuje na parse (`:205`). Render w adminie jest XSS-safe DZIŚ.

**Scenariusz ataku:** Stored-XSS payload (`<img onerror>`, `<script>`) jest trwale w
`object_values.value`. Jeśli JAKIKOLWIEK przyszły/niezaudytowany konsument renderuje tę
wartość bez DOMPurify — inny widok admina, public storefront, panel partnera, raport,
e-mail, eksport HTML do kanału — payload się odpala. Obrona jest „depend on every reader
remembering to sanitize" zamiast neutralizacji u źródła. Dodatkowo `serializeNode`
(`:303`) escapuje tylko `&<>"` w `href` — nie blokuje `javascript:` (dziś łapie to
DOMPurify przy renderze, ale to znów obrona po stronie czytelnika).

**Rekomendacja:** Sanityzować HTML serwerowo przy zapisie (allow-list tagów/atrybutów
po stronie backendu, np. HTMLPurifier) jako defense-in-depth, niezależnie od DOMPurify.
Minimum: blokować `javascript:`/`data:` w href przy zapisie wysiwyg.

**Estymacja:** M (4-6h: backend HTML sanitizer + testy).

---

### C-5 [LOW] Detekcja formatu importu po rozszerzeniu pliku, nie po treści (magic-byte)

**Lokalizacja:** `apps/api/src/Import/Application/Service/FileParserService.php:47-56`
(`pathinfo PATHINFO_EXTENSION`), `StartImportController.php:157,190`
(`str_ends_with('.xlsx')`/`'.csv'`).

**Dowód kodu:** format wybierany wyłącznie z rozszerzenia/nazwy; brak `finfo`/magic-byte
sniffu zawartości.

**Scenariusz awarii:** Plik z mylącym rozszerzeniem (np. binarny nazwany `.csv`, lub
nie-ZIP nazwany `.xlsx`). **Zmitygowane:** `.xlsx` → `XlsxArchiveGuard::validate` wymusza
poprawny ZIP (rzuca na nie-ZIP), parser OpenSpout failuje na niepoprawnej zawartości; CSV
parsowany jako tekst. Skutek = błąd parsowania / odrzucenie, nie wykonanie. Stąd LOW —
brak ścieżki do eskalacji, ale rozjazd MIME↔treść nie jest jawnie wykrywany.

**Rekomendacja:** Dodać lekki magic-byte check (ZIP `PK\x03\x04` dla xlsx, walidacja
UTF-8/encoding dla csv) i zwracać czytelne 422 przy mismatchu zamiast polegać na błędzie
parsera. Nice-to-have.

**Estymacja:** S (1-2h).

---

## Co zweryfikowano jako BEZPIECZNE (z dowodem)

- **Export formula injection**: `neutraliseFormula` w obu writerach prefiksuje TAB dla
  `= + - @ \t \r`. Test na żywo: `=cmd|calc` → `[TAB]=cmd|calc`, `=HYPERLINK(...)` →
  `[TAB]=HYPERLINK(...)`. Leading-space ` =1+1` nie neutralizowany, ale Excel/LibreOffice
  nie ewaluują komórki zaczynającej się od spacji jako formuły — bezpieczne.
- **SQLi przez wartości FilterDSL**: escaping `''` poprawny przy
  `standard_conforming_strings=on`, potwierdzone `EXPLAIN`-em (literał, nie injection).
  `attr`/identifier blokowane przez `safeIdent`. (Dług parametryzacji = C-2.)
- **ltree / category path**: wszystkie zapytania używają bound params
  `CAST(:path AS ltree)`; `computeNewPath` konkatenuje ale wynik idzie jako bound param.
- **SSRF**: `NoPrivateNetworkHttpClient` poprawnie wstrzyknięty do `ImageDownloadHandler`
  (per-redirect peer-IP check), `SsrfGuard` jako pre-filter, MAX_BYTES/redirects/timeout.
- **Zip-bomb / zip-slip**: `XlsxArchiveGuard` (metadata-only, conjunctive ratio+size),
  `ZipImageExtractor::isUnsafePath` (blokuje `..`, absolute, drive paths), extract via
  `tempnam` (nie extractTo z user path).
- **Path traversal folder import**: `FolderPathGuard` realpath-canonicalises obie strony.
- **Deserializacja**: 0 wystąpień `unserialize/eval/create_function/call_user_func/new $`
  w kodzie aplikacji.
- **XSS w adminie**: jedyny `dangerouslySetInnerHTML` sanityzowany DOMPurify (dziś safe;
  ryzyko źródłowe = C-4).
- **Limity importu**: rows 200k, file 100MB, zip 500MB, image 10MB — obecne.
