# PRD — Cortex PIM: Eksport produktów (kontekstowy + centralny `/integrations/exports`)

**Typ dokumentu:** Product Requirements Document — **Feature-level** w ramach produktu Cortex PIM
**Klasa produktu:** PIM (Product Information Management) — agentic-first SaaS
**Pozycjonowanie:** Alternatywa dla Akeneo / Pimcore / BaseLinker — operator cockpit + workflow-grade czystość + Cmd+K agent
**Producent:** Marcin Lipiec (projekt prywatny; equity / model biznesowy poza zakresem tego dokumentu)
**Data utworzenia:** 2026-05-14
**Wersja dokumentu:** 1.1 (rewizja EXR 2026-06)
**Autor:** Marcin Lipiec (synteza brainstormingu 5-falowego 2026-05-14)
**Status:** Implemented (MVP) 2026-05-15 — marathon EXP-01..EXP-16 (PR #597..#619). 4 follow-upy IMP-16..IMP-19 (#602..#605) blokują pełen round-trip per EXP-02 audit (`agent/exp-02-imp-audit.md`). Lista świadomych odejść w `Project Plan/02-plan-projektu-pim.md` epik EXP. Validation z Magdą + Tomaszem oraz dogfooding Marcin 50k SKU — manualne follow-up sesje per `agent/exp-15-smoke-report.md`.

> **Nota o scope dokumentu.** To **feature-PRD** dla *jednego* obszaru produktu (eksport produktów z dwoma entry points: kontekstowy z listy + centralny `/integrations/exports`). Pełen product-PRD dla Cortex PIM (pozycjonowanie, ICP, model biznesowy, multitenant SaaS, pricing) — patrz `Zrodla/PRD/PRD-PIM.md`. Sekcje 3, 4, 11, 12 niniejszego dokumentu zawierają wyciąg / odniesienia do master PRD; sekcje 5–10, 13–14 są feature-specific.
>
> **Bliźniaczy feature:** [`feature-imports.md`](../UI/feature-imports.md) — importy CSV/XLS/XLSX (🟢 zaimplementowane IMP-01..IMP-15 merged 2026-05-07). Ten PRD bezpośrednio reuse'uje import pipeline dla round-trip semantyki.

---

## Rewizja 2026-06 (epik EXR — #1377–#1392)

Eksporty zostały przemodelowane jako **pierwszy moduł nowego look & feel** (spec: `Project Plan/UI/feature-exports-redesign-tickets.md`). Zmiany względem v1.0:

- **Encje eksportu (D2):** już nie tylko produkty — 5 typów `ExportEntityType`: `product`, `custom_module` (treści custom ObjectType), `module_schema`, `attributes_groups`, `categories`. Encje strukturalne mają uproszczoną ścieżkę (bez query buildera, pełna struktura, kolumny z builderów EXR-06).
- **Preflight (EXR-07):** `POST /api/exports/preflight` — count + routing `sync|async` + `exceeds_cap` przed uruchomieniem; UI nigdy nie hardkoduje progu 100 (źródło prawdy: `SyncExportController::SYNC_THRESHOLD`).
- **Wycofanie modala (D5):** `ExportModal` + paste-JSON `ExportNewPage` usunięte (EXR-14). Jedyny flow konfiguracji = pełnostronicowy 4-krokowy wizard `/integrations/exports/new` (Typ → Zakres i format → Kolumny → Podsumowanie). Wejścia kontekstowe z listy (zaznaczone / wynik filtra) nawigują do wizarda z kontekstem w router state.
- **Reużywalna wyszukiwarka (EXR-10):** sekcja filtrów wizarda osadza TEN SAM `AdvancedFilterPanel` + `useFilterDslState` co lista produktów — zero drugiej implementacji DSL.
- **Formaty (D1):** payload przyjmuje wyłącznie `xlsx|csv`; kafelki XML/JSON/Google Sheets/PDF widoczne jako disabled „wkrótce".
- **Taby (D3):** Sesje + Profile Eksportu działające; Cele i Harmonogram disabled „wkrótce" (osobny epik).
- **Async UX (EXR-15):** progres per chunk (Mercure), **anulowanie** (`POST /api/exports/sessions/{id}/cancel`, nowy status `cancelled`), in-app inbox przy dzwonku (bez persystencji BE — świadome MVP).
- **Sesje sync w historii:** sync zapisuje wpis `ExportSession` (file_path=null — plik temp jest kasowany po wysyłce; download tylko dla sesji async z plikiem w MinIO).

---

## 1. Streszczenie wykonawcze (TL;DR)

Eksport produktów to dopełnienie funkcjonalności importów (`feature-imports.md` 🟢 zaimplementowane) — bez eksportu round-trip cykl pracy z katalogiem jest niepełny. Primary use case: **Magda eksportuje 247 produktów Festo do XLSX, edytuje opisy SEO w Excelu (bo szybciej niż klikanie 247× w PIM admin), reimportuje przez istniejący flow `/integrations/imports`**. Wzór UX: hybrydowo **Shopify Export** (brutalnie prosty filter + click → download w panelu, NIE mail) + **Akeneo Export Profiles** (Two-pane Akeneo-style picker columns z group sections + saved profiles per user) + **BaseLinker download w panelu** (toast + kolejka w zakładce *„Exports"*, analog do imports).

Feature obejmuje: dwa entry points (kontekstowy z listy = modal, centralny z `/integrations/exports/new` = full-page form) z **wspólnym `export_jobs` worker engine** + 4 sekcje konfiguratora (kolumny / lokale+kanały / format+encoding / co eksportujesz) + saved profiles per user + Recent exports history (forever retention do explicit delete) + manual bridge reimport przez `/integrations/imports`. SKU jako natural key, brak system protection przed zmianą — odpowiedzialność klienta.

**MVP scope brutalnie wąski:** ~30-50h backend total. Bez schedulera, bez S3/SFTP push destination, bez XML/JSON, bez pretty report template, bez Cmd+K integration, bez share profili z teamem, bez cross-user audit panel — wszystko CUT do Fazy 1+. **Świadoma decyzja: małe, robust feature jako analog do importów, rozszerzenia gdy realny pain z design partners.**

**Jednozdaniowe pozycjonowanie feature'a:**
*„Eksport produktów do Excela w 3 klikach z roundtripem przez import — bez maila, bez schedulera, bez ceremonii. Forever retention plików, audit kto-co-kiedy w Recent exports."*

---

## 2. Wizja produktu i motywacja

### 2.1 Dlaczego budujemy ten feature

Trzy konkretne sygnały:

1. **Dogfooding Marcina** — pierwsza migracja IdoSell + Shopify do Cortex PIM wymaga *„checkpoint snapshot"* przed każdym dużym bulk operation. Bez eksportu nie ma jak zrobić *„zapisz teraz, mogę cofnąć całość"* — tylko per-bulk-session 24h rollback (`feature-list-advanced.md`), co dla cross-session zmian jest niewystarczające.
2. **Pain Magdy z BaseLinker** — Magda przyzwyczajona z BaseLinker do *„wyeksportuj 247 produktów Festo, otwórz w Excel, zaznacz kolumnę description, find-replace, wklej z powrotem"*. Bez tego flow Cortex jest *„gorszy niż BaseLinker dla content editing"* — chociaż ma 1000× lepszy detail view.
3. **Symetria z importami** — `feature-imports.md` zaimplementowane (IMP-01..IMP-15 merged 2026-05-07), ale bez eksportu pipeline jest asymetryczny. Klient może wnieść dane, ale nie może ich zabrać/edytować offline. Niespójność strukturalna — eksport zamyka cykl.

### 2.2 Dlaczego teraz (timing)

- **Import pipeline gotowy** — IMP-01..IMP-15 dostarczają pipeline który eksport może *„reverse-reuse"*. Round-trip semantyka działa za darmo gdy importu rozpoznaje SKU jako natural key.
- **OpenSpout dojrzały** (~3 lata stabilny) — memory-efficient XLSX writer w PHP (streaming, brak load-all-into-memory). Bez tego eksport 50k SKU byłby memory-prohibitive na FrankenPHP worker mode.
- **MinIO tenant bucket setup** — z importów (sekcja 11.4 architektury) już mamy MinIO bucket per tenant. Eksport `exports/{tenant_id}/{session_id}.xlsx` to *„darmowy by-product"* infrastruktury.
- **Bulk actions toolbar (epik UI-02)** — wprowadzony `feature-list-advanced.md` z cross-page selection state. Eksport kontekstowy *„wybierz Zaznaczone / Cały filter / Wszystkie"* działa nad tym samym selection model.

### 2.3 Wizja 3-letnia tego feature'a

Eksport za 3 lata to **kompletny export-as-feed platform**:

- **MVP (teraz):** XLSX/CSV download w panelu, manual bridge reimport, primary use case Magda SEO round-trip.
- **Faza 1:** XML/JSON formaty + S3/SFTP push destination + scheduler (cron `0 3 * * *` dla *„Allegro feed XML codziennie 03:00"*) + share saved profiles z teamem + cross-user audit panel dla Owner.
- **Faza 2:** Channel-aware export templates (Allegro/Google Shopping/Idealo) z mapping rules per kanał + Cmd+K agent intent *„wyeksportuj zaznaczone do XLSX z SEO"* + AI-assisted column selection (*„wybierz kolumny dla copywritera SEO"*).
- **Faza 3:** Connector marketplace (analog Salsify) — gotowe templates per marketplace z one-click setup + automated quality validation pre-export (*„Twój feed Allegro ma 23 produkty bez `ean` — czy chcesz wyeksportować z braki czy skip?"*).

### 2.4 North Star Metric (feature-level)

**Średnia liczba round-trip cycles per persona Magda per miesiąc.**

Definicja round-trip cycle: export XLSX → edycja offline ≥1 row → reimport przez `/integrations/imports` → success status. Założenie: bez eksportu Magda robi 0 round-tripów (bo nie ma flow), z dobrym eksportem 4-8 round-tripów/miesiąc (bulk SEO edit, locale fill, kategoryzacja sezonowa).

Mierzalność: `export_sessions` join `import_sessions` przez `user_id + tenant_id + timestamp_close < 24h + columns_overlap > 80%`. Heurystyka, ale wystarcza dla trendu.

---

## 3. Pozycjonowanie i różnicowanie (feature-level)

> Master pozycjonowanie Cortex PIM — patrz `Zrodla/PRD/PRD-PIM.md` §3. Poniżej tylko aspekty unikalne dla *tego* feature'a.

### 3.1 Konkurencja bezpośrednia (per feature obszar eksport)

| Konkurent | Mocne strony (eksport obszar) | Słabe strony (eksport obszar) | Cena (orientacyjna) | Target |
|-----------|----|---|---|---|
| **Akeneo PIM** | Mass Edit / Export Profiles z pełną konfigurowalnością (filter + columns + locale + scope + format), saved as entities, scheduler, JSON/CSV/XLSX | Workflow-tool feel — overhead dla quick exports; wymagane uprawnienia per role; UX nie operator-friendly | Free Community / Enterprise od €40k/rok | Mid-market i enterprise, workflow-first organizacje |
| **Pimcore Data Hub / Data Director** | Full data pipeline (graph-based mapping, transformations, multi-source/destination), developer-grade kontrola | Developer-only tool — Magda nie da rady; over-engineering dla simple round-trip | Open-source / Enterprise od ~€20k/rok | Enterprise z własnym zespołem dev |
| **BaseLinker Eksport produktów** | Wbudowany kreator XML/CSV per marketplace, gotowe template'y Allegro/Amazon/eBay, scheduler, push do FTP, operator-friendly | Nie pełnoprawny PIM; brak saved profiles per user; brak round-trip safety (SKU change handling); brak audit history | Od ~399 PLN/miesiąc | Polskie e-commerce SMB multi-channel |
| **Shopify Export** | Brutalnie prosty UX: filter + click *„Export CSV"* → mail z linkiem do download (async by default nawet dla 100 produktów) | Bardzo prosty = brakuje konfiguracji (no column picker, no locale/channel selector); brak panelu download (tylko mail); brak saved profiles | Od $39/miesiąc Basic | E-commerce wszystkie wielkości |
| **Salsify Channel Sync** | Connector marketplace 200+ kanałów, template per kanał z mapping rules, AI-assisted mapping | Enterprise positioning ($30k+/rok), nie SMB; nie eksport tradycyjny — to feed management as feature | Custom, od $30k/rok | Mid-market do enterprise multi-channel |
| **Channable / Productsup** | Dedykowane SaaS-y feed management (eksport jako core product), automatyzacje, 1000+ destinations | Standalone tool, klient kupuje obok PIM-u (=podwójny koszt); zero round-trip z PIM | Od €39/miesiąc Channable / €1k+/miesiąc Productsup | E-commerce z dużym feed-spend |

### 3.2 Główna oś różnicowania feature'a

**Round-trip-first design.** Cortex eksport jest **zaprojektowany** od dnia 1 jako *„bramka do bulk content editing offline w Excel"* — nie *„dump dla integracji"*. Sygnały:
- **SKU jako natural key** (nie UUID hidden) — Magda widzi czytelne identyfikatory.
- **Variants flat z `parent_sku`** kolumną — Magda edytuje variant SEO bezpośrednio w wierszu.
- **Multi-locale toggle** w jednym eksporcie — `description.pl` i `description.en` jako osobne kolumny w jednym XLSX.
- **Reimport przez ten sam pipeline** co fresh import — SKU detection → UPDATE existing. Zero osobnego flow.

Akeneo Mass Edit nie jest *„round-trip-first"* (eksport to standalone use case dla migracji). Shopify Export nie jest (brak column picker). BaseLinker częściowo (chip filter + download), ale bez multi-locale i bez reimport safety.

### 3.3 Wspierające differentiatory

1. **Download w panelu, NIE mail** — Shopify wymusza mail jako jedyną opcję (UX założenie *„user wraca później"*). Cortex toast notification + kolejka w zakładce `/integrations/exports → Recent exports`. Klient widzi status real-time przez Mercure SSE, klika *„Download"* gdy ready. **Ważne dla operator workflow** — Kasia/Magda nie chcą sprawdzać maila co 30s.
2. **Saved Export Profiles per user** — Magda zapisuje *„SEO round-trip PL+EN"* jako profil, używa raz w tygodniu jednym kliknięciem. Akeneo ma to (Enterprise), Shopify nie, BaseLinker częściowo (per-marketplace template, nie per-user).
3. **Forever retention plików** — `feature-imports.md` ma 30 dni, większość konkurencji 7-14 dni. Cortex: nigdy nie kasujemy automatycznie, klient ręcznie usuwa w *„Recent exports"*. Storage cost na Pro/Enterprise tier — *„zaufanie do produktu"* gest.
4. **Two-pane Akeneo-grade column picker w MVP** — większość *„prostych"* PIM-ów (Plytix, Sales Layer) ma column picker jako prosty checkbox list. Cortex idzie od dnia 1 z power-user UX (left available z groups+search, right selected z drag-reorder). Spójne pozycjonowanie *„operator-cockpit, nie toy"*.

### 3.4 Czego ten feature świadomie NIE robi lepiej

- ❌ **Scheduler / recurring exports** — Faza 1. MVP wymaga manual *„Run now"* dla każdego eksportu. Dla *„Allegro feed codziennie 03:00"* klient setupu cron na własnym serwerze + curl `POST /api/exports/sessions` — Faza 1 daje native.
- ❌ **S3/SFTP push destination** — Faza 1. MVP eksport = plik w MinIO + download w panelu. Continuous integration z hurtownią klienta = Faza 1.
- ❌ **XML/JSON formaty** — Faza 1. MVP tylko XLSX + CSV. Klient potrzebujący Allegro XML feed must wait. Świadoma decyzja — *„primary use case (c) Magda Excel SEO nie wymaga XML, JSON dla devów może czekać"*.
- ❌ **Pretty report template engine** (logo, formatowanie, agregaty) — Faza 1. MVP daje *„gołą tabelę"* (auto-sized columns, header bold, freeze top row). Wystarcza dla *„send to dostawca"*, NIE jest pretty report z brandingiem klienta.
- ❌ **Cmd+K integration** *„wyeksportuj zaznaczone do XLSX z SEO"* — Faza 2. MVP wymaga manual modal/form workflow.
- ❌ **Share saved profiles z teamem** — Faza 1. MVP per-user only. Magda nie może udostępnić *„SEO round-trip"* Kasi.
- ❌ **Cross-user audit panel** dla Owner — Faza 1. MVP self-audit only (Tomasz NIE widzi Magdy eksportów).
- ❌ **Channel-aware export templates** (mapping rules per Allegro/Google Shopping) — Faza 2.
- ❌ **AI-assisted column selection** (*„wybierz kolumny dla copywritera"*) — Faza 2.
- ❌ **Built-in shared templates** seedowane przez system (*„Default"*, *„SEO content"*, *„Full backup"*) — kandydat do MVP late, ale traktowany jako Sprint 1 decision (`feature-list-advanced.md` analog dla smart filter presets).
- ❌ **Audit reason textfield** dla large exports — N/A bo brak gating.
- ❌ **Force export overrides locked attributes** — N/A (eksport nie modyfikuje danych, lock irrelevant).

### 3.5 Killer use case

**Scenariusz „Magda SEO round-trip 247 Festo PL+EN"**: Magda dostała brief od Tomasza *„do końca tygodnia uzupełnić EN description dla wszystkich Festo, mamy klienta z Niemiec na demo"*. Klasyczny workflow Akeneo: smart filter `brand=Festo` → mass edit description.en wizard 3-step → wpisz template *„Festo professional sensor..."* dla 247 produktów (bez variation per SKU = bezsensowne SEO). Czas ~30 min + zły output (template SEO).

Cortex flow: chip filter `brand=Festo` → toolbar `[Export]` → modal → load profile *„SEO round-trip PL+EN"* (saved z poprzedniego cyklu) → format XLSX → kolumny `sku, name, description.pl, description.en, meta_description.pl, meta_description.en` → locale toggle `["pl", "en"]` → `[Eksportuj]` → toast *„Eksport rozpoczęty"* → 8s → bell notification → klik download. 247 wierszy w Excel, otwiera, używa ChatGPT/Claude obok (*„translate Polish to German marketing tone for industrial sensor"*), wkleja kolumnę po kolumnie, manualnie sprawdza 5 sample, save XLSX. Idzie do `/integrations/imports`, drag-drop pliku, IMP rozpoznaje SKU → UPDATE existing, preview 247 rows → 220 będzie zmodyfikowanych, 27 bez zmian (brak edycji), 0 błędów → Import. Czas: ~45 min (głównie tłumaczenie przez AI w Excel, eksport-reimport = ~2 min). Output: realne tłumaczenia z kontekstem produkt-po-produkcie (Excel side-by-side).

Akeneo: 30 min + zły template SEO. Cortex: 45 min + realny tone-of-voice SEO. **Time wins gdy quality liczy się.**

Albo *„Marcin backup snapshot przed IdoSell migracją"*: Marcin `/integrations/exports/new` → full-page form → kolumny *„Wszystkie"* (toggle), locale *„Wszystkie"*, channels *„Wszystkie"*, format *„XLSX"*, target *„Wszystkie produkty (50000)"* → `[Eksportuj]` → 25s później XLSX w MinIO + browser download 80MB → Marcin save lokalnie *„cortex-snapshot-2026-05-14.xlsx"* → bez stresu rusza migracja. Pimcore by potrzebował developer skills + Data Hub setup. BaseLinker by ograniczył do 10k wierszy max bez Premium.

---

## 4. ICP i persony (w kontekście tego feature'a)

> Master ICP Cortex PIM — patrz `Zrodla/PRD/PRD-PIM.md` §4. Poniżej tylko zawężenie na *tego* feature'a.

### 4.1 ICP — kogo szczególnie obchodzi ten feature

- **Branże:** content-heavy katalogowanie (B2B techniczny — Marcin profile, fashion — wiele SKU z opisami marketingowymi). Mniej krytyczne dla: high-margin niche (5-50 SKU klient ręcznie edituje w detail view), commodity (description SEO nieistotne).
- **Skala asortymentu:** 200–50 000 SKU. Poniżej 200 — Magda klika ręcznie w detail view, eksport overkill. Powyżej 50k — wymaga performance benchmark (R-42 ryzyko §14).
- **Lokalizacja:** Klient ma multi-locale (PL + EN minimum) **lub** ma copywriterów freelance. Bez multi-locale primary use case (c) Magda SEO traci sens. Z copywriterami freelance — eksport jako transfer mechanism (use case (e) 3rd party).
- **Operator profile:** team teczy ≥2 osoby (Magda + Kasia), nie solo founder. Solo founder edycje w detail view lub bulk actions wystarczają. 2+ team to powstaje pain *„kto edytuje co, kiedy"* — eksport offline w Excel daje async collaboration.

### 4.2 Persony użytkowników tego feature'a

#### Magda, 29 — Marketing / Content Manager (PRIMARY)
- **Kim jest:** Marketing manager odpowiedzialny za content multi-locale (PL+EN), SEO, kategoryzacja, kolekcje sezonowe. 5-10h/tygodniowo w PIM. Comfortable z Excel (formuły, find-replace, conditional formatting).
- **Cele:** Wyeksportować 247 Festo do XLSX, edytować description.en offline w Excel (z pomocą ChatGPT/Claude obok), reimportować. Saved profile *„SEO round-trip PL+EN"* żeby drugi raz nie konfigurować od zera.
- **Frustracje dziś:** BaseLinker eksport nie ma multi-locale toggle w jednym XLSX (musi robić 2 osobne eksporty PL + EN, mergować ręcznie). Akeneo Mass Edit wymusza in-PIM editing (brak roundtrip do Excel). Excel czysty (bez SKU mapping) zostawia ją w *„skąd wiem które row to który produkt po edycji?"*.
- **Wskaźnik sukcesu:** Round-trip ≥4×/miesiąc, czas per cykl ≤90 min (eksport 2 min + offline edycja 60-80 min + reimport 5 min). North Star Metric tego dokumentu (§2.4).

#### Marcin — Founder / dogfooding (FIRST USER)
- **Kim jest:** Founder Cortex PIM, prywatny e-commerce B2B (planowana migracja z IdoSell + Shopify). Hands-on, używa CSV dla skryptów Python (data validation, sample-based testing).
- **Cele:** Checkpoint snapshot przed każdą dużą bulk operation (5000 SKU IdoSell migration, każdy nowy ObjectType deployment). CSV format dla Python pandas (`pd.read_csv`).
- **Frustracje dziś:** Akeneo Mass Edit niezgodne ze stack-em (PHP exports + brak natywnego Python integration). Shopify Export = CSV ale z hardcoded shape (brak picker), nie da się dostosować pod analizę.
- **Wskaźnik sukcesu:** ≥1 backup snapshot przed każdą bulk operation, czas trigger-do-pliku ≤60s dla 5000 SKU.

#### Kasia, 32 — Catalog Manager (SECONDARY)
- **Kim jest:** Catalog Manager, primary user listy produktów + bulk actions (`feature-list-advanced.md`). Eksport sporadyczny — backup before-bulk lub transfer do copywritera freelance.
- **Cele:** *„Eksportuj zaznaczone 30 do copywritera"* w prostym XLSX (sku, name, description.pl) — copywriter dostaje, edytuje, odsyła, Kasia reimportuje.
- **Frustracje dziś:** Brak prostego *„export selected"* w toolbar listy (BaseLinker ma, ale wymusza per-marketplace template).
- **Wskaźnik sukcesu:** 1-2 eksporty/tydzień, czas wybór-do-download ≤30s dla 30 produktów.

#### Tomasz, 55 — Owner / CEO (SPORADYCZNY)
- **Kim jest:** Owner / CEO klienta, audit + flagowe produkty edycja.
- **Cele:** Self-audit *„kto wziął katalog?"* w `/integrations/exports → Recent exports`. Sporadyczny eksport flagowych 10 produktów dla osobistego raportu.
- **Frustracje dziś:** Klasyczne PIM-y brak audit *„who downloaded what"* dla owner-perspektywy (Akeneo Enterprise ma, BaseLinker nie).
- **Wskaźnik sukcesu:** ≤30s od loginu do widoku Recent exports tego tygodnia.

### 4.3 Decydent zakupowy vs. użytkownik

- **Decydent zakupowy:** Tomasz (Owner/CEO) — audit feature jest *„nice to have"* w jego percepcji, ale **nie killer**. Eksport ogólnie waży w demo bo Magda będzie pytała *„czy mogę robić SEO w Excel?"*.
- **Daily user feature'a:** Magda (Marketing) — eksport jest **jej** flagship workflow. Jej satysfakcja z round-trip = renewal decision rok 2.
- **Champion:** Magda — wymusza adopcję jeśli jest dobre. Bez tego sięga obok PIM-a (export ad-hoc do Excel manually = nie używa Cortex dla content).

Implication: ten feature musi w demo wow-fować **Magdę** w trial period (round-trip czas ≤90 min na realnym katalogu klienta). Tomasz dostanie value-add audit panel w Fazie 1.

---

## 5. Model danych (feature-level)

> Master model danych Cortex PIM (ObjectType, Attribute, ObjectValue, attributes_indexed JSONB, ADR-006/009/010/011) — patrz `Zrodla/PRD/PRD-PIM.md` §5 i `Project Plan/01-architektura-pim.md`. Poniżej tylko delta wprowadzona przez ten feature.

### 5.1 Nowe encje wprowadzane przez feature

**`export_sessions`** — każdy eksport tworzy session (history + status + audit trail).

```sql
CREATE TABLE export_sessions (
    id UUID PRIMARY KEY,
    tenant_id UUID NOT NULL REFERENCES tenants(id),
    user_id UUID NOT NULL REFERENCES users(id),
    source VARCHAR(16) NOT NULL,             -- list_context / central_tab / saved_profile_run
    profile_id UUID REFERENCES export_profiles(id),  -- NULL dla ad-hoc, FK dla saved profile run
    format VARCHAR(8) NOT NULL,              -- xlsx / csv
    encoding VARCHAR(16),                    -- utf8_bom / windows_1250 (CSV only)
    target_scope VARCHAR(16) NOT NULL,       -- selected / filter / all
    filter_snapshot JSONB,                   -- aktywny filter w momencie eksportu (do rerun)
    selected_object_ids UUID[],              -- gdy target_scope=selected
    selected_columns JSONB NOT NULL,         -- ["sku", "name", "description.pl", ...] z order
    locales JSONB,                           -- ["pl", "en"] gdy klient włączył multi-locale
    channels JSONB,                          -- ["shopify", "baselinker"] gdy multi-channel
    include_variants BOOLEAN NOT NULL DEFAULT true,  -- flat (α) decyzja z Fali 5
    target_count INTEGER NOT NULL,
    success_count INTEGER NOT NULL DEFAULT 0,
    file_path TEXT,                          -- MinIO key: exports/{tenant_id}/{id}.{format}
    file_size_bytes BIGINT,
    duration_ms INTEGER,
    status VARCHAR(16) NOT NULL,             -- pending / running / done / error
    error_message TEXT,
    started_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    completed_at TIMESTAMPTZ
);
CREATE INDEX idx_export_sessions_user ON export_sessions(user_id, started_at DESC);
CREATE INDEX idx_export_sessions_tenant ON export_sessions(tenant_id, started_at DESC);
CREATE INDEX idx_export_sessions_status ON export_sessions(status) WHERE status IN ('pending', 'running');
```

**`export_profiles`** — Saved Export Profiles per user (MVP per-user only, share z teamem = Faza 1+).

```sql
CREATE TABLE export_profiles (
    id UUID PRIMARY KEY,
    tenant_id UUID NOT NULL REFERENCES tenants(id),
    user_id UUID NOT NULL REFERENCES users(id),  -- per-user w MVP
    name VARCHAR(255) NOT NULL,
    description TEXT,                            -- opcjonalny komentarz "co to za profil"
    config JSONB NOT NULL,                       -- format, encoding, selected_columns, locales, channels, include_variants
    last_run_at TIMESTAMPTZ,
    run_count INTEGER NOT NULL DEFAULT 0,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    UNIQUE (tenant_id, user_id, name)
);
CREATE INDEX idx_export_profiles_user ON export_profiles(user_id, name);
```

**`export_logs`** — per-job log lines (errors, warnings, info dla debugging i raport).

```sql
CREATE TABLE export_logs (
    id UUID PRIMARY KEY,
    export_session_id UUID NOT NULL REFERENCES export_sessions(id) ON DELETE CASCADE,
    level VARCHAR(8) NOT NULL,                  -- info / warning / error
    message TEXT NOT NULL,
    context JSONB,                              -- np. {"object_id": "uuid", "reason": "missing attribute"}
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);
CREATE INDEX idx_export_logs_session ON export_logs(export_session_id);
```

### 5.2 Zmiany w istniejących encjach

**Brak.** Eksport jest read-only operation na `objects` + `object_values` — nie modyfikuje danych domenowych. Nie dodaje kolumn do `objects` jak `feature-list-advanced.md` (`bulk_session_id`, `locked_attributes`).

### 5.3 JSONB format `config` dla `export_profiles`

```json
{
  "format": "xlsx",
  "encoding": null,
  "selected_columns": ["sku", "name", "brand", "description.pl", "description.en", "meta_description.pl", "meta_description.en", "main_image", "category"],
  "column_order": ["sku", "name", "brand", "main_image", "category", "description.pl", "description.en", "meta_description.pl", "meta_description.en"],
  "locales": ["pl", "en"],
  "channels": [],
  "include_variants": true,
  "default_target_scope": "filter",
  "_meta": {
    "created_in_ui_version": "1.0.0",
    "based_on_built_in_template": "seo_content"
  }
}
```

### 5.4 JSONB format `filter_snapshot` dla `export_sessions`

Format identyczny jak `smart_filter_presets.query` z `feature-list-advanced.md` §5.3 — wspólny filter DSL:

```json
{
  "operator": "AND",
  "conditions": [
    {"attribute": "brand", "op": "IS", "value": ["Festo"]},
    {"attribute": "completeness_pct", "op": "<", "value": 50}
  ]
}
```

Snapshot służy `Rerun` action — *„odpal eksport raz jeszcze z tym samym filtrem"* (note: jeśli atrybut z filter został usunięty z Modelowania między eksportem a rerun, rerun returns warning *„Filter atrybut 'brand' nie istnieje, kontynuować bez tego warunku?"*).

### 5.5 Walidacje per pole eksportu

| Pole | Walidacja |
|---|---|
| `format` | enum: `xlsx`, `csv` (MVP) |
| `encoding` | enum: `utf8_bom`, `windows_1250` — wymagane gdy `format=csv`, ignored dla `xlsx` |
| `selected_columns` | min 1, max bez limitu (ale UX warning gdy >50) |
| `target_scope` | enum: `selected`, `filter`, `all` |
| `selected_object_ids` | wymagane gdy `target_scope=selected`, min 1, max bez limitu (ale soft warning gdy >10000) |
| `locales` | array tenant locales (z `Tenant.enabled_locales`) — fail jeśli locale nie istnieje |
| `channels` | array tenant channels — fail jeśli channel nie istnieje |
| `target_count` | obliczany serwer-side z `filter_snapshot` lub `selected_object_ids` — soft cap 100k (warning), hard cap 500k (block z error) |

Backend reject w formacie RFC 7807 Problem Details (zgodnie z architecture rule §9 z CLAUDE.md).

### 5.6 Audit / provenance

- `export_sessions` to **primary audit table** dla eksportów — każdy job zapisany z user_id, timestamp, target_count, format, filter_snapshot.
- AuditBundle log (epik 0.11.4) **opcjonalny** w MVP — feature przede wszystkim używa `export_sessions` tabela jako audit, AuditBundle integration jako Faza 1 dla *„export jako jeden z typów audit events"* w global audit log.
- **Retention:** `export_sessions` forever do explicit klienta delete (decyzja Fali 4). Storage cost na Pro/Enterprise tier z modelu pricing (§12).
- **`export_logs` retention:** kasowane CASCADE gdy `export_sessions` row jest usuwany.

---

## 6. Multikanałowość (kontekst feature'a)

> Master strategia multikanałowa Cortex PIM — patrz `Zrodla/PRD/PRD-PIM.md` §6. Poniżej tylko wpływ na *ten* feature.

### 6.1 Channel-aware eksport — multi-channel toggle

Atrybut `description` może być scopable per kanał (`description.shopify`, `description.baselinker`). Atrybut `price` może mieć wartości per kanał (różne marżowanie).

**W modalu eksportu sekcja *„Wybierz lokale i kanały"*** (checkbox toggles):
- **Wszystkie lokale** (domyślnie current locale only — Magda wybiera dla SEO round-trip)
- **Wszystkie kanały** (domyślnie current channel sub-tab only — Kasia wybiera dla cross-channel inconsistency check)

Gdy `Wszystkie kanały` ON → eksport ma kolumny `description.shopify` i `description.baselinker` jako osobne pola. Excel widzi obie wartości side-by-side. Magda manualnie wybiera *„wyrównuję description na obu kanałach do same value"* lub *„zostawiam różne — premium na Shopify, basic na BaseLinker"*.

### 6.2 Channel exports vs publish to channel — explicit separation

**Decoupling (iii) z Fali 1:**
- *„Eksport"* = plik XLSX/CSV w MinIO + download w panelu. Magda otrzymuje plik.
- *„Publish to channel"* = sync produktów do Shopify Admin API / BaseLinker Products API / Allegro Offers API. Inna funkcjonalność, inny epik (04 Publikacje).

Brak wspólnego *„Connector"* abstrakcji w MVP. Świadoma decyzja — uniknięcie over-engineering. Faza 2 może zunifikować jeśli pojawia się realny pain *„dlaczego ja muszę osobno setupować eksport Allegro feed XML i publish do Allegro?"*.

### 6.3 Feed dla kanałów (Allegro XML, Google Shopping XML) — OUT OF MVP

XML format + scheduler + S3/SFTP push destination razem stanowią *„feed dla kanału"* package (use case (a) z Fali 1). Wszystkie 3 są CUT do Fazy 1+. MVP dostarcza prosty XLSX/CSV download — klient potrzebujący Allegro feed must:
- (a) Setupuje własny cron na serwerze + curl `POST /api/exports/sessions` (z saved profile filter + columns) + curl download XLSX → konwersja do XML własnym skryptem.
- (b) Czeka na Faza 1.
- (c) Używa BaseLinker / Channable obok Cortex (dual setup, pricing 2× ale działa).

---

## 7. DAM i media (kontekst feature'a)

> Master DAM strategia — patrz `Zrodla/PRD/PRD-PIM.md` §7. Tu tylko aspekty list view.

### 7.1 Asset references w eksporcie

Atrybut typu `asset` (main_image, gallery, technical_docs) w eksporcie:
- **(a) Public URL** (default w MVP) — `https://cdn.cortex.{domain}/.../img.jpg`. Round-trip safe — reimport rozpoznaje URL jako istniejący asset (przez `asset_id` lookup w MinIO bucket).
- (b) Raw object_id UUID — odrzucone (Magda nic z tym nie zrobi w Excel).
- (c) Filename only — odrzucone (ambiguous przy reimport, multiple assets może mieć ten sam filename).

**Open question §14:** czy `cdn.cortex.{domain}` jest setupowany w MVP, czy MinIO presigned URLs (ekspirują 1h-7d)? Wpływ na round-trip safety — presigned URLs po expiracji nieaktywne, ale `asset_id` w URL pozostaje stabilne.

### 7.2 Gallery (multiple assets per produkt)

Atrybut `gallery` ma multi-value (array assetów). Serialization w eksporcie: **pipe-separated URLs** (`url1|url2|url3`) zgodnie z konwencją multi-value §11.

### 7.3 DAM out of MVP eksportu

- Eksport DAM jako dedykowany feature (lista plików z metadata, batch download zip) — Faza 1+. MVP eksport tylko produkty.
- Bulk replace main image przez eksport-edit-reimport — niemożliwe w MVP (Excel nie edituje binary assetów). Magda musi użyć detail view → upload nowy asset.

---

## 8. Workflow i jakość danych (feature-level)

### 8.1 Empty values handling

JSONB `description.pl IS NULL` lub `object_values.value IS NULL` → eksport jako **blank cell** (Excel-natural, default).

Alternatywy odrzucone (Fala 5 default):
- Literal `(brak)` — confusing przy reimport (czy `(brak)` to literal value czy null indicator?).
- Explicit `NULL` string — non-natural dla Excel users.

**Open question §14:** w Sprint 1 POC z Magdą potwierdzić — *„czy blank cell vs literal `(brak)` zmienia twoją UX?"*. Default blank, można pivot jeśli walidacja inaczej decyduje.

### 8.2 Multi-value serialization

`tags = ['promo', 'nowość', 'bestseller']` → **pipe-separated** `"promo|nowość|bestseller"` (rekomendacja moja, do walidacji z Magdą w Sprint 1).

Uzasadnienie:
- Comma-separated (a) — konflikt gdy tag content zawiera przecinek (*„IP67, IP68"*).
- JSON array (c) — non-natural dla Excel users (Magda nie edytuje `["promo","nowość"]`).
- Pipe-separated (b) — uniknięcie konfliktu, prosto edytowalne (Excel find-replace `|` działa).

Reimport semantyka: pipe split + trim per token. Round-trip preservation.

### 8.3 Variants flat layout — `parent_sku` column

Decyzja Fali 5 (α): każdy variant osobny wiersz + kolumna `parent_sku`:

```
sku           parent_sku    name            description.pl    ...
TST-001       (blank)       Czujnik X-200   ...               ...
TST-001-A     TST-001       X-200 PNP M12   ...               ...
TST-001-B     TST-001       X-200 NPN M8    ...               ...
```

247 produktów (mix masterów + variants) → ~250 wierszy. Magda edytuje variant SEO bezpośrednio w wierszu, parent_sku informuje *„to jest variant TST-001"*.

**`Show variants: as tree / flat` toggle** w grid listy → wpływa na default eksportu kontekstowego (respects grid state, klient może override w modalu sekcja *„Include variants"*).

### 8.4 Encoding handling

| Format | Encoding |
|---|---|
| XLSX | UTF-8 natywnie (format wymaga, brak choice) |
| CSV | Radio w modalu: **UTF-8 with BOM** (default, *„modern"*) / **Windows-1250** (legacy Excel PL) |

UTF-8 with BOM dla CSV — Excel PL na Windows poprawnie wykrywa encoding z BOM byte order mark. Bez BOM Excel domyślnie ANSI → polskie znaki krzaki.

Windows-1250 option — dla klientów ze starszym narzędziami / klientami którzy *„zawsze tak robili"*. Default UTF-8 BOM, ale dropdown daje option.

### 8.5 Audit (self-audit only w MVP)

Decyzja Fali 5 (α):
- **Self-audit:** każdy user widzi **tylko swoje eksporty** w `/integrations/exports → Recent exports`.
- **Tomasz (Owner) NIE widzi Magdy eksportów w MVP.** Cross-user audit panel = Faza 1.
- Implementation: backend `GET /api/exports/sessions` z scope `WHERE user_id = :current_user_id`.

Open question §14: rozwiązanie półśrodek? Owner widzi metadata (User | Date | Format | Rows | Status, **bez** filter_snapshot i bez Download akcji), bez treści eksportu? Faza 1 decision.

---

## 9. Importy, eksporty, integracje (kontekst feature'a)

> Master strategia integracji Cortex PIM — patrz `Zrodla/PRD/PRD-PIM.md` §9 oraz [`feature-imports.md`](../UI/feature-imports.md). Tu tylko interakcja imports ↔ exports.

### 9.1 Round-trip semantics — manual bridge

Decyzja Fali 4 (a): **Manual bridge**. Magda po edycji XLSX **manualnie nawiguje** do `/integrations/imports`, klika *„New import"*, wybiera plik, system traktuje to jako fresh import.

**Wymagania kontraktu z `feature-imports.md`:**
- Import flow rozpoznaje *„zawiera kolumnę SKU"* w mapping step → match po SKU jako natural key.
- IMP rozpoznaje *„SKU już istnieje w bazie tenant"* → mapping mode *„UPDATE existing"* automatycznie (default).
- IMP rozpoznaje *„SKU nie istnieje"* → mapping mode *„INSERT new"* (klient w wizard może zmienić strategy).
- Variants flat layout z `parent_sku` column → IMP rozpoznaje `parent_sku NULL` jako master, `parent_sku=<existing SKU>` jako variant → wymaga implementation w IMP (open question — sprawdzić Sprint 1 czy IMP-01..IMP-15 to obsługuje, dodać IMP-16 jeśli nie).
- Multi-value pipe-separated → IMP pipe-split parser (open question — to samo).

**Brak nowego flow w MVP** — re-used IMP pipeline. Plus: zero kodu nowego dla reimport. Minus: 4-5 kroków nawigacji (export → download → otwórz w Excel → save → idź do `/integrations/imports` → new import → upload → wizard → Apply).

**Faza 1 candidate:** *„Reimport this export"* button w Recent exports row → otwiera file picker pre-set na *„Update mode by SKU"* z config kolumn z oryginalnego eksportu. Jeden klik zamiast 5.

### 9.2 Reuse IMP pipeline — kontrakt do walidacji w Sprint 1

Open questions §14:
- [ ] Czy IMP-01..IMP-15 obsługuje variants flat z `parent_sku`? Jeśli nie → IMP-16 ticket Sprint 1.
- [ ] Czy IMP obsługuje multi-value pipe-separated? Jeśli nie → IMP-17 lub parser hint w mapping step.
- [ ] Czy IMP obsługuje asset URL → asset_id resolution (lookup w MinIO bucket przez URL)? Jeśli nie → IMP-18 ticket Sprint 1.
- [ ] Czy IMP obsługuje multi-locale columns (`description.pl`, `description.en` jako osobne kolumny w jednym XLSX → JSONB envelope `{value, locale}` w `object_values`)? Jeśli nie → IMP-19 ticket Sprint 1.

**Te 4 open questions są critical** — bez nich round-trip nie działa end-to-end. POC w Sprint 1 = pierwsza priorytet.

### 9.3 API publiczne (eksport)

Wszystkie endpointy poprzez API Platform 4 (zgodnie z architecture rule §3 CLAUDE.md):
- `POST /api/products/export` — contextual export z listy (body: filter, columns, format, target_scope, selection).
- `GET /api/exports/profiles` — lista user's saved profiles.
- `POST /api/exports/profiles` — create profile.
- `PATCH /api/exports/profiles/{id}` — update.
- `DELETE /api/exports/profiles/{id}` — delete.
- `POST /api/exports/profiles/{id}/run` — Run now (creates new export_session).
- `GET /api/exports/sessions` — Recent exports (per user).
- `GET /api/exports/sessions/{id}` — Session detail + logs.
- `POST /api/exports/sessions/{id}/rerun` — Rerun.
- `DELETE /api/exports/sessions/{id}` — Delete (z MinIO cleanup).
- `GET /api/exports/sessions/{id}/download` — signed URL do MinIO (presigned 1h).
- `GET /api/exports/sessions/{id}/status` — polling fallback gdy Mercure SSE failuje.

### 9.4 Webhooks

OUT OF MVP eksportu. Klient potrzebujący *„webhook gdy eksport ready"* must poll `GET /api/exports/sessions/{id}/status`. Faza 1 candidate: `export.completed` webhook.

---

## 10. Strategia AI (feature-level)

> Master AI strategia Cortex PIM (Anthropic, BYOK, limits) — patrz `Zrodla/PRD/PRD-PIM.md` §10 oraz `Project Plan/01-architektura-pim.md` §8.5. Tu tylko aspekty eksportu.

### 10.1 AI w MVP eksportu — BRAK

Świadoma decyzja Fali 3: **Cmd+K integration eksportu CUT z MVP do Fazy 2.**

Co to znaczy:
- ❌ Cmd+K *„wyeksportuj zaznaczone do XLSX z SEO"* — nie działa w MVP.
- ❌ AI-assisted column selection (*„wybierz kolumny dla copywritera"*) — nie działa.
- ❌ AI quality validation pre-export (*„Twój feed Allegro ma 23 produkty bez `ean`"*) — nie działa.

Klient w MVP wybiera kolumny manualnie w two-pane picker. Saved profiles per user pozwalają minimalizować repetitive setup, ale każda nowa kombinacja = manual config.

### 10.2 AI w Fazie 2 eksportu

Po MVP dolacza Cmd+K integration:

| Intent | Przykład command | Mapped tool |
|---|---|---|
| `export_selected_for_seo` | *„wyeksportuj zaznaczone do XLSX z SEO"* | `tool:trigger_export` z auto-config (cols: sku/name/description.*/meta_description.*) |
| `export_for_copywriter` | *„wyeksportuj 50 Festo dla copywritera"* | `tool:trigger_export` (cols: sku/name/description.pl/category) |
| `export_full_snapshot` | *„zrób backup snapshot wszystkich produktów"* | `tool:trigger_export` (cols: wszystkie, scope: all) |
| `suggest_columns_for_purpose` | *„jakie kolumny do tłumaczenia opisów?"* | `tool:suggest_export_columns` — LLM proposes config, klient confirm |

Wymagania: tool calls w Fazie 2 muszą iść przez ten sam `ExportJobHandler` co manual flow (spójność, audit, retention).

### 10.3 AI cost — nie dotyczy MVP

Bez Cmd+K integration koszt AI w eksporcie = $0. Faza 2 dochodzi do limits z architecture §8.5 (50 tool calls/h/user, BYOK).

---

## 11. Architektura SaaS (feature-level)

> Master architektura multitenant Cortex PIM (FrankenPHP worker, Doctrine TenantFilter, RLS w Fazie 1) — patrz `Zrodla/PRD/PRD-PIM.md` §11 oraz `Project Plan/01-architektura-pim.md`. Tu tylko aspekty feature'a.

### 11.1 Multi-tenancy izolacja

- Wszystkie nowe encje (`export_sessions`, `export_profiles`, `export_logs`) mają `tenant_id UUID NOT NULL` od dnia 1 (kontrakt CLAUDE.md).
- Doctrine TenantFilter automatic clause `WHERE tenant_id = :current_tenant`.
- Postgres RLS aktywowany w Fazie 1 (sekcja 11.1a master architektury).
- **MinIO bucket isolation:** `exports/{tenant_id}/{export_session_id}.{format}` — pattern jak imports. Cross-tenant access impossible przez Symfony Flysystem adapter (presigned URLs scoped per tenant).

### 11.2 Skala docelowa feature'a

**Performance target (b) z Fali 5: <30s dla 50k SKU + 30 kolumn:**
- Chunking N=1000, lazy load attributes per chunk.
- Streaming write directly to MinIO (chunked PUT) — brak load-all-into-memory.
- Memory budget worker: 50MB constant niezależnie od scale.
- OpenSpout writer (XLSX) lub native PHP `fputcsv` (CSV) — oba memory-efficient.
- Doctrine `iterate()` + `EntityManager::clear()` per chunk (FrankenPHP memory safety z CLAUDE.md §3.10).

**Soft cap target_count: 100k SKU per export.** Warning w UI gdy klient ma filter result >100k. Hard cap 500k (block z error + suggestion *„Podziel na 5 eksportów lub poczekaj na Faza 1 streaming download"*).

### 11.3 FrankenPHP worker mode — memory safety

- `ExportJobHandler extends AbstractBatchHandler` — `EntityManager::clear()` per chunk N=1000.
- OpenSpout writer writes chunks bezpośrednio do `php://output` lub MinIO PUT (configurable per environment).
- Prometheus alert `frankenphp_worker_memory_bytes > 256MB` (z CLAUDE.md) zatrzymuje worker przy regression.

### 11.4 Async-by-default threshold — (b) Hybrid z Fali 3

- **<100 produktów** → sync streaming response, klient dostaje download bezpośrednio w przeglądarce (~1-2s). Endpoint `POST /api/products/export` zwraca `Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet` + binary body.
- **≥100 produktów** → async via Symfony Messenger. Endpoint zwraca `202 Accepted` + `Location: /api/exports/sessions/{id}`. Toast w UI *„Eksport rozpoczęty, status w /integrations/exports"*. Mercure SSE channel `export-jobs.{session_id}` dla progress. Bell notification gdy `status=done`.

**2 code paths do utrzymania** — confirmed Fala 3. Sync path optymalizuje quick exports (Tomasz 10 SKU edit), async path safe dla large exports.

### 11.5 Mercure SSE channels

- `export-jobs.{user_id}` — wszystkie user's exports live updates (lista Recent exports refresh).
- `export-jobs.{session_id}` — per-job progress (subscribed gdy user otwiera detail or sees toast).

Progress event payload:
```json
{
  "session_id": "uuid",
  "status": "running",
  "progress_pct": 45,
  "rows_done": 22500,
  "rows_total": 50000,
  "estimated_seconds_remaining": 15
}
```

### 11.6 Bezpieczeństwo i compliance

- **MVP brak gating (a) z Fali 5** — każdy user może wszystko eksportować, audit log + retention forever wystarczają. Spójne z resztą feature'ów.
- **GDPR / sensitive data:** open question §14 — czy pełen eksport 50k SKU katalog (Pro/Enterprise tier) wymaga *„audit reason"* textfield w Fazie 1? Walidacja z 5 prospects (sekcja 14.3).
- **MinIO encryption at-rest** (server-side encryption) — domyślnie włączone, zgodnie z master architecture §11.5.
- **Presigned URLs do download** — 1h TTL. Po expiracji klient klika *„Download"* znowu w UI → nowy presigned URL.

### 11.7 SLA per feature (per tier)

| Tier | Sync threshold | Async queue capacity | Performance target (50k SKU) | Retention |
|------|----------------|----------------------|------------------------------|-----------|
| Free / Trial | <100 produktów (sync), >=100 blocked | N/A | N/A | 7 dni (override forever decision dla Free) |
| Starter | <100 sync, >=100 async (max 1 concurrent) | 1 concurrent job | <60s | Forever do explicit delete |
| Pro | <100 sync, >=100 async (max 3 concurrent) | 3 concurrent | <30s | Forever do explicit delete |
| Enterprise | <100 sync, >=100 async (smart queue) | Unlimited | <20s (dedicated workers) | Forever do explicit delete + dedicated MinIO bucket |

Open question §14: Free tier retention override (7 dni vs forever) — decision Sprint 1 (storage cost vs *„zaufanie do produktu"* gest).

---

## 12. Model biznesowy i pricing (feature-level)

> Master pricing Cortex PIM — patrz `Zrodla/PRD/PRD-PIM.md` §12. Tu tylko wpływ tego feature'a na pricing tiers.

### 12.1 Co jest gated per tier

| Tier | Sync exports | Async exports | Concurrent async | Saved profiles | Retention | Audit panel |
|------|--------------|---------------|-------------------|-----------------|-----------|-------------|
| Free / Trial | <100 rows | Blocked | N/A | 1 profile | 7 dni hard delete | Self-audit only |
| Starter | <100 rows | OK | 1 | 5 profiles | Forever do explicit delete | Self-audit only |
| Pro | <100 rows | OK | 3 | Unlimited | Forever do explicit delete | Self-audit (Faza 1: cross-user) |
| Enterprise | <100 rows | OK | Unlimited (smart queue) | Unlimited + share team (Faza 1) | Forever + dedicated bucket | Self-audit (Faza 1: cross-user + GDPR audit reason) |

### 12.2 Wpływ kosztowy storage na pricing

- Eksport 50k SKU + 30 kolumn ≈ 50-100MB XLSX.
- 100 eksportów/rok per klient = 5-10GB.
- MinIO storage cost: ~$0.023/GB/month na S3-equivalent. 5GB × 12 miesięcy = ~$1.40/rok per klient.
- Marginal cost negligible, but x100 klientów × 5GB = 500GB = $138/year storage budget. Manageable.

**Pricing decision: forever retention as feature differentiator**, nie cost burden. *„Zaufanie do produktu"* gest komunikowany jako tier benefit.

### 12.3 Open business questions (feature-related)

- [ ] Free tier retention — 7 dni (cost-effective) vs forever (consistency message)? Default 7 dni dla Free (cap storage), forever dla paid tiers.
- [ ] Sync threshold per tier — czy Free <50 rows zamiast <100? Soft restriction żeby demo wymuszało paid tier dla większych?
- [ ] Async concurrent jobs limit — Pro 3 vs Pro 5? Decision na podstawie observed usage Sprint 1.

---

## 13. MVP scope i roadmap (feature-level)

### 13.1 MVP — co MUSI być w pierwszym release feature'a

**Entry points:**
- Kontekstowy z listy produktów (toolbar `[Export]` button → modal).
- Centralny `/integrations/exports/new` (dedicated route → full-page form).

**Konfigurator (modal i full-page shared sections):**
- Two-pane Akeneo-style column picker (left available z group sections + search, right selected z drag-reorder).
- Group sections: Basic / Marketing / Media / Pricing / Variants / Custom attributes.
- Locales i kanały toggles (default current only, checkbox *„Wszystkie lokale"* / *„Wszystkie kanały"*).
- Format radio (XLSX / CSV) + Encoding picker (UTF-8 BOM / Windows-1250) wyświetlany gdy CSV.
- Target scope radio (Zaznaczone / Cały filter / Wszystkie produkty) z BaseLinker-style cross-page selection.
- Save as profile checkbox + textfield z nazwą.
- Load profile dropdown na górze formy.

**Centralny tab `/integrations/exports`** z 3 sekcjami:
- Recent exports grid (kolumny: Date | User | Format | Rows | Status | Actions [⬇ Download / ↻ Rerun / 🗑 Delete]).
- Saved profiles grid (kolumny: Name | Created | Last run | Run count | Actions [▶ Run now / ✏ Edit / 🗑 Delete]).
- Run new export button → nawiguje do `/integrations/exports/new` full-page form.

**Backend:**
- 3 nowe encje (`export_sessions`, `export_profiles`, `export_logs`).
- 12 API endpoints (sekcja 9.3).
- `ExportJobHandler extends AbstractBatchHandler` z chunking N=1000 + streaming MinIO write.
- OpenSpout XLSX writer (memory-efficient) + native PHP CSV writer.
- 2 Mercure channels (`export-jobs.{user_id}`, `export-jobs.{session_id}`).
- Manual bridge reimport — re-used `feature-imports.md` IMP-01..IMP-15 pipeline.

**Edge cases:**
- Variants flat z `parent_sku` column (decyzja Fali 5 α).
- Multi-value pipe-separated (rekomendacja, walidacja Sprint 1).
- Asset references jako public URL (rekomendacja, walidacja Sprint 1 — depends on CDN setup).
- Empty values jako blank cell (default).
- UTF-8 BOM dla CSV (default) + Windows-1250 option.

**Performance:**
- Sync threshold <100 rows, async >=100.
- Target <30s dla 50k SKU + 30 kolumn (z chunking N=1000 + streaming).
- Memory budget 50MB worker.

**Estymacja: ~30-50h backend total.** Znacznie mniej niż `feature-list-advanced.md` (~131-182h) bo brak schedulera/destination/AI/template engine.

### 13.2 v1 (3-6 miesięcy po MVP) — Faza 1

- **Scheduler** (cron-based recurring exports) — *„Allegro feed XML codziennie 03:00"*.
- **S3/SFTP push destination** — continuous integration z hurtownią klienta.
- **XML i JSON formaty** — feed dla kanałów + dev-friendly JSON dla integracji.
- **Pretty report template engine** — logo, formatowanie, agregaty (use case d).
- **Share saved profiles z teamem** — Magda → Kasia (tenant-shared profiles).
- **Cross-user audit panel** dla Owner/Admin — Tomasz widzi *„kto wziął katalog?"*.
- **„Reimport this export" button** w Recent exports — 1-click round-trip.
- **`export.completed` webhook** — dla integracji custom.
- **Built-in shared templates** seedowane przez system (5 templates).

### 13.3 v2+ (Faza 2-3) — w roadmapie ale bez commit

- **Cmd+K integration eksportu** — `tool:trigger_export`, `tool:suggest_export_columns`.
- **Channel-aware export templates** (mapping rules per Allegro/Google Shopping/Idealo).
- **AI-assisted column selection** (*„wybierz kolumny dla copywritera SEO"*).
- **AI quality validation pre-export** (*„23 produkty bez `ean`"*).
- **Connector marketplace** (analog Salsify) — 200+ destinations w UI z one-click setup.
- **GDPR audit reason** textfield dla large exports — *„dlaczego eksportujesz cały katalog?"*.

### 13.4 Pierwszy klient referencyjny / design partner

- **Magda (persona owner)** — primary feedback source przed Sprint 1. Walidacja: *„czy 4-5 kroków manual bridge reimport jest do przyjęcia, czy musi być Reimport button MVP?"*.
- **Marcin (dogfooding)** — first user, IdoSell migration snapshot test case.
- **Design partner #1** — szukany przed Sprintem 1 (shared z `PRD-PIM-list-advanced.md` §13.4). Profile: polski B2B techniczny e-commerce 5-15k SKU multi-locale (PL + EN), team Magda+Kasia. Walidacja: multi-value serialization pipe vs alternatives, blank cell vs literal `(brak)` empty handling.

### 13.5 Czas do MVP

- Backend impact: **+30-50h** ponad obecny budżet.
- Frontend impact: **+20-30h** (modal/full-page form + Recent exports + Saved profiles + Two-pane picker).
- IMP integration walidacja: **+0-15h** zależnie od czterech open questions §9.2 (variants flat, multi-value pipe, asset URL, multi-locale columns w IMP).
- **Total estymacja MVP feature'a: ~50-95h** (porównaj z feature-list-advanced.md 131-182h).
- Realna estymacja w izolacji (Marcin solo dev): **3-5 tygodni**.

---

## 14. Ryzyka, sprzeczności, otwarte kwestie

### 14.1 Zidentyfikowane ryzyka

| Ryzyko | Prawdopodobieństwo | Wpływ | Mitygacja |
|--------|--------------------|----|-----------|
| **R-40 Multi-value serialization convention** — Magda preferuje JSON array zamiast pipe → impact na import parser. | Średnie | Średni (re-work parser ~4-6h) | Walidacja Sprint 1 z Magdą na 5 typowych przypadkach (tags, kategorie, gallery). Default pipe, ale gotów na pivot. |
| **R-41 SKU as natural key bez system protection** — klient zmieni SKU w Excel → duplicate produkty po reimport. | Wysokie | Średni (klient confused, ale rollback przez `bulk_session_id` 24h z `feature-list-advanced.md` ratuje) | UI tooltip *„SKU = identyfikator. Zmieniasz SKU = tworzysz nowy produkt"* w modal. Detailed dokumentacja w help section. Faza 1 candidate: warning gdy reimport widzi SKU zmiana >5% rows. |
| **R-42 Performance 50k SKU benchmark** — target <30s wymaga POC w Sprint 1. Bez benchmarku może okazać się że >60s przy 200 atrybutach × 3 lokale × 2 kanały. | Średnie | Wysoki (UX degradation, klient pyta *„dlaczego to trwa 5 minut?"*) | POC Sprint 1 priorytet #1. Benchmark syntetyczny 50k SKU dataset. Fallback: chunking N=500 (mniej memory, więcej iteracji) + Redis-cached attribute schema. |
| **R-43 Storage cost dla forever retention** — klient na Pro/Enterprise z 50k SKU katalogiem = ~50-100MB per export. 100 eksportów/rok = 5-10GB. | Niskie (manageable) | Niski | Komunikacja w pricing tier: *„Storage cost wliczony w tier do 50GB; >50GB add-on $5/100GB/miesiąc"*. Free tier override = 7 dni retention. |
| **R-44 Round-trip ID confusion** — Magda eksportuje, edytuje, dodaje wiersz dla *„nowego produktu"* bez SKU → reimport tworzy nowy produkt bez SKU (auto-generate?) lub failuje (required field). | Średnie | Średni (UX confusion, klient confused) | Sprint 1 decision: IMP-XX `SKU jest required` rule + clear error message *„Wiersz 248: brak SKU. Wypełnij SKU lub usuń wiersz."*. Auto-generate SKU pattern (`AUTO-{timestamp}-{n}`) jako opcja w wizard. |
| **R-45 Self-audit only w MVP** nie zaspokoi Tomasza (Owner) który chce *„kto wziął katalog"* widoczność. | Wysokie | Średni (Tomasz request priorytetowy → przesunięcie z Faza 1 priorytetów) | Faza 1 cross-user audit panel jako pilny dodatek post-launch. W MVP demo z Tomaszem komunikować *„coming in Q3 2026"*. |
| **R-46 Reuse IMP pipeline kontrakt** — IMP-01..IMP-15 może nie obsługiwać variants flat / multi-value pipe / asset URL / multi-locale columns. | Wysokie | Wysoki (round-trip nie działa end-to-end → MVP feature broken) | POC Sprint 1 priorytet #2. 4 open questions §9.2 → 4 potencjalne nowe IMP tickets (IMP-16/17/18/19). Bez tych tickets MVP eksport jest *„download only"*, brak round-trip. |
| **R-47 Saved profile staleness** — Magda zapisała profile rok temu z atrybutami które zostały usunięte z Modelowania. Run profile → error lub silent skip? | Niskie | Niski | Profile load → walidacja per kolumna *„czy atrybut istnieje?"* + UI warning *„Profile zawiera atrybuty których nie istnieją: X, Y. Kontynuować bez nich?"*. |
| **R-48 Concurrent export jobs** — Magda kliknęła *„Export 50k SKU"*, system jeszcze worker'uje, klika *„Run now"* na profile → 2 concurrent jobs ten sam user. | Niskie | Niski (UX confusion, ale jobs niezależne) | Soft limit per tier (Starter 1 concurrent, Pro 3, Enterprise unlimited). Warning gdy klient przekroczy: *„Już masz 1 eksport w toku. Drugi w kolejce."*. |
| **R-49 Filter atrybut usunięty między export a rerun** — Magda eksportuje z filter `brand=Festo`, atrybut `brand` zostaje usunięty z Modelowania, Magda klika *„Rerun"*. | Niskie | Niski | Rerun → walidacja filter snapshot → UI warning *„Filter atrybut 'brand' nie istnieje, kontynuować bez tego warunku? Eksport może zwrócić wszystkie produkty."*. |
| **R-50 Asset URL ekspiracja** — Magda exportuje, asset URL presigned MinIO 1h, Magda otwiera plik za 3h, klika link → 403 expired. | Średnie | Niski | Public CDN URL setup w MVP (asset_id-based) — bez ekspiracji. Open question §14 czy CDN gotowy. Fallback: 7d presigned URL z explicit comm *„link do zdjęcia ważny 7 dni"*. |

### 14.2 Otwarte kwestie

- [ ] **Multi-value serialization** — pipe vs comma vs JSON array? Walidacja z Magdą Sprint 1. **Default: pipe.**
- [ ] **Empty values** — blank cell vs literal `(brak)` vs explicit `NULL`? Walidacja z Magdą Sprint 1. **Default: blank cell.**
- [ ] **Asset references format** — public CDN URL vs MinIO presigned 7d? Sprint 1 decision na podstawie CDN setup readiness. **Default: CDN URL, fallback presigned 7d.**
- [ ] **Built-in shared templates** w MVP — 3-5 seedowane templates (`Default`, `SEO content PL+EN`, `Full backup`, `Pricing review`, `Variants snapshot`)? Sprint 1 decision (4-6h extra). **Default: NIE w MVP, Faza 1.**
- [ ] **Encoding picker UX** — radio button w modalu czy auto-detect by tenant region (PL → Windows-1250 default)? Default: explicit radio (transparency).
- [ ] **`Run new export` full-page form filter picker** — używa tego samego `AdvancedFilterPanel` co lista (~16-20h dodatkowo), czy uproszczonego filter pickera (tylko chip filtry, no query mode, ~6-8h)? Sprint 1 decision. **Lean toward uproszczony picker, query mode dla power users w Faza 1.**
- [ ] **Free tier retention** — 7 dni hard delete vs forever (consistency)? Cost-effective vs message-consistency. **Default: 7 dni dla Free.**
- [ ] **IMP-01..IMP-15 kontrakt walidacja:**
  - [ ] Variants flat z `parent_sku` column — supported?
  - [ ] Multi-value pipe-separated — parser obsługuje?
  - [ ] Asset URL → asset_id resolution — lookup w MinIO?
  - [ ] Multi-locale columns (`description.pl`, `description.en` osobne) → JSONB envelope `{value, locale}` — supported?
- [ ] **Cmd+K integration timeline** — Faza 2 (post-MVP+Faza 1) vs late MVP gdy reszta gotowa wcześniej?
- [ ] **GDPR audit reason** dla large exports w Fazie 1 — wymagane (compliance) lub optional textfield?
- [ ] **Concurrent jobs UI** — kolejka widoczna w `/integrations/exports`? Status *„Pending: position 3 in queue"*?
- [ ] **`Show variants` toggle integration** — eksport kontekstowy z listy respects grid state? Confirm Sprint 1 UX consistency.
- [ ] **Cross-tenant data leakage prevention** — automated test w Sprint 1 (2 tenanty, eksport jednego = 0 produktów drugiego)?

### 14.3 Założenia, które trzeba zwalidować

- **Założenie 1:** *„Magda będzie używać round-trip ≥4×/miesiąc"* (North Star Metric §2.4). Bazuje na intuicji *„SEO offline edit jest szybsze niż klikanie w PIM detail view"*. Walidacja: session replay na design partner #1 w pierwszych 3 miesiącach.
- **Założenie 2:** *„Manual bridge reimport (4-5 kroków) jest akceptowalny w MVP"*. Bazuje na simplicity-first decision Fali 4. Walidacja: pytanie do Magdy *„czy klikasz to bez frustracji, czy musi być Reimport button MVP?"*. Jeśli >60% feedback *„za dużo kroków"* → Faza 1 priorytet.
- **Założenie 3:** *„Performance <30s dla 50k SKU jest osiągalne z OpenSpout + chunking"*. Bazuje na benchmark estimates. Walidacja: POC Sprint 1 z syntetyczny 50k dataset.
- **Założenie 4:** *„Forever retention nie jest cost burden"* (~$1.40/rok per klient). Bazuje na S3 pricing. Walidacja: monitoring rzeczywistego storage usage przez 6 miesięcy.
- **Założenie 5:** *„IMP pipeline obsługuje 4 kluczowe round-trip kontrakty"* (variants flat, multi-value pipe, asset URL, multi-locale columns). Bazuje na założeniu że IMP-01..IMP-15 implementowali głębokie rozpoznawanie XLSX schema. Walidacja: code review IMP tickets w Sprint 0, prep dla IMP-16..19 tickets jeśli gaps.
- **Założenie 6:** *„Two-pane picker w MVP nie overload'uje Magdy"*. Bazuje na *„Akeneo używa, Magda przejdzie z BaseLinker"*. Walidacja: 5-min onboarding test z Magdą *„skonfiguruj eksport dla 247 Festo z SEO columns w 60s"* — jeśli >2 minuty = upraszczamy.
- **Założenie 7:** *„Self-audit wystarczy w MVP, Tomasz nie zablokuje deal"*. Bazuje na heurystyce *„Tomasz audit nice-to-have, nie killer"*. Walidacja: pytanie do Tomasza w demo *„czy brak cross-user audit blokuje purchase decision?"*. Jeśli tak → Faza 1 priorytet podniesiony.
- **Założenie 8:** *„Kasia użytkownik secondary nie potrzebuje feature-specific UX"*. Bazuje na *„Magda primary określa feature, Kasia adopts"*. Walidacja: Kasia w trial uses feature? Jeśli <2 eksporty/miesiąc = okay, jeśli 0 = something missing.

---

## 15. Następne kroki

1. **POC reuse IMP pipeline kontrakt** — Sprint 1 priorytet #1. Walidacja 4 open questions §9.2: variants flat, multi-value pipe, asset URL, multi-locale columns. Jeśli gaps → dopisać IMP-16..19 tickets do `Project Plan/02-plan-projektu-pim.md`.
2. **POC performance benchmark** — Sprint 1 priorytet #2. Syntetyczny 50k SKU + 30 kolumn dataset. Cel: <30s sync write to MinIO. Jeśli >60s → optymalizacja (chunking N=500, Redis-cached attribute schema, dedicated worker pool).
3. **Walidacja z Magdą** — przed Sprint 1. Pytania:
   - Multi-value serialization (pipe vs alternatives).
   - Empty values (blank vs literal).
   - Manual bridge reimport friction (4-5 kroków akceptowalne czy `Reimport button` MVP priority).
   - Two-pane picker UX (60s setup test).
4. **Walidacja z Tomaszem** — przed Sprint 1. Pytania:
   - Self-audit only w MVP — blokuje deal? Jeśli tak → cross-user audit przesunięty do MVP late.
   - Pełen catalog export bez gating — concern security? Jeśli tak → soft warning modal Faza 1.
5. **Decyzja: built-in shared templates w MVP** — 4-6h extra dla 5 seedowanych templates. Sprint 1 decision (lean toward NIE w MVP, Faza 1).
6. **Decyzja: `Run new export` filter picker** — pełen `AdvancedFilterPanel` (~16-20h) vs uproszczony chip picker (~6-8h). Sprint 1 decision (lean toward uproszczony, full panel w Faza 1).
7. **Wireframes Figma** — przekazać external UX designer (PRD master §13.5):
   - Modal eksportu (z listy) — 4 sections (columns / locale+channel / format+encoding / scope).
   - Full-page form (`/integrations/exports/new`) — shared sections z modal.
   - Recent exports grid + Saved profiles grid.
8. **Aktualizacja `Project Plan/02-plan-projektu-pim.md`** — dodać epik 0.X (Exports) z estymacją +30-50h backend + 20-30h frontend.
9. **Aktualizacja `Project Plan/03-funkcjonalnosci-mvp.md`** — dodać user stories US-EXPORT-001 do US-EXPORT-020.
10. **Aktualizacja `Project Plan/UI/00-plan-ui.md`** — dodać `feature-exports.md` jako sibling do `feature-imports.md` i `feature-list-advanced.md`.
11. **Update pitch deck Slajd 6** (USP #3 Operator-cockpit) — dodać round-trip-first design jako wspierający differentiator (§3.3).

---

## 16. Załączniki i powiązane dokumenty

- **Bliźniaczy feature:** [`../UI/feature-imports.md`](../UI/feature-imports.md) — importy (🟢 zaimplementowane IMP-01..IMP-15) — manual bridge reimport target.
- **Sibling feature:** [`../UI/feature-list-advanced.md`](../UI/feature-list-advanced.md) — lista + filtry + search + bulk + Cmd+K — selection state + filter snapshot source.
- **Epik nadrzędny:** [`../UI/epik-02-produkty.md`](../UI/epik-02-produkty.md) — Produkty (lista + detail + bulk).
- **Master plan UI:** [`../UI/00-plan-ui.md`](../UI/00-plan-ui.md).
- **Master product PRD Cortex PIM:** [`../../Zrodla/PRD/PRD-PIM.md`](../../Zrodla/PRD/PRD-PIM.md) — pozycjonowanie globalne, ICP, multitenant SaaS, pricing.
- **Sibling feature PRD:** [`PRD-PIM-list-advanced.md`](PRD-PIM-list-advanced.md) — feature-PRD dla listy + filtrów + bulk + Cmd+K.
- **Architektura:** [`../01-architektura-pim.md`](../01-architektura-pim.md) — ADR-006 hybrid attribute model, ADR-009 ObjectType, ADR-010 axis-driven variants, ADR-011 per-tenant locale fallback, sekcja 3.10 FrankenPHP memory management, sekcja 11.5 GDPR/encryption.
- **Plan projektu:** [`../02-plan-projektu-pim.md`](../02-plan-projektu-pim.md) — backlog, estymacje (do aktualizacji per §15 punkt 8).
- **Funkcjonalności MVP:** [`../03-funkcjonalnosci-mvp.md`](../03-funkcjonalnosci-mvp.md) — user stories (do aktualizacji per §15 punkt 9).
- **CLAUDE.md konstytucja projektu:** [`../../CLAUDE.md`](../../CLAUDE.md) — memory management worker mode, single-origin Caddy, multi-tenancy, BYOK, audit retention.

---

*Dokument wygenerowany 2026-05-14 jako synteza brainstormingu 5-falowego z Falami 1-5 (filozofia / formaty / UX modal / centralny tab / edge cases). Status: Draft — wymaga walidacji z Magdą (§15 punkt 3), Tomaszem (§15 punkt 4), POC IMP kontrakt (§15 punkt 1), POC performance (§15 punkt 2) przed startem implementacji.*
