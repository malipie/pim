# Epik EXR — Przemodelowanie Eksportów + nowy look & feel (pierwsza funkcjonalność w nowym designie)

> **Status:** backlog do założenia jako GitHub Issues (label `epik-EXR`, jeden ticket = jeden issue = jeden branch = jeden PR).
> **Data utworzenia:** 2026-06-09. **Autor wytycznych:** Marcin (operator). **Spisał:** agent Cowork.
> **Tryb pracy:** EPIK MARATHON RULE z `CLAUDE.md` obowiązuje. SMOKE TEST RULE i CLOSED MEANS CLOSED RULE obowiązują dla każdego ticketu.

---

## 0. Cel biznesowy

Przebudować moduł eksportu danych PIM na skalowalny, asynchroniczny silnik obsługujący zarówno małe paczki (≤100 rekordów, sync, natychmiastowy download), jak i masowe zrzuty (100 000+ rekordów, async + progress). Równolegle: **eksporty są pierwszym modułem wdrażającym nowy look & feel całego PIM** — wraz z przebudową menu (drugi poziom nawigacji) i fundamentem nowego design systemu. Pozostałe moduły będą sukcesywnie migrowane do nowego wyglądu w kolejnych epikach.

Wymagania krytyczne (z wytycznych operatora):
1. **Reużywalność** — zakaz pisania nowej logiki wyszukiwania; eksport osadza komponent zaawansowanej wyszukiwarki z listy produktów (Single Source of Truth dla filtrowania).
2. **Dynamiczny schemat (EAV)** — zakaz hardkodowanych mapowań atrybutów; silnik eksportu czyta konfigurację schematu dynamicznie; nowy typ atrybutu eksportuje się bez zmian w kodzie eksportu.
3. **Zarządzanie pamięcią** — zakaz wczytywania 100k rekordów do RAM; kursory/iteratory DB + streamowy zapis plików.
4. **Inteligentny routing sync/async** — count przed eksportem; ≤100 → sync (stream HTTP, UI loader); >100 → job w kolejce, `jobId`/sesja, UI z progresem i pollingiem/streamem.

---

## 1. Źródła prawdy

| Co | Gdzie |
|---|---|
| Design — wizard eksportu (4 kroki) | `Zrodla/Front_Claude_Design/NOWY UI/PIM-nowoczesny/Eksport-nowy.html` (samodzielny HTML, Tailwind CDN) |
| Design — layout sesji/importów, primitives, mock data | `Zrodla/Front_Claude_Design/NOWY UI/PIM-nowoczesny/integracje/*.jsx` (`importy-sessions.jsx`, `importy-profiles.jsx`, `importy-schedule.jsx`, `importy-sources.jsx`, `primitives.jsx`, `placeholders.jsx`, `data.jsx`) |
| Screeny referencyjne (5 ekranów) | `Zrodla/Front_Claude_Design/NOWY UI/PIM-nowoczesny/screens/` — mapowanie numerów „screen N" używanych w ticketach: **screen 1** = `screen-01-eksporty-sesje.png` (strona Eksporty: taby, KPI, W toku, Historia), **screen 2** = `screen-02-wizard-krok1-typ.png` (Krok 1: kafelki encji), **screen 3** = `screen-03-wizard-krok2-zakres-format.png` (Krok 2: profil, formaty, query builder), **screen 4** = `screen-04-wizard-krok3-kolumny.png` (Krok 3: two-pane picker), **screen 5** = `screen-05-wizard-krok4-podsumowanie.png` (Krok 4: podsumowanie + uruchom). Screeny są nadrzędne wobec HTML przy rozbieżnościach układu strony Eksporty (HTML nie zawiera widoku sesji — patrz uwaga niżej) |
| PRD eksportów (decyzje bazowe) | `Project Plan/PRD/PRD-PIM-exports.md` |
| Obecny backend eksportów | `apps/api/src/Export/` (pełen inwentarz w §3) |
| Obecny frontend eksportów | `apps/admin/src/features/exports/` |
| Wyszukiwarka listy produktów | `apps/admin/src/components/catalog/advanced-filter-panel.tsx` + `apps/admin/src/lib/filters/` (filter-dsl, url-serializer, use-smart-presets) |
| Sidebar / menu | `apps/admin/src/layout/sidebar-nav.tsx` |
| Theming | `apps/admin/src/index.css` (CSS variables, Tailwind v4 theme, font Inter) |

**UWAGA dla agenta:** design w `Eksport-nowy.html` pokazuje encję „Usługi" w sidebarze i liczniki przy pozycjach — to mock data. W implementacji pozycje typu „Usługi" pochodzą dynamicznie z `ObjectType.show_in_main_menu` (wzorzec Epiku UP), a liczniki z realnych endpointów (szczegóły w EXR-03).

---

## 2. Decyzje operatora (2026-06-09) — zakres NIENEGOCJOWALNY

| # | Decyzja |
|---|---|
| D1 | **Formaty:** implementacja TYLKO CSV + XLSX. Kafelki XML / JSON / Google Sheets / PDF widoczne w UI jako mock z labelem **„wkrótce"** (disabled, nie wysyłają się w payloadzie). |
| D2 | **Encje eksportu — wszystkie 5 DZIAŁAJĄCE:** Produkty (pełny konfigurator z filtrami), Moduły własne (treści custom ObjectType), Schemat modułów (definicje), Atrybuty i Grupy, Kategorie. Encje strukturalne mają uproszczoną ścieżkę (bez query buildera). |
| D3 | **Strona Eksporty — taby:** Sesje + Profile Eksportu w pełni działające. **Cele** i **Harmonogram** jako taby widoczne ale disabled z labelem „wkrótce" (osobny epik później). |
| D4 | **Nowy look & feel:** eksporty = pierwszy moduł w nowym designie. Menu/sidebar przebudowane OD RAZU globalnie (w tym rozwijane menu II poziomu). Fundament tokenów globalny; pozostałe widoki migrowane w przyszłych epikach (stary wygląd pozostałych modułów po podmianie tokenów ma pozostać używalny — patrz EXR-01). |
| D5 | Krok konfiguracji eksportu = **pełny widok (strona), nie modal**. Istniejący `ExportModal` zostaje wycofany (EXR-14). |
| D6 | Tickety → GitHub Issues robi agent kodujący na podstawie tego pliku. |

---

## 3. Stan zastany (inwentarz — co JUŻ istnieje i czego NIE wolno duplikować)

### 3.1 Backend `apps/api/src/Export/` — silnik w dużej mierze GOTOWY

- **Encje:** `Domain/Entity/ExportSession.php`, `ExportProfile.php`, `ExportLog.php` + repozytoria Doctrine.
- **Enumy:** `ExportStatus`, `ExportTargetScope` (`selected|filter|all`), `ExportEncoding`, `ExportSource` (`list_context|central_tab|saved_profile_run`), `ExportFormat` (**tylko `xlsx|csv`** — zgodne z D1), `ExportLogLevel`.
- **Routing sync/async JUŻ ZAIMPLEMENTOWANY:** `Presentation/Controller/SyncExportController.php` — `SYNC_THRESHOLD = 100`, `SOFT_CAP = 100_000`; `target_count < 100` → sync stream, `>= 100` → `ExportSession` + `RunExportMessage` do Messengera (`Application/Async/ExportJobHandler.php`, progress przez `ExportProgressPublisher.php` → Mercure).
- **Streaming JUŻ ZAIMPLEMENTOWANY:** `Infrastructure/Writer/CsvStreamWriter.php`, `XlsxStreamWriter.php`, `RowWriter.php`; chunking + `EntityManager::clear()` w handlerze (wzorzec `AbstractBatchHandler` per CLAUDE.md). Benchmark: `apps/api/src/Benchmark/Export/ExportBenchmarkCommand.php`.
- **Dynamiczne kolumny JUŻ SĄ (dla produktów):** `Application/Builder/ColumnResolver.php` (`resolve()`, `resolveOne()` z fan-outem locale/channel), `ColumnDefinition.php`, `ValueSerializer.php`, `ExportBuilder.php`, `PublicationColumnPlanner.php`.
- **Filtry:** backend rozwiązuje FilterDSL przez `FilterDslResolver::toCountSql` (ten sam DSL co lista produktów) — patrz `SyncExportRunner::resolveFilter`.
- **API:** `ExportSessionController.php` (lista/szczegół/run, `#[RequiresPermission(module: 'exports', action: 'view_all'|'run')]`), `ExportProfileController.php` (CRUD + `POST /api/exports/profiles/{id}/run`, `run_count`).
- **RBAC:** moduł `exports` z akcjami `view_all`, `run` (+ `integration.admin` dla operacji administracyjnych) — reuse, nie tworzyć nowych permissionów bez potrzeby.

**Wniosek:** wymagania „architektura i workflow" ze specyfikacji operatora są w ~80% spełnione po stronie BE dla encji *product*. Praca backendowa = generalizacja na 5 typów encji (EXR-04..06), preflight count (EXR-07) i weryfikacje/benchmark — NIE przepisywanie silnika.

### 3.2 Frontend `apps/admin/src/features/exports/` — do PRZEMODELOWANIA

- `layout/ExportsLayout.tsx`, `sessions/ExportSessionsView.tsx`, `profiles/ExportProfilesView.tsx` — istnieją, stary wygląd.
- `wizard/ExportNewPage.tsx` — **wklejanie FilterDSL jako JSON** (świadome odejście z EXP-20; chip-builder był deferred → TEN epik go realizuje przez reuse panelu).
- `wizard/ExportModal.tsx` — 3-krokowy modal z listy produktów (`target_scope: selected|filter|all`) — do wycofania (D5, EXR-14).
- `components/ColumnPicker.tsx` + `components/use-export-column-catalog.ts` — katalog kolumn z grupami, fan-out locale/channel — **hook zostaje źródłem danych**, UI do przebudowy.
- `hooks/useExportSessionsStream.ts` — live stream sesji (Mercure/SSE) — reuse w EXR-08/15.
- Routing: `/integrations/exports/{sessions,profiles,new}`, `/integrations/exports/sessions/:id` (definicje w `apps/admin/src/App.tsx`).

