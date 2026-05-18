# RBAC — tickety Phase 5 (Settings UI)

**Typ dokumentu:** Backlog ticketów Phase 5 RBAC — ready-to-paste GitHub Issues
**Status:** Draft — gotowe do utworzenia po zakończeniu Phase 4
**Powiązane:** [`07-rbac-implementation-plan.md`](07-rbac-implementation-plan.md) §4.6, [`PRD/PRD-PIM-rbac.md`](PRD/PRD-PIM-rbac.md) §5.3 mockups

> **Cel Phase 5:** kompletny Settings UI dla user/role/token management + Super Admin operator panel. Klient w Settings tworzy własne role, invituje users, zarządza API tokens, konfiguruje SSO. Super Admin na `admin.cortex.pl` zarządza tenantami + cross-tenant audit + break-glass recovery.
>
> **Harmonogram:** tygodnie 11-13, **~90-120h**. 22 tickety.

---

## Graf zależności Phase 5

```
RBAC-P5-001..004 (Users management) ── parallel-friendly
RBAC-P5-005..008 (Roles management) ── after Users
RBAC-P5-009..011 (API tokens UI)
RBAC-P5-012..013 (Profile: password, MFA)
RBAC-P5-014..016 (SSO + Tenant + Billing config)
RBAC-P5-017..018 (Accept invitation + 403 page)
RBAC-P5-019..022 (Super Admin operator panel — admin.cortex.pl)
```

---

## RBAC-P5-001: feat(admin): Settings → Users list + filters + search

**Typ:** `feat` | **Phase:** 5 | **Estymacja:** **4-6h**

**Dependencies:** Blocked by Phase 4 complete.

**Risk flags:** Lista users zawiera tylko users z current tenant (TenantFilter automatic). Cross-tenant leakage = critical bug.

**Cel:** `/settings/users` page z table users — name, email, role(s), status (Active/Invited/Deactivated), last login. Filter by role, status. Search by email/name.

**Scope:**
- `<UsersListPage>` z table (Refine `useList`)
- Columns: Avatar | Name | Email | Role(s) | Status (badge color) | Last Login | Actions (3-dot menu)
- Filters: dropdown Role (multi-select), Status (Active/Invited/Deactivated/Pending)
- Search: debounced 300ms, searches name + email
- 3-dot menu actions: View profile, Edit, Resend invitation (jeśli Invited), Deactivate/Reactivate, Reset MFA
- Empty state: *„Zaproś pierwszego użytkownika"* CTA
- Pagination — 50 users/page

**Acceptance criteria:**
- [ ] AC-1: Render table z columns matching spec
- [ ] AC-2: Filter by role works (multi-select)
- [ ] AC-3: Search debounced
- [ ] AC-4: Status badges color-coded (Active=green, Invited=yellow, Deactivated=red)
- [ ] AC-5: 3-dot menu actions conditional based on user status (Reactivate visible tylko gdy deactivated)
- [ ] AC-6: Cross-tenant test: tenant A user list NIE zawiera tenant B users

**Files affected:** `apps/admin/src/routes/settings/users/index.tsx`, `apps/admin/src/components/UsersList.tsx`

**DoD:** Standard + AC + Playwright list view test.

---

## RBAC-P5-002: feat(admin): invite user modal + magic link send

**Typ:** `feat` | **Phase:** 5 | **Estymacja:** **5-7h**

**Dependencies:** Blocked by RBAC-P5-001.

**Risk flags:** Email validation (regex + DNS MX check optional). Role assignment validation — Owner role unique check.

**Cel:** Modal *„Invite user"* z form: name, email, role(s) multi-select, locale_scope, channel_scope. Submit → POST `/api/invitations` + magic link email sent.

**Scope:**
- `<InviteUserModal>` triggered by *„+ Invite user"* button w Users list
- Form fields:
  - Email (required, validation regex)
  - Name (required)
  - Role(s) multi-select (Combobox z search) — options z `/api/roles` (custom + system templates)
  - Locale scope (multi-select, default `["*"]`) — based on tenant.locales
  - Channel scope (multi-select, default `["*"]`) — based on tenant.channels
- Validation:
  - Email unique w tenant (backend check via 422)
  - Owner role unique enforcement (frontend warning + backend 409)
- Submit → POST `/api/invitations` → success toast + close modal + refetch users list
- Resend invitation flow — 3-dot menu *„Resend"* triggers new POST z `?regenerate_token=true`

**Acceptance criteria:**
- [ ] AC-1: Modal renders z all form fields
- [ ] AC-2: Submit valid data → 201, toast, list refresh
- [ ] AC-3: Email already invited → 422 z error toast
- [ ] AC-4: Owner role assignment gdy already exists → 409 z toast *„Transfer ownership first"*
- [ ] AC-5: Resend triggers new email
- [ ] AC-6: Locale/channel scope multi-select works

**Files affected:** `apps/admin/src/components/InviteUserModal.tsx`, `apps/admin/src/services/invitations.ts`

**DoD:** Standard + AC + E2E full invite flow.

---

## RBAC-P5-003: feat(admin): edit user — role assignment + scope

**Typ:** `feat` | **Phase:** 5 | **Estymacja:** **5-7h**

**Dependencies:** Blocked by RBAC-P5-001.

