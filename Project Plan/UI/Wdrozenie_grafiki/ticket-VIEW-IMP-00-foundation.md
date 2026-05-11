# VIEW-IMP-00 — Foundation: tab container `/integrations/imports/<tab>` + 10 reusable primitives

Epik: **UI-11 — Importy redesign** (5 widoków + foundation + audit).
Status: in progress (start: 2026-05-11).
Plan epiku: `~/.claude/plans/nifty-exploring-dolphin.md`.

## 1. Kontekst i cel widoku

Foundation epiku UI-11 — dostarcza fundament dla pozostałych 4 widoków (Sesje, Profile, Źródła, Harmonogram). Zadanie:
1. Przepiąć obecny routing płaski `/integrations/imports` na zagnieżdżony tabbed hub z `<Outlet>`-em.
2. Wyciągnąć 10 reusable prymitywów z `primitives.jsx` do TS+shadcn (`apps/admin/src/features/imports/primitives/`) — będą używane w V01..V04.
3. Pokazać tab nav (4 zakładki) zgodny z designem `Integracje.html` + przekierowanie default `/integrations/imports` → `/integrations/imports/sessions`.

Operatorska zasada (`CLAUDE.md` → SMOKE TEST RULE): foundation **nie jest done** dopóki nawigacja tabami nie działa end-to-end z living backendem (każdy tab odpowiada konkretną stroną — w V00 są to stuby placeholder dla V03/V04 plus istniejący ImportsListView/ImportProfileManager dla V01/V02).

## 2. Mockup / źródło designu

- Plik JSX prymitywów: [Zrodla/Front_Claude_Design/PIM-nowoczesny/integracje/primitives.jsx](../../Zrodla/Front_Claude_Design/PIM-nowoczesny/integracje/primitives.jsx) — 10 prymitywów (ModeBadge, StatusPill, SourceIcon, Sparkline, ResultBar, ProgressBar, StagePipeline, HealthDot, FormatPill, TinyKpi).
- Layout taba: [Zrodla/Front_Claude_Design/PIM-nowoczesny/Integracje.html](../../Zrodla/Front_Claude_Design/PIM-nowoczesny/Integracje.html) — pokazuje 4 zakładki w sub-nav pod headerem sekcji Integracje.
- Powiązane widoki (poza scope V00, będą w V01..V04): `importy-sessions.jsx`, `importy-profiles.jsx`, `importy-sources.jsx`, `importy-schedule.jsx`.
- **Pixel-perfect binding**: JSX prymitywów jest single source of truth dla klas Tailwind, paddingów, kolorów. Adaptacje: TS strict typy, propsy, shadcn `cn()` helper. Wizualny rezultat <2% pixel mismatch.

## 3. Zakres frontend (FE)

### 3.1 Routing
- **Aktualny stan** (`apps/admin/src/App.tsx`):
  ```
  /integrations/imports          → ImportsListView
  /integrations/imports/new      → ImportWizardPage
  /integrations/imports/:id      → ImportShowPage
  ```
- **Po V00**:
  ```
  /integrations/imports                → <ImportsLayout> z <Outlet>
      ├── index               → <Navigate replace to="sessions">
      ├── sessions            → <ImportsListView>          ← tymczasowo (rebuild w V01)
      ├── profiles            → <ImportProfilesPlaceholder> ← stub (rebuild w V02)
      ├── sources             → <ImportSourcesPlaceholder>  ← stub (build w V03)
      ├── schedule            → <ImportSchedulePlaceholder> ← stub (build w V04)
      ├── new                 → <ImportWizardPage>          ← bez zmian (refactor w V05)
      └── :id                 → <ImportShowPage>            ← bez zmian
  ```
- **Back-compat**: stara ścieżka `/integrations/imports` redirectuje na `/integrations/imports/sessions`. Bookmarki ludzi działają.
- **Refine resources update**: `import-sessions.list` → `/integrations/imports/sessions`; `import-profiles.list` → `/integrations/imports/profiles`.

