# VIEW-IMP-02 — Profile mapowań: pełnoekranowy widok z grid/list toggle + duplicate/export/import

Epik: **UI-11 — Importy redesign**. Status: in progress (start: 2026-05-12).

## 1. Kontekst i cel widoku

Zastępuje obecny `ImportProfilesPlaceholder` (CTA do `ImportProfileManager` Sheet) pełnym widokiem strony wg `importy-profiles.jsx`. Operator ma:
- toggle grid/list (3-col grid kart / lista wierszy 9-col),
- search po name + code,
- akcje per profil: edit / duplicate / export JSON / delete,
- `NewProfileCard` jako CTA tile w grid,
- ekspozycja success rate (computed FE-side z `ImportSession` repository — jeśli zaszacujmy później).

## 2. Mockup / źródło designu

- Plik JSX: [Zrodla/Front_Claude_Design/PIM-nowoczesny/integracje/importy-profiles.jsx](../../Zrodla/Front_Claude_Design/PIM-nowoczesny/integracje/importy-profiles.jsx).
- Komponenty: `ImportProfilesView` (grid/list toggle), `ProfileCard`, `ProfileRow`, `NewProfileCard`.

## 3. Zakres FE

### 3.1 Routing
- `/integrations/imports/profiles` → `<ImportProfilesView>` (zastępuje placeholder w App.tsx).

### 3.2 Komponenty
| Komponent | Plik | Props |
|---|---|---|
| `ImportProfilesView` | `profiles/ImportProfilesView.tsx` | brak (state: q, view) |
| `ProfileCard` | `profiles/ProfileCard.tsx` | `profile`, `onEdit`, `onDuplicate`, `onDelete` |
| `ProfileRow` | `profiles/ProfileRow.tsx` | `profile`, `onEdit`, `onDuplicate`, `onDelete` |
| `NewProfileCard` | `profiles/NewProfileCard.tsx` | `onClick` |
| `ProfileEditDialog` | `profiles/ProfileEditDialog.tsx` | `open`, `mode: 'create' \| 'edit'`, `profile?`, `onClose`, `onSubmit` |

### 3.3 i18n keys
`imports.profiles.{title, subtitle_eyebrow, description, new_profile, search_placeholder, view.grid, view.list, grid.*, list.*, card.*, dialog.*, actions.*}`

### 3.4 a11y
- Grid/list toggle jako `<fieldset>` z `aria-pressed` (V01 pattern).
- Dropdown akcji (more menu) shadcn `DropdownMenu` — Radix free a11y.
- Delete dialog z `AlertDialog`.

## 4. Zakres BE

### 4.1 Migracja
`Version20260512XXXXXX_add_import_profile_code_mode.php`:
- ADD `code` VARCHAR(64) NULL initially → backfill auto-slug z `name` → SET NOT NULL.
- ADD `mode` VARCHAR(16) NOT NULL DEFAULT 'UPDATE'.
- ADD UNIQUE INDEX `(tenant_id, user_id, code)`.

### 4.2 Entity
- `ImportProfile`: dodaj `code` (string) + `mode` (ImportMode enum) + gettery/settery + setCode/setMode.
- ORM XML: dodaj pola + unique constraint.

### 4.3 Enum
- `ImportMode` (nowy): ADD, UPDATE, UPSERT, MERGE, INCREMENT, DELETE.

### 4.4 Inputy AP4
- `ImportProfileInput` + `ImportProfilePatchInput`: dodaj `code` + `mode`.

### 4.5 Custom controllery
- `DuplicateImportProfileController` — POST `/api/import-profiles/{id}/duplicate` → tworzy nowy profil z `_copy` suffix.
- `ExportImportProfileController` — GET `/api/import-profiles/{id}/export.json` → Content-Disposition attachment.
- `ImportImportProfileController` — POST `/api/import-profiles/import` (multipart JSON) → tworzy profil z JSON.

### 4.6 Voter
Re-use istniejącego `ImportProfileVoter`.

## 5. Sub-tasks

**BE**: migration, entity update, orm.xml, enum, inputs, 3 controllers, voter, ApiTestCase, unit (ImportProfile entity test).

**FE**: ImportProfilesView, ProfileCard, ProfileRow, NewProfileCard, ProfileEditDialog, App.tsx routing update, i18n keys, Playwright spec (1 test/1 login).

**Quality gates**: PHPStan max, PHPUnit, ApiTestCase, Biome, TS, Vite, Playwright, axe-core, p95<300ms.

## 6-10. Skrócone

Wszystkie pozostałe sekcje (acceptance, smoke, edge cases, ADR) zgodne z planem epiku UI-11 (`~/.claude/plans/nifty-exploring-dolphin.md`).
