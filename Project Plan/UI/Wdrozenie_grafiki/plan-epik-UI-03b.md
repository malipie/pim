# Plan epika UI-03b — Pixel-perfect harmonization (Pulpit + Modelowanie + Produkty + Admin Shell)

## Context

Operator zalogował się do panelu po marathon UI-03 (PR #359 dashboard, #360 modelowanie, #361 produkty — wszystkie merged) i zaobserwował że **strony nie odpowiadają makietom** w `Project Plan/UI/Wdrozenie_grafiki/`. Konkretnie: makieta Modelowania pokazuje sidebar z brandingiem "Pim · Klimas Sp. z o.o." + agent search bar, breadcrumb "Workspace / Modelowanie", KPI tabs z licznikami, card-based listing built-in vs custom — a w PIM widzimy stary plain table layout.

Inwentaryzacja read-only (3 parallel exploration agents) zidentyfikowała ~25 luk wizualnych rozłożonych na 4 obszary:
- **Admin shell** — brak workspace section / agent search / breadcrumb / audit indicator / user profile na dole sidebara
- **Pulpit** — 9 mock bloków istnieje (PR #359), brakuje range pickera, MOCK badges, skeleton loaderów
- **Modelowanie** — 4 sub-tabs jako tabele (nie karty), brak KPI badges w tabbar, brak 2-col layout dla Categories/Attribute Groups
- **Produkty** — list polish z #361, brakuje bulk MOCK toolbar / kbd hints / Completeness ring / agent suggestions card

**Cel epika UI-03b**: doprowadzić Pulpit, Modelowanie, Produkty + admin shell do stanu **pixel-perfect zgodnego z makietą** z fallback'iem `<MockBadge>` + tooltip dla nieoprogramowanych elementów.

**Źródło decyzji operatora**: „po zalogowaniu do panelu, pulpit, Produkty i Modelowanie ma wyglądać piksel perfect jak makieta — środki jakie zostaną do tego podjęte mnie nie interesują. Rozumiem, że jeśli coś jest nieoprogramowane to nie bedzie działać, ale wygladać ma jak na makiecie."

**Phase 1 (UI-03) NIE jest deprecated** — token migration + 9 dashboard mock blocks + base polish to fundament. UI-03b to **Phase 2 structural rebuild** na ufundowanej bazie.

## Decyzje projektowe

- **Granularność**: 1 META + 4 sub-tickety. Admin shell jako pojedynczy ticket (sidebar + topbar + MockBadge razem) bo są wizualnie sprzężone i blokują pozostałe.
- **„Pixel-perfect" = structural match**: ten sam grid, te same proporcje, ta sama hierarchia, te same komponenty/tokeny w tych samych miejscach. Nie literalny 0% pixel diff (różnice antialiasing/font hinting są naturalne). Acceptance manualne: side-by-side screenshot 1440×900 → operator zatwierdza.
- **MockBadge jako shared component** w `apps/admin/src/components/ui/mock-badge.tsx` — zbudowany w #1, używany w #2/#3/#4. Trzy warianty: `inline` (pill obok tekstu), `corner` (absolute na karcie), `overlay` (fullscreen na sheet/disabled section). Tooltip via Radix.
- **Brakujące endpointy backendowe** (`/api/object_types/{id}/audit-log`, `/api/attributes/{id}/values`, `/api/categories/{id}/move`, bulk operations, agent layer) — **NIE budujemy** w UI-03b. Tylko placeholder UI z MockBadge wskazującym że backend wymaga oprogramowania.

## Sequencing

```
META UI-03b
    │
    ├── #1 Admin Shell rebuild ──[BLOCKER]──┐
    │   (sidebar + topbar + MockBadge)      │
    │                                       ▼
    │                                 ┌──────────────┐
    │                                 │  równolegle: │
    │                                 ├── #2 Pulpit  │
    │                                 ├── #3 Modelo. │
    │                                 └── #4 Produk. │
```

| Ticket | Estymacja | Blokuje | Krytyczna ścieżka |
|--------|-----------|---------|-------------------|
| #1 Admin Shell | 8-12h | #2, #3, #4 | TAK |
| #2 Pulpit | 6-8h | — | NIE |
| #3 Modelowanie | 10-12h | — | TAK (najdłuższy z #2/#3/#4) |
| #4 Produkty | 8-10h | — | NIE |
| **Total** | **32-42h** | | |

**Calendar**: 1 dev solo → ~5-6 dni roboczych. 2 devs równolegle (po #1) → ~3 dni.

## GitHub setup (przed tworzeniem ticketów)

1. **Stworzyć label** `epik-UI-03b` (color `8b5cf6`, description: "Pixel-perfect harmonization Phase 2 — structural rebuild po UI-03 token migration")
2. Common labels per ticket: `epik-UI-03b`, `UI`, `frontend`, `must-have`. #1 dodatkowo: `blocker`.
3. Tworzymy w kolejności: META → #1 → #2 → #3 → #4. Linkujemy sub-tickety w META body przez `gh issue edit`.

---

## META Ticket UI-03b

**Title:** `[Epic UI-03b] Pixel-perfect harmonization: Pulpit + Modelowanie + Produkty + Admin Shell`
**Labels:** `epik-UI-03b`, `UI`, `frontend`, `must-have`

**Body:**
```markdown
## Context

Po Phase 1 epika UI-03 (PRs #359 token migration, #360 Modelowanie polish, #361 Produkty polish — wszystkie merged) struktura jest na miejscu, ale strony **nie są pixel-perfect zgodne z makietami** w `Project Plan/UI/Wdrozenie_grafiki/`. Inwentaryzacja read-only (2026-05-02) zidentyfikowała ~25 luk wizualnych w 4 obszarach: admin shell, Pulpit, Modelowanie (4 sub-taby), Produkty.

UI-03b to **Phase 2 structural rebuild** — nie deprecation Phase 1, tylko warstwa korekt strukturalnych na ufundowanej bazie tokenów (Inter/JetBrains, neutrals, accent palette violet/emerald/blue/amber/rose/sky/zinc, .soft-shadow, .glass-strong, --radius-*).

## Phase 1 → Phase 2

| Phase | Epic | PRs | Scope |
|-------|------|-----|-------|
| Phase 1 — Foundation | UI-03 | #359, #360, #361 | Token migration, 9 dashboard mock blocks, basic Modelowanie/Produkty polish |
| Phase 2 — Pixel-perfect | **UI-03b** | this epic | Sidebar/topbar rebuild, card-based Modelowanie, Completeness ring + agent suggestions, MOCK badges, layout corrections |

## Sub-tickety

- [ ] #N+1 Admin Shell rebuild — sidebar workspace section + topbar breadcrumb + agent search placeholder + audit indicator + shared `<MockBadge>` component **[BLOCKER]**
- [ ] #N+2 Pulpit pixel-perfect — range picker, MOCK badges, fix Dashboard `comingSoon` + index redirect
- [ ] #N+3 Modelowanie pixel-perfect — card-based UI, KPI tab badges, 2-column layouts (Categories, Attribute Groups)
- [ ] #N+4 Produkty pixel-perfect — bulk toolbar MOCK buttons, kbd hints, tab stubs viz, Completeness ring, agent suggestions card

## Acceptance criterion (epic-wide)

Operator otwiera każdą z 3 stron (Pulpit, Modelowanie 4 sub-taby, Produkty list + show) na 1440×900 obok każdej makiety z `Project Plan/UI/Wdrozenie_grafiki/`. Dla każdej pary screenshotów:

1. **Layout match**: ten sam grid, te same proporcje, ta sama hierarchia headerów
2. **Token match**: kolory/radii/shadows/typografia z #359 zastosowane konsekwentnie
3. **Content match**: te same KPI/sekcje/przyciski w tych samych miejscach
4. **MOCK clarity**: każdy nieoprogramowany element ma badge "MOCK · Wymaga oprogramowania" + tooltip wskazujący warstwę implementacji (BE endpoint / agent layer)

Operator ręcznie zatwierdza side-by-side pair (1 hero screenshot per ticket załączony do PR description).

## Out of scope (explicite)

- **Brakujące endpointy backendowe** — `/api/object_types/{id}/audit-log`, `/api/attributes/{id}/values`, `/api/categories/{id}/move`, bulk operations endpoints. Placeholder/MOCK UI tylko. Implementacja w osobnych ticketach BE.
- **Agent layer** — ⌘K agent search w topbarze + agent suggestions w detail Produktów to wyłącznie UI placeholder (input + skrót + tooltip). Backend agent system poza scope (Faza 2 produktu).
- **Dark mode tuning** — light theme polerujemy; dark mode pozostaje jak po #359.
- **Mobile responsive polish** — MVP-grade mobile (sheet sidebar) pozostaje.
- **Visual regression automation** — acceptance manual side-by-side; automated VRT (Percy/Chromatic) osobny ticket.

## References

- Makiety: `Project Plan/UI/Wdrozenie_grafiki/{plan-handoff-wdrozenie.md, dashboard-do-oprogramowania.md, modelowanie-do-oprogramowania.md, produkty-do-oprogramowania.md}`
- Phase 1 PRs: #359, #360, #361
- Tokens base: `apps/admin/src/index.css`
```

---

## Sub-ticket #1 — Admin Shell rebuild + MockBadge

**Title:** `feat(admin): admin shell rebuild — sidebar workspace + topbar breadcrumb + MockBadge component (UI-03b)`
**Labels:** `epik-UI-03b`, `UI`, `frontend`, `must-have`, `blocker`
**Estymacja:** 8-12h

**Body:**
```markdown
## Context

Admin shell po PR #359 ma już tokens (neutrals, soft-shadow, glass-strong) ale strukturalnie odbiega od makiety:
- Sidebar nie ma sekcji "Workspace" + przycisku "+ Dodaj własny moduł" + profilu użytkownika u dołu
- Topbar nie ma breadcrumba "Workspace / [Strona]", agent search bara (⌘K placeholder), audit log indicatora
- `App.tsx` index `/` redirectuje na `/products` zamiast `/dashboard` (regression z UI-02)
- `sidebar-nav.tsx` ma `comingSoon: true` przy Dashboard nav item (do usunięcia — route istnieje od #359)
- Brakuje shared `<MockBadge>` component używanego w #2/#3/#4

Ten ticket jest **BLOCKER** dla #2/#3/#4 — wszystkie zależą od `<MockBadge>` API.

## Scope

### A. Sidebar rebuild — `apps/admin/src/layout/sidebar-nav.tsx`

- [ ] Dodać sekcję "Workspace" z licznikiem aktywnych modułów (mock: "7 modułów aktywnych")
- [ ] Dodać przycisk "+ Dodaj własny moduł" z `<MockBadge variant="inline">` ("Wymaga oprogramowania")
- [ ] Przenieść `<UserMenu>` z topbara do dolnej sekcji sidebara (avatar + nazwa + email + chevron)
- [ ] Usunąć `comingSoon: true` z Dashboard nav item
- [ ] Aplikować `glass-strong` na sidebar background per makieta
- [ ] Active state: zmiana z `bg-secondary` na violet accent border-left + soft background

### B. Topbar rebuild — `apps/admin/src/layout/AppLayout.tsx` + new files

- [ ] Stworzyć `apps/admin/src/layout/topbar-breadcrumb.tsx` — czyta `useLocation()`, mapuje route → label (Workspace / Pulpit / Modelowanie / etc.) z i18n
- [ ] Stworzyć `apps/admin/src/layout/agent-search.tsx` — input z placeholder "Zapytaj agenta...", `<kbd>⌘K</kbd>` po prawej, `disabled={true}`, `<MockBadge variant="overlay">` tooltip "MOCK · Agent layer wymaga oprogramowania"
- [ ] Dodać audit log indicator (Lucide `History` icon button) z `<MockBadge variant="corner">` tooltip
- [ ] Usunąć `<UserMenu>` z topbara (przeniesione do sidebara)
- [ ] Layout topbara: `[breadcrumb] [spacer] [agent-search] [language-switcher] [notifications-bell] [audit-indicator]`

### C. MockBadge shared component — `apps/admin/src/components/ui/mock-badge.tsx` (new)

- [ ] Props: `{ variant?: 'inline' | 'corner' | 'overlay'; tooltip?: string; ticket?: string; children?: ReactNode }`
- [ ] `inline`: pill "MOCK" obok tekstu (np. w przycisku)
- [ ] `corner`: absolute top-right corner badge (np. na karcie KPI z hardcoded danymi)
- [ ] `overlay`: pełny overlay z "MOCK · Wymaga oprogramowania" + tooltip detail
- [ ] Tokens: `bg-amber-50 text-amber-900 border border-amber-200` (warning palette)
- [ ] Tooltip via Radix `<Tooltip>` (sprawdzić czy `@radix-ui/react-tooltip` jest w deps; jeśli nie → `pnpm --filter admin add @radix-ui/react-tooltip`)
- [ ] Storybook-style usage example w komentarzu na górze pliku

### D. Fix index redirect — `apps/admin/src/App.tsx`

- [ ] Linia 93: zmienić `<Navigate to="/products" replace />` na `<Navigate to="/dashboard" replace />`

### E. i18n keys — `apps/admin/src/locales/{pl,en}.json`

- [ ] `nav.workspace`, `nav.add_custom_module`, `topbar.search_agent_placeholder`, `topbar.audit_log`, `mock.requires_implementation`

## Quality gates

- [ ] `pnpm --filter admin lint` — clean
- [ ] `pnpm --filter admin typecheck` — clean
- [ ] `pnpm --filter admin build` — clean
- [ ] Smoke test (manual): logowanie → render shell → klik każdy nav item → klik MockBadge tooltip → klik agent search (disabled) → zmiana lang → notifications bell otwiera się
- [ ] Visual regression: Assets/Channels/ApiProfiles/Login bez regresji (tylko shell)

## Smoke test plan

1. `/` → redirect na `/dashboard` (nie `/products`)
2. Sidebar workspace section pokazuje "7 modułów aktywnych", "+ Dodaj moduł" ma MockBadge inline
3. UserMenu jest w sidebarze (dolne położenie), nie w topbarze
4. Topbar breadcrumb na `/dashboard` pokazuje "Workspace / Pulpit"
5. Agent search input disabled, tooltip "MOCK ..." na hover
6. Audit log indicator (History icon) w topbarze, MockBadge corner
7. Dark mode bez regresji

## Acceptance verification

Side-by-side screenshot 1440×900:
- **Lewa**: makieta (referenced HTML prototype z `Project Plan/UI/Wdrozenie_grafiki/`)
- **Prawa**: PIM screenshot `localhost:5173/dashboard`
- Match: layout/proporcje/tokens; operator ręcznie zatwierdza w PR description

## References

- Phase 1 tokens: PR #359 (`apps/admin/src/index.css`)
- Existing components: `apps/admin/src/layout/{AppLayout,sidebar-nav,user-menu,notifications-bell,language-switcher}.tsx`
- Makieta: `Project Plan/UI/Wdrozenie_grafiki/plan-handoff-wdrozenie.md`
```

**Pliki:**
- `apps/admin/src/components/ui/mock-badge.tsx` (new)
- `apps/admin/src/layout/topbar-breadcrumb.tsx` (new)
- `apps/admin/src/layout/agent-search.tsx` (new)
- `apps/admin/src/layout/AppLayout.tsx` (modify)
- `apps/admin/src/layout/sidebar-nav.tsx` (modify)
- `apps/admin/src/App.tsx` (modify line 93)
- `apps/admin/src/locales/{pl,en}.json` (modify)

---

## Sub-ticket #2 — Pulpit pixel-perfect

**Title:** `feat(admin): Pulpit pixel-perfect — range picker + MOCK badges + skeleton loaders (UI-03b)`
**Labels:** `epik-UI-03b`, `UI`, `frontend`, `must-have`
**Estymacja:** 6-8h
**Depends on:** #1 (MockBadge)

**Body:**
```markdown
## Context

`features/dashboard/page.tsx` po PR #359 ma 9 mock bloków (KpiCards, HeroAgentPanel, ActivityChart, TopEditedProducts, SyncsStatusPanel, CompletenessMetrics, AlertCenter, RecentAgentActivity, ChannelDistribution) — wszystkie z `mock-data.ts`. Brakuje vs makieta:
- Range picker (7d/30d/90d) na ActivityChart
- Konfigurowalne KPI cards (operator wybiera 4 z 6-8) — UI tylko, persystencja MOCK
- Hover tooltips na chartach (Recharts ma OOTB; weryfikować każdy chart)
- Skeleton loaders (mock data ładuje się synchronicznie — symulujemy `setTimeout` 300ms)
- MOCK badges na disabled actions (Eksportuj raport, Skonfiguruj alerty, Uruchom agenta)

## Scope

### A. ActivityChart range picker — `apps/admin/src/features/dashboard/components/ActivityChart.tsx`

- [ ] Dodać `<Tabs>` z opcjami "7d" / "30d" / "90d", default "30d"
- [ ] State lokalny → przełącza ile punktów z mock-data
- [ ] `mock-data.ts`: dorzucić `activityData_7d`, `activityData_30d`, `activityData_90d`

### B. Konfigurowalne KPI cards — `apps/admin/src/features/dashboard/components/KpiCards.tsx`

- [ ] Ikona "settings" (Lucide `SlidersHorizontal`) w prawym górnym rogu sekcji KPI
- [ ] Klik otwiera `<Sheet>` "Wybierz KPI" — checkbox lista 6-8 opcji, max 4 zaznaczone
- [ ] `<MockBadge variant="overlay">` na sheet — "Konfiguracja zapisywana lokalnie, MOCK"

### C. MOCK badges na disabled actions

- [ ] HeroAgentPanel: "Uruchom agenta" button → `<MockBadge variant="inline">`
- [ ] AlertCenter: "Skonfiguruj alerty" → `<MockBadge>`
- [ ] ChannelDistribution: "Eksportuj raport" → `<MockBadge>`
- [ ] RecentAgentActivity: "Filtruj" → `<MockBadge>`
- [ ] Każdy z 9 bloków: corner MockBadge sygnalizujący "Dane MOCK"

### D. Skeleton loaders — `apps/admin/src/features/dashboard/components/skeletons/`

- [ ] `KpiSkeleton.tsx`, `ChartSkeleton.tsx`, `ListSkeleton.tsx` — tailwind `animate-pulse` divs
- [ ] `page.tsx`: `useState` + `useEffect` z `setTimeout(300)` symuluje load

### E. Sanity checks

- [ ] Sidebar Dashboard nav item bez `comingSoon` (zrobione w #1, weryfikacja)
- [ ] `/` → `/dashboard` (zrobione w #1, weryfikacja)

## Quality gates

- [ ] lint + typecheck + build
- [ ] Smoke test poniżej

## Smoke test plan

1. Login → `/` przekierowuje na `/dashboard`
2. 9 bloków renderuje się; każdy ma corner MockBadge
3. ActivityChart range picker przełącza 7d/30d/90d
4. KPI cards "settings" sheet otwiera się, można odznaczyć 1 KPI
5. Hover na disabled buttons pokazuje MockBadge tooltip
6. Reload — skeleton loaders przez ~300ms

## Acceptance verification

Side-by-side screenshot 1440×900:
- **Lewa**: `Project Plan/UI/Wdrozenie_grafiki/dashboard-do-oprogramowania.md` (HTML prototype)
- **Prawa**: PIM `/dashboard`
- Match: 9 bloków w tym samym układzie, te same KPI w tych samych kafelkach, ten sam Hero panel u góry

## References

- Phase 1 dashboard skeleton: PR #359
- Mock data source: `apps/admin/src/features/dashboard/mock-data.ts`
- Makieta: `Project Plan/UI/Wdrozenie_grafiki/dashboard-do-oprogramowania.md`
```

**Pliki:**
- `apps/admin/src/features/dashboard/page.tsx` (modify)
- `apps/admin/src/features/dashboard/mock-data.ts` (modify)
- `apps/admin/src/features/dashboard/components/{ActivityChart,KpiCards,HeroAgentPanel,AlertCenter,ChannelDistribution,RecentAgentActivity}.tsx` (modify)
- `apps/admin/src/features/dashboard/components/skeletons/{KpiSkeleton,ChartSkeleton,ListSkeleton}.tsx` (new)

---

## Sub-ticket #3 — Modelowanie pixel-perfect

**Title:** `feat(admin): Modelowanie pixel-perfect — card-based UI + KPI tab badges + 2-col layouts (UI-03b)`
**Labels:** `epik-UI-03b`, `UI`, `frontend`, `must-have`
**Estymacja:** 10-12h
**Depends on:** #1 (MockBadge)

**Body:**
```markdown
## Context

`features/catalog/{object-types,attributes,attribute-groups,categories}/` po PR #360 mają 4 read-only listy w formie tabel z color-tokenowanymi badge'ami. Makieta wymaga:
- **Card-based UI** (nie tabele) — każda encja jako karta z metadata, ikoną, hover state, klikalna
- **KPI badges z licznikami w tab bar** (Object Types **7** · Attributes **27** · Attribute Groups **12** · Categories **14**)
- **Sekcje "Built-in (System)" + "Custom (Your Organization)"** widoczne na poziomie listy ObjectTypes (operator wkleił screen makiety potwierdzający to)
- **2-column layout dla Categories** (left: tree, right: sticky detail panel)
- **2-column layout dla Attribute Groups** (left: lista grup, right: sticky detail z attribute list)
- **Audit log indicator** (History icon) na detail per encja → MockBadge (endpoint nie istnieje)

3 endpointy są **placeholder/MOCK**: `/api/object_types/{id}/audit-log`, `/api/attributes/{id}/values`, `/api/categories/{id}/move` — w UI-03b pokazujemy disabled UI z MockBadge, NIE budujemy backendu.

## Scope

### A. ModelingLayout tab bar — `apps/admin/src/features/catalog/modeling/layout.tsx`

- [ ] Dodać `<Badge>` przy każdym tabie z licznikiem (`useList()` z Refine, `total` z metadata)
- [ ] Tokens: violet accent, `.num` font-feature

### B. Object Types card view — `apps/admin/src/features/catalog/object-types/list.tsx`

- [ ] Zamienić tabelę na sekcjowane kartowe wyświetlanie:
  - Sekcja "Built-in (System)" z ikoną kłódki + opisem
  - Sekcja "Custom (Your Organization)" z licznikiem custom + CTA "+ Nowy typ" prominentnym pod headerem
- [ ] Karta: ikona 3D/folder/image/tag (zależna od kind) → name + code → badges (system/custom + variants/hierarchical) → footer (licznik grup atrybutów + instancji + chevron)
- [ ] Reuse istniejących: `KindBadge`, `BuiltInLockBadge`, `CreateCustomObjectTypeDialog`

### C. Attributes card view — `apps/admin/src/features/catalog/attributes/list.tsx`

- [ ] Karty grupowane po `attribute_group` (collapsed sections z liczbą)
- [ ] Per-karta: type icon (Lucide per data_type), name, code, required/unique flags, MockBadge "Values" (endpoint MOCK)
- [ ] Filters bar pozostaje (type/origin/flags) — przeniesiony nad sekcje

### D. Attribute Groups 2-column — `apps/admin/src/features/catalog/attribute-groups/list.tsx`

- [ ] Left column (320px): lista grup jako karty, klikalna → `selectedId` state
- [ ] Right column (flex-1): sticky detail panel z attributes w grupie (`useOne()` na select)
- [ ] Empty state right: "Wybierz grupę aby zobaczyć szczegóły"
- [ ] Reuse `WhereUsedList` w detail panel

### E. Categories 2-column — `apps/admin/src/features/catalog/categories/list.tsx`

- [ ] Left column: tree view (już istnieje, przenieść do 2-col)
- [ ] Right column: sticky detail (parent path, children count, attributes effective)
- [ ] Action "Move" → MockBadge (endpoint MOCK)
- [ ] Reuse `EffectiveAttributesPreview`
- [ ] ObjectType filter chips (kind: product/category/asset) → MockBadge jeśli backend nie supportuje filtrowania per kind

### F. Audit log indicators (per detail page)

- [ ] `show.tsx` w object-types/attributes/attribute-groups/categories — History icon button → MockBadge "Audit log wymaga endpointu BE"

## Quality gates

- [ ] lint + typecheck + build
- [ ] Smoke test poniżej

## Smoke test plan

1. `/modeling` → redirect na `/modeling/object-types`
2. Tab bar: 4 zakładki z badge "Object Types 7" / "Attributes 27" / "Attribute Groups 12" / "Categories 14"
3. Object Types: sekcje built-in + custom, karty z ikonami i metadata, klik karty → `/modeling/object-types/:id`
4. Attributes: karty pogrupowane po grupie, MockBadge na "Values" column
5. Attribute Groups: 2-col, klik grupy po lewej → detail po prawej (sticky)
6. Categories: 2-col tree + detail, "Move" disabled z MockBadge
7. Audit log icon w show.tsx disabled z MockBadge

## Acceptance verification

Side-by-side screenshot per sub-tab (4 pary):
- **Lewa**: `Project Plan/UI/Wdrozenie_grafiki/modelowanie-do-oprogramowania.md` + screen makiety od operatora (Object Types z built-in/custom sections)
- **Prawa**: PIM `/modeling/{object-types,attributes,attribute-groups,categories}`

## Out of scope

- Backend endpointy `/audit-log`, `/values`, `/move` — placeholder UI tylko
- Drag-reorder, schema migration, bulk operations — osobny epik
- Edit forms (PATCH) — osobny epik

## References

- Phase 1 polish: PR #360
- Existing components do reuse: `apps/admin/src/components/modeling/{built-in-lock-badge,where-used-list,create-custom-object-type-dialog}.tsx`, `EffectiveAttributesPreview`
- Makieta: `Project Plan/UI/Wdrozenie_grafiki/modelowanie-do-oprogramowania.md` + screen Object Types od operatora (2026-05-02 conversation)
```

**Pliki:**
- `apps/admin/src/features/catalog/modeling/layout.tsx` (modify)
- `apps/admin/src/features/catalog/object-types/{list,show}.tsx` (modify)
- `apps/admin/src/features/catalog/attributes/{list,show,values}.tsx` (modify)
- `apps/admin/src/features/catalog/attribute-groups/{list,show}.tsx` (modify)
- `apps/admin/src/features/catalog/categories/{list,show}.tsx` (modify)

---

## Sub-ticket #4 — Produkty pixel-perfect

**Title:** `feat(admin): Produkty pixel-perfect — bulk MOCK toolbar + kbd hints + Completeness ring + agent suggestions (UI-03b)`
**Labels:** `epik-UI-03b`, `UI`, `frontend`, `must-have`
**Estymacja:** 8-10h
**Depends on:** #1 (MockBadge)

**Body:**
```markdown
## Context

`features/catalog/products/{list,show,create,edit,form}.tsx` po PR #361 mają Excel-like grid + saved views + 5 detail tabs. Brakuje vs makieta:
- Bulk toolbar: "Edytuj atrybut" / "Zmień kategorię" / "Eksport CSV" — wszystko MOCK
- `<kbd>` keyboard hints (⌘K, ⌘N, ⌘V) na buttonach
- MediaTab/RelationshipsTab/HistoryTab — empty state + visualization stub (obecnie raw empty)
- Completeness ring (zamiast linear bar) w detail header — animowany SVG circle
- Agent suggestions card w detail right sidebar — MOCK 3 sugestii ("Uzupełnij opis", "Dodaj zdjęcie 4:5") z MockBadge

## Scope

### A. Bulk toolbar MOCK buttons — `apps/admin/src/features/catalog/products/list.tsx`

- [ ] Dodać do `BulkActionsToolbar` (widoczny gdy `selectedRowKeys.length > 0`):
  - "Edytuj atrybut" + `<MockBadge variant="inline">` + `<kbd>E</kbd>`
  - "Zmień kategorię" + MockBadge + `<kbd>K</kbd>`
  - "Eksport CSV" + MockBadge + `<kbd>X</kbd>`
- [ ] Istniejący "Toggle enabled" zostaje bez MockBadge (jest wired)

### B. Keyboard hints `<kbd>` — `apps/admin/src/features/catalog/products/list.tsx`

- [ ] Search input: `<kbd>⌘K</kbd>` po prawej (focus search)
- [ ] "Nowy produkt" button: `<kbd>⌘N</kbd>`
- [ ] Saved views switcher: `<kbd>⌘V</kbd>`
- [ ] Tokens: `bg-muted text-muted-foreground rounded px-1.5 py-0.5 text-xs font-mono`

### C. Tab stubs visualization — `apps/admin/src/features/catalog/products/show.tsx`

- [ ] MediaTabStub: empty state z grid 3x3 placeholder kafelków + "Upload zdjęć" button + `<MockBadge variant="overlay">` overlay
- [ ] RelationshipsTabStub: 3 mock relations (cross-sell/up-sell/related) jako cards + MockBadge
- [ ] HistoryTabStub: timeline UI (Lucide `Clock`) z 3 mock events + MockBadge

### D. Completeness ring — `apps/admin/src/features/catalog/products/components/completeness-ring.tsx` (new)

- [ ] SVG circle z `stroke-dasharray` based on percent (z `product.completeness` lub mock 67%)
- [ ] Color: violet 0-50, amber 50-80, emerald 80-100
- [ ] Centered text: "67%" w `.num` font
- [ ] Animowany na mount (stroke-dashoffset 0 → target)
- [ ] Replace linear `CompletenessBadge` w detail header (`show.tsx`)

### E. Agent suggestions card — `apps/admin/src/features/catalog/products/components/agent-suggestions-card.tsx` (new)

- [ ] Card w right sidebar (next to Completeness ring)
- [ ] Header: "Sugestie agenta" + `<MockBadge variant="corner">`
- [ ] Lista 3 sugestii (mock): "Uzupełnij opis dla SEO", "Dodaj zdjęcie 4:5 dla Instagram", "Wybierz kategorię nadrzędną"
- [ ] Każda sugestia: ikona + text + "Zastosuj" button (disabled, MockBadge tooltip)
- [ ] Wpiąć w `DetailSidebar`

## Quality gates

- [ ] lint + typecheck + build
- [ ] Smoke test poniżej

## Smoke test plan

1. `/products` → grid renderuje, search ma `<kbd>⌘K</kbd>`
2. Zaznacz 2 wiersze → bulk toolbar pokazuje 4 buttony, 3 z MockBadge
3. Klik produkt → detail ma Completeness ring (SVG, animacja stroke-dashoffset on mount)
4. Right sidebar: Completeness ring + Agent suggestions card z 3 mock items
5. Tab Media → grid 3x3 placeholders + MockBadge overlay
6. Tab Relationships → 3 mock cards + MockBadge
7. Tab History → timeline z 3 events + MockBadge

## Acceptance verification

Side-by-side screenshot:
- List view: makieta produkty list vs PIM `/products`
- Detail view: makieta produkt detail vs PIM `/products/:id`

## Out of scope

- Backend bulk operations (edit_attribute, change_category, export_csv) — placeholder UI
- Real agent suggestions backend — placeholder UI
- Real media upload, relationships CRUD, full history wiring — osobne tickety
- Variants tree polish (już zrobiony w UI-02)

## References

- Phase 1 polish: PR #361
- Existing reuse: `CreateProductWizard`, `DetailDynamicForm`, `ExcelLikeGrid`, `BulkActionsToolbar`, `CompletenessBadge` (zostaje jako alternative dla list)
- Makieta: `Project Plan/UI/Wdrozenie_grafiki/produkty-do-oprogramowania.md`
```

**Pliki:**
- `apps/admin/src/features/catalog/products/list.tsx` (modify)
- `apps/admin/src/features/catalog/products/show.tsx` (modify)
- `apps/admin/src/features/catalog/products/components/completeness-ring.tsx` (new)
- `apps/admin/src/features/catalog/products/components/agent-suggestions-card.tsx` (new)
- `apps/admin/src/features/catalog/products/components/{media-tab-stub,relationships-tab-stub,history-tab-stub}.tsx` (new)
- `apps/admin/src/components/catalog/{bulk-actions-toolbar,detail-sidebar}.tsx` (modify dla wpięcia nowych komponentów)

---

## Reuse (komponenty już istniejące — NIE budować od nowa)

Z inwentaryzacji exploration agentów:

**Layout / Shell:**
- `apps/admin/src/layout/AppLayout.tsx` — shell wrapper
- `apps/admin/src/layout/sidebar-nav.tsx` — nav config
- `apps/admin/src/layout/user-menu.tsx` — UserMenu (przenosimy do sidebara)
- `apps/admin/src/layout/notifications-bell.tsx` — Mercure SSE
- `apps/admin/src/layout/language-switcher.tsx` — i18next

**Modelowanie:**
- `apps/admin/src/components/modeling/built-in-lock-badge.tsx`
- `apps/admin/src/components/modeling/where-used-list.tsx`
- `apps/admin/src/components/modeling/create-custom-object-type-dialog.tsx`
- `EffectiveAttributesPreview` (UI-08.14 #269)

**Produkty:**
- `apps/admin/src/components/catalog/{create-product-wizard,detail-dynamic-form,detail-sidebar,bulk-actions-toolbar,excel-like-grid,completeness-badge,variants-tab,product-filter-chips,advanced-filter-builder,empty-state-products,product-row-actions,save-view-modal,saved-views-dropdown,duplicate-product-dialog,channel-inline-icons,detail-group-nav,sync-aggregate-icon}.tsx`

**Dashboard:**
- 9 mock blocks już istnieje, wszystkie do polish (nie rebuild)

**Tokens (z PR #359, w `apps/admin/src/index.css`):**
- Neutrals (--bg, --surface, --surface-2, --ink, --ink-2, --line)
- Accent palette (violet/emerald/blue/amber/rose/sky/zinc)
- Utilities (.soft-shadow, .soft-shadow-lg, .glass-strong, .num, .focus-ring)
- Radii (--radius-lg/xl/2xl/3xl)

## Verification (epic-wide)

Po merge wszystkich 4 sub-ticketów:

1. `pnpm stack:up`, login `admin@demo.localhost / changeme`
2. Otworzyć każdą z 3 stron na 1440×900:
   - `/dashboard`
   - `/modeling/object-types`, `/modeling/attributes`, `/modeling/attribute-groups`, `/modeling/categories`
   - `/products` (list)
   - `/products/:id` (show — wybrany pierwszy produkt)
3. Side-by-side z odpowiednią makietą z `Project Plan/UI/Wdrozenie_grafiki/`
4. Sprawdzić każdy MockBadge: hover → tooltip wskazuje warstwę implementacji
5. Console DevTools clean (brak czerwonych errorów)
6. Network: brak 4xx/5xx (placeholder UI używa istniejących endpointów lub mock-data)
7. Operator zatwierdza side-by-side w opisie PR-ów

## Krytyczne pliki (single source of truth dla całego epika)

- `apps/admin/src/components/ui/mock-badge.tsx` (new — fundament, używany w #2/#3/#4)
- `apps/admin/src/layout/AppLayout.tsx` (shell rebuild)
- `apps/admin/src/layout/sidebar-nav.tsx` (workspace section)
- `apps/admin/src/features/catalog/products/show.tsx` (Completeness ring + agent suggestions — najbardziej złożona zmiana w #4)
- `apps/admin/src/features/catalog/modeling/layout.tsx` (KPI tab badges, dotyka 4 sub-tabs)
- `apps/admin/src/index.css` (referencja, NIE modyfikujemy — tokens już są z #359)

## Następne kroki po zatwierdzeniu planu

1. `gh label create epik-UI-03b --color 8b5cf6 --description "Pixel-perfect harmonization Phase 2 — structural rebuild po UI-03 token migration"`
2. `gh issue create` dla META UI-03b → zachować numer
3. `gh issue create` dla #1 (Admin Shell), zlinkować z META przez "Refs #META"
4. `gh issue create` dla #2 (Pulpit), #3 (Modelowanie), #4 (Produkty) — równolegle, każdy `Refs #META`, `Depends on #1`
5. `gh issue edit META --body` z final body zawierającym numery #1/#2/#3/#4
6. Implementacja w kolejności #1 → (#2 || #3 || #4)
