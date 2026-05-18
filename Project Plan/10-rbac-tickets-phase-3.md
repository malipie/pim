# RBAC — tickety Phase 3 (Backend permission engine + field-level)

**Typ dokumentu:** Backlog ticketów Phase 3 RBAC — ready-to-paste GitHub Issues
**Status:** Draft — gotowe do utworzenia po zakończeniu Phase 2
**Powiązane:** [`07-rbac-implementation-plan.md`](07-rbac-implementation-plan.md) §4.4, [`PRD/PRD-PIM-rbac.md`](PRD/PRD-PIM-rbac.md) §4

> **Cel Phase 3:** kompletna warstwa permission enforcement na backendzie — endpoint guards (`#[RequiresPermission]` attribute), Voters per resource, resource policies (per-attribute, per-locale, per-channel, ownership, workflow-state), field-level serializer filtering, audit log extensions, Super Admin cross-tenant bypass, break-glass CLI.
>
> **Harmonogram:** tygodnie 6-8, **~70-90h**. 14 ticketów.

---

## Graf zależności Phase 3

```
RBAC-P3-001 (#[RequiresPermission] attribute) ── core foundation
                │
                ▼
RBAC-P3-002..007 (Voters per resource) ── 6 ticketów, parallel-friendly
                │
                ▼
RBAC-P3-008..011 (Resource policies) ── 4 ticketów: attribute, locale/channel, ownership, workflow-state
                │
                ▼
RBAC-P3-012 (Field-level serializer filtering)
                │
                ▼
RBAC-P3-013 (Audit log listener z permission_check_result)
                │
                ▼
RBAC-P3-014 (Super Admin bypass + Break-glass CLI)
```

---

## RBAC-P3-001: feat(identity): #[RequiresPermission] attribute + endpoint guard

**Typ:** `feat` | **Phase:** 3 | **Estymacja:** **5-7h**

**Dependencies:** Blocks RBAC-P3-002..014. Blocked by Phase 2 complete.

**Risk flags:**
- Attribute działa via Symfony EventSubscriber (`kernel.controller`) — błąd w listener = wszystkie endpointy unguarded.
- Custom PHPStan rule z RBAC-P1-010 sprawdza obecność attribute, ale runtime fallback też potrzebny.

**Cel:** Custom Symfony PHP attribute `#[RequiresPermission(module, action)]` deklaratywnie określa wymaganą permission per endpoint. EventSubscriber przed wykonaniem controller method sprawdza permission, rzuca 403 jeśli brak.

**Scope:**
- PHP attribute class `RequiresPermission` z properties `module`, `action`, opcjonalnie `subject` (dla per-resource policies)
- Counterpart `NoPermissionRequired` — explicit whitelist (login, password reset, /api/me, etc.)
- `RequiresPermissionListener` subscribes do `kernel.controller` event:
  - Reflect controller method, find `#[RequiresPermission]` attribute
  - Call `PermissionResolver::resolve($user)` → check permission code `{module}.{action}`
  - If denied → throw `AccessDeniedHttpException` (Symfony auto-converts to 403)
  - If `subject` provided → defer to Voter (RBAC-P3-002..007)
- 403 response → RFC 7807 Problem Details z `type`, `title`, `detail`, `permission_required`
- Fallback: jeśli endpoint nie ma ani `RequiresPermission` ani `NoPermissionRequired` → throw exception w dev environment + log warning w prod (defence)

**Acceptance criteria:**
- [ ] AC-1: `#[RequiresPermission(module: 'products', action: 'edit')]` na controller method → sprawdzane przed execution
- [ ] AC-2: User bez permission → 403 z Problem Details
- [ ] AC-3: User z permission → method executes
- [ ] AC-4: `#[NoPermissionRequired]` → skip check (test: `/api/auth/login` z tym attribute)
- [ ] AC-5: Method bez żadnego attribute → dev environment throws exception, prod logs warning
- [ ] AC-6: Multiple permissions check (np. `#[RequiresPermission(module: 'products', action: 'edit')]` + `#[RequiresPermission(module: 'products', action: 'view')]`) → all must pass
- [ ] AC-7: Permission code z `{module}.{action}` matches seed z RBAC-P1-006

**Files affected:** `src/Identity/Attribute/RequiresPermission.php`, `src/Identity/Attribute/NoPermissionRequired.php`, `src/Identity/EventListener/RequiresPermissionListener.php`

**Testing requirements:**
- Unit: AttributeTest, ListenerTest
- Integration: real controllers z attributes (sample test endpoint)
- Static analysis: PHPStan custom rule z RBAC-P1-010 catches missing attribute

**DoD:** Standard + AC + custom PHPStan rule integration test.

---

## RBAC-P3-002: feat(identity): ProductVoter — per-product authorization

**Typ:** `feat` | **Phase:** 3 | **Estymacja:** **5-7h**

**Dependencies:** Blocked by RBAC-P3-001.

**Risk flags:**
- ProductVoter to **najczęściej wywoływany** Voter (60% UX na liście produktów). Performance critical — must use cached PermissionSet, nie query per call.
- Ownership check vs cross-tenant — tenant boundary już zhandlowane przez TenantFilter, ale Voter też verify dla defence.