### 3.2 Komponenty (lista płaska)
| Komponent | Plik | Props | State |
|---|---|---|---|
| `ImportsLayout` | `features/imports/layout/ImportsLayout.tsx` | `children` (przez `<Outlet>`) | brak |
| `ImportsTabNav` | `features/imports/layout/ImportsTabNav.tsx` | `tabs: TabDef[]` | aktywny tab z `useLocation()` |
| `ImportSourcesPlaceholder` | `features/imports/sources/ImportSourcesPlaceholder.tsx` | brak | brak |
| `ImportSchedulePlaceholder` | `features/imports/schedule/ImportSchedulePlaceholder.tsx` | brak | brak |
| `ImportProfilesPlaceholder` | `features/imports/profiles/ImportProfilesPlaceholder.tsx` | brak | brak |
| `ModeBadge` | `features/imports/primitives/ModeBadge.tsx` | `mode: ImportMode`, `size?: 'sm' \| 'md'` | brak |
| `StatusPill` | `features/imports/primitives/StatusPill.tsx` | `status: SessionStatus`, `label?: string` | brak |
| `SourceIcon` | `features/imports/primitives/SourceIcon.tsx` | `type: SourceType`, `size?: number` | brak |
| `Sparkline` | `features/imports/primitives/Sparkline.tsx` | `data: number[]`, `width?: number`, `height?: number`, `stroke?: string`, `fill?: string` | brak |
| `ResultBar` | `features/imports/primitives/ResultBar.tsx` | `ok: number`, `warn: number`, `err: number`, `total?: number`, `width?: number`, `height?: number` | brak |
| `ProgressBar` | `features/imports/primitives/ProgressBar.tsx` | `value: number` (0..1), `height?: number`, `animated?: boolean` | brak |
| `StagePipeline` | `features/imports/primitives/StagePipeline.tsx` | `stage: 'parsing' \| 'mapping' \| 'validating' \| 'writing' \| 'done'` | brak |
| `HealthDot` | `features/imports/primitives/HealthDot.tsx` | `health: 'ok' \| 'warn' \| 'error' \| 'off'` | brak |
| `FormatPill` | `features/imports/primitives/FormatPill.tsx` | `format: 'XLSX' \| 'XLS' \| 'CSV' \| 'JSON' \| 'XML'` | brak |
| `TinyKpi` | `features/imports/primitives/TinyKpi.tsx` | `label: string`, `value: string \| number`, `unit?: string`, `trend?: number`, `accent?: TinyKpiAccent` | brak |

Barrel: `features/imports/primitives/index.ts` eksportuje wszystkie + ich typy props.

### 3.3 State management
- Brak globalnego stanu w V00. Layout dziedziczy props przez router.
- Aktywny tab wyliczany z `useLocation().pathname` (matchuje prefix `/integrations/imports/<tab>`).

### 3.4 Struktura sekcji widoku
1. **`ImportsLayout`** (`<section>` z padding wewnętrznym `p-6 lg:p-8`):
   1. `<header>` z h1 `t('imports.tabs.section_title')` + krótki opis `t('imports.tabs.section_subtitle')`.
   2. `<ImportsTabNav>` z 4 tabami.
   3. `<Outlet>` (renderuje child route).

### 3.4a Mapping element-po-elemencie z prototypu (primitives.jsx)
Każdy prymityw 1:1 z JSX-em — patrz `primitives.jsx` linie:
- L4-22 → `ModeBadge.tsx`: 6 wariantów (ADD/UPDATE/UPSERT/MERGE/INCREMENT/DELETE), 2 rozmiary, dot + label.
- L25-42 → `StatusPill.tsx`: 7 wariantów (success/warning/error/running/queued/cancelled/paused), `running` z `pulse-dot` animation.
- L45-62 → `SourceIcon.tsx`: 6 typów (sftp/ftp/webhook/folder/upload/api), ikonki z `lucide-react` (Shield/Layers/Zap/Box/Upload/Plug), rounded square 6×6 z color tint.
- L65-82 → `Sparkline.tsx`: SVG 96×28 z `path` + `fillPath` (area chart).
- L85-99 → `ResultBar.tsx`: trzy segmenty (emerald/amber/rose) w rounded-full container.
- L102-111 → `ProgressBar.tsx`: zinc-900 fill + optional `shimmer` (CSS animation).
- L114-163 → `StagePipeline.tsx`: 5 etapów (parsing/mapping/validating/writing/done), każdy z own state (done/active/pending), chevron między etapami.
- L166-174 → `HealthDot.tsx`: dot 2×2 z color (emerald/amber/rose/zinc).
- L177-191 → `FormatPill.tsx`: 5 formatów (XLSX/XLS/CSV/JSON/XML), font-mono.
- L194-217 → `TinyKpi.tsx`: label uppercase + value font-display + unit + optional trend (▲/▼).

### 3.4b Tab nav layout (Integracje.html)
- Container: `<nav>` z `flex items-center gap-1 border-b border-zinc-200 mb-6`.
- Każdy tab: `<NavLink>` (react-router) z `px-4 py-2.5 text-sm font-medium`, active state: `text-zinc-900 border-b-2 border-zinc-900 -mb-px`, inactive: `text-zinc-500 hover:text-zinc-700`.
- Labels: `t('imports.tabs.sessions')`, `profiles`, `sources`, `schedule`.