### 3.3 Wyszukiwarka listy produktów (Single Source of Truth)

- `apps/admin/src/components/catalog/advanced-filter-panel.tsx` — komponent panelu warunków (lazy-loadowany w `universal-list-page.tsx`).
- `apps/admin/src/lib/filters/filter-dsl.ts` — typy + budowa DSL; `url-serializer.ts` (`dslToBase64`); `use-smart-presets.ts` (zapisane widoki).
- Stan filtrów żyje dziś w `apps/admin/src/components/objects/universal-list-page.tsx` (`useState` + złożenie do `searchFilters`).
- `apps/admin/src/components/catalog/saved-views-rail.tsx`, `attribute-picker.tsx` — komponenty towarzyszące.

### 3.4 Sidebar / menu

- `apps/admin/src/layout/sidebar-nav.tsx` — **płaska lista** pozycji (`nav.dashboard`, `nav.catalogsPdf`, `nav.multimedia`, `nav.workflow`, `nav.integrations`, `nav.settings`, `nav.modeling`) + dynamiczne ObjectType. Brak menu II poziomu — Integracje to dziś pojedynczy link.

### 3.5 Theming

- `apps/admin/src/index.css` — Tailwind v4, CSS variables (`--primary`, `--ink`, `--font-sans: Inter...`), brak warstwy tokenów odpowiadającej nowemu designowi.

### 3.6 Prefiksy ticketów już zajęte (nie kolidować)

`EXP` (stary epik eksportów), `IMP`, `LC`, `UP`, `MOD`, `MODR`, `MODRC`, `CHC`, `ULV`, RBAC #640-#728. **Ten epik używa prefiksu `EXR`.**

---

## 4. Nowy design system — tokeny (wyciąg z `Eksport-nowy.html`, źródło prawdy dla EXR-01)