**Cel:** `ProductVoter` decyduje czy user może `view` / `add` / `edit` / `delete` / `bulk_operations` / `approve_pending_changes` na konkretnym produkcie.

**Scope:**
- `ProductVoter extends Voter`
- `supports($attribute, $subject)`: zwraca `true` gdy attribute in `['view', 'add', 'edit', 'delete', 'bulk_operations', 'approve_pending_changes']` i `$subject instanceof Product` (lub null dla create)
- `voteOnAttribute($attribute, $subject, $token)`:
  - Resolve PermissionSet z PermissionResolver
  - Check global permission `products.{$attribute}`
  - Tenant boundary verify (defence in depth)
  - Per-attribute restrictions delegate do `AttributePermissionPolicy` (RBAC-P3-008)
  - Per-locale/channel delegate do `LocaleChannelScopePolicy` (RBAC-P3-009)
  - Workflow-state delegate do `WorkflowStatePolicy` (RBAC-P3-011)
- Compile time priorities — RequiresPermission attribute calls Voter via `$security->isGranted('edit', $product)`

**Acceptance criteria:**
- [ ] AC-1: Owner role → granted dla wszystkich akcji
- [ ] AC-2: Marketing role → `view`, `add`, `edit`, `bulk_operations` ✓; `delete` ✗
- [ ] AC-3: Viewer role → tylko `view` ✓
- [ ] AC-4: User z tenant A próbujący edit produktu z tenant B → 403 (tenant boundary check)
- [ ] AC-5: Performance: 1000 voter calls <500ms total (cache hit benchmark)
- [ ] AC-6: Integration z `$security->isGranted()` API
- [ ] AC-7: Delegacja do AttributePermissionPolicy/LocaleChannelScopePolicy/WorkflowStatePolicy

**Files affected:** `src/Identity/Voter/ProductVoter.php`

**Testing requirements:**
- Unit: ProductVoterTest — 10+ scenariuszy per role (per macierz 3.2 PRD)
- Integration: real product CRUD endpoints
- Performance: 1000 calls benchmark

**DoD:** Standard + AC + macierz 3.2 PRD validation per role.

---

## RBAC-P3-003: feat(identity): CategoryVoter + AssetVoter

**Typ:** `feat` | **Phase:** 3 | **Estymacja:** **4-6h**

**Dependencies:** Blocked by RBAC-P3-002 (shared patterns).

**Risk flags:**
- AssetVoter ownership semantyka — Marketing edit OWN uploads only (per macierz 3.2). Ownership check critical.
- Category hierarchical — delete root category cascade do dzieci. Voter check przed cascade.

**Cel:** Voters dla Category i Asset resources, reuse patterns z ProductVoter.

**Scope:**
- `CategoryVoter` z attributes `view`, `add_edit`, `delete`
- `AssetVoter` z attributes `view`, `add_edit_own`, `add_edit_any`, `delete`
- Ownership check w AssetVoter — `add_edit_own` allows tylko gdy `asset.uploaded_by === current_user_id`
- Pattern reuse — extract `AbstractCortexVoter` jako parent (jeśli warto, code review decision)

**Acceptance criteria:**
- [ ] AC-1: CategoryVoter z 3 actions, AssetVoter z 4 actions
- [ ] AC-2: Marketing add_edit_own ✓ dla własnego asset, ✗ dla cudzego
- [ ] AC-3: Catalog Manager add_edit_any ✓ dla każdego asset w tenant
- [ ] AC-4: Tenant boundary check w obu Voters
- [ ] AC-5: Macierz 3.2 validation pass

**Files affected:** `src/Identity/Voter/CategoryVoter.php`, `src/Identity/Voter/AssetVoter.php`, optional `src/Identity/Voter/AbstractCortexVoter.php`

**Testing requirements:** Unit per Voter + macierz validation tests.

**DoD:** Standard + AC.

---

## RBAC-P3-004: feat(identity): ObjectTypeVoter + AttributeVoter + AttributeGroupVoter

**Typ:** `feat` | **Phase:** 3 | **Estymacja:** **5-7h**

**Dependencies:** Blocked by RBAC-P3-002.

**Risk flags:**
- ObjectType delete musi sprawdzać `is_built_in=false` — built-in (Product, Category, Asset) blocked.
- AttributeVoter różny od ProductVoter — Modelowanie scope, not Catalog. Modeler ma dostęp, Catalog Manager nie.
- Auto-grant flag — gdy nowy ObjectType utworzony, role z `auto_grant_new_object_types=true` automatycznie zyskują view+edit. Logic w Doctrine listener (Phase 3 lub osobny ticket).

**Cel:** Voters dla Modelowanie resources — ObjectType, Attribute, AttributeGroup.

**Scope:**
- `ObjectTypeVoter` — actions `view`, `add`, `edit`, `delete` (blocked dla is_built_in=true)
- `AttributeVoter` — actions `view`, `add_edit`, `delete`
- `AttributeGroupVoter` — actions `view`, `add_edit`
- Auto-grant Doctrine listener `OnObjectTypeCreatedListener` — po insert nowego ObjectType, dla każdej roli z `auto_grant_new_object_types=true`, dodaj permissions `{object_type_code}.view`, `{object_type_code}.edit` do `role_permissions`
- Built-in protection — DELETE ObjectType z `is_built_in=true` → 409 Conflict z message *„Built-in object type cannot be deleted"*

