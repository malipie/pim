# VIEW-08 — Settings · Menu drag-drop + ObjectType.exposeToMainMenu + dynamic sidebar

> Epik: UI-04 · Status: open · Estymacja: 16–24h · Priorytet: must-have
> Issue GitHub: #427 · Supersedes: #414

## 1. Kontekst i cel widoku

Operator chce PIMCORE-like flexibility: **sam decyduje co siedzi w menu głównym** (lewy sidebar), w jakiej kolejności, i czy custom ObjectType (Faza 2 / `kind='custom'`) ma być widoczne. Aktualny stan:

- `apps/admin/src/layout/sidebar-nav.tsx:39-63` ma listę pozycji **hardcoded** w `NAV_SECTIONS`. Custom ObjectType (np. „Subskrypcja", „Usługa") nie pojawia się dynamicznie.
- `apps/admin/src/features/settings/menu/index.tsx` to `<ComingSoonPlaceholder />`.
- `ObjectType` nie ma flagi „udostępnij do menu". Brak per-tenant konfiguracji menu.

**Wybrany wariant: B-1** (z trójki ticketów wariantu B z planu rozpisz-b). Pozostałe (B-2 generyczna `<ObjectListingPage />` na `/objects/{slug}`, B-3 edytor `listing_config`) są out-of-scope — projektowane po merge B-1 na podstawie feedbacku operatora.

## 2. Scope

### 2.1 Backend

**Nowe pole w `object_types`:**
- `expose_to_main_menu BOOLEAN NOT NULL DEFAULT FALSE`
- Index częściowy `idx_object_types_expose_menu (tenant_id) WHERE expose_to_main_menu = TRUE`
- Backfill: built-in Product → `TRUE` (default sidebar zachowuje "Produkty")

**Nowa encja `MenuConfiguration` (singleton per tenant):**
- `id UUID`, `tenant_id UUID NOT NULL UNIQUE`, `items JSONB NOT NULL DEFAULT '[]'`, timestamps
- `MenuItemRecord` value object: `{kind: 'system'|'object_type', ref: string, position: int, visible: bool}`
- `SystemMenuItemRegistry` w kodzie — stała tablica z route, ikoną, labelKey, comingSoon, protected dla 7 system itemów (Pulpit, Katalogi PDF, Multimedia, Workflow, Integracje, Ustawienia, Modelowanie)

**Endpointy:**

| Method | Route | Body | Response |
|---|---|---|---|
| GET | `/api/menu_configuration` | — | `200` `{id, items, updatedAt}` (auto-seed jeśli brak) |
| PUT | `/api/menu_configuration` | `{items}` | `200` (atomic replace) |
| GET | `/api/menu_configuration/effective` | — | `200` `{visible: [...], available: [...]}` resolved per locale |
| PATCH | `/api/object_types/{id}` (rozszerzenie) | `{exposeToMainMenu?: bool, ...}` | unchanged |

**Walidacje `PUT /api/menu_configuration` (422 inaczej):**
- `position` unikalny w obrębie configu
- `kind='object_type'` → ref UUID istnieje + ObjectType.exposeToMainMenu=true
- `kind='object_type'` ref pointing at Asset → 422 (`/assets` ma własny widok DAM)
- `system:settings`, `system:modeling` mają protected=true → visible=false → 422
- (kind, ref) unikalne w obrębie configu

**Pliki BE — nowe:**
- `apps/api/migrations/Version20260504130000.php`
- `apps/api/src/Identity/Domain/Entity/MenuConfiguration.php`
- `apps/api/src/Identity/Domain/Value/MenuItemRecord.php`
- `apps/api/src/Identity/Domain/SystemMenuItemRegistry.php`
- `apps/api/src/Identity/Domain/Repository/MenuConfigurationRepositoryInterface.php`
- `apps/api/src/Identity/Application/MenuConfigurationService.php`
- `apps/api/src/Identity/Application/DefaultMenuSeeder.php`
- `apps/api/src/Identity/Infrastructure/Doctrine/Orm/Mapping/MenuConfiguration.orm.xml`
- `apps/api/src/Identity/Infrastructure/Doctrine/Repository/DoctrineMenuConfigurationRepository.php`
- `apps/api/src/Identity/Presentation/Controller/MenuConfigurationController.php` (3 routes priority 200)
- `apps/api/tests/Unit/Identity/MenuItemRecordTest.php` (8 cases)
- `apps/api/tests/Unit/Identity/SystemMenuItemRegistryTest.php` (5 cases)
- `apps/api/tests/Api/Identity/MenuConfigurationApiTest.php` (9 cases)

**Pliki BE — modyfikacja:**
- `apps/api/src/Catalog/Domain/Entity/ObjectType.php` — `bool $exposeToMainMenu` + accessors
- `apps/api/src/Catalog/Infrastructure/Doctrine/Orm/Mapping/ObjectType.orm.xml` — `<field name="exposeToMainMenu">`
- `apps/api/src/Catalog/Infrastructure/Serializer/ObjectType.xml` — `<attribute name="exposeToMainMenu">`
- `apps/api/src/Catalog/Application/ObjectTypeService.php` — `update(...)` parametr + Asset-locking
- `apps/api/src/Catalog/Presentation/Controller/UpdateObjectTypeController.php` — body parsing + response field
- `apps/api/src/Catalog/Application/BuiltInObjectTypeSeeder.php` — built-in Product `exposeToMainMenu=true`
- `apps/api/src/DataFixtures/AppFixtures.php` — wywołać `DefaultMenuSeeder::seed($tenant)` per tenant
- `apps/api/phpstan.dist.neon` — dopisać `MenuConfiguration.php` do baseline `doctrine.associationType`