**Risk flags:** Self-modification block — backend rejects (RBAC-P3-005), frontend hides edit role section dla current user. Last admin protection w UI.

**Cel:** Edit user page lub modal — modify roles, locale/channel scope. Profile fields (name, avatar) separate flow.

**Scope:**
- `/settings/users/{id}/edit` route lub modal
- Form sections:
  - **Profile:** name (editable by admin), email (read-only after invite), avatar
  - **Roles & Permissions:** role(s) multi-select + locale_scope + channel_scope (skip dla current user — show *„You cannot edit your own role"*)
  - **Activity:** last_login, created_at, deactivated_at (read-only)
- Submit:
  - PATCH `/api/users/{id}` z body
  - Success → toast + refetch
- Last admin protection — UI shows warning gdy user is last admin: *„Cannot remove admin role — this is the last administrator"*

**Acceptance criteria:**
- [ ] AC-1: Render form z current user data pre-filled
- [ ] AC-2: Update name/role → 200 + toast
- [ ] AC-3: Self-edit role section hidden lub disabled z message
- [ ] AC-4: Last admin role removal attempt → backend 409 + toast
- [ ] AC-5: Profile fields editable always (avatar, name)

**Files affected:** `apps/admin/src/routes/settings/users/[id]/edit.tsx`

**DoD:** Standard + AC.

---

## RBAC-P5-004: feat(admin): deactivate/reactivate user flow

**Typ:** `feat` | **Phase:** 5 | **Estymacja:** **3-4h**

**Dependencies:** Blocked by RBAC-P5-001.

**Risk flags:** Last admin protection enforced (RBAC-P3-005 backend).

**Cel:** 3-dot menu actions — *„Deactivate"* (soft) + *„Reactivate"*. Deactivation: confirm modal + audit log entry. Reactivation: simple action.

**Scope:**
- Deactivate action — modal z confirm: *„Are you sure?"* + optional reason textarea
- POST `/api/users/{id}/deactivate` z body `{reason?}` → user `is_active=false`, `deactivated_at=NOW()`
- Audit log entry z reason
- Deactivated user effects:
  - Cannot login (401)
  - Existing sessions invalidated (Mercure event)
  - Listed w Users z badge *„Deactivated"*
- Reactivate action — single click + confirm
- POST `/api/users/{id}/reactivate` → `is_active=true`, `deactivated_at=null`

**Acceptance criteria:**
- [ ] AC-1: Deactivate flow z confirm + reason
- [ ] AC-2: Deactivated user cannot login (manual test)
- [ ] AC-3: Existing session of deactivated user → 401 next request (SSE Mercure)
- [ ] AC-4: Reactivate simple flow
- [ ] AC-5: Last admin deactivation attempt → 409

**Files affected:** `apps/admin/src/components/DeactivateUserModal.tsx`, `apps/admin/src/services/users.ts`

**DoD:** Standard + AC.

---

## RBAC-P5-005: feat(admin): Settings → Roles list (system templates + custom)

**Typ:** `feat` | **Phase:** 5 | **Estymacja:** **3-5h**

**Dependencies:** Blocked by Phase 4.

**Risk flags:** System templates immutable code (`is_system=true`) — frontend pokazuje badge + disabled delete.

**Cel:** `/settings/roles` page z table of roles. Sortowane: system templates first (Owner, Admin, ...), potem custom roles. Każda rola ma badge `System` lub `Custom`.

**Scope:**
- `<RolesListPage>` z table:
  - Columns: Name | Description | Type (System/Custom badge) | User count (z `useCount`) | Actions
  - Default sort: type DESC (System na górze), name ASC
- 3-dot menu actions:
  - View permissions (read-only modal/page)
  - Edit (na System role też allowed — można change permissions, NIE name/code)
  - Duplicate (clone as new custom role)
  - Delete (tylko custom roles)
- Empty state: brak — zawsze są system templates seedowane
- *„+ Create custom role"* button z toolbar

**Acceptance criteria:**
- [ ] AC-1: Render 9 starter roles + custom z proper badges
- [ ] AC-2: System roles delete action disabled lub hidden
- [ ] AC-3: User count per role accurate (test: invite user z rolą, count zwiększa się)
- [ ] AC-4: Sort by Type (System first) default

**Files affected:** `apps/admin/src/routes/settings/roles/index.tsx`, `apps/admin/src/components/RolesList.tsx`

**DoD:** Standard + AC.

---

## RBAC-P5-006: feat(admin): custom role builder UI — matrix checkbox grid + default_attribute_permission + cross-tab exception badges

**Typ:** `feat` | **Phase:** 5 | **Estymacja:** **14-18h** (zwiększone z 10-14h po PRD v2.1 update §3.5 — dodanie exception badges + default_attribute_permission)

**Dependencies:** Blocked by RBAC-P5-005.

**Risk flags:** Matrix UI complex — 12 modules × 4-6 actions × responsive. Mobile breakpoint TBD (z PRD §3.2 macierz). Plus **cross-tab badges** wymagają fetch counters z `role_attribute_permissions` w real-time.

**Cel:** Page do create/edit custom roles z matrix checkbox grid (per PRD §5.3 mockup). Name, description, auto_grant_new_object_types toggle, default_attribute_permission selector, plus permission matrix z exception badges per row gdy są per-attribute exceptions.

**Scope:**
- `/settings/roles/new` + `/settings/roles/{id}/edit` routes
- Form:
  - Name (required)
  - Code (auto-generated z name, editable)
  - Description (optional textarea)
  - Auto-grant new ObjectTypes toggle
  - **Default attribute permission** — radio (Restricted / View / Edit) — z auto-inherit z macierzy gdy klient explicit nie wybiera (decyzja designerska C):
    - `products.edit` ✓ w macierzy → default `'edit'`
    - `products.view` ✓ only → default `'view'`
    - Nic → default `'restricted'`
- Matrix grid — table z modules (rows) × actions (columns):
  - Modules: Produkty, Kategorie, Multimedia, Modelowanie, Publikacje, Imports, Exports, Workflow, Cmd+K, Settings (Users/Roles/Tenant/Billing/Integrations), API tokens, Audit
  - Actions per module: View, Add, Edit, Delete, Approve, Execute (per macierz 3.2)
  - Checkbox za każdą valid (module, action) combination
- **Cross-tab exception badges** (per PRD §3.2 + §3.5):
  - Per row macierzy (np. *„Produkty — Edit"*) — gdy są per-attribute exceptions w `role_attribute_permissions` lub `role_attribute_group_permissions`:
    - Badge `[N wyjątków ⚠]` z tooltip listing exceptions (max 5, *„+ X more"* gdy więcej)
    - CTA *„Zarządzaj wyjątkami"* — link do zakładki *„Uprawnienia per atrybut"*
  - Sticky info banner na górze macierzy (gdy ANY exceptions):
    - *„⚠ Ta rola ma N wyjątków per-attribute. Sprawdź zakładkę 'Uprawnienia per atrybut'."*
- Save → POST `/api/roles` lub PATCH `/api/roles/{id}`
- Reset button → revert to original state
- Pre-fill z template — *„Start from template..."* dropdown loads existing role permissions

**Acceptance criteria:**
- [ ] AC-1: Matrix renders z 12 modules × actions
- [ ] AC-2: Checkboxes toggle independently
- [ ] AC-3: Save validates required fields + creates role
- [ ] AC-4: Edit existing custom role pre-fills matrix
- [ ] AC-5: Auto-grant toggle persists
- [ ] AC-6: *„Start from template"* dropdown copies permissions
- [ ] AC-7: System role edit allows permissions change ale NIE name/code
- [ ] AC-8: Mobile responsive — scroll horizontal lub accordion sections
- [ ] AC-9: `default_attribute_permission` radio renderuje z auto-inherit hint (e.g. *„Auto: Edit (z products.edit w macierzy)"*) z możliwością manual override
- [ ] AC-10: Exception badges per row w macierzy — fetch counters z `role_attribute_permissions` count(*) WHERE role_id = current
- [ ] AC-11: Sticky info banner pojawia się gdy ≥1 exception istnieje
- [ ] AC-12: CTA *„Zarządzaj wyjątkami"* nawiguje do tab *„Uprawnienia per atrybut"* (lub `/settings/roles/{id}/attributes`)

**Files affected:** `apps/admin/src/routes/settings/roles/new.tsx`, `apps/admin/src/routes/settings/roles/[id]/edit.tsx`, `apps/admin/src/components/PermissionMatrix.tsx`

**Testing requirements:**
- E2E: create custom role → assign to user → user sees expected permissions
- Mobile responsive snapshot

**DoD:** Standard + AC + Playwright role creation flow.

---

## RBAC-P5-007: feat(admin): "Uprawnienia per atrybut" tab — 3-state grants z bulk per-group + preview + cross-tab warnings

**Typ:** `feat` | **Phase:** 5 | **Estymacja:** **12-18h** (zwiększone z 5-7h po PRD v2.1 update §3.5 — 3-state UI + AttributeGroup grouping + bulk + preview modal + cross-tab komunikacja)

**Dependencies:** Blocked by RBAC-P5-006 (matrix + default_attribute_permission), RBAC-P1-005 (schema z 2 new tables).

**Risk flags:**
- Lista atrybutów może być duża (200+). Performance — virtualized list per AttributeGroup + lazy load expanded groups.
- 3-state segmented control vs checkbox — accessibility (keyboard navigation, screen reader announcements).
- **Mixed state per group** — gdy atrybuty w grupie mają różne permissions (część edit, część view), bulk control pokazuje state *„Mixed"* z visual indicator.
- **Cross-tab synchronization** — zmiana w tym tabie musi update badge counter w macierzy uprawnień (RBAC-P5-006) bez full page reload. Mercure SSE lub local state sync.

**Cel:** Tab *„Uprawnienia per atrybut"* w role editor z 3-state permissions (`restricted` / `view` / `edit`) per atrybut, grupowane w AttributeGroups z bulk per-group toggle, preview modal dla bulk >5, cross-tab warning gdy macierz blokuje broad permission.

**Scope:**

**Layout główny tabu:**
- Tab header z badge counter (liczba aktywnych exceptions vs role default)
- Toolbar:
  - Search box (typeahead, filter atrybutów po nazwie + code)
  - Filter dropdown: *„Pokaż: Wszystkie / Tylko Restricted / Tylko View / Tylko Edit / Mixed (groups)"*
  - Reset filtrów link
- Info banner (sticky top) gdy macierz blokuje broad permission tej roli (cross-tab warning per PRD §3.5)

**AttributeGroup panels** (analogicznie do Modelowania):
- Każda AttributeGroup jako expandable accordion section
- Group header:
  - Group name + count atrybutów (np. *„Pricing (4 atrybuty)"*)
  - **3-state segmented control bulk**: `[○ Restricted for All]  [○ View for All]  [● Edit for All]`
  - State indicator *„Mixed"* gdy atrybuty w grupie mają różne ustawienia (visual badge)
  - Expand/collapse arrow
- Expanded body (lista atrybutów):
  - Per atrybut row z kolumnami:
    - ATRYBUT (nazwa + code)
    - TYP (badge — money, text, number, enum, etc.)
    - INTEGRATION_VISIBLE (badge true/false z deep-link do Modelowania)
    - UPRAWNIENIE (**3-state segmented control**: `[○ Restricted] [○ View] [● Edit]`)
    - UWAGA (auto-generated text per state combination — patrz §3.5 PRD)

**Bulk apply logic (decyzja designerska A — Visible w current view):**
- Click `Edit for All` na grupie → apply do **widocznych atrybutów** (po active filters)
- Hint pod toolbar: *„Bulk apply zmieni N atrybutów widocznych w filtrach. Resetuj filtry żeby zmienić wszystkie."*

**Preview modal (decyzja designerska B — preview dla bulk >5):**
- Trigger: bulk apply na grupie z >5 widocznymi atrybutami
- Modal content:
  - Title: *„Potwierdź zmianę bulk"*
  - Diff summary: liczba per stan (*„47 atrybutów: Edit → Edit (bez zmian)"*, *„3: View → Edit"*, *„1: Restricted → Edit"*)
  - Expandable details — full list per change
  - Buttons: `[Anuluj]` `[Zastosuj]`
- Single atrybut toggle = instant change (no modal)

**Cross-tab warning (per PRD §3.5):**
- Gdy rola **nie ma** broad `products.view` w macierzy + klient próbuje ustawić atrybut na `view`/`edit`:
  - Info banner top: *„⚠ Ta rola nie ma 'Produkty: View' w macierzy uprawnień. Per-attribute permissions nie zadziałają dopóki nie włączysz broad permission."* + button *„Idź do macierzy"*
  - Disabled radio buttons `View`/`Edit` z tooltip *„Wymaga 'Produkty: View' w macierzy"*

**Auto-generated UWAGA column** (per PRD §3.5):

| Permission | integration_visible | Text |
|---|---|---|
| `restricted` | true | *„Restricted — atrybut ukryty dla tej roli"* |
| `restricted` | false | *„Restricted + wewnętrzny — niewidoczny w API integracji"* |
| `view` | true | *„View only — widoczny, brak edycji"* |
| `view` | false | *„View only — wewnętrzny atrybut"* |
| `edit` | true | *„Full access — view + edit"* |
| `edit` | false | *„Edit + wewnętrzny — nie idzie do API integracji"* |

**Backend integration:**
- GET `/api/roles/{id}/attribute-permissions` — zwraca aktualne permissions dla roli (per-attribute + per-group + resolved values)
- PUT `/api/roles/{id}/attribute-permissions` — bulk update z body `{attributes: [...], groups: [...]}` (atomic transaction)
- DELETE `/api/roles/{id}/attribute-permissions/{attribute_id}` — usunięcie override (powrót do group default lub role default)
- Mercure event `role.attribute_permissions.changed.{role_id}` — frontend refresh badge counter w macierzy

**Acceptance criteria:**
- [ ] AC-1: Tab renamed z *„Restrykcje per atrybut"* na *„Uprawnienia per atrybut"*
- [ ] AC-2: Atrybuty grupowane w AttributeGroups (akordeon UI z expand/collapse)
- [ ] AC-3: 3-state segmented control per atrybut (Restricted/View/Edit) renderuje
- [ ] AC-4: 3-state bulk control per AttributeGroup z state *„Mixed"* gdy różne ustawienia w grupie
- [ ] AC-5: Single attribute toggle = instant change, bulk >5 = preview modal
- [ ] AC-6: Preview modal pokazuje diff (47 unchanged, 3 view→edit, 1 restricted→edit) z [Anuluj] [Zastosuj]
- [ ] AC-7: Bulk apply respektuje active search/filter (visible only)
- [ ] AC-8: Auto-generated UWAGA column z 6 unique texts per state combination
- [ ] AC-9: Cross-tab warning banner gdy macierz nie ma broad permission
- [ ] AC-10: Disabled radio buttons View/Edit gdy macierz blokuje + tooltip explanation
- [ ] AC-11: Mercure event triggers badge counter refresh w macierzy uprawnień (RBAC-P5-006 sticky banner)
- [ ] AC-12: Atomic save — błąd w jednej zmianie cofa całe batch (transaction rollback)
- [ ] AC-13: Accessibility — keyboard navigation segmented control + screen reader announcements (axe-core test)
- [ ] AC-14: Performance — render 200+ atrybutów <500ms (virtualized lub lazy-load per-group)

**Files affected:**
- `apps/admin/src/components/role-editor/AttributePermissionsTab.tsx` (new — replaces v1 AttributeRestrictionsTab)
- `apps/admin/src/components/role-editor/AttributeGroupPanel.tsx` (new)
- `apps/admin/src/components/role-editor/AttributePermissionRow.tsx` (new)
- `apps/admin/src/components/role-editor/PermissionSegmentedControl.tsx` (new — 3-state radio)
- `apps/admin/src/components/role-editor/BulkApplyPreviewModal.tsx` (new)
- `apps/admin/src/services/role-attribute-permissions.ts` (new — API client)
- `apps/admin/src/hooks/useRoleAttributePermissions.ts` (new — z Mercure SSE subscription)

**Testing requirements:**
- Unit (Vitest): PermissionSegmentedControl renders 3 states correctly
- Unit: AttributeGroupPanel detects Mixed state correctly
- Unit: BulkApplyPreviewModal calculates diff correctly
- Integration: full flow create role → set per-attribute permissions → reflect w produkt detail
- E2E Playwright: cross-tab badge counter updates real-time po zmianie w tej zakładce
- Accessibility: axe-core scan pass

**DoD:** Standard + AC + E2E cross-tab flow test + accessibility pass.

---

## RBAC-P5-008: feat(admin): auto-grant new ObjectTypes toggle + locale/channel scope in role editor

**Typ:** `feat` | **Phase:** 5 | **Estymacja:** **3-5h**

**Dependencies:** Blocked by RBAC-P5-006.

**Risk flags:** Locale/channel scope `["*"]` jest default — easy do oversee w UI. Visual indicator gdy scope ograniczony.

**Cel:** Dodatkowe controls w role editor — auto_grant_new_object_types toggle + locale_scope + channel_scope multi-select.

**Scope:**
- Section *„Advanced"* w role editor
- Auto-grant toggle: *„Auto-grant view+edit for new ObjectTypes"* z tooltip explanation
- Locale scope: multi-select z tenant.locales + special option *„All locales (*)"* default selected
- Channel scope: analogicznie z tenant.channels
- Visual indicator nad role name: jeśli scope ograniczony → badge *„Locale: PL only"* lub *„Channel: Allegro only"*

**Acceptance criteria:**
- [ ] AC-1: Toggle persists
- [ ] AC-2: Locale scope save updates `user_roles.locale_scope` dla wszystkich users z tą rolą
- [ ] AC-3: Visual badge w role list

**Files affected:** `apps/admin/src/components/RoleAdvancedSettings.tsx`

**DoD:** Standard + AC.

---

## RBAC-P5-009: feat(admin): Settings → API tokens list (own + all users for admin)

**Typ:** `feat` | **Phase:** 5 | **Estymacja:** **4-6h**

**Dependencies:** Blocked by Phase 4.

**Risk flags:** `token_hash` NIGDY w response — frontend pokazuje tylko `last_4_chars` + name + scope. Plaintext token zwracany tylko raz przy create (RBAC-P5-010).

**Cel:** `/settings/api-tokens` page — lista tokenów. User widzi swoje, admin (z `manage_api_tokens_all`) widzi all users.

**Scope:**
- `<ApiTokensListPage>` z table:
  - Columns: Name | Owner (jeśli all view) | Scope | Last Used | Expires | Created | Actions
  - Filter by owner (dla admin view), by scope
- Token display: `Token...XYZW` (last 4 chars + dots)
- 3-dot menu: Revoke, View details
- *„+ Create token"* button → RBAC-P5-010 wizard

**Acceptance criteria:**
- [ ] AC-1: User widzi tylko swoje tokens (default)
- [ ] AC-2: Admin (`manage_api_tokens_all`) widzi wszystkie z owner column
- [ ] AC-3: `token_hash` NIE w response (test: DevTools Network)

**Files affected:** `apps/admin/src/routes/settings/api-tokens/index.tsx`

**DoD:** Standard + AC.

---

## RBAC-P5-010: feat(admin): create API token wizard (scope template + custom + expiry)

**Typ:** `feat` | **Phase:** 5 | **Estymacja:** **5-7h**

**Dependencies:** Blocked by RBAC-P5-009.

**Risk flags:** Plaintext token MUST be displayed once + force user copy. Modal close warning.

**Cel:** Wizard create token — name, scope (template lub custom), expiry. Po create: plaintext token shown once w modal z big *„Copy"* button.

**Scope:**
- `<CreateTokenWizard>` modal/page z steps:
  1. Name + description
  2. Scope: radio template (6 templates) lub *„Custom"* → checkbox grid z permissions
  3. Expiry: dropdown (30 days, 90 days default, 1 year, Never)
  4. Locale/channel scope (optional)
  5. Review summary
- POST `/api/api-tokens` → response `{token, ...}` plaintext token
- Token display modal:
  - Big monospace text z token
  - *„Copy to clipboard"* button + visual confirmation
  - Warning: *„This is the only time you will see this token. Save it now."*
  - Force checkbox *„I have saved the token"* przed Close
- Long-lived token (Never expiry) → confirm modal + audit log `LONG_LIVED_TOKEN_CREATED`

**Acceptance criteria:**
- [ ] AC-1: Wizard 5 steps z validation per step
- [ ] AC-2: 6 scope templates listed + Custom option
- [ ] AC-3: Plaintext token shown once
- [ ] AC-4: Force checkbox before close
- [ ] AC-5: Long-lived warning modal
- [ ] AC-6: Copy to clipboard works

**Files affected:** `apps/admin/src/components/CreateTokenWizard.tsx`, `apps/admin/src/components/TokenDisplayModal.tsx`

**DoD:** Standard + AC.

---

## RBAC-P5-011: feat(admin): revoke API token confirm modal

**Typ:** `feat` | **Phase:** 5 | **Estymacja:** **2-3h**

**Dependencies:** Blocked by RBAC-P5-009.

**Risk flags:** Revoke immediate — invalidates active integrations. UI warns about impact.

**Cel:** Revoke modal z confirm — shows token name, last used, scope. Warning *„This will break active integrations using this token"*.

**Scope:**
- `<RevokeTokenModal>` z token details (name, owner, scope, last_used)
- Warning text z explanation impact
- Type token name to confirm (analog do CLAUDE.md hard confirm pattern)
- DELETE `/api/api-tokens/{id}` → success toast + list refresh
- Audit log entry

**Acceptance criteria:**
- [ ] AC-1: Modal renders token details
- [ ] AC-2: Type name confirmation required
- [ ] AC-3: Revoke → token immediately invalid (test: API request z revoked token → 401)

**Files affected:** `apps/admin/src/components/RevokeTokenModal.tsx`

**DoD:** Standard + AC.

---

## RBAC-P5-012: feat(admin): Profile → Security → password change

**Typ:** `feat` | **Phase:** 5 | **Estymacja:** **3-4h**

**Dependencies:** Blocked by Phase 4.

**Risk flags:** Password change requires current password re-auth (defence przeciw session hijacking).

**Cel:** Profile → Security page → password change form. Current password + new password + confirm.

**Scope:**
- Form z 3 fields: current_password, new_password, confirm_password
- Validation: new_password min 12 chars, mixed case + number recommended (warning, nie hard reject)
- Password strength meter
- POST `/api/me/change-password` z body
- Backend verifies current_password matches → updates hash → invalidates other sessions
- Success toast + force re-login

**Acceptance criteria:**
- [ ] AC-1: Form z 3 fields
- [ ] AC-2: Strength meter updates real-time
- [ ] AC-3: Mismatched confirm → error inline
- [ ] AC-4: Wrong current password → 401 + error toast
- [ ] AC-5: Success → other sessions logged out (verify)

**Files affected:** `apps/admin/src/routes/profile/security.tsx` (extension), `apps/admin/src/components/ChangePasswordForm.tsx`

**DoD:** Standard + AC.

---

## RBAC-P5-013: feat(admin): Profile → Security → MFA enable/disable + recovery codes view

**Typ:** `feat` | **Phase:** 5 | **Estymacja:** **3-4h**

**Dependencies:** Blocked by RBAC-P4-012 (MFA wizard).

**Cel:** Profile → Security page extension — MFA status display, enable/disable controls, recovery codes status.

**Scope:**
- MFA section header z badge: *„Enabled (App)"* / *„Enabled (Email)"* / *„Disabled"*
- Enable button — opens RBAC-P4-012 wizard
- Disable button — confirm modal z password re-auth
- Recovery codes status: *„7 of 10 available"* + button *„Generate new codes"* (invalidates old)
- Switch method link — *„Switch to Authenticator app"* (re-setup)

**Acceptance criteria:**
- [ ] AC-1: MFA status correct badge
- [ ] AC-2: Disable requires password
- [ ] AC-3: Recovery codes count accurate
- [ ] AC-4: Generate new codes → old codes invalid + new ones displayed
- [ ] AC-5: Switch method → re-setup flow

**Files affected:** `apps/admin/src/routes/profile/security.tsx` (extension)

**DoD:** Standard + AC.

---

## RBAC-P5-014: feat(admin): Settings → SSO config UI (Google + Microsoft + SAML)

**Typ:** `feat` | **Phase:** 5 | **Estymacja:** **8-12h**

**Dependencies:** Blocked by RBAC-P2-012/013/014 (backend SSO endpoints).

**Risk flags:** SAML config — XML metadata upload, X.509 certificate management. Validation crucial.

**Cel:** `/settings/sso` page (Owner/Admin only) — configure SSO providers per tenant. Google Workspace, Microsoft 365 (OAuth) + SAML 2.0 (enterprise).

**Scope:**
- Tabs: Google Workspace, Microsoft 365, SAML 2.0
- Google/Microsoft tab:
  - Enable toggle
  - Client ID + Client Secret (write-only display)
  - Allowed domains list (email domain matching)
  - Auto-create users toggle
- SAML tab:
  - Enable toggle
  - SP metadata download (link)
  - IdP metadata upload (XML file lub URL)
  - Signing certificate display
  - Attribute mapping (NameID format, email attribute, name attribute)
  - Enforce SSO for users toggle
- Test connection button — initiates SAML AuthnRequest, verifies response

**Acceptance criteria:**
- [ ] AC-1: 3 tabs z separate configs
- [ ] AC-2: Save Google config → toggle z dashboard works
- [ ] AC-3: SAML metadata upload parse + display
- [ ] AC-4: Test connection initiates real SAML flow z IdP
- [ ] AC-5: Enforce toggle disables password login

**Files affected:** `apps/admin/src/routes/settings/sso/index.tsx`, `apps/admin/src/components/SamlConfigTab.tsx`

**DoD:** Standard + AC + manual smoke z Okta dev account.

---

## RBAC-P5-015: feat(admin): Settings → Tenant config (Owner only)

**Typ:** `feat` | **Phase:** 5 | **Estymacja:** **3-5h**

**Dependencies:** Blocked by Phase 4.

**Cel:** `/settings/tenant` (Owner only via permission gate). Form: tenant name, locales, channels, default locale, default channel.

**Scope:**
- Tenant name (editable)
- Locales multi-select (z list ISO 639-1 + region codes)
- Channels CRUD (list + add/edit/delete per channel)
- Default locale dropdown (z locales)
- Default channel dropdown
- Tenant delete section (danger zone) — typing tenant name confirm + cascade warning

**Acceptance criteria:**
- [ ] AC-1: Form pre-filled z current tenant data
- [ ] AC-2: Update locales → reflects w role editor scope options
- [ ] AC-3: Tenant delete confirm flow + cascade (user redirected)

**Files affected:** `apps/admin/src/routes/settings/tenant/index.tsx`

**DoD:** Standard + AC.

---

## RBAC-P5-016: feat(admin): Settings → Billing placeholder (Owner only)

**Typ:** `feat` | **Phase:** 5 | **Estymacja:** **1-2h**

**Dependencies:** Blocked by Phase 4.

**Cel:** Placeholder page `/settings/billing` — Owner only. Display *„Billing integration coming in Q3 2026. Current tier: Pro. Contact support for changes."*. Faktyczne billing integration = Faza 1.

**Scope:**
- Static page z permission gate
- Show current tier (z `tenant.pricing_tier`)
- Contact support button (mailto)
- Future-state placeholder image/text

**Acceptance criteria:**
- [ ] AC-1: Permission-gated (Owner only)
- [ ] AC-2: Current tier visible

**Files affected:** `apps/admin/src/routes/settings/billing.tsx`

**DoD:** Standard + AC.

---

## RBAC-P5-017: feat(admin): magic link accept invitation page

**Typ:** `feat` | **Phase:** 5 | **Estymacja:** **5-7h**

**Dependencies:** Blocked by RBAC-P2-008 (backend invitation endpoints).

**Risk flags:** Token validity check upfront — wrong token, show error, NIE allow setup attempts.

**Cel:** `/accept-invitation?token=X` (public route). User klika z email link, ustawia password + opcjonalnie MFA, gets logged in.

**Scope:**
- Page z 3 steps:
  1. Token validity check (GET `/api/invitations/{token}/verify`) — wrong/expired → error page
  2. Setup password form (new password + confirm)
  3. Optional MFA setup (skip option)
- Show user info from invitation: email, name, tenant, roles assigned
- POST `/api/invitations/{token}/accept` z body
- Success → JWT issued → redirect dashboard
- Failure (token now invalid) → error + suggest resend invitation

**Acceptance criteria:**
- [ ] AC-1: Valid token → form
- [ ] AC-2: Expired token → error page z resend CTA
- [ ] AC-3: Wrong token → error page
- [ ] AC-4: Successful accept → JWT + redirect

**Files affected:** `apps/admin/src/routes/accept-invitation.tsx`

**DoD:** Standard + AC + E2E full invite flow.

---

## RBAC-P5-018: feat(admin): 403 page design + last admin protection modals

**Typ:** `feat` | **Phase:** 5 | **Estymacja:** **3-4h**

**Dependencies:** Blocked by RBAC-P4-003.

**Cel:** Polish design dla 403 page + modal warning gdy próba block last admin / Owner uniqueness violation.

**Scope:**
- 403 page polish:
  - Centered card z icon (lock/shield)
  - Heading: *„Brak dostępu"*
  - Context message z backend Problem Details `detail`
  - Permission required code w expandable details (dla debug)
  - Buttons: *„Wróć"*, *„Wyloguj"*
- Last admin protection modal — triggered when delete/deactivate ostatniego admina
- Owner uniqueness modal — gdy assign Owner role i już istnieje

**Acceptance criteria:**
- [ ] AC-1: 403 page styled
- [ ] AC-2: Last admin modal explicit message *„Przypisz Administrator innemu użytkownikowi najpierw"*
- [ ] AC-3: Owner uniqueness modal z transfer suggestion

**Files affected:** `apps/admin/src/routes/403.tsx` (polish), `apps/admin/src/components/LastAdminProtectionModal.tsx`, `apps/admin/src/components/OwnerUniquenessModal.tsx`

**DoD:** Standard + AC.

---

## RBAC-P5-019: feat(admin-panel): Super Admin operator panel — Tenant list view

**Typ:** `feat` | **Phase:** 5 | **Estymacja:** **5-7h**

**Dependencies:** Blocked by RBAC-P3-014 (Super Admin backend endpoints).

**Risk flags:** **Admin panel na separate subdomain** `admin.cortex.pl`. Different JWT cookie domain. Routing isolation.

**Cel:** `admin.cortex.pl` setup — Super Admin login + Tenant list page. Privacy boundary: tylko metadata.

**Scope:**
- Subdomain setup w Caddy (per CLAUDE.md §3.10a):
  - `admin.cortex.pl/*` → FrankenPHP z `/admin/*` routing
  - Separate JWT cookie `cortex_admin_access`
- Super Admin login `/admin/login` (separate od user login)
- Tenant list `/admin/tenants` z table:
  - Columns: Name | Plan | Created | User Count | Storage Used | Status (active/suspended) | Actions
  - Filter by plan, status
  - Search by name
- Privacy boundary visible — *„Domain data inaccessible by Super Admin"* footnote

**Acceptance criteria:**
- [ ] AC-1: `admin.cortex.pl` accessible, separate od `pim.cortex.pl`
- [ ] AC-2: Super Admin login → JWT z `super_admin` scope
- [ ] AC-3: Tenant list renders metadata
- [ ] AC-4: NIE może access tenant.products endpoint (privacy boundary)

**Files affected:** `apps/admin-panel/` (new app w monorepo) lub `apps/admin/src/routes/admin/`, Caddy config.

**DoD:** Standard + AC + manual smoke admin subdomain.

---

## RBAC-P5-020: feat(admin-panel): Tenant detail (metadata + read-only data)

**Typ:** `feat` | **Phase:** 5 | **Estymacja:** **3-5h**

**Dependencies:** Blocked by RBAC-P5-019.

**Cel:** `/admin/tenants/{id}` — detail view z metadata + counts (users, products, storage). Brak access do domain data (Privacy).

**Scope:**
- Tenant detail page sections:
  - General info (name, slug, plan, created, last_login_recent)
  - Users count + Activity (last 30d active users)
  - Storage usage (DB size, MinIO bucket size, audit logs size)
  - Integrations enabled (list, no secrets)
  - Recent audit events (last 50) — Super Admin może view cross-tenant audit

**Acceptance criteria:**
- [ ] AC-1: Metadata visible
- [ ] AC-2: Products/Attributes data NIE accessible (privacy boundary)
- [ ] AC-3: Audit log section visible

**Files affected:** `apps/admin-panel/src/routes/tenants/[id].tsx`

**DoD:** Standard + AC.

---

## RBAC-P5-021: feat(admin-panel): Tenant CRUD + cross-tenant audit log

**Typ:** `feat` | **Phase:** 5 | **Estymacja:** **6-8h**

**Dependencies:** Blocked by RBAC-P5-020.

**Risk flags:** Tenant deletion — destructive, cascade. Confirm flow z typing tenant name. Audit logged z special flag.

**Cel:** Create new tenant + edit existing + suspend/activate + delete + view cross-tenant audit.

**Scope:**
- Create tenant form: name, slug, plan, owner_email (invite owner)
- Edit form: name, plan, status
- Suspend/Activate toggle
- Delete flow — confirm modal z typing tenant slug + cascade warning (all users, products, integrations destroyed)
- `/admin/audit` page — cross-tenant audit log z filters (tenant, user, action, date range)

**Acceptance criteria:**
- [ ] AC-1: Create tenant flow → tenant created + owner invitation email sent
- [ ] AC-2: Suspend tenant → users cannot login (401)
- [ ] AC-3: Delete cascade — verify all data destroyed
- [ ] AC-4: Cross-tenant audit log filter works

**Files affected:** `apps/admin-panel/src/routes/tenants/new.tsx`, `apps/admin-panel/src/routes/tenants/[id]/edit.tsx`, `apps/admin-panel/src/routes/audit.tsx`

**DoD:** Standard + AC + manual smoke delete flow.

---

## RBAC-P5-022: feat(admin-panel): Break-glass recovery UI

**Typ:** `feat` | **Phase:** 5 | **Estymacja:** **3-5h**

**Dependencies:** Blocked by RBAC-P3-014 (CLI command already exists).

**Risk flags:** Break-glass = emergency tool. Heavy audit + MFA + rate limit (5/24h).

**Cel:** `/admin/break-glass` page — UI dla rescue admin recovery (alternatywa do CLI). Super Admin wybiera tenant + user, MFA verify, assign Owner role.

**Scope:**
- Form: tenant slug autocomplete + user email autocomplete (within selected tenant)
- Reason textarea (required, audit log entry)
- MFA TOTP code input (required)
- Submit → POST `/admin/break-glass` → backend executes recovery
- Rate limit display — *„3/5 daily uses remaining"*
- Audit log entry z `special_flag=SUPER_ADMIN_RECOVERY`

**Acceptance criteria:**
- [ ] AC-1: Form z tenant + user autocomplete
- [ ] AC-2: MFA TOTP verification required
- [ ] AC-3: Reason audit logged
- [ ] AC-4: Rate limit enforced (6th use → 429)
- [ ] AC-5: Target user gets Owner role assigned

**Files affected:** `apps/admin-panel/src/routes/break-glass.tsx`

**DoD:** Standard + AC + manual smoke z MFA.

---

## Phase 5 zakończony — deliverables

Po merge 22 ticketów:
- ✅ Users list + invite + edit + deactivate
- ✅ Roles list + custom builder (matrix UI) + restrictions + scope
- ✅ API tokens list + create + revoke
- ✅ Profile MFA + password change
- ✅ SSO config (Google + Microsoft + SAML)
- ✅ Tenant config (Owner)
- ✅ Billing placeholder
- ✅ Accept invitation page
- ✅ 403 page + protection modals
- ✅ Super Admin operator panel (admin.cortex.pl)

**Phase 5 → Phase 6:** Settings + Super Admin UI complete. Phase 6 refactor existing endpoints + components z RBAC integration.

**Estymacja Phase 5: ~90-120h. 22 tickety. Tempo: 3 tygodnie.**