**Acceptance criteria:**
- [ ] AC-1: Modeler role → view + add_edit + add + delete dla all attributes/object_types (z is_built_in protection)
- [ ] AC-2: Catalog Manager → view only modeling (per macierz 3.2)
- [ ] AC-3: Marketing → ✗ modeling everywhere
- [ ] AC-4: DELETE built-in ObjectType (Product) → 409
- [ ] AC-5: New ObjectType created → audit log entry per role która auto-granted permissions
- [ ] AC-6: User w roli z auto_grant=false → must manually assign permissions po new ObjectType

**Files affected:** `src/Identity/Voter/ObjectTypeVoter.php`, `src/Identity/Voter/AttributeVoter.php`, `src/Identity/Voter/AttributeGroupVoter.php`, `src/Identity/EventListener/OnObjectTypeCreatedListener.php`

**Testing requirements:** Unit + integration auto-grant flow test.

**DoD:** Standard + AC + auto-grant test.

---

## RBAC-P3-005: feat(identity): UserVoter + RoleVoter

**Typ:** `feat` | **Phase:** 3 | **Estymacja:** **5-7h**

**Dependencies:** Blocked by RBAC-P3-002.

**Risk flags:**
- Self-modification block — user nie może edytować swojej roli (privilege escalation). Hard-coded w UserVoter.
- Last admin protection — UserVoter sprawdza czy delete/deactivate ostatniego usera z `manage_users` → 409.
- Owner uniqueness — przypisanie roli Owner gdy `is_unique=true` i już istnieje → 409.

**Cel:** Voters dla User i Role management — Settings → Users + Settings → Roles.

**Scope:**
- `UserVoter` — actions `view`, `invite`, `edit`, `deactivate`, `reactivate`, `change_roles`
- `RoleVoter` — actions `view`, `add`, `edit`, `delete`
- Self-modification check — `edit` z `subject.id === current_user.id` na role-changing fields → reject (user może edit profile, NIE role)
- Last admin protection — `deactivate` lub `change_roles` ostatniego usera z `manage_users` → reject z explanation
- Owner uniqueness — `change_roles` przypisanie `tenant_owner` (is_unique=true) gdy już istnieje user z tą rolą → reject z message *„Transfer ownership first"*
- System role protection — `delete` na role z `is_system=true` → reject

**Acceptance criteria:**
- [ ] AC-1: Admin może invite/edit/deactivate users w tenant
- [ ] AC-2: User próbuje edytować swoją własną rolę → 403 z self_modification message
- [ ] AC-3: Próba deactivation ostatniego admina → 409 z last_admin_protection
- [ ] AC-4: Próba assignment Tenant Owner gdy już istnieje → 409 z owner_uniqueness
- [ ] AC-5: Próba delete `is_system=true` role → 409 z system_role_protection
- [ ] AC-6: Catalog Manager bez `manage_users` próba invite → 403
- [ ] AC-7: Audit log dla każdej akcji z resource_type, resource_id

**Files affected:** `src/Identity/Voter/UserVoter.php`, `src/Identity/Voter/RoleVoter.php`

**Testing requirements:** Unit + integration last-admin protection + Owner uniqueness tests.

**DoD:** Standard + AC + dedicated last-admin protection test pass.

---

## RBAC-P3-006: feat(identity): ApiTokenVoter + IntegrationVoter

**Typ:** `feat` | **Phase:** 3 | **Estymacja:** **4-6h**

**Dependencies:** Blocked by RBAC-P3-002.

**Risk flags:**
- API token Voter sprawdza ownership — user może edit/revoke tylko swoich tokenów (z wyjątkiem `manage_api_tokens_all`).
- Integration secrets — IntegrationVoter sprawdza separate permission dla `integration_secrets.read` (osobno od `manage_integrations`).

**Cel:** Voters dla API Tokens + Integrations management.

**Scope:**
- `ApiTokenVoter` — actions `view`, `create`, `revoke`, `view_all`, `revoke_all`
- `IntegrationVoter` — actions `view`, `manage_config`, `read_secrets`, `manage_webhooks`
- Ownership: `view`/`revoke` allowed gdy `token.user_id === current_user.id`, lub user ma `manage_api_tokens_all`
- Read secrets separate permission — even Owner musi mieć `integration_secrets.read` żeby zobaczyć Shopify access_token
- Last-used IP filter — Voter nie filtruje, ale Serializer w RBAC-P3-012 redacts `last_used_ip` dla users bez `view_all`

**Acceptance criteria:**
- [ ] AC-1: User can view/revoke own tokens
- [ ] AC-2: User bez `manage_api_tokens_all` próba view innego user'a token → 403
- [ ] AC-3: Integration Manager z `manage_api_tokens_all` może view all tokens
- [ ] AC-4: User z `manage_integrations` ale bez `integration_secrets.read` → widzi Shopify config, NIE access_token
- [ ] AC-5: User z `integration_secrets.read` → widzi access_token w response (po manage_integrations grant)

