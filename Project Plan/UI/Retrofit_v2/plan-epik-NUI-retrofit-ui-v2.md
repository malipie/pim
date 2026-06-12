# Epik NUI — Retrofit widoków do nowego designu (PIM-nowoczesny)

> **Status:** backlog założony jako GitHub Issues (label `epik-NUI`, milestone „Epik NUI — Retrofit UI v2 (nowy design)", jeden ticket = jeden issue = jeden branch = jeden PR).
> **Data utworzenia:** 2026-06-10. **Autor wytycznych:** Marcin (operator). **Spisał:** agent.
> **Tryb pracy:** EPIK MARATHON RULE z `CLAUDE.md` obowiązuje przy realizacji. SMOKE TEST RULE i CLOSED MEANS CLOSED RULE obowiązują dla każdego ticketu.

---

## 0. Cel biznesowy

Epik EXR wdrożył nowy design system (tokeny navy/orange, prymitywy `ui-v2`, shell z menu II poziomu) i przemodelował eksporty jako pierwszy moduł. **Epik NUI domyka migrację reszty aplikacji do nowego designu** na podstawie kompletu mockupów operatora w `Zrodla/Front_Claude_Design/NOWY UI/PIM-nowoczesny/`: dashboard, lista + karta produktu, modelowanie, **Multimedia (pełna przebudowa na eksplorator plików)**, **Import (pełna przebudowa: kreator 6 kroków, hub, widok sesji)**, sidebar/menu (podmenu Ustawień, custom OT bez wyróżnienia) oraz restyle stron Użytkownicy/Role.

**Zero pracy tokenowej** — paleta mockupów to dokładnie tokeny wdrożone w EXR-01 (`apps/admin/src/index.css`). Epik jest czysto widokowy: retrofit komponentów i layoutów, bez zmian w kontraktach API i logice biznesowej.

---

## 1. Źródła prawdy

| Co | Gdzie |
|---|---|
| Design — sidebar/topbar (wspólny shell) | `Zrodla/Front_Claude_Design/NOWY UI/PIM-nowoczesny/modeling/shared.jsx` (NAV, Sidebar, Topbar) |
| Design — dashboard + paleta ⌘K | `…/PIM-nowoczesny/Dashboard.html` (samodzielny, inline JSX) |
| Design — lista produktów | `…/PIM-nowoczesny/Produkty.html` + `produkty/list-view-v2.jsx` (**autorytatywny wariant**; `list-view.jsx`/`list-view-v1.jsx` to starsze iteracje) + `produkty/list-v2-overlays.jsx` + `produkty/data.jsx` |
| Design — karta produktu | `…/PIM-nowoczesny/produkty/detail-view.jsx` |
| Design — modelowanie (4 taby) | `…/PIM-nowoczesny/Modelowanie.html` + `modeling/{object-types,attributes,attribute-values,groups-categories}.jsx` |
| Design — Multimedia (eksplorator) | `…/PIM-nowoczesny/Multimedia.html` (samodzielny) |
| Design — hub Importów + sub-taby | `…/PIM-nowoczesny/Integracje.html` + `integracje/{importy-sessions,importy-profiles,importy-sources,importy-schedule,primitives,placeholders,data}.jsx` |
| Design — kreator importu (6 kroków) | `…/PIM-nowoczesny/Import-nowy.html` |
| Design — widok sesji importu | `…/PIM-nowoczesny/Import-sesja.html` |
| Design — Ustawienia (shell + Users/Roles) | `…/PIM-nowoczesny/Ustawienia.html` + `settings/{page,sections,users,roles,data}.jsx` |
| Pakiet handoff (intencje, design system, interakcje) | `…/PIM-nowoczesny/design_handoff_modelowanie/README.md` (sekcje Screens/Interactions; **uwaga:** sekcja „Target stack" w tamtejszym CLAUDE.md opisuje Next.js — NIE dotyczy nas, stack pozostaje React 19 + Vite + Refine) |
| Tokeny + prymitywy v2 (wdrożone w EXR) | `apps/admin/src/index.css`, `apps/admin/src/components/ui-v2/` |
| Poprzedni epik handoff (wzorzec MOCK/backlog) | `Project Plan/UI/Wdrozenie_grafiki/plan-handoff-wdrozenie.md` + `*-do-oprogramowania.md` |
| Plan EXR (wzorzec struktury epiku) | `Project Plan/UI/feature-exports-redesign-tickets.md` |

**UWAGA dla agenta (mock data w designie):** mockupy pokazują liczniki („12 847", „8 421"), nazwy folderów, encję workspace „Klimas Sp. z o.o." itd. — to dane przykładowe. Liczniki pochodzą z realnych endpointów (wzorzec `use-nav-counts.ts`), pozycje menu z `useEffectiveMenu()`. Design pokazuje też lokalizacje EN/DE/CS i kanały Shopify/BaseLinker/Allegro — w implementacji listy locale/kanałów są dynamiczne z API (puste = pusty select, nigdy hardkod — lesson `feedback_channel_empty_select_not_mock`).

---

## 2. Decyzje operatora (2026-06-10) — zakres WIĄŻĄCY

| # | Decyzja |
|---|---|
| D1 | **Zakres epiku:** sidebar/menu, dashboard, lista + karta produktu, modelowanie (dopracowanie), Multimedia (pełna przebudowa), Import (pełna przebudowa), Ustawienia (restrukturyzacja menu **+ restyle stron Użytkownicy i Role**). |
| D2 | **Eksporty POZA zakresem** — EXR świeżo wdrożony wg tego samego designu. Ewentualne rozjazdy wychwycą smoke testy; nie otwieramy diff-check ticketu. |
| D3 | **Backend: trzymamy się danych i funkcjonalności, które system MA.** Element designu bez backendu → **MOCK** (widoczny `MockBadge` + wpis w backlogu `*-do-oprogramowania.md`) **albo SKIP** (pominięcie elementu) — decyzja per element spisana w tabelach WIRE/MOCK/SKIP w ticketach tego planu. Żadnych nowych endpointów w tym epiku. |
| D4 | **Custom ObjectType bez wyróżnienia** w sidebarze (koniec fioletowej dashed ramki i badge'a CUSTOM) — renderują się jak zwykłe pozycje. Dashed CTA „Dodaj własny moduł" zostaje. |
| D5 | **Podmenu Ustawień w głównym sidebarze** (3 grupy: Twoje konto / Workspace / Tenant + karta „Audyt zmian"), rozwijane gdy aktywna trasa `/settings/*`. Drugi sidebar `SettingsLayout` znika; **drzewo routingu bez zmian**. |
| D6 | Tickety → GitHub Issues założone na podstawie tego pliku (zrobione przy utworzeniu epiku). |

---

## 3. Stan zastany (inwentarz — co JUŻ istnieje i czego NIE wolno duplikować)

### 3.1 Fundament v2 (z EXR) — GOTOWY, reuse obowiązkowy
- Tokeny: `apps/admin/src/index.css` — navy `zinc`, `orange` CTA (`--cta` = orange-700 `#b9491a`), `brick`, `emerald`, `--shadow-card`, radiusy, Inter + JetBrains Mono.
- Prymitywy: `apps/admin/src/components/ui-v2/` — `PageHeader`, `PillTabs`, `KpiCard`, `StatusPill`, `ResultBar`, `ProgressBar`, `ModeBadge`, `FormatPill`, `SelectableCard`, `WizardStepper`, `EmptyState`, `Sparkline`, `HealthDot`, `status-maps.ts` + testy axe.
- Shell: `apps/admin/src/layout/topbar-v2.tsx` (breadcrumb + PageActionsContext), `sidebar-nav.tsx` z menu II poziomu dla Integracji (`renderIntegrationsParent`), `app-footer.tsx`.
- Wzorzec routingu poza `IntegrationsLayout`: `App.tsx:528` — komentarz EXR-08 (#1384): eksporty żyją bezpośrednio w shellu v2; *„Imports keep the legacy IntegrationsLayout until their redesign"* → ten epik domyka.

### 3.2 Sidebar / menu
- `apps/admin/src/layout/sidebar-nav.tsx` — pozycje dynamicznie z `useEffectiveMenu()` (`lib/use-effective-menu.ts`), liczniki z `layout/use-nav-counts.ts` (w tym `child:imports`). Custom OT: gałąź `customLeafClass` (fioletowa dashed ramka + badge CUSTOM) — **do usunięcia (D4)**. Integracje rozwijane (Imports/Exports/API Configurator). Ustawienia = zwykły link.
- `apps/admin/src/layout/SettingsLayout.tsx` — drugi sidebar z `NAV_GROUPS` (Account: security; Workspace: users, roles, api-tokens, sso, menu, locales, channels, ai; Tenant: tenant, billing[owner]) + `AuditCard`. **Grupy logicznie identyczne z designem** — zmiana dotyczy miejsca renderowania i stylu, nie struktury.

### 3.3 Dashboard
- `apps/admin/src/features/dashboard/page.tsx` + komponenty — statyczne mocki z UI-03/03b: `HeroAgentPanel`, `KpiCards`, `ActivityChart`, `TopEditedProducts`, `ChannelDistribution`, `SyncsStatusPanel`, `CompletenessMetrics`, `AlertCenter`, `RecentAgentActivity`; skeletony z symulowanym opóźnieniem 300 ms.
- Backlog endpointów dashboardu istnieje: `Project Plan/UI/Wdrozenie_grafiki/dashboard-do-oprogramowania.md`.

### 3.4 Produkty
- Lista: default `/products` → `apps/admin/src/components/objects/universal-list-page.tsx` (ULV-11 #993; obsługuje też `/objects/:slug` dla custom OT). Legacy `/products/legacy` → `features/catalog/products/list.tsx` (okno dual-maintenance UP-10 #1026 — minęło, EXR wszedł od tamtej pory). **Funkcjonalnie lista ma już prawie wszystko z designu**: `SavedViewsRail`, `SmartFilterPresetsRow`, `AdvancedFilterPanel` (lazy), `FilterChipsBar` z „Skopiuj URL z filtrami", cross-page selection („Zaznacz wszystkie X pasujących" w `selection-toolbar.tsx`), Karty/Excel (`view-mode-toggle.tsx`), Płasko/Drzewo (`variants-toggle.tsx`), kolumny img/SKU/nazwa/kategorie/kompletność/kanały/cena/enabled. Retrofit = warstwa wizualna.
- Karta: default `/products/:id` → `features/catalog/products/components/product-detail-page.tsx` (27 plików komponentów: `LocaleChannelToolbar`, `CompletenessRing`, `SyncStatusCard`, `AgentSuggestionsCard`, `AttrGroupCard`, `AttrRow`, `VariantsTabHost`, `RelationsTab`, `CategoriesTab`, `ProductMultimediaTab`…). `components/objects/universal-detail-page.tsx` (opt-in `?universal=1`) **importuje te same komponenty** → restyle współdzielonych plików podnosi obie strony naraz, bez cutoveru.

### 3.5 Multimedia
- `apps/admin/src/features/asset/assets/list.tsx` — płaska lista folderów (`GET /api/asset-folders`, `folder=root` = bez przypisania), grid kart, `AssetUploadDropzone`, `AssetFilterBar` (search + mimeGroup all/images/documents/video), `AssetBulkActionsBar` (bulk delete), `AssetDuplicateDialog`, polling miniatur 3,5 s. Detal `/assets/:id`.
- Backend NIE ma: zagnieżdżonych folderów, quoty magazynu, workflow approve, bulk zip, (do weryfikacji w tickecie) licznika powiązanych produktów per asset.

### 3.6 Import
- Frontend: `apps/admin/src/features/imports/` — `ImportsLayout` (4 taby: sessions/profiles/sources/schedule) **wewnątrz legacy `IntegrationsLayout`**; wizard 4 kroki (`wizard/ImportWizardPage.tsx` + `StepUpload/StepMapping/StepValidation/StepConfirm`, stan w `hooks/useImportWizard.ts` z `persist()/restore()`); widok sesji `show/ImportShowPage.tsx`; live progress `hooks/useImportProgress.ts` (Mercure SSE, topic `imports/{session_id}`, eventy `progress|row_processed|error|completed`).
- Backend (kontrolery w `apps/api/src/Import/Presentation/Controller/`): `ParsePreviewController` (zwraca `headers, sample_rows, total_rows, encoding [wykryty], delimiter, sheet_name, had_multiple_sheets`), `AutoMapController`, `ValidateDryRunController`, `StartImportController` (payload: `file, target_object_type_id, mapping, profile_id, encoding, delimiter` — **bez** trybu insert/update, strategii duplikatów, wyboru arkusza), `ImportReportCsvController`, `RollbackImportController`, `TestImportSourceConnectionController`, `POST /api/import-schedules/{id}/run-now`. Profile CRUD + duplicate/export, źródła FTP/SFTP, harmonogram — działają.

### 3.7 Modelowanie
- `features/catalog/modeling/layout.tsx` (4 taby z licznikami KPI) + `features/catalog/{object-types,attributes,attribute-groups,categories}/` (list/show/new, `attributes/values.tsx`, `attributes/migrate-type.tsx`) + 28 komponentów w `components/modeling/`. Funkcjonalność z designu (sekcje built-in/custom, wartości atrybutów, migration impact, inheritance kategorii) **wdrożona w VIEW-01..04/MOD/MODR** — retrofit = wizualny + akcent violet→orange.

### 3.8 Ustawienia (strony)
- `features/settings/users/` + `features/settings/roles/` — realne UI z RBAC Phase 5 (listy, edytory, macierz uprawnień, zaproszenia). Restyle bez zmian logiki.

### 3.9 Paleta ⌘K
- `apps/admin/src/components/agent/cmd-k-palette.tsx` (VIEW-19 #550) — paleta kontekstowa listy produktów (bulk-intencje przez `POST /api/agent/cmd-k`). Pill w sidebarze jest `disabled`. Globalnej palety nawigacyjnej brak.

### 3.10 Prefiksy ticketów zajęte
`EXP`, `IMP`, `LC`, `UP`, `MOD`, `MODR`, `MODRC`, `CHC`, `ULV`, `EXR`, `VIEW`, `RF`, RBAC #640–#728. **Ten epik używa prefiksu `NUI`.**

---

## 4. Zasady realizacji (WIRE / MOCK / SKIP)

- **WIRE** — element designu podpięty pod istniejący endpoint/dane. Domyślna ścieżka.
- **MOCK** — element renderowany z danymi przykładowymi + **obowiązkowo** `MockBadge` (`components/ui/mock-badge.tsx`) + wpis w backlogu `*-do-oprogramowania.md` + komentarz `{/* MOCK: <opis> — wymaga oprogramowania (backlog: <plik>) */}`.
- **SKIP** — element pominięty (nie renderujemy), z notką w backlogu jeśli wart przyszłej pracy. Stosować gdy mock wprowadzałby w błąd (np. fałszywa detekcja formatu liczb) albo element jest z innej fazy (agent runtime).
- Tabele WIRE/MOCK/SKIP w ticketach niżej są **wiążące** — zmiana klasyfikacji wymaga aktualizacji tego pliku w PR.
- Zmiany czysto prezentacyjne: **zakaz zmian w payloadach, query params i logice mutacji.** Gdzie ticket dotyka komponentów współdzielonych (universal list/detail), E2E musi pokryć obie trasy.
- i18n: wszystkie nowe stringi przez `t()` (klucze EN, tłumaczenia `pl/`, `en/`); po edycji JSON-ów locale — restart Vite (lesson `feedback_i18next_vite_restart_locale`).

---

## 5. Mapa ticketów i zależności

```
FALA 0 (równoległe, rozłączne pliki)
  NUI-01 sidebar v2 (shell)        NUI-02 dashboard v2       NUI-05 wygaszenie /products/legacy
  NUI-07 modelowanie v2            NUI-08 Multimedia v2      NUI-09 hub Importów v2

FALA 1 (po zależnościach)
  NUI-03 paleta ⌘K        (po NUI-01)
  NUI-12 Users+Roles v2   (po NUI-01)
  NUI-04 lista produktów  (po NUI-05)
  NUI-10 wizard importu   (po NUI-09)   ∥   NUI-11 widok sesji importu (po NUI-09)

DOWOLNIE (największy, bez zależności — planować równolegle z falą 1)
  NUI-06 karta produktu v2

BRAMKA (na końcu)
  NUI-13 E2E sweep + a11y + i18n + dead code + docs
```

Sumaryczna estymata: **~130–190 h**. NUI-01 ląduje pierwszy — zmienia globalny chrome (reszta smoke-testowana na finalnym shellu) i odblokowuje NUI-03/12.

---
---

# Tickety

---

## NUI-01 — feat(admin/shell): sidebar v2 — podmenu Ustawień, custom OT bez wyróżnienia, live-dot Importów

### Kontekst
Design (`modeling/shared.jsx`, `settings/page.jsx`, `Integracje.html`) pokazuje jeden spójny sidebar: pozycje Workspace, rozwijane poddrzewo Integracji (już jest) **i Ustawień (nowe — D5)**, custom OT jako zwykłe pozycje (D4). Obecnie podnawigacja ustawień żyje w drugim sidebarze `SettingsLayout`.

### Zakres
- [ ] Wydzielić `NAV_GROUPS` z `SettingsLayout.tsx` do nowego `apps/admin/src/layout/settings-nav-data.ts` (jedno źródło danych dla poddrzewa).
- [ ] `sidebar-nav.tsx`: render poddrzewa Ustawień (wzorzec `renderIntegrationsParent`) gdy `pathname.startsWith('/settings')`: 3 nagłówki grup (Twoje konto / Workspace / Tenant), pozycje z ikonami, kropka „primary" przy nieaktywnych głównych, amber badge `owner` przy Rozliczeniach, wcięcie + lewa linia (`border-l`) jak w designie; na dole poddrzewa karta „Audyt zmian" (przeniesiony `AuditCard`; link „Zobacz audit log →" zostaje disabled z tooltipem „wkrótce" — Phase 7 #724).
- [ ] `SettingsLayout.tsx`: zostaje jako cienki content shell (nagłówek strony + `<Outlet/>`); usunięcie własnego `<aside>`. **Żadnych zmian w drzewie routingu w `App.tsx`** (deep-linki `/settings/channels/new` itd. działają bez zmian).
- [ ] Usunąć gałąź custom OT: `customLeafClass`, badge CUSTOM — custom OT renderują się identycznie jak built-in (D4). CTA „Dodaj własny moduł" zostaje (dashed, hover w odcieniu orange zamiast violet).
- [ ] Live-dot przy „Importy" gdy aktywne sesje > 0 (źródło licznika `child:imports` w `use-nav-counts.ts` już istnieje; dot = pulsująca kropka jak w designie).
- [ ] Gating RBAC bez zmian: poddrzewo respektuje `isMenuRefVisible` / `protected` — pozycje niewidoczne dla roli nie renderują się.

### Poza zakresem
Zmiana kolejności pozycji menu (operator konfiguruje przez `/settings/menu`; ewentualna zmiana domyślnej kolejności rejestru = osobna decyzja — notka w §8). Usuwanie pozycji Workflow/Katalogi PDF (sterowane rejestrem menu z BE). Restyle stron ustawień (NUI-12). Paleta ⌘K (NUI-03).

### Pliki
`apps/admin/src/layout/sidebar-nav.tsx`, `apps/admin/src/layout/SettingsLayout.tsx`, nowy `apps/admin/src/layout/settings-nav-data.ts`, `apps/admin/src/locales/{pl,en}.json`, e2e.

### Kryteria akceptacji
- Deep-link `/settings/users` → główny sidebar z rozwiniętym poddrzewem i aktywną pozycją; `/settings/channels/new` utrzymuje poddrzewo; wyjście z `/settings/*` zwija poddrzewo.
- Custom OT (fixture z seedu) renderuje się bez fioletowej ramki i badge'a.
- Strony ustawień renderują się pełną szerokością bez drugiego sidebara — wizualny smoke wszystkich 11 podstron.
- E2E: macierz deep-linków + brak regresji istniejących speców settings/RBAC. Gates: tsc, Biome, build, Playwright, manual smoke.

**Estymata:** 8–14 h. **Zależności:** brak.

---

## NUI-02 — feat(admin/dashboard): dashboard v2 — KPI live + widgety wg nowego designu

### Kontekst
`Dashboard.html` definiuje nowy układ: rząd 4 KPI z deltami, widget Synchronizacja (4 integracje, live-dots, pushed/failed), wykres Aktywność katalogu (30 dni, 2 linie), widget Backup bazy (RPO, ostatni backup, rozmiar, heatmapa 14 dni), tabela Top edytowane produkty (SKU/nazwa/rodzina/edycje/kompletność/kanały), log Aktywność agentów, Centrum alertów. Obecny dashboard to mocki z UI-03 o innym układzie.

### Zakres — tabela WIRE/MOCK/SKIP (wiążąca)

| Element designu | Klasyfikacja | Realizacja |
|---|---|---|
| KPI: Produkty / Atrybuty / Rodziny→**Grupy atrybutów** / Kategorie | **WIRE** | liczniki wzorcem `useList pageSize=1 → totalItems` (jak `use-nav-counts.ts` / taby modelowania); label „Rodziny" z designu → „Grupy atrybutów" (Family deprecated, ADR-009) |
| Delty KPI („+184 w tym tygodniu") | MOCK | brak endpointu agregacji historycznej → backlog (append `dashboard-do-oprogramowania.md`) |
| Widget Synchronizacja | MOCK | integracje = Faza 1 (BaseLinker/Shopify nie istnieją) → `MockBadge` |
| Wykres Aktywność katalogu 30 dni | MOCK | dane przykładowe + statyczny zakres; endpoint agregatu audytu → backlog |
| Widget Backup bazy + heatmapa | MOCK | pgBackRest działa, ale bez API → backlog |
| Top edytowane produkty | MOCK | endpoint w backlogu dashboardu (istniejący wpis — zweryfikować/odświeżyć) |
| Aktywność agentów | MOCK | agent layer = Faza 2 → `MockBadge` „Faza 2" |
| Centrum alertów | MOCK | backlog |
| Paleta ⌘K | poza ticketem | NUI-03 |

- [ ] Przebudowa `features/dashboard/page.tsx` + komponentów do układu z designu (grid 10-kolumnowy, karty `shadow-card rounded-3xl`); reuse `ui-v2`: `KpiCard`, `Sparkline`, `StatusPill`, `HealthDot`, `EmptyState`.
- [ ] Usunąć bloki nieobecne w nowym designie: `HeroAgentPanel` (zastąpi go pill ⌘K w sidebarze), `ChannelDistribution`, `CompletenessMetrics`.
- [ ] Skeletony per blok zostają; usunąć sztuczne opóźnienie 300 ms — KPI na realnym `isPending`.
- [ ] Append do `Project Plan/UI/Wdrozenie_grafiki/dashboard-do-oprogramowania.md`: backup-status API (pgBackRest), delty KPI, agregat aktywności 30 dni; zweryfikować istniejące wpisy (top edited, alerty, sync) — **nie forkować pliku**.

### Poza zakresem
Jakiekolwiek nowe endpointy. Paleta ⌘K (NUI-03). Wpinanie realnych danych sync/backup (Faza 1 / backlog).

### Pliki
`apps/admin/src/features/dashboard/page.tsx` + `features/dashboard/components/*` (przebudowa/usunięcia), `locales/{pl,en}.json`, append `Project Plan/UI/Wdrozenie_grafiki/dashboard-do-oprogramowania.md`, e2e.

### Kryteria akceptacji
- KPI pokazują realne liczby z seedu (zgodne z licznikami modelowania/sidebara).
- Wszystkie zamockowane widgety mają widoczny `MockBadge`; konsola bez czerwonych błędów.
- E2E: render dashboardu, obecność KPI z liczbami, obecność badge'y MOCK. Gates standardowe + manual smoke.

**Estymata:** 16–24 h. **Zależności:** brak (stylistycznie korzysta z shellu NUI-01, ale nie blokuje).

---

## NUI-03 — feat(admin/agent): globalna paleta ⌘K — realna nawigacja, sekcja agenta jako mock

### Kontekst
Design (Dashboard.html, sidebar we wszystkich widokach) pokazuje globalną paletę ⌘K: sugestie zadań agenta (violet→orange), nawigacja, dokumentacja, modal „Plan zmian" z operacjami CREATE/ATTACH do akceptacji. Istnieje paleta kontekstowa listy (`components/agent/cmd-k-palette.tsx`, VIEW-19) — bulk-intencje na zaznaczeniu. Pill w sidebarze jest disabled. Agent runtime = Faza 2.

### Zakres
- [ ] Globalny host palety w `AppLayout` (jedna rejestracja skrótu `mod+k` — żadnych podwójnych bindingów z paletą listy; na liście produktów istniejący flow bulk-intencji pozostaje dostępny z tej samej palety jako sekcja kontekstowa).
- [ ] **WIRE — sekcja „Przejdź do":** statyczne trasy (Dashboard, Modelowanie [4 taby], Multimedia, Importy/Eksporty/Konfigurator API, podstrony Ustawień z `settings-nav-data.ts`) + dynamiczne ObjectType z `useEffectiveMenu()`; fuzzy-filter, nawigacja klawiaturą (↑↓ ↵ esc), footer ze skrótami jak w designie.
- [ ] **MOCK — sekcja „Agent":** przykładowe sugestie zadań + modal „Plan zmian" (rendering diff-podglądu z danych przykładowych, przyciski disabled) z `MockBadge` „Faza 2"; komentarz MOCK + wpis w backlogu (notka w §8 tego planu — sekcja agent).
- [ ] Odblokować pill „Zapytaj agenta lub szukaj… ⌘K" w sidebarze (otwiera paletę).
- [ ] SKIP: realne wywołania LLM, wykonywanie planów zmian (Faza 2, limity z sekcji 8.5 architektury).

### Poza zakresem
Jakiekolwiek wywołania API agenta poza istniejącym flow bulk-intencji listy. Persystencja historii palety.

### Pliki
`apps/admin/src/components/agent/cmd-k-palette.tsx` (rozszerzenie lub nowy `global-palette.tsx` + host), `apps/admin/src/layout/AppLayout.tsx`, `apps/admin/src/layout/sidebar-nav.tsx`, `locales/{pl,en}.json`, e2e.

### Kryteria akceptacji
- ⌘K działa z każdego widoku; wpisanie „Użytkownicy" → nawigacja do `/settings/users`; OT z menu osiągalne.
- Sekcja agenta widoczna z `MockBadge`; nic nie wysyła requestów do agenta.
- Na liście produktów: dotychczasowe intencje bulk dostępne bez regresji (istniejący spec VIEW-19 zielony).
- E2E: otwarcie palety, filtrowanie, nawigacja, badge MOCK. Gates standardowe.

**Estymata:** 10–16 h. **Zależności:** NUI-01 (settings-nav-data, pill w sidebarze).

---

## NUI-04 — feat(admin/catalog): lista produktów v2 — retrofit UniversalListPage

### Kontekst
`produkty/list-view-v2.jsx` = kosmetycznie dopracowana wersja tego, co lista już robi (§3.4). Retrofit jest **czysto wizualny**: rząd „Smart filtry" jako poziomy pill-kontener z emoji + licznikami, toolbar (search „SKU, nazwa, EAN, atrybut…", „Filtruj zaawansowane", Płasko/Drzewo, Karty/Excel), chipy aktywnych filtrów + „Skopiuj URL", dolny pasek selekcji cross-page, typografia tabeli v2 (nagłówki uppercase 11px, mono dla liczb, pasek kompletności, pill-e kanałów).

### Zakres
- [ ] Pass wizualny po `universal-list-page.tsx` + komponentach `components/catalog/`: `smart-filter-presets-row` (pill-kontener z ikoną zap, emoji presetów, licznikami, „+ Własny preset"), `saved-views-rail`, `filter-chips-bar`, `products-grid` (kolumny i proporcje jak w designie: checkbox/48px img/SKU mono 150px/nazwa 1.6fr/kategorie/kompletność 170px/kanały/cena/enabled/menu), `pagination-bar`, `bulk-bar`, `selection-toolbar`, `view-mode-toggle`, `variants-toggle` — wszystko na tokenach v2.
- [ ] **WIRE:** liczniki przy presetach — jeśli dziś nie są liczone, policzyć client-side z aktywnego widoku LUB oznaczyć `MockBadge` i wpisać do backlogu (decyzja w tickecie po weryfikacji `use-smart-presets.ts`).
- [ ] Placeholder searcha: „Szukaj: SKU, nazwa, EAN, atrybut…" (semantyka bez zmian — token EAN to istniejące wyszukiwanie pełnotekstowe; dedykowany token EAN → backlog append `produkty-do-oprogramowania.md`).
- [ ] **SKIP:** wiersze locked/pinned (LOCK_SKUS/STAR_SKUS z mock data — brak backendu) → append `produkty-do-oprogramowania.md`.
- [ ] Zero zmian w: FilterDSL, serializacji URL, zapisanych widokach, bulk akcjach, payloadach.

### Poza zakresem
Karta produktu (NUI-06). Nowe kolumny/atrybuty. Legacy lista (usunięta w NUI-05).

### Pliki
`apps/admin/src/components/objects/universal-list-page.tsx`, `apps/admin/src/components/catalog/{smart-filter-presets-row,saved-views-rail,filter-chips-bar,products-grid,pagination-bar,bulk-bar,selection-toolbar,view-mode-toggle,variants-toggle}.tsx`, `locales/{pl,en}.json`, append `Project Plan/UI/Wdrozenie_grafiki/produkty-do-oprogramowania.md`, e2e.

### Kryteria akceptacji
- **E2E na OBU trasach:** `/products` i `/objects/:slug` (custom OT fixture) — filtr → chip → kopiuj URL → wejście z URL odtwarza stan; toggle enabled; selekcja cross-page.
- Istniejące specki listy (ULV/UI-09) zielone.
- Manual smoke per SMOKE TEST RULE na żywym stacku.

**Estymata:** 10–16 h. **Zależności:** NUI-05 (żeby nie retrofitować dwóch implementacji).

---

## NUI-05 — chore(admin/catalog): wygaszenie /products/legacy po oknie dual-maintenance UP-10

### Kontekst
ULV-11 (#993) przeciął default na `ProductsUniversalListPage`, legacy zostawiono na 1 sprint fallbacku (UP-10 #1026). Od tego czasu wszedł cały epik EXR — okno minęło. Utrzymywanie dwóch list podwajałoby koszt retrofitu NUI-04.

### Zakres
- [ ] Usunąć route `/products/legacy` z `App.tsx`; redirect `/products/legacy` → `/products` (back-compat dla zakładek operatora).
- [ ] Usunąć `features/catalog/products/list.tsx` + komponenty/importy używane wyłącznie przez legacy listę (weryfikacja `grep` przed usunięciem — część komponentów `components/catalog/` jest współdzielona z universal listą i ZOSTAJE).
- [ ] Sweep speców e2e odwołujących się do legacy trasy (usunąć/przepiąć na `/products`).

### Poza zakresem
Universal detail (`?universal=1` opt-in zostaje bez zmian). Legacy karta produktu (to default — nie ruszamy).

### Pliki
`apps/admin/src/App.tsx`, `apps/admin/src/features/catalog/products/list.tsx` (delete) + osierocone komponenty, e2e.

### Kryteria akceptacji
- `/products/legacy` przekierowuje na `/products`; build bez dead importów; pełna suita Playwright zielona.

**Estymata:** 2–4 h. **Zależności:** brak.

---

## NUI-06 — feat(admin/catalog): karta produktu v2 — header, sticky locale/channel, grupy atrybutów, taby z licznikami

### Kontekst
`produkty/detail-view.jsx` definiuje nowy układ karty: header (wstecz + breadcrumb, akcje Podgląd/Duplikuj/menu, zapis, miniatura 72px, SKU mono + marka + badge statusu, edytowalna nazwa, pill-e kategorii, wskaźnik wariantowości, **pierścień kompletności**), taby z licznikami (Atrybuty/Multimedia/Powiązania/Historia), sticky selektor locale + kanału, zwijane karty grup atrybutów (wypełnienie X/Y + pasek %), wiersze atrybutów (badge i18n, kłódka, inline edit, badge provenance), prawa szyna 320px. Wszystkie te elementy istnieją funkcjonalnie w komponentach legacy karty (§3.4) — universal detail importuje te same pliki.

### Zakres
- [ ] Ekstrakcja wspólnego `DetailHeaderV2` (jedyny zdublowany markup między `product-detail-page.tsx` a `universal-detail-page.tsx`) — układ headera wg designu; reszta = restyle komponentów współdzielonych **w miejscu**: `AttrGroupCard` (zwijanie + licznik wypełnienia + pasek), `AttrRow` (i18n badge, lock, inline edit, provenance badge po prawej), `LocaleChannelToolbar` (sticky pod tabami; locale/kanały **dynamiczne z API** — puste = pusty select), `CompletenessRing` (header), taby z licznikami (**WIRE** z załadowanych danych: liczba grup / assetów / relacji / wpisów historii), `ProductMultimediaTab` (grid 3-kolumnowy + kafel „Dodaj zasób"), `RelationsTab`, zakładka Historia (timeline — WIRE z istniejącego audytu produktu w zakresie, w jakim endpoint zwraca dane; braki → `MockBadge`).
- [ ] Prawa szyna 320px: `SyncStatusCard` (zostaje MOCK — integracje Faza 1, badge już jest), `AgentSuggestionsCard` (MOCK „Faza 2", badge już jest), `EffectiveModelCard`, `VariantsListCard`, selektor kategorii — restyle.
- [ ] **Zero zmian w payloadach PATCH, logice edit-mode, query keys.** Czysto prezentacyjnie.
- [ ] SKIP: elementy designu „dla nowego produktu" sprzeczne z istniejącym create flow (universal create wizard UP-08 obsługuje tworzenie — karta nie przejmuje trybu „new").

### Poza zakresem
Cutover universal detail na default (zostaje opt-in `?universal=1`). Zmiany w wariantach/relacjach funkcjonalne. Endpoint historii rozszerzony.

### Pliki
`apps/admin/src/features/catalog/products/components/` (~12 z 27 plików: product-detail-page, attr-group-card, attr-row, locale-channel-toolbar, completeness-ring, sync-status-card, agent-suggestions-card, product-multimedia-tab, relations-tab, variants-list-card…), nowy `detail-header-v2.tsx`, `apps/admin/src/components/objects/universal-detail-page.tsx`, `locales/{pl,en}.json`, e2e.

### Kryteria akceptacji
- E2E: header (breadcrumb, ring, status), zwijanie grup, liczniki tabów, inline edit + zapis na `/products/:id` ORAZ na universal detail (`?universal=1`).
- Istniejące specki karty (#1348–#1352, #1225/#1226) zielone.
- Manual smoke: edycja atrybutu → PATCH 200 → wartość widoczna po odświeżeniu.

**Estymata:** 20–28 h. **Zależności:** brak twardych (stylistycznie po NUI-01).

---

## NUI-07 — feat(admin/modeling): modelowanie v2 — nagłówki, sekcje, akcent orange zamiast violet

### Kontekst
Modelowanie funkcjonalnie odpowiada designowi (wdrożone w VIEW-01..04 z tych samych mockupów). Design dopracowany: wzorzec nagłówka (tytuł + opis + **CTA pod opisem**, nie obok), sekcje Built-in(🔒)/Custom, kolumna „Wartości" jako badge, akcent **violet zmapowany na orange** (w designie cała skala violet = orange — koniec fioletu w chrome modelowania).

### Zakres
- [ ] `modeling-page-header.tsx`: wariant z CTA pod opisem (wg designu); zastosować we wszystkich 4 tabach.
- [ ] Sweep violet→orange w plikach modelowania (~14 plików wg `grep -rl violet apps/admin/src/components/modeling apps/admin/src/features/catalog`): badge'y custom, „Wartości" badge, akcenty grup — na tokeny orange/`--cta`; ikony typów atrybutów zachowują semantyczne kolory per typ (blue/amber/sky/emerald — zgodne z designem).
- [ ] Restyle list 4 tabów do rytmu designu (wiersze: kafel ikony 40px, nazwa + kod mono, liczniki, chevron); sekcje ObjectTypes Built-in/Custom — weryfikacja zgodności + szlif.
- [ ] Detail sheets (ObjectType/Attribute/Group), `AttributeValuesView`, `MigrationImpactModal`, drzewo kategorii + panel inheritance — **restyle bez zmian funkcjonalnych** (wszystko działa — VIEW/MODR).
- [ ] Footer `Modelowanie`: notka „model schema rev" — jeśli licznik rev nie istnieje w API → SKIP (bez fałszywego numeru).

### Poza zakresem
Jakiekolwiek zmiany w logice migracji typów, visible_when, inheritance. Nowe pola.

### Pliki
`apps/admin/src/features/catalog/modeling/layout.tsx`, `apps/admin/src/components/modeling/*` (pliki z violet + nagłówki), `apps/admin/src/features/catalog/{object-types,attributes,attribute-groups,categories}/*` (restyle list/show), `locales/{pl,en}.json`, e2e.

### Kryteria akceptacji
- `grep violet` w obrębie modelowania = 0 trafień (poza ewentualnymi semantycznymi wyjątkami opisanymi w PR).
- Smoke per tab: lista → detail → edycja pola → zapis. Istniejąca suita speców modelowania zielona.

**Estymata:** 10–16 h. **Zależności:** brak.

---

## NUI-08 — feat(admin/assets): Multimedia v2 — eksplorator plików

### Kontekst
`Multimedia.html` to pełna przebudowa: lewa szyna folderów („Wszystkie zasoby" + foldery z licznikami + „Bez przypisania" z badge ⚠), pill-filtry typów (Wszystkie/Zdjęcia/PDF/Wideo), search, przełącznik grid/lista, breadcrumb folderu + strzałka w górę, kafle folderów, karty assetów (miniatura tintowana per typ, checkbox na hover, nazwa, format mono, rozmiar, licznik powiązań, status), **drawer szczegółów 460px** (podgląd, akcje, metadane, URL CDN, powiązane produkty), modal uploadu, dolny pasek bulk, pasek magazynu. Obecny widok (§3.5) ma foldery/grid/upload/bulk — układ całkowicie inny.

### Zakres — tabela WIRE/MOCK/SKIP (wiążąca)

| Element designu | Klasyfikacja | Realizacja |
|---|---|---|
| Szyna folderów: „Wszystkie zasoby" + płaska lista z licznikami + „Bez przypisania" | **WIRE** | `GET /api/asset-folders` (+ `folder=root`); zagnieżdżenia z mocka (audio/laptopy/… pod Produkty) NIE odtwarzamy — płaska lista wg realnych folderów |
| Zagnieżdżone foldery (drzewo) | SKIP | backend płaski → backlog `multimedia-do-oprogramowania.md` |
| Pill-filtry typów + search | **WIRE** | istniejący `AssetFilterBar` (mimeGroup all/images/documents/video — mapowanie labeli: Zdjęcia/PDF→Dokumenty/Wideo), restyle |
| Grid ↔ lista | **WIRE (nowe FE)** | toggle z persystencją w localStorage |
| Kafle folderów + breadcrumb + strzałka w górę | **WIRE** | nawigacja po płaskiej liście (root ↔ folder) |
| Karty assetów (miniatura, format mono, rozmiar, status) | **WIRE** | dane z listy assetów; rozmiar/format z `attributesIndexed` (weryfikacja dostępnych pól w tickecie); polling miniatur zostaje |
| Licznik „powiązane produkty" na karcie + sekcja w drawerze | MOCK/WIRE | w tickecie zweryfikować czy API zwraca powiązania asset→produkt; jest → WIRE, brak → `MockBadge` + backlog |
| Drawer 460px: podgląd, Pobierz, Usuń, metadane, URL + kopiuj | **WIRE** | nowy komponent; link „Otwórz pełną stronę" → istniejący `/assets/:id` |
| Akcja/status Zatwierdź (approve) | MOCK | brak workflow approve → `MockBadge` + backlog |
| Pasek magazynu (142/500 GB) | MOCK | brak quota API → `MockBadge` + backlog |
| Bulk: Usuń | **WIRE** | istnieje (`AssetBulkActionsBar`) |
| Bulk: Przypisz (do folderu) | **WIRE** | per-asset PATCH atrybutu folderu (weryfikacja ścieżki PATCH w tickecie; jeśli niewykonalne bez BE → MOCK + backlog) |
| Bulk: Pobierz (zip) | MOCK | brak endpointu zip → przycisk disabled z tooltipem + backlog |
| Modal uploadu (formaty, limit, notka o miniaturach) | **WIRE** | restyle `AssetUploadDropzone`; realne limity/formaty z obecnej walidacji (nie kopiować „50 MB" z mocka jeśli realny limit inny — patrz spec e2e #1214 large-pdf) |

- [ ] Przebudowa `features/asset/assets/list.tsx` na układ eksploratora; nowe komponenty: `FolderRail`, `AssetCard`, `AssetListRow`, `AssetDrawer`, `UploadModal`; reuse logiki z istniejących `AssetFilterBar`/`AssetBulkActionsBar`/`AssetDuplicateDialog`.
- [ ] Utworzyć backlog `Project Plan/UI/Retrofit_v2/multimedia-do-oprogramowania.md` (wpisy wg tabeli).

### Poza zakresem
Nowe endpointy. Zmiany w uploadzie/duplikatach poza stylem. Strona `/assets/:id` (zostaje; tylko link z drawera).

### Pliki
`apps/admin/src/features/asset/assets/list.tsx` (przebudowa) + nowe komponenty w `features/asset/assets/components/`, `locales/{pl,en}.json`, backlog md, e2e.

### Kryteria akceptacji
- E2E: nawigacja folderów (root → folder → up), filtr mime, otwarcie drawera + metadane, upload happy-path, bulk delete, toggle grid/lista.
- Mocki (approve, magazyn, zip) z widocznym `MockBadge`/disabled.
- Manual smoke na żywym stacku z realnymi plikami (obraz + PDF).

**Estymata:** 20–28 h. **Zależności:** brak.

---

## NUI-09 — feat(admin/imports): hub Importów v2 — wyjście z IntegrationsLayout, PillTabs, sesje live + historia

### Kontekst
Eksporty po EXR-08 żyją bezpośrednio w shellu v2; importy zostały w legacy `IntegrationsLayout` (komentarz w `App.tsx:528`). Design (`Integracje.html` + `importy-*.jsx`) pokazuje hub z sub-tabami **Sesje / Profile mapowań / Źródła / Harmonogram** (z licznikami), widok sesji: pasek KPI, karty sesji live z 6-fazowym pipeline (shimmer), tabela historii (format pill, result bar, sparkline).

### Zakres
- [ ] Przenieść `/integrations/imports/*` poza `IntegrationsLayout` — dokładny wzorzec EXR-08 z `App.tsx`; `ImportsLayout` na `PageHeader` + `PillTabs` z **realnymi licznikami** (totalItems per zasób, wzorzec liczników EXR).
- [ ] Widok Sesje: KPI strip (`KpiCard`), karty live z `StagePipeline`/shimmer (komponent istnieje w `features/imports/primitives/` — skonsolidować z `ui-v2` gdzie identyczny), tabela historii (`FormatPill`, `ResultBar`, `Sparkline`, `StatusPill` ze `status-maps`).
- [ ] Restyle pozostałych tabów do designu: Profile mapowań (karty/lista jak `importy-profiles.jsx`), Źródła (karty z `HealthDot` + test połączenia — istnieje), Harmonogram (lista + `NextRunsTimeline` — restyle).
- [ ] Przenieść `api-configurator` do shellu v2 i **usunąć `IntegrationsLayout`** (redirect indeksu `/integrations` bez zmian funkcjonalnych).
- [ ] Breadcrumb topbar: `Workspace / Integracje / Importy` (PageActionsContext — wzorzec EXR).

### Poza zakresem
Wizard (NUI-10), widok sesji (NUI-11). Zmiany w endpointach. Eksporty (D2).

### Pliki
`apps/admin/src/App.tsx`, `apps/admin/src/features/imports/layout/ImportsLayout.tsx`, `features/imports/sessions/ImportSessionsView.tsx` + komponenty, `features/imports/{profiles,sources,schedule}/*` (restyle), `features/integration-hub/IntegrationsLayout.tsx` (delete) + przepięcie api-configuratora, e2e.

### Kryteria akceptacji
- Taby z licznikami; deep-linki `/integrations/imports/{sessions,profiles,sources,schedule}` działają; `/integrations` redirect bez regresji; api-configurator osiągalny.
- Istniejące specki importów zielone; E2E nawigacji tabów.
- Manual smoke: wejście w każdy tab na żywym stacku.

**Estymata:** 12–18 h. **Zależności:** brak (równoległy z resztą fali 0).

---

## NUI-10 — feat(admin/imports): wizard importu v2 — 6 kroków na istniejącym backendzie

### Kontekst
`Import-nowy.html`: kreator 6 kroków — Źródło → Wykrywanie → Mapowanie → Reguły → Podgląd → Start. Obecny wizard ma 4 kroki (Upload → Mapping → Validation → Confirm) i **działa produkcyjnie end-to-end**. Przebudowa = re-aranżacja istniejących możliwości + nowe elementy UI; **endpointy i payloady bajt w bajt jak dziś**.

### Zakres — mapa kroków (wiążąca)

| Krok designu | Klasyfikacja | Realizacja |
|---|---|---|
| **1. Źródło** — strefa uploadu (CSV/XLSX/XLS, kodowania) | **WIRE** | istniejący `FileDropzone`/`StepUpload`; realne limity i lista kodowań z enuma `FileEncoding` (nie kopiować wartości z mocka) |
| 1. Źródło — zapisane źródła FTP/SFTP z health-dot + test | **WIRE** | lista źródeł + `TestImportSourceConnectionController`; wybór źródła jako kafel |
| 1. Źródło — „uruchom import z tego źródła" (ad-hoc) | MOCK | brak endpointu ad-hoc run (jest tylko `run-now` na harmonogramie) → kafel z `MockBadge` + link do Harmonogramu; backlog |
| 1. Źródło — prawa szyna „Sugerowane profile" | **WIRE (client-side)** | ranking istniejących profili po pokryciu nagłówków z parse-preview (czysty FE) |
| **2. Wykrywanie** — tabela detekcji: format/encoding/delimiter/nagłówek + podgląd 5 wierszy | **WIRE** | `ParsePreviewController` zwraca `encoding, delimiter, headers, sample_rows, total_rows, sheet_name, had_multiple_sheets` |
| 2. Wykrywanie — detekcja separatora dziesiętnego / formatu dat / line-endings / quote char | SKIP | backend tego nie wykrywa — nie fabrykujemy wyników detekcji |
| 2. Wykrywanie — wybór arkusza XLSX (radio) | MOCK | backend parsuje pierwszy arkusz; przy `had_multiple_sheets` baner info + disabled radio z `MockBadge`; backlog (param wyboru arkusza) |
| 2. Wykrywanie — obsługa pustych komórek (radio: null/pusty string/default) | MOCK | brak parametru w backendzie → disabled + `MockBadge`; backlog |
| **3. Mapowanie** — kolumny pliku → atrybuty PIM | **WIRE** | istniejący `StepMapping` + `AutoMapController` (sugestie), round-trip tworzenia atrybutu (`persist()/restore()`) — restyle do układu dwóch kolumn z designu |
| 3. Mapowanie — modal „Nowa kolumna obliczona" (konkatenacja, separatory, podgląd) | MOCK | brak wsparcia BE → modal renderuje się z live-preview na sample_rows, przycisk Zastosuj disabled + `MockBadge`; backlog |
| **4. Reguły** — tryb insert/update, przełączniki walidacji, strategia duplikatów | MOCK + karta prawdy | karta opisująca REALNE zachowanie silnika (upsert po identyfikatorze) + przełączniki disabled z `MockBadge`; backlog (tryby, strategie skip/overwrite/create-variant) |
| **5. Podgląd** — dry-run, próbka, podsumowanie błędów | **WIRE** | istniejący `ValidateDryRunController` + `StepValidation` (KPI + modal błędów), restyle |
| **6. Start** — potwierdzenie, backup, uruchom | **WIRE** | istniejący `StepConfirm` (checkbox backupu pgBackRest, powiadomienie e-mail jeśli jest) → `StartImportController` → redirect do widoku sesji |

- [ ] Rozszerzyć `useImportWizard` (`WizardStepIndex` 0..5) **addytywnie** — istniejące pola stanu i `persist()/restore()` bez zmian łamiących (deep-link powrotu z tworzenia atrybutu musi przeżyć).
- [ ] Stepper: `ui-v2/WizardStepper` (jak EXR-09) zamiast lokalnego.
- [ ] Utworzyć backlog `Project Plan/UI/Retrofit_v2/importy-do-oprogramowania.md` (wpisy wg tabeli).

### Poza zakresem
Zmiany w backendzie importu. Hub (NUI-09), widok sesji (NUI-11). Import ZIP ze zdjęciami — zostaje jak jest (obecny upload wspiera; tylko restyle).

### Pliki
`apps/admin/src/features/imports/wizard/` (`ImportWizardPage.tsx`, nowe `StepSource.tsx`/`StepDetect.tsx`/`StepRules.tsx`, restyle `StepMapping/StepValidation/StepConfirm`), `features/imports/hooks/useImportWizard.ts`, `locales/{pl,en}.json`, backlog md, e2e.

### Kryteria akceptacji
- **E2E happy-path pełnego flow:** seedowany CSV → krok 1 upload → krok 2 wartości detekcji widoczne (encoding/delimiter z parse-preview) → krok 3 automap + ręczne mapowanie → krok 4 (karta prawdy) → krok 5 dry-run KPI → krok 6 commit → redirect do sesji → sesja kończy się sukcesem.
- Wszystkie mocki z `MockBadge`; żaden mock nie wysyła requestów.
- Payload `StartImportController` identyczny jak przed zmianą (asercja w spece/teście).
- **Merge bramkowany manual live smoke** (realny plik na `pim.localhost`) per SMOKE TEST RULE.

**Estymata:** 20–28 h. **Zależności:** NUI-09.

---

## NUI-11 — feat(admin/imports): widok sesji importu v2 — fazy, live log, podsumowanie

### Kontekst
`Import-sesja.html`: header (status/czas trwania/%), timeline faz, live log strumieniowany, podsumowanie (utworzone/zaktualizowane/pominięte/błędy). Obecny `ImportShowPage` ma surowszy układ; Mercure SSE działa (`useImportProgress`: `progress|row_processed|error|completed`).

### Zakres
- [ ] Header wg designu: nazwa, `StatusPill`, czas trwania, % (z GET sesji + live z Mercure), akcje (anuluj jeśli wspierane — weryfikacja w tickecie; rollback — istnieje `RollbackButton`; pobierz raport CSV — `ImportReportCsvController`).
- [ ] Timeline faz: `StagePipeline` **uczciwie wyprowadzony ze statusu sesji** (backend nie ma timestampów per faza → stany done/active/pending bez czasów; per-phase timestamps → backlog `importy-do-oprogramowania.md`).
- [ ] **WIRE live log:** strumień wpisów z eventów Mercure `row_processed`/`error` (bufor ograniczony, np. ostatnie 200 wpisów; poziomy info/warn/error kolorowane jak w designie); rozszerzyć `useImportProgress` o ekspozycję bufora logów (addytywnie).
- [ ] Podsumowanie po zakończeniu: liczniki + `ResultBar` (dane z GET sesji).

### Poza zakresem
Backend (timestampy faz, persystencja logu). Hub/wizard.

### Pliki
`apps/admin/src/features/imports/show/ImportShowPage.tsx`, `features/imports/hooks/useImportProgress.ts` (rozszerzenie addytywne), primitives, `locales/{pl,en}.json`, e2e.

### Kryteria akceptacji
- Fixture zakończonej sesji: render headera, faz, podsumowania, działający download raportu.
- Live: test jednostkowy hooka z mockowanym EventSource (bufor logów, statusy) + manual smoke na żywym imporcie.
- Istniejące specki sesji zielone.

**Estymata:** 10–16 h. **Zależności:** NUI-09.

---

## NUI-12 — feat(admin/settings): restyle Użytkownicy + Role i uprawnienia do v2

### Kontekst
Design `settings/users.jsx` + `roles.jsx`: lista użytkowników (rola, ostatnie logowanie, status, zaproszenie), edytor użytkownika, lista ról (szablony systemowe vs custom z licznikami), edytor roli z macierzą uprawnień. Strony istnieją i działają (RBAC Phase 5) — **restyle bez zmian logiki**. Po NUI-01 strony renderują się pełną szerokością (bez drugiego sidebara).

### Zakres
- [ ] `features/settings/users/*`: lista (typografia tabeli v2, `StatusPill` dla statusów, chipy ról), modale (Zaproś/Dodaj ręcznie/Dezaktywuj — restyle), strona detalu użytkownika — układ wg designu.
- [ ] `features/settings/roles/*`: sekcje „Szablony systemowe" (🔒) vs „Role własne", karty/wiersze z licznikami użytkowników, edytor macierzy uprawnień (restyle siatki checkboxów do rytmu designu), przycisk „Nowa rola".
- [ ] Dostosowanie do pełnej szerokości po NUI-01 (max-width treści jak w designie).
- [ ] **Zero zmian:** w wywołaniach API, strukturze uprawnień, walidacjach, flow zaproszeń.

### Poza zakresem
Pozostałe podstrony ustawień (Security/SSO/Tokens/… — obecny stan zostaje; pełny restyle = przyszły epik, notka w §8). Funkcje nowe (np. „Testuj rolę" z designu — SKIP + notka w §8).

### Pliki
`apps/admin/src/features/settings/users/*`, `apps/admin/src/features/settings/roles/*`, `locales/{pl,en}.json`, e2e.

### Kryteria akceptacji
- Istniejące specki RBAC P5 (users/roles) zielone bez zmian semantyki.
- E2E: lista użytkowników renderuje dane, otwarcie edytora roli, toggle uprawnienia + zapis (istniejący flow).
- Manual smoke: zaproszenie użytkownika (lub edycja istniejącego) na żywym stacku.

**Estymata:** 10–16 h. **Zależności:** NUI-01.

---

## NUI-13 — chore(admin): bramka jakości epiku NUI — E2E sweep, a11y, i18n, dead code, docs

### Kontekst
Analogia EXR-16: domknięcie epiku jedną bramką jakości zanim ogłosimy „done".

### Zakres
- [ ] Pełna suita Playwright na czystym stacku (wszystkie specki, nie tylko nowe).
- [ ] axe-core pass na widokach epiku: dashboard, lista+karta produktu, modelowanie (4 taby), Multimedia, hub+wizard+sesja importów, Users/Roles, sidebar/paleta — naprawa naruszeń serious+.
- [ ] i18n sweep: zero literałów user-facing poza `t()` w plikach dotkniętych epikiem.
- [ ] Dead-code sweep: `IntegrationsLayout` (usunięty w NUI-09 — weryfikacja braku referencji), `HeroAgentPanel`/`ChannelDistribution`/`CompletenessMetrics` (NUI-02), legacy lista (NUI-05), resztki `violet` w zakresie de-violet (NUI-01/07), nieużywane prymitywy w `features/imports/primitives` po konsolidacji.
- [ ] Screenshoty wszystkich widoków epiku do `Project Plan/UI/Retrofit_v2/screens-final/` + aktualizacja `Project Plan/UI/00-plan-ui.md` (linki) + CHANGELOG.
- [ ] Macierz smoke close-out: per widok — login → trigger → status 200 → wynik widoczny → konsola czysta (SMOKE TEST RULE).

### Poza zakresem
Nowe funkcje. Naprawy spoza zakresu epiku (nowe issues).

### Pliki
e2e, dotknięte widoki (naprawy), docs.

### Kryteria akceptacji
- CI zielone na pełnej suicie; raport axe bez serious/critical na widokach epiku; macierz smoke w komentarzu zamykającym epik.

**Estymata:** 8–14 h. **Zależności:** wszystkie pozostałe tickety.

---
---

## 6. Quality gates (per ticket — definicja „Done")

1. `tsc` (z `NODE_OPTIONS="--max-old-space-size=4096"`), Biome, Vite build — zielone.
2. Playwright: nowe specki per widoczna zmiana (konwencja `<issue>-<slug>.spec.ts` w `apps/admin/e2e/`) + istniejąca suita bez regresji.
3. **SMOKE TEST RULE**: manual live smoke na `pim.localhost` przed użyciem słowa „działa" w PR; **CLOSED MEANS CLOSED**: proof (HTTP code / screenshot) w komentarzu zamykającym issue.
4. Mocki: `MockBadge` + komentarz `MOCK:` + wpis w backlogu — sprawdzane na code review (checklist w PR).
5. PR opis po polsku; commit messages po angielsku (Conventional Commits), bez wzmianek o tooling'u AI.

## 7. Ryzyka i mitygacje

| # | Ryzyko | Mitygacja |
|---|---|---|
| 1 | **Wspólna powierzchnia universal list/detail** — retrofit psuje custom OT albo universal detail | Zmiany czysto prezentacyjne (zakaz zmian props/payload); E2E na `/products` ORAZ `/objects/:slug` (+ `?universal=1` dla detalu); istniejące specki jako merge gate |
| 2 | **Restrukturyzacja nav Ustawień** — deep-linki, RBAC gating, reflow 11 podstron | Drzewo routingu nietknięte; poddrzewo z tych samych `NAV_GROUPS` + `isMenuRefVisible`; macierz E2E deep-linków; wizualny smoke wszystkich podstron w NUI-01 |
| 3 | **Przebudowa działającego wizarda importu** | Endpointy/payloady bajt w bajt; stan wizarda rozszerzany addytywnie; E2E happy-path + obowiązkowy live smoke przed merge |
| 4 | **MOCK creep** — wysokiej wierności mocki (heatmapa backupu, kolumny obliczane, strategie duplikatów) kuszą częściowym podpinaniem | Wiążące tabele WIRE/MOCK/SKIP; `MockBadge` + backlog per mock; pozycja w checklist PR |
| 5 | **Wizualny churn vs ~78 speców E2E** | Selektory rolowe/tekstowe (nie klasy); pełna suita per ticket w CI; budżet na naprawę speców wliczony w estymaty; NUI-13 jako finalny sweep |

## 8. Notki poza zakresem epiku (świadome decyzje)

- **Kolejność pozycji menu**: design pokazuje Dashboard/Produkty/Modelowanie/Integracje/Multimedia/Ustawienia; obecny default rejestru menu różni się (Catalogs PDF, Workflow). Operator zarządza kolejnością przez `/settings/menu` — zmiana domyślnego rejestru (BE) świadomie poza epikiem.
- **Pozostałe podstrony Ustawień** (Security, SSO, Tokens, Locales, Channels, AI, Tenant, Billing) — restyle poza zakresem (D1 obejmuje Users+Roles); przyszły epik.
- **„Testuj rolę"** (design roles.jsx) — funkcja nieistniejąca; SKIP, kandydat do backlogu RBAC.
- **Agent ⌘K — realne wykonanie zadań** — Faza 2 (epik 0.7); paleta NUI-03 zostawia sekcję agenta jako MOCK.
- **Eksporty** — D2; ewentualne rozjazdy z `Eksport-nowy.html` wychwytują smoke testy, bez dedykowanego ticketu.
- **`design_handoff_modelowanie/`** — bundle z własnym CLAUDE.md/tasks.md zakłada stack Next.js + Drizzle — **ignorujemy wskazania stackowe**, obowiązuje stack repo; bundle służy tylko jako opis intencji designu (sekcje Screens/Interactions/Design System).

---

## 9. Korekta po realizacji (NUI-13, 2026-06-12) — odstępstwa od planu

Epik zrealizowany w 13/13 ticketach (issues #1420–#1432, PR #1444–#1449, #1451–#1457 + bramka). Wiążące odstępstwa od sekcji ticketowych:

1. **§NUI-02 (dashboard)**: plan nakazywał usunięcie `HeroAgentPanel`/`ChannelDistribution`/`CompletenessMetrics` jako „nieobecnych w designie" — **błąd eksploracji planu**: `Dashboard.html` zawiera Hero (CTA agenta), CompletenessGauge i ChannelDistribution. Widgety ZOSTAŁY; usunięto tylko sztuczne opóźnienie skeletonów i martwe komponenty skeletonów.
2. **§NUI-08 (Multimedia)**: plan opisywał „lewą szynę folderów" — design nie ma osobnej szyny (nawigacja = kafle folderów + path bar ze strzałką w górę). Zaimplementowano wg designu. Dodatkowo: nowa semantyka roota („Wszystkie zasoby" = wszystkie pliki, bez parametru folder; pseudo-folder „Bez przypisania" = `folder=root`).
3. **§NUI-06 (karta produktu)**: zakres strukturalny dostarczyła równoległa seria #1434 (unifikacja detalu) + #1351/#1440/#1442 — `universal-detail-page.tsx` usunięty, więc ekstrakcja `DetailHeaderV2` bezprzedmiotowa. Ticket domknięty weryfikacją parytetu + spec-em strażniczym `1425-product-detail-v2.spec.ts` (zero restyle'u — najgoręcej bugfixowana powierzchnia).
4. **§NUI-07 (modelowanie)**: dwa świadome wyjątki od violet→orange: aktywny tab = podkreślenie ink/czarne (spec tabów designu), `CompletenessRing` <50% = rose (semantyka błędu). Hue-coded palety (typy atrybutów, moduły RBAC CoverageStrip/PermissionMatrix, odcienie awatarów, `ScopePill`) świadomie zachowują wielobarwność — to kodowanie kategorii, nie akcent.
5. **§NUI-10 (wizard)**: lokalny `WizardStepper` importów ZOSTAŁ (jest pixel-perfect ze stepperem designu — emerald done / dark active / hint line); ui-v2 stepper ma inną stylistykę. Spec happy-path odsłonił **pre-existing bug backendu widoczny tylko w CI**: 500 FK `objects_import_session_fk` na ścieżce inline-commit → issue **#1455** (spec skipuje wyłącznie na tej sygnaturze).
6. **§NUI-03 (⌘K)**: zamiast rozszerzać paletę listy (ryzyko regresji VIEW-19) — osobny `GlobalCmdK`; na trasach universal listy skrót zostaje przy palecie kontekstowej. Modal „Plan zmian" pominięty (sekcja agenta disabled do epiku 0.7).
7. **§NUI-12 (Users/Roles)**: weryfikacja wykazała, że RBAC P5 zbudował strony 1:1 z mockupów — realna delta = de-violet 15 plików ustawień.
8. **NUI-13 a11y (nowe, poza planem)**: bramka axe wymusiła **przyciemnienie tokenów akcentów** w `index.css` (accent-emerald/rose/amber/blue/sky/zinc → odcienie AA-safe na tintach /10 przy 10–11px) oraz globalny sweep `text-zinc-400`→`text-zinc-500` dla tekstów (65 plików; placeholdery bez zmian); `role="grid"` listy produktów zastąpiony semantyczną `<section>` (wzorzec ARIA grid wymaga keyboard nav, której nie implementujemy). Trwała bramka: `e2e/nui-13-a11y.spec.ts` (10 widoków, serious+critical = 0).