### 2.2 Frontend

**Hook nowy:**
- `apps/admin/src/lib/use-effective-menu.ts` — `useEffectiveMenu()`, `useMenuConfiguration()`, `useReplaceMenuConfiguration()` (TanStack via Refine), staleTime 30s, invalidate po PUT

**Strona settings/menu (full rebuild):**
- `apps/admin/src/features/settings/menu/index.tsx` — DndContext + SortableContext + 2 sekcje (Widoczne / Dostępne) + Hide button (visible→false) + Add button (push do Widoczne) + auto-save po drag-end / show / hide
- Pattern: `apps/admin/src/features/catalog/attribute-groups/show.tsx:280-320` (dnd-kit + arrayMove + optimistic update + replace mutation)

**Sidebar refactor:**
- `apps/admin/src/layout/sidebar-nav.tsx` — usunięcie hardcoded `NAV_SECTIONS`, użycie `useEffectiveMenu()`. Loading skeleton + error fallback do hardcoded list. ICON_MAP dla string→LucideIcon. Disabled coming-soon items (Workflow) z badge „Wkrótce".

**ObjectType detail toggle:**
- `apps/admin/src/features/catalog/object-types/show.tsx` — w Settings card, po `allowedParentTypeIds`, dodać `<SettingToggleRow>` „Udostępnij do głównego menu" + opis + link do `/settings/menu` (warunkowo gdy `exposeToMainMenu=true`). Disabled gdy `kind === 'asset'` z dedykowanym opisem.

**i18n PL/EN keys w `apps/admin/src/locales/{pl,en}.json`:**
- `settings.menu.title`, `settings.menu.intro`, `settings.menu.section_visible`, `settings.menu.section_available`, `settings.menu.empty_available_prefix`, `settings.menu.empty_available_link`, `settings.menu.action_hide`, `settings.menu.action_add`, `settings.menu.protected_tooltip`, `settings.menu.toast_error`, `settings.menu.error_loading`

**E2E spec:** `apps/admin/e2e/settings-menu.spec.ts` (1 test, 6 etapów: sidebar default → Brand expose toggle → Settings/Menu Available → Visible 8 rows → protected Lock badge → Asset toggle locked).

### 2.3 Decyzje confirmed (operator 2026-05-04)
1. **Asset toggle = disabled** z tooltipem „Asset używa dedykowanego widoku /assets — zarządzaj kolejnością przez system item Multimedia". UI: `<SettingToggleRow disabled>`. Backend: walidacja w `ObjectTypeService::update` rzuca `LogicException` → 422.
2. **Built-in Product = edytowalny** — `protected=false` dla item kind=`object_type`. Operator może go ukryć/przesunąć. Tylko `system:settings` i `system:modeling` są `protected=true`.
3. **Sidebar = jedna lista, bez grupowania**. `MenuItemRecord` nie ma pola `group`. Sidebar renderuje wszystko w jednej `<ul>` w kolejności z `effective.visible[]`. Stara sekcja `id: 'modeling'` (separator border-t) znika — `system:modeling` jest po prostu kolejną pozycją (default seed: pozycja 7, ostatnia).

## 3. Definition of Done

- `pnpm typecheck` (admin) green
- `pnpm lint` (admin) green
- `composer phpstan` (api) max level green
- PHPUnit + ApiTestCase: 14 unit tests + 9 ApiTestCase tests (wszystkie green)
- Playwright `settings-menu.spec.ts` green
- `pnpm build` (admin) ok
- Manual smoke per checklist (NIENEGOCJOWALNE — CLAUDE.md SMOKE TEST RULE)

## 4. Smoke test plan

1. `pnpm stack:up` → FrankenPHP ready, login admin@demo.localhost / changeme
2. Sidebar: 8 pozycji w kolejności **Pulpit, Produkty, Katalogi PDF, Multimedia, Workflow (Wkrótce), Integracje, Ustawienia, Modelowanie** (bez Usług)
3. DevTools Network: `GET /api/menu_configuration/effective` → 200, response shape jak w plan
4. /modeling/object-types → wybierz „Marki" (built-in Brand) → Settings → toggle „Udostępnij do głównego menu" ON → DevTools: `PATCH /api/object_types/{id}` → 200, response zawiera exposeToMainMenu=true
5. /settings/menu → sekcja „Dostępne (ukryte)" pokazuje „Marki"
6. Klik „+ Dodaj" przy „Marki" → przeskakuje do „Widoczne" na końcu. PUT 200
7. Drag-drop „Marki" przed „Pulpit" → kolejny PUT 200
8. Hard refresh → kolejność zachowana, sidebar reflects
9. Klik [👁] przy „Katalogi PDF" → przeskakuje do „Dostępne" → sidebar usuwa pozycję
10. Próba ukrycia „Ustawienia" → button nieaktywny (Lock icon zamiast EyeOff), tooltip „Ta pozycja jest wymagana"
11. /modeling/object-types → wybierz „Asset" → toggle „Udostępnij do głównego menu" disabled, opis wyjaśnia
12. DevTools Console: brak czerwonych errorów

## 5. Out of scope (kolejne tickety wariantu B)
- B-2: Generyczna `<ObjectListingPage />` na `/objects/{slug}` driven-by-metadata.
- B-3: Edytor `listing_config` (kolumny, search, filters) w detail view ObjectType.