**Files affected:** `src/Identity/Voter/ApiTokenVoter.php`, `src/Identity/Voter/IntegrationVoter.php`

**Testing requirements:** Unit + integration ownership tests.

**DoD:** Standard + AC.

---

## RBAC-P3-007: feat(identity): AuditLogVoter — own vs cross-user

**Typ:** `feat` | **Phase:** 3 | **Estymacja:** **3-5h**

**Dependencies:** Blocked by RBAC-P3-002.

**Risk flags:**
- Cross-user audit dostęp tylko dla Owner/Admin/Approver/Viewer (per macierz 3.2). Critical privacy.
- Super Admin platform-level audit (cross-tenant) — separate permission `platform.audit.view_all`, nie tenant-scoped.

**Cel:** Voter dla audit log — distinguish między *„own actions"* vs *„cross-user"* visibility.

**Scope:**
- `AuditLogVoter` — actions `view_own`, `view_cross_user`, `view_platform_cross_tenant`
- `view_own` — allowed dla wszystkich authenticated users (filter `WHERE user_id = current_user`)
- `view_cross_user` — allowed dla ról z permission `audit.view_cross_user` (filter `WHERE tenant_id = current_tenant`)
- `view_platform_cross_tenant` — allowed tylko dla Super Admin (no tenant filter)

**Acceptance criteria:**
- [ ] AC-1: Magda widzi tylko swoje audit entries (Catalog Manager bez cross-user permission)
- [ ] AC-2: Owner widzi wszystkie entries w tenant (cross-user)
- [ ] AC-3: Approver widzi wszystkie entries (per macierz 3.2)
- [ ] AC-4: Super Admin widzi entries z wszystkich tenants
- [ ] AC-5: Endpoint `/api/audit-logs` filtruje response zgodnie z permission

**Files affected:** `src/Identity/Voter/AuditLogVoter.php`, `src/Identity/Service/AuditLogQueryBuilder.php`

**Testing requirements:** Unit + integration cross-user filtering.

**DoD:** Standard + AC.

---

## RBAC-P3-008: feat(identity): resource policy — 3-state attribute permissions enforcement (resolution attribute → group → role default)

**Typ:** `feat` | **Phase:** 3 | **Estymacja:** **8-12h** (zwiększone z 5-7h po PRD v2.1 update §3.5)

**Dependencies:** Blocked by RBAC-P3-002 (ProductVoter delegates to this), RBAC-P1-005 (schema z 2 new tables).

**Risk flags:**
- Per-attribute check happens **per request body field** — performance impact. Resolution order **attribute → group → role default** wymaga 1-2 DB queries per check (cached agresywnie).
- Edit attempt na restricted/view field → 403 + detailed error z field name + reason (`attribute_restricted`, `view_only`, `requires_broad_permission`).
- **Resolution priority CRITICAL** — per-attribute override per-group override role default. Błąd kolejności = security hole (klient ustawia per-attribute Edit na price, group default View → finalnie price musi być Edit).

**Cel:** `AttributePermissionPolicy` — sprawdza 3-state permission (`restricted`/`view`/`edit`) per (rola × atrybut) z resolution order. Wywoływane z ProductVoter / AttributeVoter dla PATCH + GET requests.

**Scope:**
- `AttributePermissionPolicy::resolvePermission(User $user, Attribute $attribute): string` — zwraca `'restricted'` / `'view'` / `'edit'`
- Resolution order (per PRD §3.5):
  1. Broad gate check — czy rola ma `products.view` (lub edit) w macierzy? Jeśli NIE → return `'restricted'` (per-attribute grants nieaktywne)
  2. Per-attribute override — `role_attribute_permissions(role_id, attribute_id)` query. Found → return permission
  3. Per-group override — `role_attribute_group_permissions(role_id, attribute.group_id)` query. Found → return permission
  4. Role default — `roles.default_attribute_permission`. Return
- `AttributePermissionPolicy::canEditField()` / `canViewField()` — convenience wrappers
- Cached per (user_id, attribute_id) tuple — TTL 5min, invalidated on:
  - `role_attribute_permissions` insert/update/delete (Doctrine listener)
  - `role_attribute_group_permissions` insert/update/delete
  - `roles.default_attribute_permission` change
  - `user_roles` change (user gets/loses role)
- Plus `integration_visible` check — Integration Manager z `integration_visible=false` atrybutem → forced `'restricted'` (overrides per-rola permissions)
- Integration z PATCH request validator — iterate body fields, reject if any not `'edit'` (atomic transaction)
- Detailed error responses z `field`, `reason`, `current_permission`, `required_permission`