**Czcionki:** Inter (300/400/500/600/700/800) — już jest; **JetBrains Mono** (400/500) dla wartości technicznych (kody, liczby w tabelach, breadcrumb „krok 1 z 4").

**Paleta (Tailwind override z designu):**

| Token | Wartości |
|---|---|
| `zinc` (granatowy neutral — tła, teksty, sidebar) | 50 `#f4f6fa` · 100 `#e9edf4` · 200 `#d7dfeb` · 300 `#b4c1d5` · 400 `#7f8ea9` · 500 `#5b6b87` · 600 `#43536e` · 700 `#2f405a` · 800 `#1d2c47` · 900 `#16233f` · 950 `#0e1830` |
| `orange` (akcent / CTA; w designie `violet` jest zmapowany na te same wartości) | 50 `#fef4ec` · 100 `#fde3d2` · 200 `#fbc6a4` · 300 `#f6a36f` · 400 `#f3823f` · 500 `#ef6a1f` · 600 `#df5b16` · 700 `#b9491a` · 800 `#933c19` · 900 `#5f2a14` · 950 `#3a1709` |
| `brick` (błędy/err w ResultBar) | 50 `#fcefec` · 100 `#f8d9d1` · 200 `#eda99a` · 300 `#e07e69` · 400 `#d15a42` · 500 `#c0432b` · 600 `#a8371f` · 700 `#8c2c18` · 800 `#6f2415` · 900 `#4d1a10` |
| `emerald` (sukces) | 50 `#ecf5f0` · 100 `#d2e9dd` · 200 `#a6d4bd` · 300 `#73b896` · 400 `#449c71` · 500 `#1f8257` · 600 `#176a47` · 700 `#15533a` · 800 `#134330` · 900 `#0f3526` · 950 `#082018` |

**Charakterystyka wizualna (ze screenów + HTML):** jasne tło `zinc-50`-ish, karty białe `rounded-2xl` z subtelnym borderem/cieniem; sidebar jasny z aktywną pozycją jako pełny granatowy pill (`zinc-900`, biały tekst, `rounded-xl`); CTA pomarańczowe (`orange-600`, hover `orange-500`, `rounded-xl`); pill-taby (aktywny = granatowy pill z licznikiem); statusy jako kropka + label (`częściowy` = amber/orange, sukces = emerald, błąd = brick); nagłówki tabel `text-[11px] uppercase tracking-wider zinc-400`; breadcrumb w topbarze `Workspace / Integracje / Eksporty` (ostatni segment bold ink); badge'y „WYBRANE"/„UPDATE"/„wkrótce" jako małe uppercase chipy.

---

## 5. Mapa ticketów i zależności

```
GRUPA A — fundament look & feel
  EXR-01 tokeny ──► EXR-02 primitives v2 ──► EXR-03 shell (sidebar 2-poziomowy + topbar)

GRUPA B — backend
  EXR-04 ExportEntityType ──► EXR-05 pipeline per ObjectType (moduły własne)
                         └──► EXR-06 eksportery strukturalne (schemat / atrybuty / kategorie)
  EXR-07 preflight count + weryfikacja pamięci (niezależny, wymaga tylko EXR-04 dla entity_type w payload)

GRUPA C — frontend eksportów (wymaga A; per krok także B)
  EXR-08 strona Sesje+taby (po EXR-02/03; live po EXR-15)
  EXR-09 wizard szkielet + Krok 1 (po EXR-02/04)
  EXR-10 Krok 2 zakres i format (po EXR-09, EXR-07; reuse wyszukiwarki)
  EXR-11 Krok 3 kolumny (po EXR-09; dla encji strukturalnych po EXR-06)
  EXR-12 Krok 4 podsumowanie + uruchomienie (po EXR-10/11)
  EXR-13 Profile Eksportu (po EXR-04, EXR-12)
  EXR-14 wejścia kontekstowe + wycofanie ExportModal (po EXR-12)
  EXR-15 async UX live (po EXR-08/12)

GRUPA D — domknięcie
  EXR-16 E2E + a11y + i18n + benchmark + docs (na końcu)
```

Sumaryczna estymata: **~150-210 h** (szczegóły per ticket). Kolejność wykonywania: A → B i C przeplatane wg zależności → D.

---
---

# GRUPA A — Fundament nowego look & feel

---

## EXR-01 (#1377) — feat(admin/theme): tokeny design systemu v2 (paleta, typografia, radiusy, cienie)

### Kontekst
Nowy design (§4) wprowadza granatowo-pomarańczową paletę. Tokeny podmieniamy **globalnie** w warstwie CSS variables / Tailwind theme — tak, by nowe widoki (eksporty, shell) renderowały się 1:1 z designem, a istniejące widoki (produkty, modelowanie, ustawienia…) **pozostały w pełni używalne** (dopuszczalna zmiana odcieni, niedopuszczalna utrata kontrastu/czytelności — pozostałe moduły dostaną pełny redesign w kolejnych epikach).

### Zakres
- [ ] W `apps/admin/src/index.css` (Tailwind v4 `@theme`): nadpisać skalę `zinc` wartościami z §4 (granatowy neutral), dodać skale `orange` (akcent), `brick`, nadpisać `emerald` wg §4.
- [ ] Zdefiniować semantyczne tokeny v2: `--accent` → orange-600, `--accent-hover` → orange-500, `--accent-soft` → orange-50, `--ink` → zinc-900 (`#16233f`), `--surface` (białe karty), `--surface-muted` (zinc-50), `--status-success/-warning/-error/-partial`. Istniejące semantyki (`--primary` itd.) zmapować na nowe wartości — NIE usuwać starych nazw (kompatybilność istniejących widoków).
- [ ] Dodać `JetBrains Mono` jako `--font-mono` (self-hosted przez `@fontsource` lub plik w `public/` — **zakaz Google Fonts CDN w produkcie**, spójnie z dotychczasowym podejściem do Inter; sprawdzić jak Inter jest dziś dostarczany i powielić wzorzec).
- [ ] Skala radiusów: karty `rounded-2xl` (16px), kontrolki/pills `rounded-xl` (12px), chipy `rounded-md`. Cień kart: `0 1px 2px rgba(0,0,0,0.04), 0 4px 12px rgba(0,0,0,0.04)` jako token `--shadow-card`.
- [ ] Dark mode: jeśli istnieje wariant dark w `index.css` — zaktualizować mapowania tak, by się kompilowały i nie wywracały UI; pełny dark-mode design poza zakresem (odnotować w PR).
- [ ] Wizualna regresja: przejść ręcznie (smoke) główne widoki — lista produktów, karta produktu, modelowanie, ustawienia, login — brak złamanych layoutów, kontrast AA dla tekstu na nowych tłach.

### Poza zakresem
Przebudowa jakichkolwiek komponentów/widoków (to EXR-02+); dark mode redesign; czyszczenie nieużywanych klas.

### Pliki
`apps/admin/src/index.css` (główny), ewent. `apps/admin/package.json` (fontsource), `apps/admin/public/` (font), `apps/admin/src/App.tsx` tylko jeśli wymaga importu fontu.

### Kryteria akceptacji
- Cała aplikacja kompiluje się i renderuje z nową paletą; E2E (istniejące Playwright) zielone bez zmian w specach (dopuszczalne aktualizacje snapshotów kolorów, jeśli jakieś istnieją).
- Live smoke per SMOKE TEST RULE: login → lista produktów → karta produktu → ustawienia; DevTools console bez czerwonych errorów.
- Gates: tsc, Biome, Vite build, Playwright CI.

**Estymata:** 6-10 h. **Zależności:** brak.

---

## EXR-02 (#1378) — feat(admin/ui): pakiet primitives v2 (komponenty nowego designu)

### Kontekst
Design używa zestawu drobnych komponentów (inwentarz z `integracje/primitives.jsx` + `Eksport-nowy.html`). Budujemy je jako reużywalne komponenty React (TS, shadcn-compatible, Tailwind na tokenach z EXR-01), w `apps/admin/src/components/ui-v2/`. To one będą nośnikiem nowego look & feel w eksportach i kolejnych modułach.

### Zakres — komponenty (każdy: props z TSDoc, i18n przez `t()` dla labeli, a11y)
- [ ] `PageHeader` — breadcrumb (`Workspace / Integracje / Eksporty`, segmenty jako linki, ostatni bold ink) + slot akcji po prawej (CTA, ikony PL/historia/dzwonek). Render w topbarze layoutu.
- [ ] `PillTabs` — taby jak na screenie 1 (aktywny = granatowy pill + licznik w jaśniejszym badge'u; nieaktywny = tekst zinc-500; wariant `disabled` z tooltipem „wkrótce").
- [ ] `KpiCard` (odpowiednik `TinyKpi`) — label uppercase 11px, wartość 28-32px bold, sub-line (np. `✓0 ⚠1 ✗0`, `throughput 0 wier/s`), opcjonalna ikona w rogu, opcjonalny mini-trend (Sparkline).
- [ ] `StatusPill` — kropka + label; warianty: `success` (emerald), `warning/częściowy` (orange/amber), `error` (brick), `cancelled` (zinc), `running` (zinc + pulsująca kropka). Mapowanie z `ExportStatus` w jednym miejscu (`status-maps.ts`).
- [ ] `ResultBar` — pozioma belka rozkładu OK/WARN/ERR (emerald/orange/brick) + licznik; props `{ok, warn, err, total}`.
- [ ] `ProgressBar` — animowana belka postępu (async sesje).
- [ ] `ModeBadge` — chip „UPDATE"/„CREATE" itd. (uppercase, kropka, odcień per mode).
- [ ] `FormatPill` — chip formatu pliku (XLSX/CSV/…).
- [ ] `SelectableCard` — kafelek wyboru (Krok 1 i format w Kroku 2): ikona w kwadracie `rounded-xl`, tytuł + opcjonalny badge („WYBRANE" orange / „wkrótce" zinc), opis 13px zinc-500; stany: default (border zinc-200), selected (border ink 2px + badge), disabled (opacity, cursor-not-allowed, tooltip „wkrótce"); pełna obsługa klawiatury (radiogroup semantics).
- [ ] `WizardStepper` — pasek 4 kroków (numer w kółku, tytuł, podtytuł 11px; stany: done = zielone tło + check, active = granatowy, future = biały/zinc); klik w done-step cofa (po potwierdzeniu jeśli dirty — logika w EXR-09, tu tylko API komponentu `onStepClick`).
- [ ] `EmptyState` — ikona/tekst/CTA (sekcja „W toku" bez aktywnych eksportów).
- [ ] `Sparkline`, `HealthDot` — przenieść z designu (porty 1:1 z `primitives.jsx`).
- [ ] Tabela v2: nie nowy komponent, lecz **klasy/wzorzec** (nagłówek uppercase 11px tracking-wider zinc-400, wiersze hover zinc-50, separator zinc-100, mono dla liczb) udokumentowany w `ui-v2/README.md` + przykład.

### Poza zakresem
`StagePipeline` (specyficzny dla importów — przeniesiemy przy redesignie importów). Storybook.

### Pliki
Nowe: `apps/admin/src/components/ui-v2/{page-header,pill-tabs,kpi-card,status-pill,result-bar,progress-bar,mode-badge,format-pill,selectable-card,wizard-stepper,empty-state,sparkline,health-dot}.tsx`, `ui-v2/status-maps.ts`, `ui-v2/README.md`.

### Kryteria akceptacji
- Każdy komponent ma test Vitest (render + warianty stanów) — razem ≥ 13 testów.
- axe-core bez naruszeń na stronie demo (tymczasowa route dev-only lub test-only render).
- Gates standardowe + i18n: zero literałów PL/EN w komponentach.

**Estymata:** 12-16 h. **Zależności:** EXR-01.

---

## EXR-03 (#1379) — feat(admin/shell): przebudowa sidebara (menu II poziomu) + topbar wg nowego designu

### Kontekst
Obecny `sidebar-nav.tsx` to płaska lista. Nowy design (screeny) wprowadza: workspace header, search „Zapytaj agenta lub szukaj… ⌘K", **rozwijane menu drugiego poziomu** (wzorzec: Integracje → Importy / Eksporty / Konfigurator API), liczniki przy pozycjach, „+ Dodaj własny moduł", kartę użytkownika w stopce oraz topbar z breadcrumbem i akcjami. Zmiana globalna — dotyka całej aplikacji.

### Zakres — sidebar
- [ ] Rozszerzyć model pozycji menu o `children?: MenuItem[]` + stan rozwinięcia. Pozycja z dziećmi: klik = toggle expand (chevron), aktywna gdy route pasuje do któregokolwiek dziecka; dziecko aktywne = jaśniejszy pill (zinc-100, tekst ink) wcięty pod rodzicem (jak screen 1: Integracje rozwinięte, „Eksporty" podświetlone).
- [ ] Struktura docelowa menu (kolejność jak na screenach):
  1. `Dashboard` → `/`
  2. dynamiczne ObjectType z `show_in_main_menu=true` (Produkty, …) → `/products`, `/objects/:slug` — **bez zmian logiki źródła** (wzorzec Epiku UP), tylko nowy wygląd + licznik
  3. `Modelowanie` → jak dotychczas
  4. `Integracje` — **rodzic z dziećmi:** `Importy` → `/integrations/imports/sessions` (+ licznik aktywnych sesji), `Eksporty` → `/integrations/exports/sessions`, `Konfigurator API` → istniejąca route konfiguratora (sprawdzić w `App.tsx`; jeśli funkcjonalność nie istnieje — link disabled „wkrótce")
  5. `Multimedia`, `Workflow` (Katalogi PDF — zostawić jeśli jest dziś), `Ustawienia` — jak dotychczas
- [ ] Liczniki przy pozycjach (Produkty `12 847`, Multimedia `8 421`, Importy `3` na screenach): wartość = `totalItems` z istniejących endpointów list (HEAD/`itemsPerPage=1`) lub istniejący endpoint statystyk jeśli jest; cache w pamięci 60 s (jeden hook `useNavCounts`); formatowanie liczb ze spacją tysięcy (`Intl.NumberFormat('pl-PL')`); gdy fetch nieudany/wolny — pozycja bez licznika (bez skeleton-shift). **Jeśli koszt zapytań okaże się problemem — liczniki za flagą `VITE_NAV_COUNTS=off`, decyzję odnotować w PR.**
- [ ] Workspace header: logo-kwadrat (inicjał tenanta, zinc-900), nazwa instancji + subline z nazwą tenanta (z `/api/workspaces/current`), kropka statusu.
- [ ] Search box „Zapytaj agenta lub szukaj… ⌘K": pozostaje **disabled** (agent = Faza 2) — restyle istniejącego placeholdera do nowego wyglądu.
- [ ] „+ Dodaj własny moduł" (przycisk na dole listy): link do istniejącego wizarda tworzenia ObjectType w Modelowaniu.
- [ ] Stopka: karta użytkownika (avatar z inicjałami, imię nazwisko, rola — dane z istniejącego session bootstrap) + ikona ustawień → `/settings`.

### Zakres — topbar
- [ ] `PageHeader` (EXR-02) globalnie w layoucie: breadcrumb generowany z route (mapowanie segmentów na labele i18n; ostatni segment = tytuł strony), slot na akcje strony (np. „Nowy eksport" rejestrowane przez stronę — prosty context `PageActionsContext`).
- [ ] Akcje stałe po prawej: przełącznik języka PL/EN (reuse istniejącego mechanizmu i18n), ikona historii (disabled, tooltip „wkrótce"), ikona powiadomień (disabled, kropka-badge; realne podpięcie w EXR-15).

### Poza zakresem
Redesign zawartości pozostałych stron; command palette ⌘K; realne powiadomienia (EXR-15).

### Pliki
`apps/admin/src/layout/sidebar-nav.tsx` (przebudowa), nowy `apps/admin/src/layout/topbar.tsx`, layout główny (znaleźć komponent montujący sidebar — prawdopodobnie `apps/admin/src/layout/`), `apps/admin/src/layout/use-nav-counts.ts`, i18n `pl/en` JSON.

### Kryteria akceptacji
- Nawigacja klawiaturą: expand/collapse Enter/Space, focus ring widoczny; aria-expanded/aria-current poprawne; axe-core clean.
- Deep-link na `/integrations/exports/sessions` otwiera sidebar z rozwiniętym „Integracje" i aktywnym „Eksporty".
- E2E Playwright: scenariusz nawigacji przez menu II poziomu (Integracje → Eksporty, Integracje → Importy) + zachowanie liczników (mock).
- Live smoke: wszystkie pozycje menu klikalne, żadna istniejąca route nie zgubiona.

**Estymata:** 12-18 h. **Zależności:** EXR-01, EXR-02.

---
---

# GRUPA B — Backend (rozszerzenie istniejącego silnika)

---

## EXR-04 (#1380) — feat(export): ExportEntityType — eksport 5 typów encji w modelu danych i API

### Kontekst
Silnik eksportu obsługuje dziś wyłącznie produkty. Wprowadzamy pojęcie typu encji eksportu, wspólne dla sesji, profili i payloadów API.

### Zakres
- [ ] Nowy enum `App\Export\Domain\Enum\ExportEntityType`: `product`, `custom_module` (treści custom ObjectType; wymaga parametru `object_type_id`), `module_schema`, `attributes_groups`, `categories`.
- [ ] Migracja: `export_sessions.entity_type VARCHAR NOT NULL DEFAULT 'product'` + `export_sessions.object_type_id UUID NULL` (FK do object_types, SET NULL); to samo na `export_profiles`. Backfill istniejących wierszy na `product`. `down()` odtwarza stan.
- [ ] Walidacja: `custom_module` wymaga `object_type_id` wskazującego ObjectType z `is_built_in=false` (eksport built-in Product idzie ścieżką `product`); pozostałe typy zabraniają `object_type_id` (422 RFC 7807).
- [ ] Payloady: `POST /api/exports` (sync controller) i profile CRUD przyjmują `entity_type` (+ `object_type_id`); response sesji/profilu zwraca oba pola. Stare payloady bez `entity_type` → default `product` (backward compat, odnotować w OpenAPI).
- [ ] `target_scope`/`filter` dozwolone tylko dla `product` i `custom_module`; dla typów strukturalnych wymuszone `all` (422 przy próbie filtra).
- [ ] OpenAPI regen + `packages/shared-types` regen.
- [ ] Testy: PHPUnit unit (enum, walidacje) + ApiTestCase (create sesji każdego typu, walidacje 422, backward compat bez entity_type, izolacja tenant).

### Poza zakresem
Logika generowania danych dla nowych typów (EXR-05/06) — w tym tickecie typy strukturalne mogą zwracać 501/`placeholder` za flagą, ALBO ticket merguje się przed EXR-05/06 i endpointy przyjmują tylko typy z zaimplementowanym builderem (preferowane: rejestr builderów per typ, patrz EXR-05; nieobsługiwany typ → 422 z czytelnym komunikatem).

### Pliki
`apps/api/src/Export/Domain/Enum/ExportEntityType.php` (nowy), `Domain/Entity/ExportSession.php`, `ExportProfile.php`, migracja `apps/api/migrations/VersionXXXX.php`, `Presentation/Controller/SyncExportController.php`, `ExportProfileController.php`, `ExportSessionController.php` (serializacja), testy w `apps/api/tests/Export/`.

### Kryteria akceptacji
- Gates: PHPStan max 0, Deptrac 0, PHPUnit zielone, OpenAPI regen bez driftu.
- Live smoke: `POST /api/exports {entity_type: product}` → 200/201 jak dotychczas; `{entity_type: custom_module}` bez `object_type_id` → 422; stary payload bez `entity_type` → działa.

**Estymata:** 8-12 h. **Zależności:** brak (pierwszy ticket grupy B).

---

## EXR-05 (#1381) — feat(export): generalizacja pipeline'u danych na dowolny ObjectType (`custom_module`)

### Kontekst
`ExportBuilder`/`ColumnResolver`/`ValueSerializer` działają dziś dla produktów. Custom ObjectType (np. „Usługi", „Salony") mają identyczny model EAV (`objects` + `object_values` + `attributes_indexed`) — pipeline ma być sparametryzowany przez `object_type_id`, bez duplikacji kodu. **To jest realizacja wymogu „dynamiczny schemat/EAV" dla danych:** zestaw kolumn wynika z atrybutów podpiętych do ObjectType (junction `object_type_attributes` + grupy z `EffectiveAttributeGroupResolver`), nigdy z hardkodu.

### Zakres
- [ ] Wprowadzić rejestr builderów: interfejs `EntityExportBuilderInterface { supports(ExportEntityType): bool; columns(...): iterable<ColumnDefinition>; rows(...): iterable<array>; }` + tagged services (`#[AutoconfigureTag]`). Istniejący produktowy pipeline = pierwsza implementacja (`ObjectExportBuilder` obsługująca `product` ORAZ `custom_module` — parametr `object_type_id`; dla `product` resolve built-in Product ObjectType).
- [ ] `ColumnResolver`: przyjąć `objectTypeId` i budować katalog kolumn z atrybutów danego ObjectType (sprawdzić obecną implementację — jeśli już czyta z atrybutów per ObjectType, tylko sparametryzować wejście). Fan-out locale/channel — bez zmian logiki.
- [ ] Wiersze: iteracja kursorem (`toIterable()`) po obiektach danego ObjectType w obrębie tenanta, chunking + `clear()` — reuse istniejącego wzorca z `ExportJobHandler`; ZERO nowych ścieżek ładujących całość do pamięci (custom PHPStan rule flush-bez-clear obowiązuje).
- [ ] Filtry: FilterDSL scoped do ObjectType (sprawdzić czy `FilterDslResolver` przyjmuje object_type — jeśli nie, rozszerzyć; lista uniwersalna już filtruje per typ, więc resolver powinien to umieć — REUSE, nie fork).
- [ ] Sync i async: obie ścieżki działają dla `custom_module` (threshold 100 — wspólny, bez zmian).
- [ ] Endpoint katalogu kolumn dla wizarda: sprawdzić skąd `use-export-column-catalog.ts` bierze dane (prawdopodobnie endpoint kolumn produktowych) — rozszerzyć o `?entity_type=&object_type_id=` zwracając grupy+kolumny per typ.
- [ ] Testy: ApiTestCase — eksport custom ObjectType sync CSV (≤100) i async XLSX (>100, fixture ~150 obiektów), poprawność wartości EAV (text/select/price/relation jako kody), izolacja tenantów, filtr DSL zawęża wynik.

### Poza zakresem
Eksport wariantów/asset binarek; typy strukturalne (EXR-06).

### Pliki
`apps/api/src/Export/Application/Builder/*` (refactor), nowy `EntityExportBuilderInterface.php` + rejestr, `Application/Sync/SyncExportRunner.php`, `Application/Async/ExportJobHandler.php`, ewent. `Catalog`-side: `FilterDslResolver` (rozszerzenie), testy.

### Kryteria akceptacji
- Gates komplet; PHPUnit ≥80% nowej logiki.
- **Test dynamiczności schematu:** test który tworzy w fixture nowy atrybut na ObjectType i asercją potwierdza, że pojawia się w katalogu kolumn i w wyeksportowanym pliku **bez żadnej zmiany w kodzie eksportu**.
- Live smoke: utworzyć custom ObjectType z 2 atrybutami + 3 obiekty → eksport CSV przez API → plik zawiera 3 wiersze i kolumny obu atrybutów (proof w issue close comment).

**Estymata:** 16-24 h. **Zależności:** EXR-04.

---

## EXR-06 (#1382) — feat(export): eksportery strukturalne — schemat modułów, atrybuty i grupy, kategorie

### Kontekst
Trzy typy encji eksportują **konfigurację systemu**, nie dane EAV. Implementowane jako kolejne `EntityExportBuilderInterface` w rejestrze z EXR-05. Wymóg „zakaz hardkodów" obowiązuje: kolumny konfiguracyjne atrybutu wyprowadzane refleksyjnie z modelu (typy atrybutów z enuma/registry, validation_rules z JSONB per kontrakt `docs/api/jsonb-schemas.md`) — nowy typ atrybutu nie wymaga zmian w eksporterze.

### Zakres — `module_schema` (Schemat modułów)
- [ ] Builder zwracający strukturę definicji ObjectType: jeden wiersz = jeden atrybut przypięty do ObjectType. Kolumny: `object_type_code`, `object_type_name`, `kind`, `is_built_in`, capability flags (`show_in_main_menu`, `is_categorizable`, `has_variants` — czytane dynamicznie z encji, nie listowane na sztywno: refleksja po polach boolean z prefixem capability LUB jedna kolumna JSON `capabilities`), `attribute_code`, `attribute_type`, `group_code`, `group_display_mode`, `required`, `display_mode`, `show_in_list`, `list_position`, konfiguracja relacji (`relation_target`, `relation_cardinality`) gdy typ = relation.
- [ ] Zakres: wszystkie ObjectType tenanta (built-in + custom).

### Zakres — `attributes_groups` (Atrybuty i Grupy)
- [ ] Builder słownika atrybutów: jeden wiersz = atrybut. Kolumny: `code`, `type`, `label` (per locale tenanta — fan-out `label.pl`, `label.en` z aktywnych locale, NIE hardcoded), `help`, `validation_rules` (JSON string), `is_localizable`, `is_scopable`, `unit`, `options` (dla select/multiselect: kody+labele JSON), `groups` (kody grup, joined), `is_built_in`, `created_at`.
- [ ] Drugi arkusz/sekcja dla grup: `group_code`, `label.*`, `display_mode`, `position`, liczba atrybutów. XLSX = drugi sheet `groups`; CSV = drugi plik w ZIP **albo** osobne kolumny z prefixem — DECYZJA: dla CSV eksportujemy dwa pliki spakowane ZIP (`attributes.csv`, `groups.csv`); dla XLSX dwa sheety. Odnotować w PR i w pomocy UI.
- [ ] Wartości domyślne atrybutów — jeśli model je ma (sprawdzić encję `Attribute`), kolumna `default_value`; jeśli nie ma — pominąć i odnotować.

### Zakres — `categories` (Kategorie)
- [ ] Builder drzewa kategorii (obiekty ObjectType kind=category): jeden wiersz = kategoria. Kolumny: `id`, `code`, `name.*` (per locale), `parent_code`, `path` (ltree → human-readable `A > B > C`), `level`, `position`, `is_primary_capable`/flagi jeśli istnieją, **przypisane grupy atrybutów** (overlay z `category_attribute_groups`: kody joined) — to pokrywa „reguły dziedziczenia" z designu.
- [ ] Kolejność wierszy: DFS po drzewie (rodzic przed dzieckiem) — round-trip friendly.

### Zakres — wspólne
- [ ] Wszystkie trzy buildery: streaming (iterable, bez materializacji całości), działają sync (typowo < 100 wierszy... ale przy 100k atrybutów EAV może być więcej — threshold wspólny, async path też wspierany), pliki nazwane `pim-export-{entity}-{timestamp}.{ext}`.
- [ ] Testy ApiTestCase per typ: eksport → parsowanie pliku → asercje na strukturę i wartości; test dynamiczności (nowy typ atrybutu w fixture → eksportuje się bez zmiany kodu — asercja na obecność wiersza/kolumny).

### Poza zakresem
Import struktur (round-trip schematu) — przyszły epik. Eksport uprawnień RBAC per atrybut.

### Pliki
Nowe: `apps/api/src/Export/Application/Builder/Entity/{ModuleSchemaExportBuilder,AttributesGroupsExportBuilder,CategoriesExportBuilder}.php`, rozszerzenie writerów o multi-sheet/ZIP (`Infrastructure/Writer/`), testy.

### Kryteria akceptacji
- Gates komplet; live smoke: każdy z 3 typów przez API → pobrany plik otwiera się, struktura zgodna ze spec (proof: fragmenty plików w issue close comment).

**Estymata:** 16-22 h. **Zależności:** EXR-04, EXR-05 (rejestr builderów).

---

## EXR-07 (#1383) — feat(export): preflight count + kontrakt routingu sync/async dla UI + weryfikacja pamięci

### Kontekst
Wizard potrzebuje live licznika „Do wyeksportowania: N produktów" (Krok 2) i wiedzy sync-czy-async PRZED uruchomieniem (Krok 4 pokazuje notę o asynchroniczności). Backend ma już `FilterDslResolver::toCountSql` — wystawiamy go jako lekki endpoint.

### Zakres
- [ ] `POST /api/exports/preflight` body: `{entity_type, object_type_id?, target_scope, filter?, selected_ids?}` → response: `{count: int, mode: "sync"|"async", threshold: 100, soft_cap: 100000, exceeds_cap: bool}`. Mode liczone tą samą stałą `SYNC_THRESHOLD` (jedno źródło prawdy — wystawić stałą przez response, UI NIE hardkoduje 100). Dla typów strukturalnych count = liczba wierszy danego buildera (szybki COUNT, nie iteracja).
- [ ] Rate-limit/debounce-friendly: endpoint tylko COUNT SQL, bez side-effectów, `#[RequiresPermission(module:'exports', action:'run')]`.
- [ ] `exceeds_cap=true` (>100k) → UI blokuje uruchomienie z komunikatem „podziel eksport" (obsługa w EXR-10/12; tu kontrakt).
- [ ] **Weryfikacja pamięci (wymóg specyfikacji):** uruchomić `ExportBenchmarkCommand` na 100k+ wierszy (fixture generator), zmierzyć peak memory workera; asercja w benchmarku `< 256 MB` (zgodnie z Prometheus alertem z CLAUDE.md). Jeśli przekracza — naprawić root cause w tym tickecie (iterate/clear/writer flush), NIE podnosić limitu. Wynik benchmarku w PR body.
- [ ] OpenAPI + shared-types regen; ApiTestCase: count z filtrem, count selected_ids, mode przełącza się na granicy 99/100/101, cap.

### Poza zakresem
Zmiana wartości thresholdu (zostaje 100); estymacja czasu eksportu.

### Pliki
`apps/api/src/Export/Presentation/Controller/ExportPreflightController.php` (nowy) lub akcja w `SyncExportController`, testy, `apps/api/src/Benchmark/Export/ExportBenchmarkCommand.php` (asercja pamięci).

### Kryteria akceptacji
- Gates komplet; live smoke: curl preflight z filtrem → poprawny count zgodny z listą produktów przy tym samym filtrze (porównać liczby — proof w close comment).

**Estymata:** 6-10 h. **Zależności:** EXR-04 (entity_type w payload); dla typów strukturalnych count po EXR-06 (dopuszczalne: preflight dla strukturalnych dodany w EXR-06, tu product/custom_module).

---
---

# GRUPA C — Frontend eksportów (nowy look & feel)

---

## EXR-08 (#1384) — feat(admin/exports): strona Eksporty — taby + widok Sesje (KPI, W toku, Historia)

### Kontekst
Screen 1. Przebudowa `ExportsLayout` + `ExportSessionsView` do nowego designu. Layout wzorowany na `integracje/importy-sessions.jsx` z designu (ten sam układ co importy — przyszły redesign importów użyje tych samych komponentów).

### Zakres
- [ ] `ExportsLayout`: `PageHeader` (breadcrumb `Workspace / Integracje / Eksporty`, CTA **„Nowy eksport"** pomarańczowe w topbarze przez `PageActionsContext`), pod spodem `PillTabs`: `Sesje [count]`, `Profile Eksportu [count]`, `Cele` (disabled „wkrótce"), `Harmonogram` (disabled „wkrótce"). Liczniki z API (total sesji 30 dni, total profili).
- [ ] Pasek KPI (4 × `KpiCard`, dane z istniejących endpointów sesji — jeśli brak agregatów, policzyć client-side z listy 30 dni; jeśli to za drogie → mini-endpoint statystyk jako podzadanie, decyzja w PR):
  1. **W toku** — liczba aktywnych sesji + sub `throughput N wier/s` (suma z aktywnych; 0 gdy brak),
  2. **Dziś · {data}** — liczba sesji dziś + sub `✓n ⚠n ✗n`,
  3. **Sukces · 30 dni** — % sesji completed bez błędów + mini progress-line,
  4. **Top błędy · 30 dni** — lista 1-3 najczęstszych statusów błędów/typów z licznikiem (z `ExportLog`/statusów sesji; jeśli typologia błędów niedostępna — top statusy nie-sukces).
- [ ] Sekcja **„W toku"**: gdy 0 aktywnych → `EmptyState` w ramce dashed („Brak aktywnych eksportów. Zacznij od „Nowy eksport"…" + CTA); gdy aktywne → karty sesji z `ProgressBar`, throughput, przycisk anuluj (EXR-15).
- [ ] Sekcja **„Historia"** (`ostatnie 30 dni · N sesji`): tabela v2 — kolumny: PLIK·ŹRÓDŁO (ikona encji, nazwa pliku mono, sub: encja/ObjectType), PROFIL (nazwa lub „—"), TRYB (`ModeBadge`), WIERSZE (mono), ROZKŁAD OK/WARN/ERR (`ResultBar`), START·CZAS (data + duration), UŻYTKOWNIK, STATUS (`StatusPill`), chevron → `/integrations/exports/sessions/:id` (istniejący show — restyle minimalny: karta szczegółów w nowych tokenach; pełny redesign show = świadomie płytki, odnotować).
- [ ] Toolbar historii: search `plik, profil, użytkownik…` (client-side po załadowanej stronie lub param API jeśli jest), segmenty: `wszystkie / sukces / ostrzeżenia / błędy / anulowane` (mapowanie na `ExportStatus`), paginacja `← poprzednie / następne →` + „Pokazano X z Y sesji" (cursor-based jeśli API wspiera, inaczej page).
- [ ] Download pliku z wiersza historii (sesje completed) — reuse istniejącego mechanizmu linku (MinIO URL z sesji).
- [ ] i18n pl/en komplet; states: loading skeletony, error RFC 7807 toast.

### Poza zakresem
Realne taby Cele/Harmonogram; redesign strony szczegółu sesji ponad minimalny restyle; live updates (EXR-15 podpina stream — tu fetch + refetch interval 30 s).

### Pliki
`apps/admin/src/features/exports/layout/ExportsLayout.tsx`, `sessions/ExportSessionsView.tsx` (przebudowa), nowe podkomponenty w `features/exports/sessions/` (`KpiStrip.tsx`, `HistoryTable.tsx`, `ActiveSessions.tsx`), i18n JSON.

### Kryteria akceptacji
- Zgodność wizualna ze screenem 1 (układ, typografia, kolory) — porównanie side-by-side w PR (screenshot).
- E2E Playwright: render KPI, filtrowanie segmentem `błędy`, search, przejście do szczegółu, empty state „W toku".
- Live smoke per SMOKE TEST RULE (login → Eksporty → KPI niepuste przy seedzie, historia renderuje sesje, network 200, konsola czysta).

**Estymata:** 14-18 h. **Zależności:** EXR-02, EXR-03; pełny licznik tabów po EXR-13 (akceptowalne: licznik profili podpięty od razu do istniejącego endpointu).

---

## EXR-09 (#1385) — feat(admin/exports): wizard „Nowy eksport" — szkielet, store, Krok 1 (Typ)

### Kontekst
Screen 2. Pełnostronicowy 4-krokowy kreator na `/integrations/exports/new` zastępujący `ExportNewPage` (paste-JSON). Jeden store stanu dla całego wizarda.

### Zakres
- [ ] Route `/integrations/exports/new` → nowy `ExportWizardPage` (stary `ExportNewPage` usuwany w EXR-14 — do tego czasu nowa strona montowana równolegle pod `/integrations/exports/new` i stara dostępna pod `/integrations/exports/new-legacy` TYLKO w trakcie epiku; final cleanup w EXR-14).
- [ ] Nagłówek: tytuł „Kreator eksportu" + lead „Skonfiguruj parametry eksportu danych — format, zakres i docelowe kolumny."; `WizardStepper` z 4 krokami: `1 Typ — co eksportujesz`, `2 Zakres i format — profil · format · filtry`, `3 Kolumny — wybór atrybutów`, `4 Podsumowanie — sprawdź i uruchom`.
- [ ] Store wizarda (React context + reducer albo zustand — wybrać wzorzec już używany w repo; jeśli brak — context+reducer): `{entityType, objectTypeId?, profileId?, format, filterDsl?, selectedIds?, targetScope, columns: string[], locales?, channels?, profileName}`. Typy w jednym pliku `wizard/types.ts` zsynchronizowane z shared-types z OpenAPI.
- [ ] **Krok 1 — kafelki (`SelectableCard`, układ 3+2 jak screen 2):**
  1. **Produkty** — „Eksportuj główny katalog produktów, warianty, ceny i powiązane multimedia." (default selected, badge WYBRANE)
  2. **Moduły własne** — „Eksportuj dane ze zdefiniowanych przez użytkownika modułów niestandardowych (np. Producenci, Kolekcje)." — po wyborze pojawia się **drugi rząd: select ObjectType** (lista custom ObjectType `is_built_in=false` z API; gdy 0 → kafelek disabled z tooltipem „Brak własnych modułów — utwórz w Modelowaniu")
  3. **Schemat modułów** — „Pobierz strukturę definicji, relacje i ustawienia konfiguracyjne modułów."
  4. **Atrybuty i grupy** — „Eksport słownika atrybutów, ich wartości domyślnych, typów oraz podziału na grupy."
  5. **Kategorie** — „Struktura drzewa kategorii, tłumaczenia nazw oraz przypisane reguły dziedziczenia."
- [ ] Stopka wizarda: mono `krok 1 z 4 · Typ` po lewej; `Anuluj` (→ powrót do sesji; confirm dialog gdy stan dirty), `← Wstecz` (disabled na kroku 1), `Dalej →` (orange CTA).
- [ ] Zmiana encji po skonfigurowaniu dalszych kroków → confirm + reset kroków 2-4 (filtry/kolumny są per encja).
- [ ] Nawigacja stepperem: klik w ukończony krok wraca; przyszłe kroki nieklikalne.

### Poza zakresem
Persist draftu między sesjami przeglądarki (świadomie NIE — wizard jest szybki); treść kroków 2-4 (następne tickety renderują w tym szkielecie).

### Pliki
Nowe: `apps/admin/src/features/exports/wizard/{ExportWizardPage,WizardFooter,steps/StepEntityType}.tsx`, `wizard/wizard-store.ts(x)`, `wizard/types.ts`; `apps/admin/src/App.tsx` (route), i18n.

### Kryteria akceptacji
- E2E: wybór każdego z 5 kafelków → Dalej przechodzi; Moduły własne wymagają wyboru ObjectType (walidacja inline); Anuluj z confirm.
- axe-core: kafelki jako radiogroup, pełna obsługa klawiatury.
- Live smoke: wejście z CTA „Nowy eksport" (EXR-08), Krok 1 renderuje 5 kafelków, custom ObjectType z seeda widoczny w selekcie.

**Estymata:** 10-14 h. **Zależności:** EXR-02, EXR-03, EXR-04 (typy w API).

---

## EXR-10 (#1386) — feat(admin/exports): wizard Krok 2 — Zakres i format (REUŻYWALNA WYSZUKIWARKA — krytyczne)

### Kontekst
Screen 3. Najważniejszy wymóg specyfikacji: **zakaz pisania nowej logiki wyszukiwania**. Sekcja „Zakres danych (filtrowanie)" osadza TEN SAM komponent `AdvancedFilterPanel` + lib `filter-dsl`, których używa lista produktów/uniwersalna. Każda przyszła zmiana mechaniki szukania na liście MA automatycznie działać w eksporcie.

### Zakres — sekcja „Profil i format pliku"
- [ ] Select „Wybierz zapisany profil (opcjonalnie)" — lista profili pasujących do wybranej encji (z EXR-04/13); wybór profilu nadpisuje format/filtry/kolumny w store (z toast „Załadowano profil X") + hint „Użycie profilu nadpisze aktualne ustawienia formatu i filtrów."
- [ ] „Format docelowy" — radio-cards (`SelectableCard` w wariancie radio): **XLSX** „Arkusz kalkulacyjny Excel" i **CSV** „Wartości rozdzielane przecinkiem" AKTYWNE; **XML** „Feed hierarchiczny", **JSON** „Dane strukturalne / API", **Google Sheets** „Arkusz w chmurze", **PDF** „Katalog / cennik" — disabled z badge „wkrótce" (D1; payload nigdy ich nie wysyła; tooltip „Format dostępny wkrótce").
- [ ] (dla `product`/`custom_module`) sekcja opcji locale/channel jeśli obecny `ExportModal` ją ma (fan-out kolumn) — przenieść mechanikę do tego kroku LUB zostawić w Kroku 3 przy katalogu kolumn — DECYZJA: zostaje w Kroku 3 (kolumny per locale/channel są tam widoczne); w Kroku 2 tylko format+filtry. Odnotować w PR.

### Zakres — sekcja „Zakres danych (filtrowanie)" — TYLKO `product` i `custom_module`
- [ ] **Refactor-for-reuse `AdvancedFilterPanel`:** wydzielić panel tak, by był w pełni props-driven: `{objectTypeId, value: FilterDsl, onChange(FilterDsl)}` — bez sprzężenia z routerem/URL listy. Jeżeli dziś panel czyta stan z `universal-list-page` — wyciągnąć współdzielony hook `useFilterDslState` do `@/lib/filters/`. **Lista produktów po refactorze MUSI działać identycznie** (E2E listy zielone + manual smoke listy w ramach TEGO ticketu).
- [ ] Osadzić panel w karcie „Zbuduj zapytanie" (wiersze warunków: pole / operator / wartość, `×` per wiersz, „+ Dodaj warunek", „Wyczyść wszystko") — UI w nowych tokenach (restyle wewnątrz panelu dopuszczalny przez klasy/warianty, bez forka logiki).
- [ ] Badge w nagłówku sekcji: **„Do wyeksportowania: N produktów/obiektów"** — `POST /api/exports/preflight` (EXR-07) z debounce 500 ms na każdą zmianę DSL; spinner inline podczas liczenia; przy `exceeds_cap` czerwony stan + komunikat „Przekroczono limit 100 000 — zawęź filtry"; `Dalej` disabled przy cap.
- [ ] `count = 0` → `Dalej` aktywny ale z ostrzeżeniem (eksport pustego zbioru dozwolony — nagłówki kolumn).
- [ ] Tryb `targetScope=selected` (wejście z listy, EXR-14): zamiast query buildera chip „Zaznaczone obiekty: N" + link „przełącz na filtrowanie" (czyści selected).
- [ ] Encje strukturalne (`module_schema`, `attributes_groups`, `categories`): sekcja filtrowania ukryta, w zamian karta informacyjna „Eksport pełnej struktury — N wierszy" (count z preflight).

### Poza zakresem
Nowe operatory/pola filtrów; zapisywanie samych filtrów jako smart-preset z poziomu eksportu (profile eksportu pokrywają potrzebę).

### Pliki
`apps/admin/src/features/exports/wizard/steps/StepScopeFormat.tsx` (nowy), `apps/admin/src/components/catalog/advanced-filter-panel.tsx` + `apps/admin/src/lib/filters/*` (refactor reuse), `apps/admin/src/components/objects/universal-list-page.tsx` (adaptacja do hooka), i18n.

### Kryteria akceptacji
- **Dowód reuse w PR body:** diff pokazuje, że eksport importuje `AdvancedFilterPanel`/`useFilterDslState` z istniejących ścieżek; `git grep` nie znajduje drugiej implementacji budowy DSL w `features/exports`.
- E2E: zbudowanie 3 warunków (kategoria równa się / producent jest jednym z / status równa się — jak screen 3) → badge pokazuje count zgodny z listą produktów przy identycznym filtrze (asercja krzyżowa w teście).
- E2E regresji listy produktów: istniejące spec'y filtrów zielone po refactorze.
- Live smoke: filtr na żywym stacku, count = liczba z listy, format disabled nie da się wybrać.

**Estymata:** 16-24 h (refactor panelu = połowa). **Zależności:** EXR-07, EXR-09.

---

## EXR-11 (#1387) — feat(admin/exports): wizard Krok 3 — Kolumny (two-pane picker z reorderem)

### Kontekst
Screen 4. Dwupanelowy wybór atrybutów: lewy = dostępne (grupy z licznikami), prawy = wybrane (kolejność = kolejność kolumn w pliku, drag & drop). Źródłem danych pozostaje `use-export-column-catalog.ts` (rozszerzony o entity_type w EXR-05).

### Zakres
- [ ] Panel lewy „Dostępne atrybuty": search „Wyszukaj atrybut…" (filtrowanie w locie po nazwie i kodzie, podświetlenie trafień, auto-expand grup z trafieniami), „Zaznacz wszystko" (header), grupy collapsible (chevron, NAZWA GRUPY uppercase, badge `wybrane/wszystkie` np. `2/5` — granatowy gdy >0), checkbox „cała grupa" per grupa (indeterminate przy częściowym), wiersze atrybutów z checkboxami.
- [ ] Fan-out locale/channel (kolumny `code.locale`/`code.channel`) — reuse istniejącej mechaniki ColumnPicker/hooka (grupowanie wariantów pod atrybutem rodzica, jak po fixie #1278 — nie zdublować bare+variants buga).
- [ ] Panel prawy „Wybrane atrybuty (N)": lista kart (numer kolejności, nazwa, sub: grupa, uchwyt drag `⠿`, usuń `×`), drag & drop reorder (dnd-kit jeśli już w repo — sprawdzić; jeśli brak, dodać `@dnd-kit/sortable` — odnotować w PR; fallback klawiaturowy: strzałki przy focusie na uchwycie), „Wyczyść" (header, czerwonawy link), hint „Kolejność = kolejność kolumn w pliku. Przeciągnij, aby zmienić."
- [ ] Encje strukturalne: katalog kolumn = stały zestaw z buildera (EXR-06, endpoint katalogu per entity_type) — kolumny domyślnie wszystkie zaznaczone, można odznaczać; bez fan-outu locale (poza kolumnami `label.*`, które przychodzą z katalogu jako gotowe pozycje).
- [ ] Walidacja: minimum 1 kolumna do `Dalej`; klucz naturalny (SKU/code — zgodnie z PRD round-trip) zawsze obecny jako pierwsza kolumna locked (nieusuwalna, badge „klucz") dla `product`/`custom_module` — sprawdzić jak robi to obecny ColumnPicker i zachować kontrakt.
- [ ] Stan zaznaczeń trzymany w store wizarda; powrót Wstecz/Dalej nie gubi wyboru; zmiana encji w Kroku 1 czyści (EXR-09).

### Poza zakresem
Zapamiętywanie „ostatnio używanych kolumn" per user (profile to pokrywają); wirtualizacja listy (chyba że >500 pozycji w katalogu powoduje lagi — wtedy `@tanstack/react-virtual`, decyzja w PR).

### Pliki
`apps/admin/src/features/exports/wizard/steps/StepColumns.tsx` (nowy), `apps/admin/src/features/exports/components/ColumnPicker.tsx` (przebudowa UI lub nowy `ColumnPickerV2.tsx` z reuse `use-export-column-catalog.ts` — stary usuwany w EXR-14 razem z modalem), i18n.

### Kryteria akceptacji
- E2E: search zawęża, „cała grupa" zaznacza/odznacza, reorder drag (lub klawiaturą) zmienia kolejność, licznik `(5)` aktualny, Wyczyść czyści, min 1 kolumna enforced.
- Kolejność z panelu = kolejność kolumn w wygenerowanym pliku (asercja w E2E sync-eksportu z EXR-12 lub teście integracyjnym).
- axe-core clean (checkboxy z labelami, drag z alternatywą klawiaturową).
- Live smoke na żywym katalogu atrybutów (seed demo).

**Estymata:** 14-18 h. **Zależności:** EXR-09; katalog per encja: EXR-05/06.

---

## EXR-12 (#1388) — feat(admin/exports): wizard Krok 4 — Podsumowanie, zapis profilu, uruchomienie (sync/async)

### Kontekst
Screen 5. Ostatni krok: przegląd konfiguracji, opcjonalny zapis profilu, uruchomienie z routingiem sync/async po stronie UI.

### Zakres
- [ ] Karty podsumowania: **ENCJA** (nazwa + ikona; dla custom_module nazwa ObjectType), **FORMAT** (`FormatPill` XLSX/CSV + badge EXCEL), **ZAKRES DANYCH** („N produktów/obiektów/wierszy" + chipy zastosowanych filtrów `Kategoria`, `Producent`, `+1` z tooltipem pełnej listy; dla selected: „Zaznaczone: N"; dla strukturalnych: „Pełna struktura"), **STRUKTURA PLIKU** (chipy kolumn w kolejności, „N wybranych atrybutów"), **PROFIL** („Brak (eksport jednorazowy)" lub nazwa).
- [ ] Zapis profilu: input „Wpisz nazwę profilu…" + przycisk „Zapisz jako profil" → `POST /api/exports/profiles` payload: `{name, entity_type, object_type_id?, format, columns: string[], filter: FilterDsl|null, target_scope}`; sukces → toast + karta PROFIL pokazuje nazwę; walidacja: nazwa unikalna per tenant (409 → inline error), 1-120 znaków. Zapis profilu NIE uruchamia eksportu.
- [ ] Nota informacyjna (zielona, jak screen 5): treść zależna od preflight — async: „Eksport zostanie uruchomiony asynchronicznie. Po zakończeniu otrzymasz powiadomienie z linkiem do pobrania pliku."; sync: „Plik zostanie wygenerowany natychmiast i pobrany automatycznie."
- [ ] **„Uruchom eksport"** (orange CTA z ikoną):
  - preflight `mode=sync` → `POST /api/exports` (istniejący sync controller; payload z pełną konfiguracją), response stream → download blob (nazwa pliku z `Content-Disposition`), button w stanie loading z spinnerem (UI „blokuje się na ułamek sekundy" per spec), sukces → toast + redirect do `/integrations/exports/sessions` (sesja sync też jest logowana — zweryfikować; jeśli sync nie tworzy wpisu historii, dodać w tym tickecie po stronie BE wpis `ExportSession` completed dla sync — spójność z historią na screenie 1, która pokazuje 1s eksport),
  - preflight `mode=async` → ten sam POST, response `{session_id}` → redirect do `/integrations/exports/sessions` z highlight nowej sesji w „W toku" (scroll + pulse),
  - błędy: 422 RFC 7807 → mapowanie na kroki (np. błąd kolumn → link „wróć do kroku 3"), 403 permissions → toast, sieć → retry button.
- [ ] Disabled state CTA podczas requestu (double-submit guard).

### Poza zakresem
Edycja profilu (EXR-13); harmonogram (poza epikiem); e-mail z linkiem (powiadomienie = in-app, EXR-15).

### Pliki
`apps/admin/src/features/exports/wizard/steps/StepSummary.tsx` (nowy), `wizard/use-run-export.ts` (mutacje + download), ewent. `apps/api/src/Export/Presentation/Controller/SyncExportController.php` (wpis historii dla sync — jeśli brak), i18n.

### Kryteria akceptacji
- E2E sync: filtr ≤100 → Uruchom → plik pobrany (Playwright download event), zawartość: nagłówki = kolumny z Kroku 3 w kolejności, wiersze zgodne z filtrem.
- E2E async: fixture >100 → Uruchom → redirect, sesja widoczna w „W toku", po zakończeniu w Historii ze statusem.
- E2E profil: zapis → widoczny w Kroku 2 select (po refetch) i w tabie Profile.
- Live smoke per SMOKE TEST RULE: oba tryby na żywym stacku (proof: response codes + plik).

**Estymata:** 12-16 h. **Zależności:** EXR-10, EXR-11; EXR-07 (mode).

---

## EXR-13 (#1389) — feat(admin/exports): tab „Profile Eksportu" — zarządzanie profilami w nowym designie

### Kontekst
Tab z screena 1 (`Profile Eksportu 2`). Backend CRUD istnieje (`ExportProfileController` + `run`); przebudowa widoku + rozszerzenie o entity_type.

### Zakres
- [ ] Tabela/karty profili (wzorzec wizualny: `integracje/importy-profiles.jsx` z designu): nazwa, encja (ikona + label; ObjectType dla custom_module), `FormatPill`, liczba kolumn, skrót filtrów (chipy, `+n`), właściciel, ostatnie uruchomienie, `run_count`, success rate jeśli dostępny.
- [ ] Akcje per profil: **Uruchom teraz** (`POST /api/exports/profiles/{id}/run` — respektuje sync/async: sync → download natychmiast, async → redirect do sesji; reuse `use-run-export` z EXR-12), **Edytuj** (otwiera wizard z prefill całego store z profilu, zapis = update `PUT/PATCH` zamiast create — parametr `?profile={id}` na route wizarda), **Usuń** (confirm dialog z nazwą; 204 → refetch).
- [ ] Empty state: „Brak zapisanych profili" + CTA „Nowy eksport".
- [ ] Licznik w tabie (EXR-08) podpięty do realnego totalu.
- [ ] BE (drobne): upewnić się, że update profilu istnieje (jeśli `ExportProfileController` nie ma PUT/PATCH — dodać z testem); response listy zawiera pola potrzebne UI (entity_type po EXR-04, last_run_at, run_count — uzupełnić serializację jeśli brak).

### Poza zakresem
Udostępnianie profili między userami / per-rola (dziś per-tenant zgodnie z istniejącym modelem — nie zmieniać); duplikowanie profilu.

### Pliki
`apps/admin/src/features/exports/profiles/ExportProfilesView.tsx` (przebudowa), `apps/admin/src/App.tsx` (param route), ewent. `ExportProfileController.php` + testy, i18n.

### Kryteria akceptacji
- E2E: run profilu sync → download; edytuj → wizard prefilled (wszystkie 4 kroki odzwierciedlają profil) → zapis aktualizuje; usuń → znika.
- Live smoke: pełen cykl save (z EXR-12) → run → edit → delete na żywym stacku.

**Estymata:** 10-14 h. **Zależności:** EXR-04, EXR-12.

---

## EXR-14 (#1390) — refactor(admin/exports): wejścia kontekstowe z listy + wycofanie ExportModal i legacy strony

### Kontekst
Spec: „Eksport dotyczy wyłącznie produktów przefiltrowanych przez komponent wyszukiwarki" + D5 (pełny widok, nie modal). Dotychczasowe wejście z toolbara listy produktów (modal, `ExportSource::ListContext`) przekierowujemy do wizarda z zachowaniem kontekstu. Sprzątamy legacy.

### Zakres
- [ ] Wejście „eksportuj zaznaczone": bulk toolbar listy (uniwersalnej) → nawigacja do `/integrations/exports/new?scope=selected` z przekazaniem `selectedIds` (state nawigacji routera — NIE URL przy setkach id; przy braku state → redirect na czysty wizard) + `objectTypeId` bieżącej listy → wizard: Krok 1 pre-selected (product/custom_module wg typu listy, locked z możliwością odblokowania=reset), Krok 2 w trybie chip „Zaznaczone: N" (EXR-10).
- [ ] Wejście „eksportuj wynik filtra": akcja na liście przy aktywnym filtrze → `/integrations/exports/new?scope=filter` + bieżący DSL przez state/`dslToBase64` → Krok 2 z panelem prefilled tym samym DSL (count się zgadza z listą).
- [ ] `ExportSource` w payloadzie: `list_context` dla obu wejść z listy, `central_tab` dla wejścia z menu — zachować telemetrię.
- [ ] **Usunięcie:** `wizard/ExportModal.tsx`, stary `wizard/ExportNewPage.tsx` (paste-JSON), route `new-legacy` (z EXR-09), stary `components/ColumnPicker.tsx` jeśli zastąpiony przez V2 (EXR-11) — wraz z testami legacy; `git grep` po nazwach = 0 referencji.
- [ ] Aktualizacja wszystkich punktów wywołania modala (bulk toolbar listy uniwersalnej/produktowej — znaleźć przez grep `ExportModal`).
- [ ] E2E starych flow zastąpione nowymi (nie kasować scenariuszy biznesowych — przepisać na wizard).

### Poza zakresem
Eksport z karty pojedynczego produktu (nie istnieje dziś — nie dodajemy).

### Pliki
`apps/admin/src/components/objects/universal-list-page.tsx` (+ produktowa lista jeśli osobna ścieżka), `apps/admin/src/features/exports/wizard/*` (cleanup), `apps/admin/src/App.tsx`, testy E2E.

### Kryteria akceptacji
- E2E: zaznacz 3 produkty → eksportuj → wizard pokazuje „Zaznaczone: 3" → sync download zawiera dokładnie 3 wiersze; filtr na liście → eksportuj → count w wizardzie = count listy.
- `pnpm build` bez dead-code warningów dot. usuniętych plików; brak referencji do ExportModal.
- Live smoke obu wejść.

**Estymata:** 10-14 h. **Zależności:** EXR-12 (działający wizard end-to-end).

---

## EXR-15 (#1391) — feat(admin/exports): async UX live — postęp, anulowanie, powiadomienia

### Kontekst
Async sesje mają już stream (`useExportSessionsStream.ts`, Mercure przez `ExportProgressPublisher`). Podpinamy live UX do nowych widoków: progres w „W toku", KPI na żywo, powiadomienie po zakończeniu (dzwonek z EXR-03), link do pobrania.

### Zakres
- [ ] Sekcja „W toku" (EXR-08): subskrypcja streamu → `ProgressBar` per sesja (processed/target), throughput `N wier/s` (z payloadu progresu lub liczony z delty), bez pollingu gdy stream działa; fallback polling 5 s gdy Mercure niedostępny (degradacja odnotowana w konsoli, nie w UI).
- [ ] KPI „W toku" + licznik taba aktualizują się na zdarzeniach streamu.
- [ ] Zakończenie sesji: wpis przeskakuje do Historii (refetch listy), toast „Eksport zakończony — pobierz plik" z akcją download; status partial/error → toast wariantowy z linkiem do szczegółu sesji.
- [ ] **Anulowanie:** sprawdzić czy BE ma endpoint cancel (`ExportSessionController`); jeśli TAK → przycisk „Anuluj" na karcie aktywnej sesji (confirm; status `cancelled` w historii, segment „anulowane" działa); jeśli NIE → dodać `POST /api/exports/sessions/{id}/cancel` po stronie BE (flaga sprawdzana między chunkami w `ExportJobHandler`, graceful stop, status cancelled) — wraz z testem; przycisk podpięty.
- [ ] Dzwonek w topbarze (EXR-03): prosty in-app inbox (ostatnie 20 zdarzeń eksportu w pamięci klienta + badge unread) — zakończone eksporty dodają wpis z linkiem; **bez** persystencji BE (świadome MVP, odnotować).
- [ ] Link download w powiadomieniu i w historii: presigned URL z sesji (istniejący mechanizm MinIO; retention per PRD — wyświetlać bez daty wygaśnięcia jeśli forever).

### Poza zakresem
Powiadomienia e-mail/webhook; centrum powiadomień cross-modułowe (tylko eksporty zapisują do inboxa — architektura inboxa ma przyjąć inne moduły później: prosty `NotificationsContext`).

### Pliki
`apps/admin/src/features/exports/hooks/useExportSessionsStream.ts` (adaptacja), `sessions/ActiveSessions.tsx`, `apps/admin/src/layout/topbar.tsx` + nowy `layout/notifications-context.tsx`, ewent. BE cancel: `ExportSessionController.php`, `ExportJobHandler.php` + testy.

### Kryteria akceptacji
- E2E (lub test integracyjny z mock streamem): progress rośnie, completion → toast + wpis w historii + badge dzwonka.
- Live smoke: async eksport >100 na żywym stacku → progres widoczny na żywo, anulowanie działa (status cancelled), powiadomienie z działającym linkiem download (proof: nagranie/screenshoty w close comment).

**Estymata:** 12-18 h. **Zależności:** EXR-08, EXR-12; EXR-03 (dzwonek).

---
---

# GRUPA D — Domknięcie

---

## EXR-16 (#1392) — test/docs(exports): E2E całości, a11y, i18n, benchmark 100k, dokumentacja, screencast

### Kontekst
Bramka jakości epiku — zgodnie z DoD z CLAUDE.md (sekcja 2.2 planu) i wymogami specyfikacji (pamięć, skala).

### Zakres
- [ ] **E2E Playwright — pakiet `exports-redesign`:** (1) pełny happy path sync: menu II poziomu → Nowy eksport → Produkty → filtr 2 warunki → XLSX → 5 kolumn z reorderem → podsumowanie → download → plik poprawny; (2) async >100 z progresem i downloadem z historii; (3) każda z 4 pozostałych encji happy path (custom_module z selectem ObjectType, module_schema, attributes_groups, categories); (4) profil: save → run z taba → edit → delete; (5) wejście selected z listy; (6) formaty disabled nieklikalne; (7) taby Cele/Harmonogram disabled.
- [ ] **a11y:** axe-core na: stronie sesji, 4 krokach wizarda, profilach — 0 violations (poziom A/AA).
- [ ] **i18n:** audit `git grep` literałów PL w `features/exports` + `ui-v2` + `layout` = 0; klucze obecne w `pl/` i `en/`.
- [ ] **Benchmark skali (wymóg spec):** `ExportBenchmarkCommand` 100k wierszy × ≥20 kolumn EAV: CSV i XLSX — czas + peak memory < 256 MB w raporcie PR; smoke async na dev stacku z seedem ≥10k (realne dane, nie tylko benchmark in-vitro).
- [ ] **Dokumentacja:** `Project Plan/PRD/PRD-PIM-exports.md` — bump wersji + sekcja „Rewizja 2026-06 (EXR)": nowe encje, preflight, wycofanie modala, decyzje D1-D5; `Project Plan/UI/Wdrozenie_grafiki/` — notka, że eksporty = pierwszy moduł nowego look&feel + wskazanie tokenów (EXR-01) jako bazy dla kolejnych modułów; `agent/lessons.md` per lessons epiku; OpenAPI snapshot zgodnie z procesem release.
- [ ] **Screencast 5 min** (zasada sub-faz z CLAUDE.md): nowe menu → strona eksportów → pełny wizard → async progress → profil.

### Kryteria akceptacji
- Cały pakiet E2E zielony w CI; benchmark w PR body; dokumenty zaktualizowane w tym samym PR.

**Estymata:** 12-16 h. **Zależności:** wszystkie poprzednie.

---
---

## 6. Definicja Done — obowiązuje KAŻDY ticket (przypomnienie z CLAUDE.md)

1. PHPStan max 0 / Deptrac 0 (BE) · tsc + Biome 0 (FE) · Vite build OK.
2. PHPUnit ≥80% nowej logiki + ApiTestCase dla każdego nowego/zmienionego endpointu.
3. Playwright E2E dla każdej widocznej zmiany UI — **bez E2E ticket NIE jest done**.
4. composer/npm audit czyste; OpenAPI + shared-types regen przy każdej zmianie kontraktu.
5. **SMOKE TEST RULE:** manual smoke na żywym stacku przed użyciem słowa „działa" w PR (login → klik → Network 2xx → wynik widoczny → konsola bez errorów).
6. **CLOSED MEANS CLOSED:** `gh issue close` wyłącznie z proofem live-stack w komentarzu.
7. i18n: wszystkie stringi przez `t()`, klucze EN, tłumaczenia `pl/` + `en/`.
8. Conventional Commits EN, bez wzmianek o AI; PR opis po polsku.
9. Każdy ticket = osobny branch + PR + CI + merge (EPIK MARATHON RULE przy komendzie „przez cały epik").

## 7. Świadome ograniczenia zakresu (do wpisania w PR-y, NIE rozszerzać samowolnie)

- Formaty XML/JSON/Google Sheets/PDF — wyłącznie mock „wkrótce" (D1).
- Taby Cele i Harmonogram — wyłącznie disabled „wkrótce" (D3); cykliczne eksporty = przyszły epik.
- Dark mode — tylko niełamiący się fallback (EXR-01).
- Redesign importów, produktów i pozostałych modułów — przyszłe epiki (komponenty `ui-v2` mają być na to gotowe).
- Powiadomienia: in-app, bez persystencji BE i bez e-maili (EXR-15).
- Brak persystencji draftu wizarda między sesjami przeglądarki.
- Round-trip importu struktur (schemat/atrybuty/kategorie) — poza epikiem.
