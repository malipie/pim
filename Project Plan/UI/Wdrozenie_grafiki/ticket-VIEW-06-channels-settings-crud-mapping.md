# VIEW-06 — Kanały · Pełen CRUD + mapping editor (`/settings/channels`)

## 1. Kontekst i cel widoku

Widok `/settings/channels` to konfiguracyjna sekcja Ustawień, gdzie operator definiuje kanały publikacji (Allegro, BaseLinker, sklep, B2B). Każdy kanał ma kod, multi-lang label, dozwolone locale, dozwolone waluty, FK do root kategorii oraz tabelę mapowań `(ObjectType × Attribute) → targetField` decydującą jak atrybuty produktu/kategorii/zasobu trafią do docelowego formatu integracji (np. Shopify metafield, BaseLinker pole własne).

**Kanały są fundamentem przyszłego API Configurator** (epik 0.10) — w kreatorze API operator wybiera kanał i system wie z jakiego scope brać per-kanałowe wartości atrybutów (`ObjectValue.channelId`) oraz jak mapować pola na wyjściu. Bez pełnego CRUD + mapping editora ten flow jest niemożliwy.

Po PR #416 (sidebar refactor) widok `/settings/channels` jest produkcyjnie pod właściwą lokalizacją, ale **read-only**: lista istnieje, detail z 5 tabami pokazuje overview/locales/currencies, a taby `mapping` i `preview` są placeholderami. Backend `Channel` ApiResource ma tylko Get/GetCollection — brak Post/Patch/Delete. Encja `ChannelObjectTypeMapping` istnieje w domenie i bazie (`channel_object_type_mappings` table z migracji `Version20260429064833`), ale nie jest wystawiona przez API Platform.

Cel VIEW-06: dorobić pełen CRUD kanału + mapping editor + pickery Locale/Currency, zgodnie z DoD CLAUDE.md (PHPStan max, Biome strict, ≥80% coverage, ApiTestCase, Playwright, p95 < 300ms, axe-core 0).

## 2. Mockup / źródło designu

- **Brak JSX prototypu / screenshota** dla tego widoku. Decyzja operatora (4-pytaniowy briefing planu): rozszerzyć obecny `list.tsx` + `show.tsx` zachowując aktualny layout shadcn, nowe komponenty (form, mapping editor) zaprojektowane samodzielnie z reuse istniejących wzorców z reszty admina.
- **Pixel-perfect binding: N/A** — nie ma single source of truth dla layoutu. Reference patterns:
  - Toolbar z przyciskiem akcji w prawym górnym rogu: [apps/admin/src/features/catalog/products/list.tsx](../../../apps/admin/src/features/catalog/products/list.tsx)
  - Form z multi-language inputs: [apps/admin/src/features/catalog/attributes/new.tsx](../../../apps/admin/src/features/catalog/attributes/new.tsx)
  - Akordeon z grouped rows: shadcn Accordion (Radix) — przykład w `apps/admin/src/components/catalog/`
  - Inline edit z debounce: nowy pattern, podobny do edit-in-grid z products/list ale per-row
- **Widoki niewchodzące w scope**:
  - `/settings/channels/:id` tab `preview` — zostaje placeholderem (potrzebuje API Configurator, Faza 1)
  - Per-channel value editor w produkcie (override `description` per kanał) — osobny view-first ticket
  - API Configurator integration (wybór kanału w api profile editor) — osobny ticket epik 0.10
  - Bulk import mapowań (CSV upload) — odroczone

## 3. Zakres frontend (FE)

### 3.1 Routing

- `/settings/channels` (list, ✅ istnieje) — rozszerzyć: przycisk „Nowy kanał" + akcje wiersza Edytuj/Usuń
- `/settings/channels/:id` (show, ✅ istnieje) — dokończyć tab `mapping`
- `/settings/channels/new` (create) — **nowy widok pełnoekranowy trasowany** (NIE popup, zgodnie z view-first regułą)
- `/settings/channels/:id/edit` (edit) — **nowy widok pełnoekranowy trasowany**
- Auth gate: `<AuthedRoute>` (już obejmuje `/settings/*` przez `<SettingsLayout>` w App.tsx)

### 3.2 Komponenty (lista płaska)