**Acceptance criteria:**
- [ ] AC-1: Marketing PATCH `/products/{id}` z `{values: {price: 999}}` (Marketing role z `role_attribute_permissions(marketing, price, 'view')`) → 403 z `field: "price", reason: "view_only", current_permission: "view"`
- [ ] AC-2: Marketing PATCH same endpoint z `{values: {description: "..."}}` (Marketing default `'edit'`, no per-attribute override) → 200
- [ ] AC-3: Marketing PATCH mixed `{values: {description: "...", price: 999}}` → 403, **żadne pole nie zostaje updated** (atomic transaction)
- [ ] AC-4: Accountant (role z `default_attribute_permission='restricted'` + `role_attribute_group_permissions(accountant, pricing_group, 'edit')`) PATCH price → 200, PATCH description → 403 z `reason: "attribute_restricted"`
- [ ] AC-5: Owner PATCH z any field → 200 (Owner default `'edit'`, brak overrides)
- [ ] AC-6: Resolution priority test — per-attribute Edit + group default View + role default Restricted → per-attribute wins (Edit allowed)
- [ ] AC-7: Resolution priority test — brak per-attribute, group Edit + role default View → group wins (Edit allowed)
- [ ] AC-8: Cross-cutting macierz — Marketing bez `products.view` w macierzy + `role_attribute_permissions(marketing, price, 'edit')` → effective permission `'restricted'` (broad gate first)
- [ ] AC-9: Integration Manager GET `/products/{id}` → response NIE zawiera `cost_price` (integration_visible=false override, even gdy per-attribute permission 'edit')
- [ ] AC-10: Performance: 100 PATCH requests <2s total (cached restrictions)
- [ ] AC-11: Cache invalidation — after `role_attribute_permissions` insert → next request returns fresh permission (test z manual cache miss)

**Files affected:**
- `src/Identity/Policy/AttributePermissionPolicy.php` (new — replaces v1 AttributePermissionPolicy)
- `src/Identity/Voter/ProductVoter.php` (extension — delegate do nowej policy)
- `src/Identity/Voter/AttributeVoter.php` (extension)
- `src/Identity/EventListener/AttributePermissionCacheInvalidationListener.php` (new — Doctrine listener)

**Testing requirements:**
- Unit: resolution priority test (8+ scenarios per priority combination)
- Unit: integration_visible override test
- Unit: broad gate first test
- Integration: full PATCH flow z mixed fields atomic rejection
- Integration: cache invalidation flow (insert permission → next query fresh)
- Performance: 100 PATCH benchmark

**DoD:** Standard + AC + **resolution priority test suite pass** (8+ scenarios) + atomic transaction test.

---

## RBAC-P3-009: feat(identity): resource policy — per-locale + per-channel scope enforcement

**Typ:** `feat` | **Phase:** 3 | **Estymacja:** **6-8h**

**Dependencies:** Blocked by RBAC-P3-008 (shared policy infrastructure).

**Risk flags:**
- Locale/channel scope check happens per field — atrybut może być scopable per channel (description.shopify, description.baselinker). Each variant checked separately.
- Wildcard `["*"]` — default, no restriction. Performance optimization — early return.

**Cel:** `LocaleChannelScopePolicy` — sprawdza czy user może edit konkretny locale/channel variant atrybutu. Zgodnie z `user_roles.locale_scope` + `channel_scope`.

**Scope:**
- `LocaleChannelScopePolicy::canEditValue(User $user, Product $product, Attribute $attribute, string $locale, string $channel): bool`
- Logic:
  - Resolve user.roles → aggregate locale_scope z wszystkich ról (union)
  - If `["*"]` in scope → allow all
  - Else check `locale` in scope
  - Analogicznie dla channel_scope
- Integration z PATCH request body parsing — body `values: {description: {pl: "...", en: "..."}}` iterate per locale, check scope
- Per-channel: body `values: {description.shopify: "...", description.baselinker: "..."}` iterate per channel, check scope
- Error response: 403 z details `field: "description.en", reason: "locale_scope_violation"`

**Acceptance criteria:**
- [ ] AC-1: Magda z `locale_scope=["en"]` PATCH `{values: {description: {pl: "X"}}}` → 403 (pl nie w scope)
- [ ] AC-2: Magda z `locale_scope=["en"]` PATCH `{values: {description: {en: "X"}}}` → 200
- [ ] AC-3: User z `locale_scope=["*"]` → wszystkie locale allowed
- [ ] AC-4: Channel Manager z `channel_scope=["allegro"]` PATCH `{values: {description.shopify: "X"}}` → 403
- [ ] AC-5: Mixed locales (allowed + restricted) → 403, atomic rejection
- [ ] AC-6: Performance: locale scope check <1ms (cached)

**Files affected:** `src/Identity/Policy/LocaleChannelScopePolicy.php`, `src/Identity/Voter/ProductVoter.php` (extension)

**Testing requirements:** Unit + integration scope tests per locale/channel combination.

**DoD:** Standard + AC.

---

## RBAC-P3-010: feat(identity): resource policy — ownership check (own vs all)

**Typ:** `feat` | **Phase:** 3 | **Estymacja:** **3-5h**

**Dependencies:** Blocked by RBAC-P3-002.

**Risk flags:**
- Ownership semantyka tylko dla **operational resources** (exports, imports, multimedia uploads, api_tokens, audit logs). NIE dla domain resources (products, categories).
- API endpoint `?scope=own|all` parameter — backend decyduje na podstawie permissions.

**Cel:** `OwnershipPolicy` — sprawdza czy user może access *„own"* vs *„all"* na operational resources.