### 3.5 i18n
Nowe klucze (pl + en):
```
imports.tabs.section_title          = "Importy"
imports.tabs.section_subtitle       = "Sesje, profile mapowań, źródła i harmonogram"
imports.tabs.aria.label             = "Zakładki Importy"
imports.tabs.sessions               = "Sesje"
imports.tabs.profiles               = "Profile mapowań"
imports.tabs.sources                = "Źródła"
imports.tabs.schedule               = "Harmonogram"
imports.placeholder.coming_soon     = "Wkrótce"
imports.placeholder.sources_subtitle  = "Konfiguracja źródeł SFTP/FTP/HTTP — zakładka aktywowana w VIEW-IMP-03"
imports.placeholder.schedule_subtitle = "Harmonogram cyklicznych importów — zakładka aktywowana w VIEW-IMP-04"
imports.placeholder.profiles_subtitle = "Biblioteka profili mapowań — zakładka aktywowana w VIEW-IMP-02"
```
Ban na literały w JSX — wszystko przez `t()`.

### 3.6 a11y
- Tab nav jako `<nav aria-label={t('imports.tabs.aria.label')}>` z listą `<ul role="tablist">`.
- Każdy tab jako `<NavLink role="tab" aria-selected={isActive}>`.
- Keyboard navigation: `Arrow Left/Right` przełącza między tabami (Refine używa react-router `<NavLink>`, native focus management).
- Focus ring shadcn `focus-visible:ring-2 ring-offset-2`.
- axe-core 0 violations serious/critical.

### 3.7 Empty / loading / error states
- N/A dla layoutu — to wrapper.
- Placeholdery dla V02/V03/V04 pokazują `<EmptyState>` z ikoną + tytuł `t('imports.placeholder.coming_soon')` + opisem.

## 4. Zakres backend (BE)

**N/A — pure FE foundation.** Backend bez zmian. Wszystkie placeholdery V02/V03/V04 nie uderzają w żadne nowe endpointy.

## 5. Sub-tasks (checklist)

**Backend**:
- [ ] (N/A — pure FE)

**Frontend**:
- [ ] Utwórz `features/imports/primitives/` z 10 prymitywami + barrel `index.ts`
- [ ] Utwórz `features/imports/layout/ImportsLayout.tsx`
- [ ] Utwórz `features/imports/layout/ImportsTabNav.tsx`
- [ ] Utwórz 3 placeholdery: `sources/ImportSourcesPlaceholder.tsx`, `schedule/ImportSchedulePlaceholder.tsx`, `profiles/ImportProfilesPlaceholder.tsx` (placeholder dla profili tymczasowy — V02 zrobi pełny widok)
- [ ] Zmodyfikuj `apps/admin/src/App.tsx`: nested routing + redirect default
- [ ] Zmodyfikuj `apps/admin/src/App.tsx`: Refine resources update (`list` paths)
- [ ] Dodaj `imports.tabs.*` + `imports.placeholder.*` do `pl.json` + `en.json`
- [ ] Sprawdź czy istnieje `shimmer` CSS animation (jeśli nie — dodaj do `apps/admin/src/styles/globals.css`)
- [ ] Sprawdź czy istnieje `pulse-dot` CSS animation (jeśli nie — dodaj)

**E2E + integration**:
- [ ] Nowy spec: `apps/admin/e2e/imports-tabs.spec.ts` (4 tab clicks + deep-link refresh + redirect default)
- [ ] Vitest dla prymitywów: `features/imports/primitives/__tests__/primitives.test.tsx` (snapshot wszystkich 10 + axe-core)
- [ ] Sprawdź czy istniejący `imports.spec.ts` nadal zielony po zmianach routingu

**Testy non-functional**:
- [ ] Biome strict: 0 errors
- [ ] TypeScript noEmit: 0 errors
- [ ] Vite build: success
- [ ] pnpm audit: 0 high/critical

**Dokumentacja**:
- [ ] Aktualizuj `agent/current_status.md` z VIEW-IMP-00 done
- [ ] PR description z manual smoke checklist

**Manual smoke (operator)**:
- [ ] Login admin@demo.localhost / changeme
- [ ] Nawigacja do `/integrations/imports` → przekierowanie na `/sessions`
- [ ] Klik każdej z 4 zakładek (Sesje / Profile / Źródła / Harmonogram)
- [ ] Deep-link refresh `/integrations/imports/sources` → ładuje placeholder Źródła
- [ ] DevTools Console — brak czerwonych errorów

## 6. Acceptance criteria — funkcjonalne