#### Reuse (bez zmian)
| Komponent | Plik | Cel |
|---|---|---|
| `Button`, `Input`, `Label`, `Card`, `Table`, `Dialog`, `DropdownMenu`, `Toast` | `components/ui/*.tsx` | shadcn primitives |
| `Tag` (chip) | `components/ui/...` lub helper inline | Locale/Currency chips |
| `useList`, `useOne`, `useCreate`, `useUpdate`, `useDelete` | `@refinedev/core` | Refine hooks dla data fetching |
| `useToast` | `components/ui/toast.tsx` | Notifications po success/error |
| `resolveLabel(label, lang)` | `lib/i18n.ts` lub helper inline (pattern z `show.tsx`) | Multi-lang label resolution |

#### Nowe komponenty
| Komponent | Plik (nowy) | LOC est. | Props |
|---|---|---|---|
| `ChannelForm` | `features/channel/channels/form.tsx` | ~280 | `{ mode: 'create' \| 'edit', defaultValues?, onSubmit, onCancel, isSubmitting }` |
| `ChannelCreatePage` | `features/channel/channels/create.tsx` | ~80 | Page wrapper — useCreate + redirect na detail po success |
| `ChannelEditPage` | `features/channel/channels/edit.tsx` | ~100 | Page wrapper — useOne + useUpdate + ChannelForm w mode='edit' |
| `ChannelMappingEditor` | `features/channel/channels/mapping-editor.tsx` | ~220 | `{ channelId: string }` — useList z resource `channel_object_type_mappings`, akordeon per ObjectType, inline edit z debounced PATCH |
| `LocalePicker` | `features/channel/channels/locale-picker.tsx` | ~100 | `{ value: string[], onChange: (codes: string[]) => void }` — multi-select chips |
| `CurrencyPicker` | `features/channel/channels/currency-picker.tsx` | ~100 | `{ value: string[], onChange: (codes: string[]) => void }` |
| `CategoryRootCombobox` | `features/channel/channels/category-root-combobox.tsx` | ~110 | `{ value: string \| null, onChange: (id: string \| null) => void }` |
| `ChannelDeleteConfirmDialog` | `features/channel/channels/delete-confirm-dialog.tsx` | ~80 | `{ channelId, channelLabel, open, onClose, onSuccess }` |
| `useDebouncedCallback` | `lib/use-debounced-callback.ts` | ~30 | Generic debounce hook (jeśli nie ma) |

### 3.3 State management

- **Refine resources** (do dodania w `App.tsx`):
  - `channel_object_type_mappings`: `list: '/channel_object_type_mappings'` (read-only — Patch przez useUpdate na konkretny `id`)
  - `locales`: `list: '/locales'`
  - `currencies`: `list: '/currencies'`
- **Channel resource** (już istnieje): rozszerzyć o `create: '/settings/channels/new'`, `edit: '/settings/channels/:id/edit'`
- **Local state per komponent**: react-hook-form (jeśli już używane w innych formach) lub useState z manual validation. Sprawdzić w `attributes/new.tsx` przed wyborem.
- **Mutacje + invalidacje cache**: po Patch mappingu Refine automatycznie invaliduje cache resource — w `useUpdate` callback dodać `onSuccess` toast.
- **Debounce**: 500ms na blur dla mapping `targetField` input, hook `useDebouncedCallback` (jeśli nie istnieje, dorobić w `lib/`).

### 3.4 Struktura sekcji widoku

#### `/settings/channels` (list — extend)
1. Header: tytuł „Kanały" (h1, klasy z `show.tsx` pattern) + opis sekcji + przycisk „Nowy kanał" w prawym górnym rogu
2. Tabela kanałów (zachować obecne kolumny: code, label, locales, currencies)
3. **Nowa kolumna `Akcje`**: per wiersz `DropdownMenu` z opcjami Edytuj (link) + Usuń (otwiera `ChannelDeleteConfirmDialog`)
4. Loading state (skeleton)
5. Empty state „Nie utworzono jeszcze kanałów" + CTA „Nowy kanał"

#### `/settings/channels/new` (create — new)
1. Breadcrumb: Workspace › Ustawienia › Kanały › Nowy kanał (auto z `topbar-breadcrumb.tsx` po dodaniu regex)
2. Header: tytuł „Nowy kanał"
3. `<ChannelForm mode="create" />` — body
4. Przyciski: Zapisz / Anuluj (link do `/settings/channels`)

#### `/settings/channels/:id/edit` (edit — new)
1. Breadcrumb: ... › Edytuj
2. Header: tytuł „Edytuj kanał: {label}"
3. `<ChannelForm mode="edit" defaultValues={channelData} />`
4. Przyciski: Zapisz zmiany / Anuluj

