# Plan: wdrożenie design handoffu (Dashboard / Modelowanie / Produkty)

> **Canonical location** (od 2026-05-01). Ten plik jest source of truth dla epiku UI-03 (issues #356/#357/#358). Kopia w `~/.claude/plans/do-folderu-design-handoff-modelowanie-wr-polymorphic-nova.md` to plan-mode artifact, jest stale i nie powinna być edytowana. Każda aktualizacja idzie tutaj. Zob. `CLAUDE.md` § "Pliki, które utrzymujesz atomowo" + `agent/lessons.md` § "Epik UI-03 — single source of truth lokalizacja".

## Context

Operator wrzucił do `Zrodla/Front_Claude_Design/design_handoff_modelowanie/` prototypy HTML+JSX (Babel-in-browser) dla 3 widoków: **Dashboard**, **Modelowanie** (4 sub-taby: Object Types, Attributes, Attribute Groups, Categories), **Produkty**. Handoff opisuje stack docelowy Next.js 14 + shadcn — **świadomie pomijamy** i zostajemy przy obecnym stacku PIM (React 19 + Refine + shadcn/ui + Vite + react-i18next). Powód: CLAUDE.md PIM definiuje stack jako "nienegocjowalny w MVP".

Stan startowy:

- **Dashboard** nie istnieje. `apps/admin/src/App.tsx:93` redirectuje `/` → `/products`. `layout/sidebar-nav.tsx` ma item Dashboard z `comingSoon: true`. Backend nie ma żadnego dashboardowego endpointu (jest tylko `MetricsController` dla Prometheus, nie dla biznesu).
- **Modelowanie** ~60% gotowe — 4 sub-taby read-only (list + detail show pages) pod `/modeling/{object-types|attributes|attribute-groups|categories}`. Brakuje create forms, AttributeValuesView, MigrationImpactModal, effective-attributes preview dla kategorii, drag-reorder, audit log per encja, schema_rev counter.
- **Produkty** ~70-80% gotowe (po marathonie UI-02) — list z saved views/bulk toolbar/Excel grid/variants tree, detail z dynamic form/sidebar/wizard create. Brakuje: bulk operacji innych niż `toggle_enabled`, relationships/associations CRUD, CSV import, full variants inheritance, agent suggestions wiring.

Cel: rozpisać 3 GitHub tickety — po jednym per widok. Dashboard = mock HTML z `{/* MOCK: ... wymaga oprogramowania */}` komentarzami. Modelowanie i Produkty = pełna harmonizacja wizualna z designem + analogiczne komentarze TODO przy nieoprogramowanych featurach + plik `.md` w `Project Plan/handoff-modelowanie/` z konkretną listą brakujących rzeczy jako baza pod kolejne tickety.

## Decyzje strategiczne (uzgodnione z operatorem)

1. **Tokens** — pełna migracja design tokens (Inter + JetBrains Mono przez `@fontsource`, hex palette `#fafaf9 / #18181b / #ececea / ...`, akcent palette violet/emerald/blue/amber/rose/sky/zinc, soft-shadow + soft-shadow-lg + glass-strong utilities, `.num` tabular-nums utility, radii lg=12/xl=16/2xl=20/3xl=24px). **Wpływa na cały admin** — Assets, Channels, ApiProfiles, Login dostają nowe fonty i przestrojone neutrale.
2. **Pliki .md backlogu** w `Project Plan/handoff-modelowanie/`:
   - `dashboard-do-oprogramowania.md`
   - `modelowanie-do-oprogramowania.md`
   - `produkty-do-oprogramowania.md`
3. **Dashboard route** — dodajemy `/dashboard` w `App.tsx`, redirect `/` → `/dashboard` (zamiast `/products`), zdejmujemy `comingSoon: true` w `sidebar-nav.tsx`.
4. **Stack** — zostajemy przy React 19 + Refine + shadcn/Radix + Vite + react-i18next + Tailwind 4 (zgodnie z CLAUDE.md PIM § "Stack nienegocjowalny w MVP"). Nie wprowadzamy Next.js, TanStack Query/Table per handoff CLAUDE.md (już mamy Refine + RQ pod spodem; TanStack Table 8 jest już w zależnościach z UI-02).
5. **Token migration jako część ticketu #1 Dashboard** — nie rozdzielamy do osobnego ticketu, bo Dashboard jako pierwszy widok handoffu naturalnie wprowadza tokens; #2 i #3 zakładają że #1 jest zmergowany. Test plan #1 ma sekcję "Visual regression: Assets/Channels/ApiProfiles/Login".

## Konwencje TODO/MOCK w komentarzach (spójne między 3 ticketami)

W kodzie używamy dwóch markerów:

- **`{/* MOCK: <opis> — wymaga oprogramowania (#<ticket-id>) */}`** — dla bloków UI z hardcoded danymi, których backend jeszcze nie ma. ID ticketu wstawiamy po utworzeniu .md backlogu (na początku zostawiamy `(#TBD)`).
- **`// TODO(handoff): <opis> — patrz Project Plan/handoff-modelowanie/<view>-do-oprogramowania.md`** — dla luk w logice/wiringu (nie czysto wizualnych mock-bloków), gdzie istnieje już wireup ale brakuje funkcjonalności (np. bulk export button bez backendu).

Każdy plik `.md` backlogu ma format:

```markdown
# {View} — backlog do oprogramowania

> Baza pod kolejne GitHub tickety. Każda pozycja zaznaczona w kodzie
> komentarzem `MOCK:` lub `TODO(handoff)`.

## Frontend-only (mock UI, nie wymaga backendu)
- [ ] <feature> — pliki: `<path>:<line>` — szacowanie: <S/M/L>

## Frontend + nowy endpoint backendowy
- [ ] <feature> — endpoint: `<METHOD> /api/...` — pliki FE/BE
- ...

## Wymaga decyzji architektonicznej (przed wdrożeniem)
- [ ] <decyzja> — kontekst, pytanie do rozstrzygnięcia
- ...
```

---

# Ticket #1: Dashboard handoff + migracja design tokens

**Branch:** `feat/handoff-dashboard-tokens`
**GitHub title:** `feat(admin): handoff Dashboard widok + migracja design tokens`
**Scope estimate:** L (3-5 dni)

## Zakres

### Część A: Migracja design tokens (cały admin)

**Pliki do modyfikacji:**

- `apps/admin/package.json` — dodać `@fontsource/inter` (300/400/500/600/700/800) + `@fontsource/jetbrains-mono` (400/500)
- `apps/admin/src/main.tsx` — importy fontów `@fontsource/inter/{...}.css` + `@fontsource/jetbrains-mono/{400,500}.css`
- `apps/admin/src/index.css` — pełna przebudowa `:root` i `.dark` zgodnie z handoffu README § "Design System":
  - **Neutrals (light):** `--bg #fafaf9`, `--surface #ffffff`, `--surface-2 #f5f5f4`, `--ink #18181b`, `--ink-2 #3f3f46`, `--muted #71717a`, `--line #ececea`
  - **Akcent palette:** `--accent-violet #a855f7`, `--accent-emerald #10b981`, `--accent-blue #3b82f6`, `--accent-amber #f59e0b`, `--accent-rose #f43f5e`, `--accent-sky #0ea5e9`, `--accent-zinc #71717a`
  - **Radii:** `--radius-lg 12px`, `--radius-xl 16px`, `--radius-2xl 20px`, `--radius-3xl 24px` (mapowane na `border-radius-lg`/`xl`/`2xl`/`3xl` w Tailwind)
  - **Fonty:** `--font-sans Inter, ui-sans-serif, system-ui, ...`, `--font-mono "JetBrains Mono", ui-monospace, ...` z feature settings `"ss01", "cv11"` na body i `"tnum", "ss01"` na `.num`
  - **Letter-spacing:** body `-0.011em`, display class `-0.035em`
  - **Utilities** w warstwie `@layer utilities`:
    - `.soft-shadow { box-shadow: 0 1px 0 rgba(24,24,27,.04), 0 1px 2px rgba(24,24,27,.04), 0 12px 30px -12px rgba(24,24,27,.06) }`
    - `.soft-shadow-lg { box-shadow: 0 1px 0 rgba(24,24,27,.04), 0 2px 4px rgba(24,24,27,.04), 0 24px 60px -20px rgba(24,24,27,.10) }`
    - `.glass-strong { background: rgba(255,255,255,.86); backdrop-filter: saturate(180%) blur(28px); -webkit-backdrop-filter: saturate(180%) blur(28px) }`
    - `.num { font-feature-settings: "tnum","ss01"; font-variant-numeric: tabular-nums }`
    - `.focus-ring:focus-visible { outline: none; box-shadow: 0 0 0 4px rgba(24,24,27,.08) }`
- `apps/admin/src/components/ui/*` — punktowo zaktualizować `Button`, `Card`, `Input`, `Sheet` żeby zamiast `rounded-md` używały `rounded-xl`/`rounded-2xl` zgodnie z designem (per shadcn override; nie hard-replace, tylko zmiana defaultu)
- Tailwind config (jeśli jest dedykowany; w v4 to inline `@theme` w `index.css`) — `extend` obejmuje akcent palette jako Tailwind classes (`bg-accent-violet`, `text-accent-emerald`)

### Część B: Dashboard mock + routing

**Pliki do utworzenia:**

```
apps/admin/src/features/dashboard/
├── page.tsx                   # główny widok DashboardPage
├── components/
│   ├── HeroAgentPanel.tsx     # MOCK: agent CTA card + command palette placeholder
│   ├── KpiCards.tsx           # MOCK: 4 cards (Produkty, Atrybuty, Rodziny, Kategorie)
│   ├── ActivityChart.tsx      # MOCK: wykres 30-dniowy (statyczny SVG / inline data)
│   ├── TopEditedProducts.tsx  # MOCK: top 10 edytowanych produktów
│   ├── SyncsStatusPanel.tsx   # MOCK: 4 integracje (Shopify, BaseLinker, Google Shopping, Comarch)
│   ├── CompletenessMetrics.tsx # MOCK: overall + per-channel (4 progress rings)
│   ├── RecentAgentActivity.tsx # MOCK: 6 logów z provenance/status badges
│   ├── AlertCenter.tsx        # MOCK: 5 alertów (severity err/warn/info)
│   └── ChannelDistribution.tsx # MOCK: stacked bar publikacji po kanałach
└── mock-data.ts               # wszystkie hardkody w jednym miejscu, z komentarzem
                               # "// MOCK DATA — backend endpointy w Project Plan/handoff-modelowanie/dashboard-do-oprogramowania.md"
```

**Każdy komponent zaczyna się od:**

```tsx
/**
 * MOCK component — dane statyczne z mock-data.ts.
 * Backend: brak. Patrz Project Plan/handoff-modelowanie/dashboard-do-oprogramowania.md
 */
```

**Wewnątrz JSX dla bloków funkcjonalnych z brakującą logiką** (np. button "Force sync", "Zapytaj agenta", filtry chartu):

```tsx
{/* MOCK: button "Wymuś synchronizację" — wymaga POST /api/integrations/{id}/sync (#TBD) */}
<Button>Wymuś synchronizację</Button>
```

**Pliki do modyfikacji:**

- `apps/admin/src/App.tsx` — dodać `import { DashboardPage } from '@/features/dashboard/page';` + Route `/dashboard` jako pierwszy w `<Route element={<AppLayout />}>`. Zmienić index `<Navigate to="/products" />` na `<Navigate to="/dashboard" />`.
- `apps/admin/src/layout/sidebar-nav.tsx` — Dashboard item: usunąć `comingSoon: true`, dodać `to: '/dashboard'`, dodać `icon: LayoutDashboard` z `lucide-react`.
- `apps/admin/src/locales/pl/common.json` (i `en/common.json`) — klucze dla user-facing stringów (operator-facing copy z handoffu jest po polsku, ale per CLAUDE.md PIM § "i18n" wszystkie stringi UI muszą być przez `t()`).

### Część C: Plik backlogu

**Plik do utworzenia:** `Project Plan/handoff-modelowanie/dashboard-do-oprogramowania.md`

Treść (skrócona — w PR pełna wersja):

```markdown
# Dashboard — backlog do oprogramowania

## Frontend-only (już mock w UI, czeka na decyzje)
- [ ] Wybór zakresu czasowego dla ActivityChart (7d/30d/90d)
- [ ] Konfigurowalne KPI cards (operator wybiera które 4 widoczne)

## Frontend + nowy endpoint backendowy
- [ ] KPI cards counts — endpoint `GET /api/dashboard/kpis` zwracający
      `{ products: int, attributes: int, families: int, categories: int }`
      + delta vs. poprzedni okres
- [ ] Activity chart (dodania/modyfikacje per dzień) — `GET /api/dashboard/activity?range=30d`
- [ ] Top edited products — `GET /api/dashboard/top-edited?limit=10`
- [ ] Syncs status panel — `GET /api/integrations/status` (per-integration: lastSync, ok/warn/err, pushed, failed)
- [ ] Completeness metrics overall + per-channel — `GET /api/dashboard/completeness`
- [ ] Recent agent activity — `GET /api/audit-log?actor=agent&limit=6`
      (uwaga: agent provenance dopiero w Fazie 2 per CLAUDE.md PIM)
- [ ] Alert center — `GET /api/alerts?limit=5` (encja Alert nie istnieje)
- [ ] Channel distribution — `GET /api/dashboard/channel-distribution`

## Wymaga decyzji architektonicznej
- [ ] Hero "Zapytaj agenta" CTA → wymaga decyzji o LLM provider integration
      (Anthropic SDK PHP per CLAUDE.md, ale agent layer = Faza 2)
- [ ] Schema completeness algorytm — jak liczymy `completeness` per kanał?
      Po wymaganych atrybutach z mappingu integracji?
```

## Quality gates dla Ticketu #1

- `pnpm --filter admin typecheck` zielony
- `pnpm --filter admin lint` (Biome strict) zielony
- `pnpm --filter admin build` zielony
- Playwright e2e:
  - `dashboard.spec.ts` — login → /dashboard → widoczne wszystkie 9 bloków + brak czerwonych errorów w Console
  - **Visual regression** — screenshoty dla `/products`, `/assets`, `/channels`, `/api-profiles`, `/login` (sprawdzenie że migracja tokenów nie złamała żadnej istniejącej strony)
- Manual smoke test (per CLAUDE.md PIM § "SMOKE TEST RULE"):
  1. Login (admin@demo.localhost / changeme)
  2. Klik /dashboard w sidebarze
  3. Każdy z 9 bloków renderuje się
  4. Brak 4xx/5xx w DevTools Network (poza dopuszczonymi 404 dla nieistniejących endpointów — ale tych mockujemy w pełni, więc nie powinno być żadnych żądań do `/api/dashboard/*`)
  5. Brak czerwonych errorów w Console
  6. Sprawdzić Login + Assets + Channels + ApiProfiles (że tokeny nie zepsuły istniejącego)

---

# Ticket #2: Modelowanie — harmonizacja z handoffem + backlog brakujących features

**Branch:** `feat/handoff-modelowanie`
**Blocker:** Ticket #1 (zmergowany)
**GitHub title:** `feat(admin): handoff Modelowanie 4 sub-taby — wizualna harmonizacja + TODO komentarze`
**Scope estimate:** L (4-6 dni)

## Zakres

Każda z 4 sub-zakładek dostaje:

1. **Wizualną harmonizację** z handoffem (layout, padding, typography, akcent kolorów per typ atrybutu, soft-shadows na cards/sheets, sticky header z `glass-strong`).
2. **Komentarze MOCK/TODO(handoff)** przy konkretnych blokach UI których nie umiemy dziś podpiąć.
3. **Wpis w `Project Plan/handoff-modelowanie/modelowanie-do-oprogramowania.md`** dla każdej luki.

Świadomie **NIE robimy** w tym tickecie:
- Tworzenia nowych formularzy create/edit (ObjectType, Attribute, AttributeGroup) — to osobne tickety per .md backlog
- AttributeValuesView (full-page editor wartości select) — osobny ticket z nowym endpointem
- MigrationImpactModal — osobny ticket
- Effective-attributes preview kategorii — osobny ticket z nowym endpointem
- visible_when rule builder — wymaga decyzji architektonicznej (JsonLogic vs custom DSL — patrz handoff CLAUDE.md "Open architecture decisions")
- Schema_rev counter w footerze — osobny ticket z backendem

### Sub-tab 1: Object Types

**Pliki do modyfikacji:**
- `apps/admin/src/features/catalog/object-types/list.tsx`
  - Layout: header z opisem nad `Nowy typ` button (po lewej, NIE floating right) per handoff
  - Cards "Built-in (system)" i "Custom" jako dwie sekcje z ikoną 🔒 na rzędach built-in
  - Każdy row: 40px icon tile (color-tinted background `style={{ background: type.color + "18" }}`), name, PL/EN, groups count, instances count, chevron
- `apps/admin/src/features/catalog/object-types/show.tsx`
  - Sheet 780px (już używamy shadcn `<Sheet>`?) — sprawdzić, dostosować szerokość
  - Sekcje: Identifikacja, Grupy atrybutów (z mock drag handle + komentarzem `TODO(handoff): drag-reorder`), Hierarchical/Variants toggles, Audit log section z `{/* MOCK: audit log last 5 changes — wymaga GET /api/object-types/{id}/audit-log */}`, Right rail ze stats
- **Nowy przycisk** `Stwórz nowy ObjectType` na końcu cards Custom — dziś prowadzi do `/modeling/object-types/new` (route nie istnieje); dodać route + placeholder page z `{/* MOCK: 4-step wizard — wymaga oprogramowania (#TBD) */}` (4 puste step containerów + "Anuluj" button)

### Sub-tab 2: Attributes

**Pliki do modyfikacji:**
- `apps/admin/src/features/catalog/attributes/list.tsx`
  - **Brakująca kolumna "Wartości"** — dla `select`/`multi-select` violet badge z licznikiem (`7 wartości` + 🪟 layers icon), klikalna — onClick prowadzi do `/modeling/attributes/{id}/values` (route nie istnieje, więc placeholder page z `{/* MOCK: AttributeValuesView ... */}`)
  - Filter chips (typ atrybutu): `wszystkie / text / number / select / boolean / money / datetime / ...` (już mamy filtry?)
- `apps/admin/src/features/catalog/attributes/show.tsx`
  - Sekcje: Identyfikacja, Walidacja, Default, Typ + scope, Allowed values preview (dla select) z `{/* MOCK: button "Zarządzaj wartościami" — wymaga endpointów /values */}`, Stats, **Migration impact button** (już mamy `migrate-type` route — wzbogacić opis i dodać `{/* MOCK: dryRun preview — wymaga POST /api/attributes/{id}/migrate?dryRun=true */}`)

### Sub-tab 3: Attribute Groups (⭐ first-class entity)

**Pliki do modyfikacji:**
- `apps/admin/src/features/catalog/attribute-groups/list.tsx`
  - Header z badge `⭐ first-class entity` + count
  - Card podzielona: "System (auto-attached)" 🔒 + "Business groups"
  - 40px icon tile + attribute count + ObjectTypes count + categories count
- `apps/admin/src/features/catalog/attribute-groups/show.tsx`
  - Sekcje: Identyfikacja, Atrybuty in group (drag handle mock, `{/* MOCK: drag-reorder, +Z biblioteki picker, +Stwórz inline ... */}`), **Conditional visibility section** (`{/* MOCK: visible_when rule builder — wymaga decyzji JsonLogic vs custom DSL */}`), Where used (ObjectTypes + Categories with inheritance trace)

### Sub-tab 4: Categories

**Pliki do modyfikacji:**
- `apps/admin/src/features/catalog/categories/list.tsx` (`CategoriesTreePage`)
  - Two-column grid: 320px tree po lewej + flex detail po prawej
  - ObjectType filter (Service / Product / Asset) per handoff
  - Tree: drag handle placeholder (z `TODO(handoff): drag-and-drop`), expand/collapse, badges grup attached at node
- `apps/admin/src/features/catalog/categories/show.tsx`
  - Sekcje: identyfikacja, ltree path, attached groups (declared directly), Inherited from parents (read-only z arrow → source), **Effective preview card** (violet border) z `{/* MOCK: computed final attribute set z provenance ("od lekarz", "od lekarz.chirurg", "własne") — wymaga GET /api/categories/{id}/effective-attributes (#TBD) */}`

**Plik do utworzenia/aktualizacji:** `apps/admin/src/features/catalog/modeling/layout.tsx`
- Tab nav z handoffu: aktywny tab = czarny underline 2px + count pill flip black-on-white → white-on-black
- Routing przez React Router (URL routing per CLAUDE.md PIM, nie hash)

### Plik backlogu

`Project Plan/handoff-modelowanie/modelowanie-do-oprogramowania.md`:

```markdown
# Modelowanie — backlog do oprogramowania

## Object Types
### Frontend + nowy endpoint backendowy
- [ ] NewObjectType wizard (4 kroki) — endpoint `POST /api/object_types`
- [ ] Edit ObjectType (icon, color, hierarchical/hasVariants/isAbstract toggle) — `PATCH /api/object_types/{id}`
- [ ] Drag-reorder grup atrybutów w detailu — `PATCH /api/object_types/{id}/groups/order`
- [ ] Audit log section — `GET /api/object_types/{id}/audit-log`

## Attributes
### Frontend + nowy endpoint backendowy
- [ ] AttributeValuesView (full-page editor select/multi-select values)
      — `GET/POST/PATCH/DELETE /api/attributes/{id}/values`
      — entity attribute_values (czy istnieje? Sprint 0 tabele)
- [ ] NewAttribute form — `POST /api/attributes`
- [ ] Edit Attribute metadata — `PATCH /api/attributes/{id}`
- [ ] MigrationImpactModal — `POST /api/attributes/{id}/migrate-type?dryRun=true`
      i `POST /api/attributes/{id}/migrate-type` (commit)
- [ ] Kolumna "Wartości" violet badge z licznikiem — wymaga endpointu values

## Attribute Groups (ADR-012 first-class)
### Frontend + nowy endpoint backendowy
- [ ] NewAttributeGroup form — `POST /api/attribute_groups`
- [ ] Edit Group — `PATCH /api/attribute_groups/{id}`
- [ ] AddAttributeFromLibrary modal — `POST /api/attribute_groups/{id}/attributes`
- [ ] CreateAttributeInGroup modal (skrócona forma + auto-attach)
- [ ] Drag-reorder atrybutów w grupie — `PATCH /api/attribute_groups/{id}/attributes/order`

### Wymaga decyzji architektonicznej
- [ ] visible_when rule format (JsonLogic vs JSON-DSL custom)
      → wpływa na schema attribute_group.visible_when oraz UI rule builder

## Categories
### Frontend + nowy endpoint backendowy
- [ ] Effective attributes preview z provenance —
      `GET /api/categories/{id}/effective-attributes`
- [ ] Drag-and-drop tree (move subtree) — `PATCH /api/categories/{id}/move`
- [ ] Attach/detach groups at node — `POST/DELETE /api/categories/{id}/groups/{groupCode}`

## Cross-cutting (Modelowanie)
- [ ] schema_rev counter w footerze (`model schema rev 47`)
      — wymaga global schema rev tracking
- [ ] Audit log per encja (last 5 changes w detailu)
      — wymaga endpointu audit-log per resource
```

## Quality gates dla Ticketu #2

- typecheck / lint / build zielone
- Playwright `modelowanie.spec.ts` — login → przeklik wszystkich 4 sub-tabów → otwarcie detail row z każdej zakładki → brak Console errors
- Manual smoke test:
  1. Login → /modeling
  2. Klik każdy z 4 tabów — visualnie matchują handoff (sticky header glass, soft-shadow cards, ikona violet dla Attribute Groups badge ⭐)
  3. Otwórz detail row — Sheet z prawej, sekcje renderują się z mock comments
  4. Network: tylko zapytania do istniejących endpointów (object_types, attributes, attribute_groups, categories list/show)
  5. Console clean

---

# Ticket #3: Produkty — harmonizacja z handoffem + backlog brakujących features

**Branch:** `feat/handoff-produkty`
**Blocker:** Ticket #1 (zmergowany)
**GitHub title:** `feat(admin): handoff Produkty list+detail — wizualna harmonizacja + TODO komentarze`
**Scope estimate:** M (3-4 dni)

## Zakres

Po marathonie UI-02 mamy juz większość features. Ten ticket robi:

1. **Wizualną harmonizację** istniejących komponentów z handoffem.
2. **Mock/TODO komentarze** przy nieoprogramowanych blokach (bulk export, relationships tab content, agent suggestions wiring, CSV import).
3. **Plik backlogu** z konkretnymi rzeczami do dorobienia.

### Pliki do modyfikacji

- `apps/admin/src/features/catalog/products/list.tsx` — toolbar layout, saved views chips styling, keyboard hints (⌘C/⌘V/⇧↓/F2) jako `<kbd>` elementy stylizowane z handoffu
- `apps/admin/src/features/catalog/products/components/excel-like-grid.tsx` — channel sync dots styling per accent palette, completeness bar w soft-shadow, tabular nums (.num) na cenach
- `apps/admin/src/features/catalog/products/components/bulk-actions-toolbar.tsx`:
  - **Edytuj atrybut** button — `{/* MOCK: bulk attribute edit modal — wymaga rozszerzenia POST /api/products/bulk-edit o operation 'edit_attribute' (#TBD) */}`
  - **Zmień kategorię** button — analogicznie `{/* MOCK: ... operation 'change_category' (#TBD) */}`
  - **Eksport** button — `{/* MOCK: bulk export modal — wymaga GET /api/products/export?ids=...&format=csv (#TBD) */}`
- `apps/admin/src/features/catalog/products/show.tsx` (lub `detail-view.tsx`):
  - Header z completeness ring (animated arc 0-100%) — istnieje `completeness-badge.tsx`, dostosować do ringa zamiast linear bara per handoff
  - Sticky tabs `Atrybuty / Multimedia / Powiązania / Historia` z liczników
  - Locale toggles PL/EN/DE/CS jako radio buttons
  - Channel selector (Shopify / BaseLinker / Allegro)
- `apps/admin/src/features/catalog/products/components/detail-dynamic-form.tsx` — provenance badge styling (violet dla agent w Fazie 2 + komentarz że teraz tylko manual/import/integration/system)
- `apps/admin/src/features/catalog/products/components/detail-sidebar.tsx`:
  - Agent suggestions card — `{/* MOCK: agent suggestions — wymaga agent layer Fazy 2 (#TBD) */}` na każdym z 3 placeholderów
  - Force sync button — `{/* MOCK: force sync — wymaga POST /api/integrations/{id}/sync */}`
- **Tabs Multimedia / Powiązania / Historia** — jeśli nie istnieją jako osobne komponenty, utworzyć stub'y:
  - `MediaTab.tsx` — `{/* MOCK: 4 image grid + Upload — wymaga DAM + S3 storage (#TBD) */}`
  - `RelationshipsTab.tsx` — `{/* MOCK: 3 typy powiązań (Akcesoria, Cross-sell, Alternatywa) — wymaga AssociationController CRUD (#TBD) */}`
  - `HistoryTab.tsx` — `{/* MOCK: audit timeline — wymaga GET /api/products/{id}/audit-log (#TBD) */}`
- **Toolbar "Import"** — `{/* MOCK: CSV/XLSX import — wymaga POST /api/products/import?dryRun=true (#TBD) — patrz Project Plan/handoff-modelowanie/produkty-do-oprogramowania.md */}`

### Plik backlogu

`Project Plan/handoff-modelowanie/produkty-do-oprogramowania.md`:

```markdown
# Produkty — backlog do oprogramowania

## Frontend + nowy endpoint backendowy

### Bulk operations (rozszerzenie istniejącego /bulk-edit)
- [ ] Bulk attribute edit — `POST /api/products/bulk-edit` z operation `edit_attribute`
      (frontend BulkEditAttributeModal — picker atrybutu + value input)
- [ ] Bulk category change — `POST /api/products/bulk-edit` z operation `change_category`
- [ ] Bulk export CSV — `GET /api/products/export?ids=...&format=csv` (streaming)

### Relationships / Associations
- [ ] AssociationController CRUD — Entity + Repository istnieją, brak controllera
      `GET /api/products/{id}/relationships`
      `POST /api/products/{id}/relationships`
      `DELETE /api/products/{id}/relationships/{relationId}`
      `PATCH /api/products/{id}/relationships/order`
- [ ] RelationshipsTab UI (3 sekcje per typ: akcesorium / cross-sell / alternatywa)
      z autocomplete SKU pickerem, drag-reorder

### Media / DAM
- [ ] Upload do MinIO/S3 przez Flysystem — `POST /api/products/{id}/media`
- [ ] Image transformations (thumbnail, channel-specific resize)
- [ ] AI metadata extraction (Faza 2)

### History / Audit
- [ ] Per-product audit log — `GET /api/products/{id}/audit-log`
      (event sourcing już istnieje w Catalog/Contracts/Event/*; brak endpointu HTTP)

### Import
- [ ] CSV/XLSX import dryRun + commit — `POST /api/products/import?dryRun=true`
      streaming response z preview, validation errors, sample rows
- [ ] Import commit — `POST /api/products/import`

### Agent (Faza 2 per CLAUDE.md PIM)
- [ ] Agent suggestions card wiring — Anthropic SDK PHP integration
- [ ] "Wygeneruj opis EN" tool dispatch
- [ ] "Uzupełnij kod HS" tool dispatch
- [ ] "Zaproponuj akcesoria" tool dispatch

## Wymaga decyzji architektonicznej

- [ ] Variants inheritance model — lazy compute (per read) vs. eager denormalization
      → performance tradeoff dla dużych masterów z wieloma variantami
- [ ] CSV import schema — fixed columns vs. ObjectType-aware (dynamic columns
      per attached AttributeGroups)
- [ ] Saved views per-user vs. tenant-shared — obecnie shared (MVP), per-user
      dopiero po implementacji CurrentUserProvider w Identity (Faza 1)
- [ ] AttributeLevel enum (master|variant|both) — czy variant może mieć własne
      atrybuty których master nie ma? Wpływa na schema product_values
```

## Quality gates dla Ticketu #3

- typecheck / lint / build zielone
- Playwright istniejące testy (`products-list.spec.ts`, `products-detail.spec.ts`) zielone
- Nowe Playwright assertions: detail Sheet renderuje 4 tabs (Atrybuty/Multimedia/Powiązania/Historia), kliknięcie każdego pokazuje mock content
- Manual smoke test (krytyczny per CLAUDE.md PIM § "SMOKE TEST RULE" — UI-02 wykryło 7 silent bugów):
  1. Login → /products
  2. Saved views chip click — Network: GET /saved-views OK 200
  3. Bulk select 2 produkty → toolbar pokazuje wszystkie buttony, ale Edytuj atrybut/Zmień kategorię/Eksport mają widoczne mock komentarze albo pokazują toast "Funkcja w przygotowaniu" zamiast 404
  4. Otwórz produkt detail → wszystkie 4 tabs renderują się
  5. Console clean
  6. Network: brak 4xx/5xx (poza dopuszczalnymi mock-only blokami)

---

# Verification (po wszystkich 3 ticketach)

End-to-end test scenariusza operatora:

1. Login na `https://pim.localhost` (admin@demo.localhost / changeme)
2. **Dashboard** ładuje się jako default — operator widzi 9 bloków, każdy z mock data, czytelne komentarze TODO przy interaktywnych elementach
3. Klik **Modelowanie** — 4 sub-taby visualnie matchują handoff, każdy detail Sheet otwiera się z mock comments dla brakujących sekcji (np. effective preview kategorii)
4. Klik **Produkty** — list z saved views/bulk/Excel grid działa jak dziś (po UI-02), detail z 4 tabs (Atrybuty/Multimedia/Powiązania/Historia) renderują się
5. Operator otwiera 3 pliki w `Project Plan/handoff-modelowanie/` — widzi konkretną listę co dorobić jako kolejne tickety, każda pozycja z (a) co/gdzie w kodzie, (b) endpoint backendowy do dodania, (c) jeśli decyzja architektoniczna — opis pytania
6. Wizualna regresja: Assets, Channels, ApiProfiles, Login renderują się bez wizualnych regresji (Inter zamiast system font, soft-shadows, ale layout zachowany)

# Pliki krytyczne (referencja)

- Design handoff: [Zrodla/Front_Claude_Design/design_handoff_modelowanie/](../../../Library/CloudStorage/SynologyDrive-MiM/Dokumenty/Programowanie/Projekty/PIM/Zrodla/Front_Claude_Design/design_handoff_modelowanie/)
- Routing: [apps/admin/src/App.tsx](../../../Library/CloudStorage/SynologyDrive-MiM/Dokumenty/Programowanie/Projekty/PIM/apps/admin/src/App.tsx)
- Sidebar: [apps/admin/src/layout/sidebar-nav.tsx](../../../Library/CloudStorage/SynologyDrive-MiM/Dokumenty/Programowanie/Projekty/PIM/apps/admin/src/layout/sidebar-nav.tsx)
- Tokens: [apps/admin/src/index.css](../../../Library/CloudStorage/SynologyDrive-MiM/Dokumenty/Programowanie/Projekty/PIM/apps/admin/src/index.css)
- Modelowanie features: [apps/admin/src/features/catalog/](../../../Library/CloudStorage/SynologyDrive-MiM/Dokumenty/Programowanie/Projekty/PIM/apps/admin/src/features/catalog/)
- Produkty features: [apps/admin/src/features/catalog/products/](../../../Library/CloudStorage/SynologyDrive-MiM/Dokumenty/Programowanie/Projekty/PIM/apps/admin/src/features/catalog/products/)

# Sekwencja merge'owania

1. **#1 Dashboard + tokens** — pierwszy, bo wprowadza globalne tokeny. Operator manualnie waliduje całość admina po merge.
2. **#2 Modelowanie** + **#3 Produkty** — równolegle (oba startują na main z #1 zmergowanym), bo nie kolidują plikowo.
3. Po merge'u trzech ticketów: utworzenie kolejnych ticketów z plików `.md` w `Project Plan/handoff-modelowanie/` jako osobne issues z linkami do konkretnych pozycji backlogu.