- Wygląda pixel-perfect jak design (Integracje.html tab nav + primitives.jsx prymitywy) — wizualny diff <2%.
- `/integrations/imports` redirectuje na `/integrations/imports/sessions`.
- Klik każdej zakładki ładuje odpowiednią stronę (sessions/profiles/sources/schedule).
- Deep-link refresh każdej zakładki działa (bookmark-friendly).
- Stary URL `/integrations/imports/new` zachowuje funkcjonalność wizardu.
- Stary URL `/integrations/imports/:id` zachowuje funkcjonalność show page.
- i18n PL/EN przełącza się dla wszystkich nowych kluczy.
- Wszystkie 10 prymitywów renderują się zgodnie z designem dla wszystkich wariantów props.

## 7. Acceptance criteria — non-functional (TWARDE GATES)

- **Performance**: brak nowych endpointów BE, ale FE bundle delta <50KB gzip (Vite build report w PR).
- **Indeksy**: N/A.
- **Pagination**: N/A.
- **Memory**: N/A.
- **Bundle size FE**: Δ <50KB gzip.
- **Lighthouse**: performance ≥85, a11y =100, best-practices ≥90 na `/integrations/imports`.
- **PHPStan max**: 0 errors (brak zmian PHP, ale CI musi nadal przejść).
- **Biome strict**: 0 errors.
- **TypeScript noEmit**: 0 errors.
- **PHPUnit coverage**: N/A (brak nowej logiki PHP).
- **Vitest coverage prymitywów**: ≥80% (snapshot każdego + props edge cases).
- **Playwright E2E**: `imports-tabs.spec.ts` zielony (happy path + deep-link).
- **axe-core**: 0 violations serious/critical na `/integrations/imports` + każdej zakładce.
- **composer audit + pnpm audit**: 0 high/critical.
- **Multi-tenancy**: N/A (brak zmian w danych).
- **RBAC**: re-use istniejącej `ROLE_IMPORT_VIEW`.
- **Audit log**: N/A.
- **Provenance**: N/A.
- **i18n coverage**: wszystkie nowe klucze obecne w `pl.json` i `en.json`.
- **OpenAPI snapshot**: N/A (brak nowych endpointów).

## 8. Smoke-test scenariusze (manualne, dla operatora)

1. Login `admin@demo.localhost / changeme` na `https://pim.localhost`.
2. Sidebar → **Integracje** → **Importy**.
3. URL bar powinien pokazywać `/integrations/imports/sessions` (po redirect).
4. Klik tab **Profile mapowań** → URL → `/integrations/imports/profiles` → placeholder "Wkrótce".
5. Klik tab **Źródła** → URL → `/integrations/imports/sources` → placeholder.
6. Klik tab **Harmonogram** → URL → `/integrations/imports/schedule` → placeholder.
7. F5 (refresh) na `/integrations/imports/sources` → ta sama strona ładuje się natychmiast (deep-link działa).
8. Klik tab **Sesje** → wraca do `/integrations/imports/sessions` z listą sesji (dotychczasowa funkcjonalność).
9. URL `/integrations/imports/new` w pasku → wizard otwiera się (back-compat).
10. DevTools Console — brak czerwonych errorów.
11. DevTools Network — wszystkie requesty `200`, brak `404`/`500`.

## 9. Edge cases / poza zakresem

**Świadomie poza zakresem V00** (deferred do follow-up ticketów):
- Pełen widok Sesje wg `importy-sessions.jsx` (KPI strip, hero card, history table) → **VIEW-IMP-01**.
- Pełen widok Profile wg `importy-profiles.jsx` (grid/list toggle, CRUD) → **VIEW-IMP-02**.
- Pełen widok Źródła + BE (ImportSource encja, health-check, polling) → **VIEW-IMP-03**.
- Pełen widok Harmonogram + BE (ImportSchedule encja, cron worker, notifications) → **VIEW-IMP-04**.
- Refactor wizardu wg `Import-nowy.html` → **VIEW-IMP-05**.

**Edge cases pokryte w V00**:
- Refresh na deep-link (`/integrations/imports/sources`) → ładuje placeholder.
- Back button browser → wraca do poprzedniego taba.
- Direct URL old `/integrations/imports` → redirect na `/sessions`.

**Edge cases zostawione na później** (audit ticket lub V01+):
- Tab nav responsive na mobile (<640px) → audit ticket.
- Tab nav z badge "12 aktywnych" na zakładce Sesje → V01 (LiveSessionCard kontekst).

## 10. Powiązane ADR / dokumenty

- **ADR**: brak (czysty FE refactor + addition).
- **Aktualizacje**:
  - `agent/current_status.md` — sekcja VIEW-IMP-00.
  - `Project Plan/02-plan-projektu-pim.md` — checkbox dla VIEW-IMP-00 (po merge).
  - `~/.claude/plans/nifty-exploring-dolphin.md` — referencja planu epiku (nie edytujemy w trakcie ticketu).