#### `/settings/channels/:id` (show — extend tab `mapping`)
1. Tab `mapping` (był placeholderem) — wyrenderuj `<ChannelMappingEditor channelId={id} />`
2. Editor renderuje: nagłówek „Mapowanie atrybutów" + opis + Akordeon
3. Akordeon group per ObjectType (Produkty, Kategorie, Zasoby — built-in + custom):
   - Header akordeonu: nazwa ObjectType + licznik atrybutów
   - Body: tabela atrybutów (kolumny: code, label, type chip, **input `targetField`**, status save indicator)
4. Inline edit input → blur → debounced PATCH → toast success / error
5. Empty state per ObjectType: „Brak atrybutów do zmapowania — najpierw przypisz atrybuty"

### 3.4a Mapping element-po-elemencie (ChannelForm)

| Pole | Komponent | Walidacja FE | Walidacja BE |
|---|---|---|---|
| `code` | `<Input>` z label „Kod" | Required, regex `^[a-z0-9_]+$`, max 64, lowercase auto-transform | Unique per tenant (422 przy duplikacie) |
| `label.pl` | `<Input>` z label „Etykieta (PL)" | Required, max 255 | Required |
| `label.en` | `<Input>` z label „Label (EN)" | Required, max 255 | Required |
| `locales` | `<LocalePicker>` | Required, ≥1 | Required, ≥1 |
| `currencies` | `<CurrencyPicker>` | Required, ≥1 | Required, ≥1 |
| `categoryTreeRootId` | `<CategoryRootCombobox>` | Optional | Optional (validator istnieje: `ChannelCategoryRootValidator`) |

### 3.5 i18n (lista kluczy)

#### PL (`apps/admin/src/locales/pl.json`)
```json
"channels": {
  "list": {
    "title": "Kanały",
    "subtitle": "Konfiguracja kanałów publikacji — locale, waluty, mapowania pól.",
    "create_button": "Nowy kanał",
    "empty": "Nie utworzono jeszcze kanałów.",
    "actions": {
      "edit": "Edytuj",
      "delete": "Usuń"
    }
  },
  "create": {
    "title": "Nowy kanał",
    "submit": "Utwórz kanał",
    "submitting": "Tworzenie...",
    "success": "Kanał utworzony.",
    "error": "Nie udało się utworzyć kanału."
  },
  "edit": {
    "title_prefix": "Edytuj kanał:",
    "submit": "Zapisz zmiany",
    "submitting": "Zapisywanie...",
    "success": "Zmiany zapisane.",
    "error": "Nie udało się zapisać zmian."
  },
  "delete": {
    "confirm_title": "Usunąć kanał?",
    "confirm_body": "Operacja nieodwracalna. Wszystkie mapowania pól zostaną usunięte. Wartości atrybutów per-kanałowe stracą scope.",
    "confirm_submit": "Usuń kanał",
    "success": "Kanał usunięty.",
    "error": "Nie udało się usunąć kanału."
  },
  "form": {
    "fields": {
      "code": "Kod",
      "code_help": "Lowercase, znaki: a-z, 0-9, _. Niezmienny po utworzeniu.",
      "label_pl": "Etykieta (PL)",
      "label_en": "Label (EN)",
      "locales": "Wersje językowe",
      "currencies": "Waluty",
      "category_root": "Korzeń kategorii"
    },
    "validation": {
      "required": "Pole wymagane.",
      "code_format": "Tylko małe litery, cyfry i podkreślenia.",
      "code_taken": "Kod jest już zajęty.",
      "locales_min": "Wybierz co najmniej jedną wersję językową.",
      "currencies_min": "Wybierz co najmniej jedną walutę."
    },
    "cancel": "Anuluj"
  },
  "mapping": {
    "title": "Mapowanie atrybutów",
    "subtitle": "Określ jak każdy atrybut mapuje się na pole w docelowym formacie integracji.",
    "loading": "Ładowanie mapowań...",
    "empty_object_type": "Brak atrybutów do zmapowania w tym typie. Przypisz atrybuty do typu w sekcji Modelowanie.",
    "target_field_label": "Docelowe pole",
    "target_field_placeholder": "np. metafield.custom.color",
    "save_success": "Zapisano",
    "save_error": "Nie udało się zapisać"
  }
}
```

#### EN (analogicznie do `en.json`):
- list/create/edit/delete/form/mapping z angielskimi tłumaczeniami
- Wszystkie te same klucze co PL

**Ban na literały w JSX** — zero hardkodowanych stringów w nowych komponentach.

### 3.6 a11y (axe-core 0 violations serious/critical)