**Scope:**
- `OwnershipPolicy::canViewAll(User $user, string $resourceType): bool` — sprawdza permission `{resource}.view_all`
- `OwnershipPolicy::canViewOwn(User $user, string $resourceType): bool` — sprawdza permission `{resource}.view_own`
- API endpoint helper — `?scope=` query param interpreted by Voter:
  - `?scope=all` + user has view_all → filter `WHERE tenant_id = current_tenant`
  - `?scope=all` + user has tylko view_own → 403
  - `?scope=own` lub brak param + user has view_own → filter `WHERE tenant_id = ? AND created_by = current_user`
  - User ma both → default `?scope=own` (broader scope musi być explicit)
- Resource owner attribution: `created_by`, `uploaded_by`, `user_id` (varies per resource type, table mapping)

**Acceptance criteria:**
- [ ] AC-1: Magda GET /api/exports/sessions → tylko jej exports (own scope default)
- [ ] AC-2: Magda GET /api/exports/sessions?scope=all → 403 (brak view_all)
- [ ] AC-3: Approver GET /api/exports/sessions?scope=all → wszyscy users w tenant
- [ ] AC-4: Approver GET /api/exports/sessions (bez param) → tylko swoje (default own)
- [ ] AC-5: Cross-tenant attempt → still blocked by TenantFilter (defence in depth)

**Files affected:** `src/Identity/Policy/OwnershipPolicy.php`, `src/Identity/Voter/*` (multiple extensions)

**Testing requirements:** Unit + integration scope filtering per resource type.

**DoD:** Standard + AC.

---

## RBAC-P3-011: feat(identity): resource policy — workflow-state policy (Symfony Workflow integration)

**Typ:** `feat` | **Phase:** 3 | **Estymacja:** **6-8h**

**Dependencies:** Blocked by RBAC-P3-002, existing Symfony Workflow setup (z epiku 06 jeśli istnieje, inaczej basic implementation).

**Risk flags:**
- Workflow state w `objects` JSONB — sprawdzenie wymaga read entity przed update.
- Auto-transition `published → draft` przy edit — atomic operation, must rollback if edit fails.
- Per-role workflow access table (per PRD §3.8) — Marketing edit tylko w draft, Catalog Manager edit w draft+review.

**Cel:** `WorkflowStatePolicy` — sprawdza czy user może edit entity w aktualnym workflow state. Plus auto-transition support dla destructive edits.

**Scope:**
- `WorkflowStatePolicy::canEditInState(User $user, Entity $entity): bool`
- State table per role (per PRD §3.8):
  - Owner/Admin: edit w każdym state
  - Catalog Manager: draft, review
  - Marketing: draft only
  - Approver: review only
  - Inne: ✗
- Auto-transition logic — jeśli user has `workflow.transition.unpublish` permission i state=`published`, PATCH request:
  - Option A: explicit `?auto_unpublish=true` → published → draft + edit atomically + audit_log special_flag `AUTO_UNPUBLISH_FOR_EDIT`
  - Option B: bez param → 409 Conflict z message *„Product published, unpublish first"*
- Symfony Workflow integration (jeśli existing infrastructure) — extends transition guards

**Acceptance criteria:**
- [ ] AC-1: Marketing PATCH `published` product → 409 *„Unpublish first"*
- [ ] AC-2: Catalog Manager PATCH `published` product (bez auto_unpublish) → 409
- [ ] AC-3: Catalog Manager PATCH `published` product z `?auto_unpublish=true` → 200 + state changed to draft + audit log entry
- [ ] AC-4: Owner PATCH `published` product → 200 (Owner bypass)
- [ ] AC-5: Marketing PATCH `draft` product → 200
- [ ] AC-6: Marketing PATCH `review` product → 403 *„No edit in review state for Marketing"*

**Files affected:** `src/Identity/Policy/WorkflowStatePolicy.php`, `src/Identity/Voter/ProductVoter.php` (extension), `src/Workflow/Configuration/ProductWorkflow.php` (if existing)

**Testing requirements:** Unit + integration per state-role combination + auto-transition atomic test.

**DoD:** Standard + AC + auto-transition rollback test.

---

## RBAC-P3-012: feat(identity): field-level serializer filtering (dynamic SerializerGroups)

**Typ:** `feat` | **Phase:** 3 | **Estymacja:** **6-8h**

**Dependencies:** Blocked by RBAC-P3-008 (uses attribute restrictions logic).

**Risk flags:**
- **CRITICAL: Integration secrets leakage** — `access_token`, `webhook_secret`, etc. must be scrubbed from responses. Single missed field = breach.
- Serializer groups must be **dynamic** — based on user permissions at runtime, not hardcoded annotations.
- Per-attribute restrictions enforcement — Marketing GET /products → response NIE zawiera `cost_price`, `price` jeśli restricted.

**Cel:** Symfony Serializer extension który dynamically excludes fields based on user permissions. Integration tokens, sensitive fields, **3-state attribute permissions** (restricted → hide, view → include z `editable: false` flag, edit → include z `editable: true`). Wszystko scrubbed.

**Scope:**
- `DynamicGroupsContextBuilder` — Symfony Serializer context builder który adds groups dynamicznie based on user:
  - User has `integration_secrets.read` → add group `integration:secrets`
  - User has `audit.view_cross_user` → add group `audit:cross_user`
  - Else → no group added → fields with `#[Groups("integration:secrets")]` excluded