- **Form**: każdy `<input>` z `<label htmlFor>`, `aria-describedby` dla help text, `aria-invalid` przy błędzie, `role="alert"` dla validation messages
- **DropdownMenu** (akcje wiersza): Radix automatycznie zapewnia keyboard nav (Enter/Space/Arrow)
- **Combobox** (CategoryRoot): `aria-controls`, `aria-expanded`, `aria-activedescendant`, `aria-autocomplete="list"`
- **Akordeon** (Mapping): Radix Accordion zapewnia keyboard (Space/Enter na header, Tab pomiędzy)
- **Mapping inline edit**: `aria-live="polite"` dla save status indicator, `aria-busy` podczas debounced request
- **Confirm Dialog**: focus trap (Radix Dialog default), Escape zamyka, focus return na trigger po close
- **Skip-link**: jeśli istnieje w AppLayout, Settings podstrony mają być dostępne (sprawdzić)

### 3.7 Locales / Currency picker UX

- Picker pokazuje listę wszystkich dostępnych locales (z `GET /locales`) + checkbox per code
- Wybrane locales renderowane jako chipy z X do usunięcia
- Search filter w pickerze (gdy >10 opcji)
- Empty state: „Wybierz wersje językowe..."

### 3.8 Empty / loading / error states

- **List page**: empty (brak kanałów + CTA Nowy), loading (skeleton 3 wierszy), error (toast)
- **Create/Edit form**: loading podczas submit (button disabled + „Zapisywanie..."), error per pole z 422 RFC 7807 + toast top-level
- **Mapping editor**: loading (skeleton akordeonu), empty per ObjectType, error per row (czerwone obramowanie + tooltip „Nie udało się zapisać")
- **Delete dialog**: loading podczas DELETE (button „Usuwanie..."), error toast

## 4. Zakres backend (BE)

### 4.1 Endpointy

| Method | Path | Request | Response | Permissions | Filtry |
|---|---|---|---|---|---|
| `GET` | `/locales` | — | Hydra collection of `{code, label}` | `ROLE_USER` | — |
| `GET` | `/currencies` | — | Hydra collection of `{code, symbol, label}` | `ROLE_USER` | — |
| `POST` | `/channels` | `ChannelInputDto` JSON | `Channel` JSON-LD 201 + `Location` header | `is_granted('CREATE', 'App\\Channel\\Domain\\Entity\\Channel')` | — |
| `PATCH` | `/channels/{id}` | `ChannelInputDto` (partial) | `Channel` JSON-LD 200 | `is_granted('UPDATE', object)` (instance + tenant) | — |
| `DELETE` | `/channels/{id}` | — | 204 | `is_granted('DELETE', object)` | — |
| `GET` | `/channel_object_type_mappings` | — | Hydra collection | `ROLE_USER` (tenant scoped via Doctrine filter) | `?channel={uuid}` (SearchFilter), pagination off (max 200/page) |
| `GET` | `/channel_object_type_mappings/{id}` | — | JSON-LD | `ROLE_USER` | — |
| `PATCH` | `/channel_object_type_mappings/{id}` | `{targetField: string}` | JSON-LD 200 | `is_granted('UPDATE', 'App\\Channel\\Domain\\Entity\\Channel')` (mapping = atrybut Channel) | — |

**Errors w RFC 7807** (API Platform native).
**Cursor pagination** — N/A, mappings zwracane jako pełna lista per channel (max ~5000 rows = 200 atrybutów × 25 ObjectType, mieści się w jednej stronie 200).

### 4.2 Encje / schema / migracje

- **`Channel`** (✅ istnieje) — bez zmian schematu
- **`ChannelObjectTypeMapping`** (✅ istnieje) — bez zmian schematu
- **`Locale`**, **`Currency`** (✅ istnieją) — bez zmian, dodajemy tylko ApiResource
- **Nowy DTO**: `ChannelInputDto` (Symfony Validator constraints):
  - `code`: `#[NotBlank, Regex('/^[a-z0-9_]+$/'), Length(max: 64)]`
  - `label`: `#[NotBlank, Type('array'), Count(min: 1)]` — keys = locale codes, values = strings
  - `locales`: `#[Type('array'), Count(min: 1)]` — array of locale codes
  - `currencies`: `#[Type('array'), Count(min: 1)]` — array of currency codes
  - `categoryTreeRootId`: `#[Optional, Uuid]`

**Migracja DH Auditor** — wygeneruje się automatycznie po dodaniu `ChannelObjectTypeMapping` do `audited_entities`. Sprawdzić `bin/console doctrine:migrations:diff` przed commitem.

### 4.3 Listenery / event subscribers

- **`ChannelMappingSeedListener`** — nowy. Trigger: domain event `ChannelCreated` (już emitowany w `Channel::assignTenant`). Akcja: dla każdego ObjectType w tenancie + każdego Attribute przypisanego do tego ObjectType, utwórz `ChannelObjectTypeMapping` z `targetField = ''`. **Worker memory-safe**: pętla z `EntityManager::clear()` po flush() per N=200 (per CLAUDE.md). Dla typowego seedu (3 ObjectType × 50 Attributes = 150 rows) jeden flush wystarcza.
- **Drugie zdarzenie**: `ObjectTypeAttributeAttached` (event z #413 backend) — nasłuchiwanie i dodanie row w `channel_object_type_mappings` dla każdego istniejącego kanału (idempotent: ON CONFLICT DO NOTHING przez uniq constraint). **Decyzja**: zostawić out-of-scope tego ticketu (nowe atrybuty po utworzeniu kanału nie pojawią się automatycznie w mappingu — operator musi „odświeżyć" przez UI lub ponowić przypisanie). Komentarz w listener TODO follow-up.

### 4.4 Permissions / RBAC

- **`ChannelVoter`** extends `AbstractRbacVoter`:
  - `attributeMap`: `[READ => 'read', CREATE => 'write', UPDATE => 'write', WRITE => 'write', DELETE => 'delete']`
  - `resource`: `'channel'`
  - Tenant scoping: voter sprawdza `subject.tenantId === user.tenantId` dla instance checks
- **RBAC fixtures** — dodać permissions do role `admin` w fixtures (sprawdzić `apps/api/src/DataFixtures/RoleFixtures.php`):
  - `channel:read` (✅ powinno już być, używane przez GET)
  - `channel:write` (nowy — dla POST/PATCH)
  - `channel:delete` (nowy)
- **Audit log entries**: każdy POST/PATCH/DELETE pisze entry przez DH Auditor (auto)

### 4.5 Provenance — N/A

ObjectValue.provenance dotyczy `object_values`. Channel + ChannelObjectTypeMapping mają audit log via DH Auditor (kto zmienił + kiedy), provenance jest dla wartości atrybutu produktu, nie dla konfiguracji systemu.

### 4.6 Worker / async

`ChannelMappingSeedListener` synchroniczny przy `ChannelCreated` event (per `recordThat()` mechanism w domain). Dla typowego seedu (do 1000 rows) sync OK. **Jeśli per-tenant skala >1000 atrybutów** — przepuścić przez Symfony Messenger async handler (out-of-scope tego ticketu, TODO komentarz w listenerze).

`Channel CRUD` sam — synchroniczny, single entity, brak batch.

### 4.7 Real-time (Mercure) — N/A

Channel CRUD nie wymaga real-time push. Mapping editor — możliwa optymalizacja w przyszłości (operator A widzi zmianę operatora B), ale nie w MVP.

## 5. Sub-tasks (checklist)

### Backend
- [ ] `apps/api/src/Channel/Application/Command/CreateChannel/CreateChannelCommand.php`
- [ ] `apps/api/src/Channel/Application/Command/CreateChannel/CreateChannelHandler.php`
- [ ] `apps/api/src/Channel/Application/Command/UpdateChannel/UpdateChannelCommand.php`
- [ ] `apps/api/src/Channel/Application/Command/UpdateChannel/UpdateChannelHandler.php`
- [ ] `apps/api/src/Channel/Application/Command/DeleteChannel/DeleteChannelCommand.php`
- [ ] `apps/api/src/Channel/Application/Command/DeleteChannel/DeleteChannelHandler.php`
- [ ] `apps/api/src/Channel/Application/Command/PatchChannelMapping/{Command,Handler}.php`
- [ ] `apps/api/src/Channel/Infrastructure/ApiPlatform/Resource/ChannelInputDto.php`
- [ ] `apps/api/src/Channel/Infrastructure/ApiPlatform/State/ChannelProcessor.php`
- [ ] `apps/api/src/Channel/Infrastructure/ApiPlatform/State/ChannelObjectTypeMappingProcessor.php`
- [ ] `apps/api/src/Channel/Infrastructure/ApiPlatform/Resource/ChannelObjectTypeMapping.xml`
- [ ] `apps/api/src/Channel/Infrastructure/ApiPlatform/Resource/Locale.xml`
- [ ] `apps/api/src/Channel/Infrastructure/ApiPlatform/Resource/Currency.xml`
- [ ] `apps/api/src/Channel/Infrastructure/EventSubscriber/ChannelMappingSeedListener.php`
- [ ] `apps/api/src/Identity/Infrastructure/Security/ChannelVoter.php`
- [ ] Update `apps/api/src/Channel/Infrastructure/ApiPlatform/Resource/Channel.xml` — dodać Post/Patch/Delete + security
- [ ] Update `apps/api/config/packages/dh_auditor.yaml` — dodać `ChannelObjectTypeMapping`
- [ ] Update RBAC fixtures (admin role permissions)
- [ ] Domain events: `ChannelUpdated`, `ChannelDeleted` (jeśli nie istnieją — dorobić w `Contracts/Event/`)
- [ ] Migration diff (DH Auditor table for ChannelObjectTypeMapping)
- [ ] PHPStan max → 0 errors
- [ ] PHP-CS-Fixer dry-run → 0 changes

### Frontend
- [ ] `apps/admin/src/lib/use-debounced-callback.ts` (jeśli nie istnieje)
- [ ] `apps/admin/src/features/channel/channels/form.tsx` (ChannelForm shared)
- [ ] `apps/admin/src/features/channel/channels/create.tsx` (ChannelCreatePage)
- [ ] `apps/admin/src/features/channel/channels/edit.tsx` (ChannelEditPage)
- [ ] `apps/admin/src/features/channel/channels/mapping-editor.tsx`
- [ ] `apps/admin/src/features/channel/channels/locale-picker.tsx`
- [ ] `apps/admin/src/features/channel/channels/currency-picker.tsx`
- [ ] `apps/admin/src/features/channel/channels/category-root-combobox.tsx`
- [ ] `apps/admin/src/features/channel/channels/delete-confirm-dialog.tsx`
- [ ] Update `apps/admin/src/features/channel/channels/list.tsx` — przycisk + akcje wiersza
- [ ] Update `apps/admin/src/features/channel/channels/show.tsx` — wyrenderuj ChannelMappingEditor w tab `mapping`
- [ ] Update `apps/admin/src/App.tsx` — nowe Refine resources (`channel_object_type_mappings`, `locales`, `currencies`) + nowe routes (`/settings/channels/new`, `/settings/channels/:id/edit`)
- [ ] Update `apps/admin/src/locales/pl.json` + `en.json` — nowe klucze
- [ ] Update `apps/admin/src/layout/topbar-breadcrumb.tsx` — regex dla `/settings/channels/new` i `/edit`
- [ ] TypeScript noEmit → 0 errors
- [ ] Biome strict → 0 errors
- [ ] Vite build smoke → success

### E2E + integration
- [ ] `apps/api/tests/Api/Channel/CreateChannelApiTest.php`
- [ ] `apps/api/tests/Api/Channel/UpdateChannelApiTest.php`
- [ ] `apps/api/tests/Api/Channel/DeleteChannelApiTest.php`
- [ ] `apps/api/tests/Api/Channel/ChannelMappingApiTest.php`
- [ ] `apps/api/tests/Api/Channel/LocaleApiTest.php`
- [ ] `apps/api/tests/Api/Channel/CurrencyApiTest.php`
- [ ] `apps/api/tests/Unit/Channel/Application/Command/CreateChannelHandlerTest.php`
- [ ] `apps/api/tests/Unit/Channel/Application/Command/UpdateChannelHandlerTest.php`
- [ ] `apps/api/tests/Unit/Channel/Application/Command/DeleteChannelHandlerTest.php`
- [ ] `apps/api/tests/Unit/Identity/Infrastructure/Security/ChannelVoterTest.php`
- [ ] `apps/admin/e2e/settings-channels-crud.spec.ts` (Playwright happy path + edge case)
- [ ] axe-core scan w E2E na 4 podstronach

### Testy non-functional
- [ ] EXPLAIN ANALYZE dla `GET /channels`, `GET /channel_object_type_mappings?channel={id}` — w PR description
- [ ] p95 < 300ms na seed 50k SKU (k6 jeśli dostępny)
- [ ] Lighthouse: performance ≥85, a11y =100, best-practices ≥90 na `/settings/channels`
- [ ] Bundle size delta < 50KB gzip
- [ ] axe-core 0 violations serious/critical
- [ ] composer audit + pnpm audit clean
- [ ] Multi-tenancy test: cross-tenant read = 0 wyników
- [ ] RBAC test: admin allowed, viewer denied

### Dokumentacja
- [ ] `docs/api-spec/v0.json` — regen po nowych routes
- [ ] `agent/current_status.md` — dopisz sekcję VIEW-06
- [ ] `agent/lessons.md` — jeśli nowe wzorce (debounced PATCH, mapping seed listener, locale/currency Resource)

### Manual smoke (operator po merge)
- [ ] Login → sidebar → Ustawienia → Kanały
- [ ] Lista pokazuje istniejące kanały + przycisk Nowy
- [ ] Klik Nowy → form → wypełnij → Zapisz → 201 → redirect
- [ ] Edit → zmień label PL → Zapisz → 200
- [ ] Tab Mapping → zmień targetField → blur → 200
- [ ] Delete → confirm → 204 → znika z listy
- [ ] DevTools Console: 0 czerwonych errorów

## 6. Acceptance criteria — funkcjonalne

- Lista `/settings/channels` pokazuje wszystkich kanałów tenanta + przycisk Nowy + akcje wiersza Edytuj/Usuń
- Klik Nowy → fullscreen-routed `/settings/channels/new` z formem
- Walidacja FE blokuje submit przy niepełnych danych, walidacja BE odrzuca z 422 RFC 7807
- Klik Edytuj → fullscreen-routed `/settings/channels/:id/edit` z pre-filled formem
- Klik Usuń → Dialog z confirmem → 204 → kanał znika z listy + cascade na mappings
- Tab Mapping w detail → akordeon per ObjectType → inline edit `targetField` → debounced 500ms PATCH → toast success
- Wszystkie 4 sub-pages: empty / loading / error states zaobserwowalne
- i18n PL/EN przełącza się — wszystkie nowe klucze obecne

## 7. Acceptance criteria — non-functional (TWARDE GATES, NIENEGOCJOWALNE)

- **Performance**: p95 < 300ms dla `GET /channels`, `GET /channel_object_type_mappings?channel={id}`, `POST /channels`, `PATCH /channels/{id}`, `PATCH /channel_object_type_mappings/{id}` na seed 50k SKU + 200 atrybutów
- **N+1**: EXPLAIN ANALYZE każdego nowego query w PR description, zero N+1
- **Indeksy**: `(channel_id, object_type_id, attribute_id)` ✅ istnieje (uniq constraint). `(channel_id)` single — istnieje przez ten uniq prefix
- **Pagination**: mappings zwracane bez paginacji (max ~5000 rows mieści się w 200 page size, jeśli większe — cursor)
- **Memory**: ChannelMappingSeedListener `EntityManager::clear()` per N=200
- **Bundle size FE**: Δ < 50KB gzip
- **Lighthouse**: performance ≥85, a11y =100, best-practices ≥90 na `/settings/channels`
- **PHPStan max**: 0 errors
- **Biome strict**: 0 errors
- **PHPUnit coverage**: ≥80% nowych klas (Handlery, Voter, ChannelInputDto, ChannelMappingSeedListener)
- **ApiTestCase**: 401 + 403 + 404 + walidacja + happy path dla każdego nowego endpointu (5 endpoint × 4 cases minimum)
- **Playwright E2E**: happy path + ≥1 edge case (np. 422 walidacja duplikatu code)
- **axe-core**: 0 violations serious/critical na 4 nowych podstronach
- **composer + pnpm audit**: 0 high/critical
- **Multi-tenancy**: cross-tenant read test = 0 wyników (tenant B nie widzi kanałów tenanta A)
- **RBAC**: admin allowed, viewer denied dla write/delete; cross-tenant denied dla wszystkich
- **Audit log**: write/update/delete pisze entry w `channels_audit` + `channel_object_type_mappings_audit`
- **Provenance**: N/A (Channel = system config, nie object_values)
- **i18n coverage**: wszystkie nowe klucze w `pl.json` i `en.json`
- **OpenAPI snapshot**: `docs/api-spec/v0.json` zaktualizowany

## 8. Smoke-test scenariusze (manualne, dla operatora)

1. **Login** `admin@demo.localhost / changeme` na `https://pim.localhost`
2. Sidebar → **Ustawienia → Kanały**: tabela z istniejącymi kanałami + przycisk „Nowy kanał" widoczny w prawym górnym rogu
3. Klik **„Nowy kanał"** → URL = `/settings/channels/new`, breadcrumb = „Ustawienia › Kanały › Nowy kanał"
4. Wypełnij form: code = `testowy`, label PL = `Test`, label EN = `Test`, wybierz 1 locale (np. `pl_PL`), wybierz 1 currency (`PLN`), opcjonalnie root kategorii → Klik **Zapisz** → DevTools Network: `POST /channels` → status `201` → URL = `/settings/channels/{newId}`
5. Sprawdź response: czy zwrócił `Channel` z assigned `tenantId` i pełnym labelem
6. Przejdź do tabu **Mapping** → akordeon pokazuje grupy per ObjectType. Rozwiń pierwszą grupę (np. Produkty) → tabela atrybutów z pustymi inputami `targetField`
7. Klik input `targetField` przy pierwszym atrybucie, wpisz `metafield.custom.test`, Tab/blur → DevTools Network: `PATCH /channel_object_type_mappings/{id}` → status `200` → toast „Zapisano"
8. Wróć do listy `/settings/channels` → klik kebab przy kanale `testowy` → **Edytuj** → zmień label PL na `Test edytowany` → Zapisz → status `200` → toast
9. Klik kebab → **Usuń** → Dialog confirm → klik „Usuń kanał" → status `204` → kanał znika z listy
10. **Multi-tenant test**: zaloguj jako user innego tenanta (jeśli seed istnieje), sprawdź że kanał z tenanta A nie jest widoczny
11. **RBAC test**: spróbuj `curl -X POST /api/channels` z tokenem viewera → status `403`
12. **DevTools Console**: 0 czerwonych errorów na każdym kroku
13. **Refresh**: po refresh każda podstrona zachowuje stan (lista, detail, edit form pre-filled z BE)

## 9. Edge cases / poza zakresem

### Edge cases pokryte
- Walidacja: duplikat code → 422 z polem code w detail
- Walidacja: brak locales → 422 z polem locales
- Walidacja: invalid categoryTreeRootId (nie kategoria) → 422 (ChannelCategoryRootValidator)
- DELETE z active mappings → cascade delete (FK ON DELETE CASCADE w ORM mapping)
- Refresh przy niezapisanym formie → ostrzeżenie browser (Refine `warnWhenUnsavedChanges`)
- Mapping inline edit przy przerwanym połączeniu → retry po błędzie + UI shows error state

### Świadomie poza zakresem (deferred)
- **`isPublished` toggle** w mapping editor — kolumna istnieje w DB, UI tylko `targetField` w MVP. Follow-up VIEW-XX.
- **Auto-seed mappingu przy `ObjectTypeAttributeAttached`** — operator musi ręcznie odświeżyć / ponowić przypisanie. Follow-up gdy będzie konkretny pain point.
- **Bulk import mapowań CSV** — odroczone, inline edit per row wystarczy w MVP.
- **Per-channel value editor w produkcie** (override `description` per Channel) — osobny view-first ticket VIEW-XX, wymaga zmian w `ProductEditPage`.
- **API Configurator integration** (wybór kanału w `ApiProfile`) — osobny ticket epik 0.10.
- **Tab Preview w Channel show page** — placeholder, wymaga API Configurator preview, Faza 1.
- **Mercure real-time** dla mapping editor — gdy >1 operator edytuje mappings naraz; out-of-scope MVP.

## 10. Powiązane ADR / dokumenty

### ADR
- **N/A** — VIEW-06 nie wymaga nowego ADR. Wszystko zgodne z istniejącym ADR-009 (ObjectType generic), wzorce CQRS+API Platform z innych BC, multi-tenancy via TenantFilter.

### Aktualizacje
- `Project Plan/01-architektura-pim.md` — bez zmian (Channel context już opisany)
- `Project Plan/02-plan-projektu-pim.md` — checkbox dla VIEW-06 jeśli był na backlog (sprawdzić)
- `agent/current_status.md` — dopisz sekcję `## 2026-05-04: VIEW-06 view-first marathon — Kanały CRUD + mapping editor` z aktualnym stanem (sub-faza, ostatnie 3 akcje, następny krok)
- `agent/lessons.md` — po implementacji jeśli odkryjesz: pattern debounced PATCH w Refine, ChannelMappingSeedListener pattern, Locale/Currency global Resource (non-TenantScoped), itd.
- `docs/api-spec/v0.json` — regen po dodaniu 5 nowych operations + 3 nowych Resources

### Powiązane PR-y / ticketów
- **PR #416** (zmergowany) — sidebar refactor, settings layout, placeholdery → VIEW-06 buduje na tej infrastrukturze
- **#372 / #413** (zmergowane) — wizard step 2 i VIEW-01b detail, mają związek bo `ObjectTypeAttributeAttached` event może w przyszłości triggerować mapping seed (out-of-scope tego ticketu)
- **Epik 0.10** (przyszły) — API Configurator z wyborem kanału, konsumer VIEW-06