- Per-attribute 3-state permissions w product/object response (per PRD §3.5):
  - Iterate attributes per response
  - Call `AttributePermissionPolicy::resolvePermission(user, attribute)` per atrybut
  - `'restricted'` → field NIE w response (full removal)
  - `'view'` → field w response z metadata `{value, editable: false, reason: "view_only"}` (frontend renderuje jako tekst — RBAC-P4-009)
  - `'edit'` → field w response z `{value, editable: true}` (frontend renderuje jako input)
- Response shape extension dla produktu (przykład):
  ```json
  {
    "id": "...",
    "sku": "...",
    "values": {
      "name": { "value": "Czujnik", "editable": true },
      "price": { "value": 1234.56, "editable": false, "reason": "view_only" },
      // "cost_price" NIE w response (restricted dla Marketing)
    }
  }
  ```
- Sensitive fields zawsze excluded:
  - `users.password_hash`
  - `users.mfa_secret` (always)
  - `api_tokens.token_hash` (always, return only `last_4_chars`)
  - `integrations.access_token` (groups-based)
  - `audit_logs.ip_address`, `user_agent` (only for cross-user audit viewers)
- Test fixtures — sample product z różnymi rolami testowymi → response shape verification

**Acceptance criteria:**
- [ ] AC-1: GET /me response NIE zawiera password_hash, mfa_secret (zawsze)
- [ ] AC-2: Marketing GET /products/{id} z `role_attribute_permissions(marketing, cost_price, 'restricted')` → response NIE zawiera `cost_price` (field removed)
- [ ] AC-3: Marketing GET /products/{id} z `role_attribute_permissions(marketing, price, 'view')` → response zawiera `price` z `{value: 1234.56, editable: false, reason: "view_only"}`
- [ ] AC-4: Catalog Manager GET same product → response zawiera wszystkie atrybuty z `editable: true` (Catalog Mgr default `'edit'`)
- [ ] AC-5: Integration Manager GET /integrations/shopify → response zawiera `access_token`
- [ ] AC-6: Marketing GET /integrations/shopify → 403 (brak permission overall)
- [ ] AC-7: Catalog Manager GET /integrations/shopify → response NIE zawiera `access_token` (brak integration_secrets.read)
- [ ] AC-8: GET /api/api-tokens → response zawiera `last_4_chars` + `name`, NIE `token_hash`
- [ ] AC-9: Integration Manager GET produktu — atrybuty z `integration_visible=false` (np. cost_price) NIE w response (independent layer od per-rola permissions)
- [ ] AC-10: Performance — 100 GET produktów <2s total (response transformation cached)

**Files affected:** `src/Identity/Serializer/DynamicGroupsContextBuilder.php`, `src/Identity/Serializer/FieldRestrictionNormalizer.php`, `src/**/Entity/*.php` (add `#[Groups]` annotations)

**Testing requirements:**
- **Field-level scrubbing suite (Layer 4)** — dedicated tests per (field × role) combination
- Property-based fuzzing — 30+ sensitive fields × 10 ról = 300 scenarios
- Integration: real endpoints z each role test response shape

**DoD:** Standard + AC + **dedicated field scrubbing suite pass (Layer 4 z 07-plan)**.

---

## RBAC-P3-013: feat(identity): audit log listener — permission_check_result + special_flags

**Typ:** `feat` | **Phase:** 3 | **Estymacja:** **4-6h**

**Dependencies:** Blocked by RBAC-P3-001 (uses listener infrastructure).

**Risk flags:**
- Audit log volume — every API request logged = high write throughput. Async via Symfony Messenger.
- Sensitive data w audit `old_value` / `new_value` JSONB — same scrubbing as serializer (Phase 3 RBAC-P3-012 patterns).

**Cel:** Audit log listener który zapisuje per-request: user_id, action, resource, permission_check_result, special_flags. Asynchronous via Messenger.

**Scope:**
- `AuditLogListener` subscribes do `kernel.response` event
- Per request: build audit entry z:
  - `user_id` lub `super_admin_id` z Security context
  - `tenant_id` z TenantContext (nullable dla cross-tenant Super Admin)
  - `action` z controller/route name
  - `resource_type`, `resource_id` z route params lub response body
  - `permission_check_result`: `granted` (success 200/201/204), `denied` (403), `n_a` (public endpoints)
  - `cross_tenant_access`: true gdy Super Admin
  - `special_flags`: array, np. `["AUTO_UNPUBLISH_FOR_EDIT"]`, `["SUPER_ADMIN_RECOVERY"]`, `["LONG_LIVED_TOKEN_CREATED"]`
  - `old_value`, `new_value` JSONB diff (Doctrine listener integration)
  - `timestamp`, `ip_address`, `user_agent`
- Async via Symfony Messenger — `AuditLogMessage` handler writes to DB asynchronously
- Field-level scrubbing — sensitive fields in old_value/new_value NIE są logged

**Acceptance criteria:**
- [ ] AC-1: Every API request creates audit log entry
- [ ] AC-2: 403 response → `permission_check_result=denied`
- [ ] AC-3: Public endpoints (login, /me) → `permission_check_result=n_a` lub skipped (config decision)
- [ ] AC-4: Super Admin access tenant data → `cross_tenant_access=true`
- [ ] AC-5: Auto-unpublish edit → audit entry z `special_flags=["AUTO_UNPUBLISH_FOR_EDIT"]`
- [ ] AC-6: old_value/new_value diff scrubbed z sensitive fields (test: PATCH user.password_hash → audit nie zawiera plaintext)
- [ ] AC-7: Async write — performance test: 1000 requests, audit entries written async, response time nie affected
- [ ] AC-8: Audit entries queryable po user_id, resource_type, time range

**Files affected:** `src/Audit/EventListener/AuditLogListener.php`, `src/Audit/Message/AuditLogMessage.php`, `src/Audit/MessageHandler/AuditLogMessageHandler.php`, `src/Audit/Service/AuditLogScrubber.php`

**Testing requirements:**
- Integration: full request → audit entry
- Async test: response time z + bez listener
- Scrubbing test: sensitive fields NIE w entries

**DoD:** Standard + AC + async benchmark.

---

## RBAC-P3-014: feat(identity): Super Admin cross-tenant bypass + Break-glass CLI

**Typ:** `feat` | **Phase:** 3 | **Estymacja:** **5-7h**

**Dependencies:** Blocked by RBAC-P3-013.

**Risk flags:**
- Super Admin bypass jest **najpotężniejszym** tool — błąd implementation = total compromise. Heavy audit + MFA required.
- Break-glass CLI musi być rate-limited — max 5 actions/day per Super Admin.
- Privacy boundary — Super Admin widzi metadata tenants, NIE dane domenowe (Products, Attributes z values).

**Cel:** Super Admin cross-tenant bypass mode + CLI command `cortex:rescue-admin` dla emergency recovery.

**Scope:**
- `SuperAdminContext::useCrossTenantMode(): void` — disables TenantFilter + Postgres RLS bypass policy + audit log entry z `cross_tenant_access=true`
- Endpoint enforcement — only `/api/admin/*` (admin subdomain) allows Super Admin operations
- Privacy boundary — `/api/admin/tenants/{id}` zwraca metadata (name, plan, user_count, storage), NIE products/attributes/values
- CLI command `cortex:rescue-admin {email}`:
  - Argumenty: target user email, target tenant slug
  - Effect: assignuje rolę `tenant_owner` do user'a (skipping permission stack)
  - Audit log: `special_flag=SUPER_ADMIN_RECOVERY` + reasoning text prompt
  - MFA verification — Super Admin musi enter TOTP code w CLI (interactive prompt)
  - Rate limit — 5 invocations/24h/super_admin
- Audit entries `cross_tenant_access=true` + `super_admin_id` set
- Dedicated Super Admin login flow — separate JWT secret + endpoint `/admin/login`

**Acceptance criteria:**
- [ ] AC-1: Super Admin login z `/admin/login` → JWT z super_admin scope
- [ ] AC-2: Super Admin GET `/api/admin/tenants` → lista tenants z metadata
- [ ] AC-3: Super Admin GET `/api/admin/tenants/{id}/products` → 403 (privacy boundary, brak dostępu do domain data)
- [ ] AC-4: Super Admin GET `/api/admin/audit-logs` → cross-tenant audit log
- [ ] AC-5: CLI `cortex:rescue-admin user@email.com tenant-slug` → user gets `tenant_owner` role + audit entry
- [ ] AC-6: CLI bez MFA TOTP input → declined
- [ ] AC-7: 6th rescue invocation w 24h → declined z rate limit
- [ ] AC-8: All cross-tenant ops logged z `cross_tenant_access=true`

**Files affected:** `src/Identity/Service/SuperAdminContext.php`, `src/Identity/Controller/AdminController.php`, `src/Identity/Command/RescueAdminCommand.php`, `config/packages/security.yaml` (admin firewall)

**Testing requirements:**
- Integration: full Super Admin flow
- Security: privacy boundary test (GET /admin/tenants/{id}/products → 403)
- CLI test: rate limit, MFA enforcement

**DoD:** Standard + AC + manual smoke z CLI z real MFA TOTP.

---

## Phase 3 zakończony — deliverables

Po merge wszystkich 14 ticketów:
- ✅ `#[RequiresPermission]` attribute + endpoint guard
- ✅ 9 Voters (Product, Category, Asset, ObjectType, Attribute, AttributeGroup, User, Role, ApiToken, Integration, AuditLog)
- ✅ Resource policies (per-attribute, per-locale/channel, ownership, workflow-state)
- ✅ Field-level serializer filtering (CRITICAL — integration secrets, password hash, mfa_secret scrubbed)
- ✅ Audit log z permission_check_result + special_flags + async write
- ✅ Super Admin cross-tenant bypass + Break-glass CLI

**Phase 3 → Phase 4 transition:** backend chroni endpointy + filtuje response. Phase 4 dodaje frontend integration (Refine route guards, PermissionGate, HTTP interceptor).

**Estymacja Phase 3: ~70-90h. 14 ticketów. Realne tempo: 2.5-3 tygodnie.**
