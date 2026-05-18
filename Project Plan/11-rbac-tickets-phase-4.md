# RBAC — tickety Phase 4 (Frontend core)

**Typ dokumentu:** Backlog ticketów Phase 4 RBAC — ready-to-paste GitHub Issues
**Status:** Draft — gotowe do utworzenia po zakończeniu Phase 3
**Powiązane:** [`07-rbac-implementation-plan.md`](07-rbac-implementation-plan.md) §4.5, [`PRD/PRD-PIM-rbac.md`](PRD/PRD-PIM-rbac.md) §5

> **Cel Phase 4:** Frontend integration z backend RBAC — session bootstrap, route guards, component-level permission checks, HTTP interceptor, field-level form rendering, Mercure SSE invalidation, Cmd+K palette filtering, MFA UI, password reset UI.
>
> **Harmonogram:** tygodnie 9-10, **~70-90h**. 13 ticketów.

---

## Graf zależności Phase 4

```
RBAC-P4-001 (Session bootstrap) ─── foundation
        │
        ├── RBAC-P4-002 (httpOnly cookie + token refresh)
        ├── RBAC-P4-003 (Refine route guards + 403 page)
        └── RBAC-P4-004 (<PermissionGate> + useCanI hook)
                    │
                    ├── RBAC-P4-005 (Sidebar nav filtering)
                    ├── RBAC-P4-006 (Tenant-switch dropdown)
                    ├── RBAC-P4-007 (HTTP interceptor 403)
                    ├── RBAC-P4-008 (HTTP interceptor 401 → logout)
                    ├── RBAC-P4-009 (Field-level form rendering)
                    ├── RBAC-P4-010 (Mercure SSE permission invalidation)
                    ├── RBAC-P4-011 (Cmd+K palette filtering)
                    ├── RBAC-P4-012 (MFA setup wizard UI)
                    └── RBAC-P4-013 (Password reset UI)
```

---

## RBAC-P4-001: feat(admin): session bootstrap — login form + GET /api/me + store

**Typ:** `feat` | **Phase:** 4 | **Estymacja:** **6-8h**

**Dependencies:** Blocked by Phase 3 complete (zwłaszcza RBAC-P2-007 /api/me).

**Risk flags:**
- Token w `localStorage` = XSS exploit. MUST be httpOnly cookie (RBAC-P4-002).
- Permission set może być duży (~50 codes + restrictions object) — store layout efficient.
- Auto-refresh permissions po Mercure event (RBAC-P4-010) — store mutation atomic.

**Cel:** Login page + bootstrap flow. Po successful login → GET /api/me → store layout (Zustand/Redux) → redirect do dashboard. Frontend zna user, role, permissions, restrictions.

**Scope:**
- Login form `/login` route — email + password fields + MFA challenge step
- POST `/api/auth/login` → success: store login_session_token, jeśli `requires_mfa` → MFA challenge UI
- MFA challenge UI — 6-digit code input + recovery code option
- Po success JWT: GET `/api/me` → response do store
- Store layout (Zustand):
  ```ts
  interface IdentityStore {
    user: User | null;
    tenant: Tenant | null;
    memberships: Membership[];
    roles: Role[];
    permissions: Set<string>;
    attributeRestrictions: Record<string, { canView: boolean; canEdit: boolean; reason?: string }>;
    localeScope: string[];
    channelScope: string[];
    features: Features;

    setIdentity(data: MeResponse): void;
    clearIdentity(): void;
    hasPermission(code: string): boolean;
    canEditAttribute(code: string): boolean;
  }
  ```
- Auto-redirect — jeśli zalogowany (cookie istnieje) i odwiedza `/login` → redirect do `/`

**Acceptance criteria:**
- [ ] AC-1: Login form pokazuje email + password fields
- [ ] AC-2: Login success bez MFA → direct redirect do dashboard
- [ ] AC-3: Login success z `requires_mfa: true` → MFA challenge UI
- [ ] AC-4: MFA challenge z valid code → JWT issued, /me fetched, redirect
- [ ] AC-5: Store contains permissions Set z O(1) lookup `hasPermission(code)`
- [ ] AC-6: Bad credentials → error message z generic *„Invalid credentials"*
- [ ] AC-7: Already logged user visiting `/login` → redirect do `/`
- [ ] AC-8: Store hydration <100ms po /me response

**Files affected:** `apps/admin/src/routes/login.tsx`, `apps/admin/src/stores/identity.ts`, `apps/admin/src/services/auth.ts`, `apps/admin/src/components/MfaChallenge.tsx`

**Testing requirements:**
- Unit (Vitest): IdentityStore reducer tests
- E2E (Playwright): full login flow z MFA + bez MFA
- E2E: bad credentials handling

**DoD:** Standard + AC + Playwright login flow.

---

## RBAC-P4-002: feat(admin): httpOnly secure cookie + JWT token refresh

**Typ:** `feat` | **Phase:** 4 | **Estymacja:** **4-6h**

**Dependencies:** Blocked by RBAC-P4-001.

**Risk flags:**
- httpOnly cookie wymaga `SameSite=Strict` lub `Lax` (CSRF protection).
- Token refresh race condition — jeśli 2 requests równocześnie z expired token, oba próbują refresh → conflict. Mutex/queue refresh.
- Cookie domain config — `pim.cortex.pl` (cookie-domain), wsparcie subdomain (`admin.cortex.pl` dla Super Admin).

**Cel:** JWT token storage w httpOnly secure cookie zamiast localStorage. Auto-refresh access token (15min TTL) używając refresh token (7d TTL).

**Scope:**
- Backend: login response set httpOnly cookie z access_token + refresh_token
  - `Set-Cookie: cortex_access=eyJ...; HttpOnly; Secure; SameSite=Strict; Path=/; Max-Age=900`
  - `Set-Cookie: cortex_refresh=eyJ...; HttpOnly; Secure; SameSite=Strict; Path=/api/auth/refresh; Max-Age=604800`
- Frontend: NIE manipuluje cookies (httpOnly = invisible JS). Cookie sent automatically z każdym request.
- Refresh strategy — proactive refresh przy <2min do exp (decoded JWT exp claim available w response header lub /me)
- Refresh endpoint POST `/api/auth/refresh` — uses refresh cookie, returns new access cookie + rotates refresh
- Mutex pattern — single refresh request even gdy 5 concurrent API calls (queue waiting promises)
- Logout flow — DELETE cookies przez backend response

**Acceptance criteria:**
- [ ] AC-1: Login success — cookies set z httpOnly, Secure, SameSite=Strict
- [ ] AC-2: Cookies NIE są visible w `document.cookie` (httpOnly verification)
- [ ] AC-3: API request sends cookie automatically (no Authorization header needed)
- [ ] AC-4: Access cookie expires po 15min → next request triggers refresh
- [ ] AC-5: 5 concurrent expired requests → 1 refresh + 5 retries (mutex test)
- [ ] AC-6: Refresh cookie rotation — old refresh invalid po use
- [ ] AC-7: Logout endpoint clears both cookies
- [ ] AC-8: CSRF protection — POST z origin != Cortex domain → blocked

**Files affected:** `apps/admin/src/services/auth.ts`, `apps/admin/src/lib/http-client.ts`, backend `src/Identity/Controller/AuthController.php` (cookie setup)

**Testing requirements:**
- Integration: cookie lifecycle test
- Concurrent refresh mutex test
- CSRF test

**DoD:** Standard + AC + CSRF smoke test.

---

## RBAC-P4-003: feat(admin): Refine route guards + 403 page

**Typ:** `feat` | **Phase:** 4 | **Estymacja:** **5-7h**

**Dependencies:** Blocked by RBAC-P4-001.

**Risk flags:**
- Refine accessControlProvider integration — Refine ma własny pattern dla per-resource access checks.
- Route metadata permission codes — coupling z backend macierz 3.2 (must match). Drift = unauthorized navigation.

**Cel:** Refine router middleware (accessControlProvider) sprawdza permission per route przed renderem. Brak permission → redirect do 403 page z context.

**Scope:**
- Refine `accessControlProvider`:
  ```ts
  const accessControlProvider = {
    can: async ({ resource, action }) => {
      const code = `${resource}.${action}`;
      return { can: identityStore.hasPermission(code) };
    },
  };
  ```
- Per-resource Refine config z `meta.canAccess`:
  ```ts
  { name: "products", list: "/products", meta: { canAccess: "products.view" } }
  ```
- Public routes whitelist — `/login`, `/accept-invitation`, `/reset-password`, `/403`, `/404` (skip guard)
- 403 page z context: *„Nie masz dostępu do {resource}. Skontaktuj się z administratorem."* + button *„Wróć do dashboardu"* + button *„Wyloguj się"*
- Smart redirect — po 403 jeśli user ma `products.view` ale nie `products.add`, redirect do `/products` (list view), nie ogólny dashboard

**Acceptance criteria:**
- [ ] AC-1: Marketing próba nawigacji do `/settings/users` → redirect do `/403`
- [ ] AC-2: Owner nawigacja do `/settings/users` → renders page
- [ ] AC-3: 403 page pokazuje context (jaka permission missing)
- [ ] AC-4: 403 page ma button *„Wróć"* — navigates do `/` lub poprzedniej accessible route
- [ ] AC-5: Public routes (`/login`, `/accept-invitation`) accessible bez auth
- [ ] AC-6: Bezpośrednie wpisanie URL adresu bez permission → redirect (test: type `/settings/billing` jako Marketing)

**Files affected:** `apps/admin/src/providers/accessControlProvider.ts`, `apps/admin/src/routes/403.tsx`, `apps/admin/src/App.tsx`

**Testing requirements:**
- E2E Playwright: per role test nawigacji do restricted routes
- E2E: 403 page z context message

**DoD:** Standard + AC + Playwright per-role flow test.

---

## RBAC-P4-004: feat(admin): <PermissionGate> component + useCanI() hook

**Typ:** `feat` | **Phase:** 4 | **Estymacja:** **3-5h**

**Dependencies:** Blocked by RBAC-P4-001.

**Risk flags:**
- Component vs hook usage — gdy używać `<PermissionGate>` vs `useCanI()`? Documentation crucial.
- Performance — `useCanI` called w wielu komponentach. Selector pattern (subscribe only to relevant store slice) zapobiega re-renders.

**Cel:** Reusable building blocks dla permission-based rendering. `<PermissionGate>` to wrapper, `useCanI()` to hook. Component decides hide/disable/render-as-text.

**Scope:**
- `<PermissionGate permission="products.edit" fallback={<Disabled />}>` — renders children gdy permission grants, else `fallback` (default null = hide)
- `<PermissionGate anyOf={["products.edit", "products.bulk_operations"]}>` — OR semantics
- `<PermissionGate allOf={["products.edit", "settings.users.manage"]}>` — AND semantics
- `useCanI("products", "edit"): boolean` — hook z store subscription (re-renders tylko gdy permission set changes)
- `useCanIEditAttribute(attributeCode: string): boolean` — specjalny hook dla attribute-level
- Documentation w `apps/admin/src/components/PermissionGate.md`:
  - Use `<PermissionGate>` dla simple show/hide
  - Use `useCanI()` gdy logic decision (disable, tooltip, alternative render)
  - Use `useCanIEditAttribute()` dla form fields

**Acceptance criteria:**
- [ ] AC-1: `<PermissionGate permission="X">` renders children gdy permission OK, hides (null) gdy brak
- [ ] AC-2: `fallback` prop renders zamiast children
- [ ] AC-3: `anyOf` / `allOf` props zachowują się semantically correctly
- [ ] AC-4: `useCanI()` zwraca boolean, re-renders tylko gdy relevant permission changes (selector optimization)
- [ ] AC-5: `useCanIEditAttribute("price")` używa `attributeRestrictions` ze store
- [ ] AC-6: Documentation w `.md` z 5+ usage examples

**Files affected:** `apps/admin/src/components/PermissionGate.tsx`, `apps/admin/src/hooks/useCanI.ts`, `apps/admin/src/hooks/useCanIEditAttribute.ts`

**Testing requirements:**
- Unit (Vitest): component snapshot tests + hook tests
- Performance: re-render benchmark

**DoD:** Standard + AC.

---

## RBAC-P4-005: feat(admin): layout-level visibility — Sidebar nav filtering

**Typ:** `feat` | **Phase:** 4 | **Estymacja:** **3-5h**

**Dependencies:** Blocked by RBAC-P4-004.

**Risk flags:**
- Sidebar items hidden (not rendered to DOM) — Gemini insight. Better security + performance than `display:none`.
- Items list MUST match macierz 3.2 — drift catches przez E2E test per role.

**Cel:** Sidebar nav renderuje tylko itemy do których user ma permission. Itemy NIE są w DOM jeśli brak permission (not hidden via CSS).

**Scope:**
- Refactor Sidebar component — iterate nav items, filter wg. `<PermissionGate>` per item
- Sidebar nav items z metadata:
  ```ts
  const navItems = [
    { label: "Produkty", href: "/products", permission: "products.view" },
    { label: "Modelowanie", href: "/modeling", permission: "modeling.view" },
    { label: "Ustawienia", href: "/settings", permission: "settings.users.manage", anyOf: ["settings.users.manage", "settings.roles.manage", "settings.tenant.manage"] },
    ...
  ];
  ```
- Settings sub-menu — gdy user ma żaden z `settings.*.manage` → sidebar item *„Ustawienia"* nie pokazuje wcale
- Notifications bell — visible only gdy user ma notifications enabled (`features.notifications_enabled`)
- Help button + Profile menu — always visible (no permission gate)

**Acceptance criteria:**
- [ ] AC-1: Marketing widzi w sidebar: Produkty, Kategorie, Multimedia, Publikacje, Eksporty (NIE Settings, NIE Modelowanie)
- [ ] AC-2: Modeler widzi: Modelowanie, Produkty (view), Kategorie (view), Audit (NIE Ustawienia)
- [ ] AC-3: Owner widzi wszystko including Settings + tenant config
- [ ] AC-4: Hidden items NIE są w DOM (verify: `document.querySelector` zwraca null)
- [ ] AC-5: Settings parent item hidden gdy żadne sub-permissions
- [ ] AC-6: Macierz 3.2 validation per role w E2E test

**Files affected:** `apps/admin/src/components/Sidebar.tsx`, `apps/admin/src/config/navItems.ts`

**Testing requirements:**
- Unit: per role render snapshot
- E2E: visit `/` as each persona, assert sidebar items count

**DoD:** Standard + AC.

---

## RBAC-P4-006: feat(admin): tenant-switch dropdown (multi-tenant membership)

**Typ:** `feat` | **Phase:** 4 | **Estymacja:** **5-7h**

**Dependencies:** Blocked by RBAC-P4-001.

**Risk flags:**
- Tenant switch = full re-bootstrap — clear store, fetch new /me, redirect. Half-state = stale permissions = security risk.
- Memberships może być pusta (user freshly invited do 1 tenant) — UI hides dropdown.

**Cel:** Top bar dropdown pozwala switch między tenants. Visible gdy `memberships.length >= 2`. Switch triggers full re-bootstrap.

**Scope:**
- Component `<TenantSwitcher>` w top bar
- Visibility: `if (memberships.length >= 2) show dropdown else show static tenant name`
- Dropdown items: lista memberships z tenant name + current role
- Switch flow:
  1. Click membership
  2. POST `/api/auth/switch-tenant` z `{tenant_id}` → response: new JWT cookie z target tenant
  3. Clear identity store
  4. Fetch GET `/api/me` z new context
  5. Redirect do `/` (dashboard) — current view może nie być accessible w new tenant
- Loading state during switch — overlay z spinner *„Przełączanie tenanta..."*
- Error handling — backend zwraca 403 jeśli user nie ma membership do target tenant

**Acceptance criteria:**
- [ ] AC-1: User z 1 membership → static tenant name (no dropdown)
- [ ] AC-2: User z 2+ memberships → dropdown z lista
- [ ] AC-3: Click switch → loading overlay → redirect do `/`
- [ ] AC-4: Store cleared during switch (NO stale data leak)
- [ ] AC-5: Network error during switch → toast error + stay on current tenant
- [ ] AC-6: Attempt switch do unauthorized tenant → 403 + error toast

**Files affected:** `apps/admin/src/components/TenantSwitcher.tsx`, `apps/admin/src/services/auth.ts` (switch endpoint), backend `src/Identity/Controller/AuthController.php` (switch endpoint)

**Testing requirements:**
- E2E: Marcin z 3 testowymi tenants switch flow
- Negative: unauthorized switch attempt

**DoD:** Standard + AC + E2E flow.

---

## RBAC-P4-007: feat(admin): global HTTP interceptor — 403 handling + optimistic rollback

**Typ:** `feat` | **Phase:** 4 | **Estymacja:** **6-8h**

**Dependencies:** Blocked by RBAC-P4-004.

**Risk flags:**
- Optimistic UI rollback — gdy save fails 403, rewert state do oryginalnej wartości. State mutation tracking required.
- Multiple 403 in quick succession — debounce permission refresh (1 refresh per 5s).

**Cel:** Global HTTP interceptor (Axios/Fetch wrapper) handles 403 responses — toast error + rollback optimistic UI state + trigger /me refresh.

**Scope:**
- HTTP client interceptor (`apps/admin/src/lib/http-client.ts`):
  - Catches 403 response
  - Reads `permission_check_result` z response body (Problem Details `field` lub `permission_required`)
  - Calls callback w request config (`onPermissionDenied`) — caller może rollback state
  - Shows toast: *„Brak uprawnień: {detail}. Twoje uprawnienia mogły się zmienić."*
  - Triggers GET `/api/me` w background (debounce 5s) — refresh store
  - Audit log entry (frontend telemetry — optional)
- Request config extension — opcjonalny `onPermissionDenied: (error) => void` callback
- Optimistic UI pattern (example pattern dla developers):
  ```ts
  const prevValue = product.description;
  setProductDescription(newValue);  // optimistic
  try {
    await http.patch(`/api/products/${id}`, { description: newValue }, {
      onPermissionDenied: () => setProductDescription(prevValue),  // rollback
    });
  } catch {}
  ```
- Per-route smart redirect — jeśli current route blocked po permission change → redirect do `/403`

**Acceptance criteria:**
- [ ] AC-1: API 403 → toast error wyświetlony z message z backend response
- [ ] AC-2: 403 → GET /me triggered po 0-5s (debounced)
- [ ] AC-3: Updated store reflects new permissions (test: revoke permission backend, action → 403 + store reload)
- [ ] AC-4: Optimistic UI rollback — `onPermissionDenied` callback invoked
- [ ] AC-5: Multiple 403 w 5s → 1 refresh (debounce works)
- [ ] AC-6: Current route blocked po refresh → redirect do `/403`
- [ ] AC-7: 401 → diff handling (RBAC-P4-008)

**Files affected:** `apps/admin/src/lib/http-client.ts`, `apps/admin/src/lib/error-handler.ts`

**Testing requirements:**
- Unit: interceptor logic
- E2E: revoke permission scenario + UI rollback

**DoD:** Standard + AC + manual smoke (revoke role mid-session).

---

## RBAC-P4-008: feat(admin): global HTTP interceptor — 401 → logout flow

**Typ:** `feat` | **Phase:** 4 | **Estymacja:** **2-3h**

**Dependencies:** Blocked by RBAC-P4-007 (shared infrastructure).

**Risk flags:**
- 401 race — token expired w trakcie request. First try refresh (RBAC-P4-002), then logout jeśli refresh fails.
- Don't logout on 401 z `/api/auth/refresh` itself — infinite loop.

**Cel:** 401 response → attempt refresh, jeśli refresh fails → clear store + redirect do `/login`. Toast informuje *„Sesja wygasła"*.

**Scope:**
- Interceptor catches 401
- If endpoint == `/api/auth/refresh` → don't retry, immediately logout
- Else: attempt refresh via RBAC-P4-002 mutex pattern
- If refresh succeeds → retry original request
- If refresh fails → clear identity store + clear cookies (via logout endpoint) + redirect `/login?expired=true`
- Login page reads `?expired=true` query → shows toast *„Sesja wygasła. Zaloguj się ponownie."*

**Acceptance criteria:**
- [ ] AC-1: 401 z access cookie expired + valid refresh → silent refresh, retry, user nie zauważa
- [ ] AC-2: 401 z both cookies expired → logout flow
- [ ] AC-3: 401 z `/api/auth/refresh` → no retry, direct logout
- [ ] AC-4: After logout — store cleared, cookies cleared, redirect `/login?expired=true`
- [ ] AC-5: Login page reads query param → shows expired toast

**Files affected:** `apps/admin/src/lib/http-client.ts` (extension RBAC-P4-007), `apps/admin/src/routes/login.tsx` (toast logic)

**Testing requirements:** E2E session expiry scenario.

**DoD:** Standard + AC.

---

## RBAC-P4-009: feat(admin): field-level form rendering — text vs input vs hidden

**Typ:** `feat` | **Phase:** 4 | **Estymacja:** **6-8h**

**Dependencies:** Blocked by RBAC-P4-004.

**Risk flags:**
- Dynamic form generator must read `attributeRestrictions` z store dla każdego field.
- 3 render modes (input/text/hidden) — distinct UX. Documentation crucial.

**Cel:** Product detail form generator renderuje pola dynamicznie based on attribute restrictions:
- `can_view: true, can_edit: true` → normalny input
- `can_view: true, can_edit: false` → tekst (read-only display, NIE disabled input — Gemini insight)
- `can_view: false` → field nie renderowany w DOM

**Scope:**
- `<DynamicAttributeField>` component:
  ```tsx
  function DynamicAttributeField({ attribute, value, onChange }) {
    const restriction = useAttributeRestriction(attribute.code);
    
    if (restriction?.canView === false) return null;
    
    if (restriction?.canEdit === false) {
      return <ReadOnlyField label={attribute.label} value={value} />;
    }
    
    return <InputField label={attribute.label} value={value} onChange={onChange} />;
  }
  ```
- ReadOnlyField component — pretty text rendering (currency, date, list — typed)
- Hook `useAttributeRestriction(code)` — store subscription per attribute
- Product detail page refactor — używa `<DynamicAttributeField>` dla każdego pola
- Hint tooltip nad ReadOnlyField — *„Pole jest tylko do odczytu dla Twojej roli"*

**Acceptance criteria:**
- [ ] AC-1: Marketing otwiera produkt → pole `price` renderowane jako tekst *„1234.56 PLN"*, NIE input
- [ ] AC-2: Marketing → pole `cost_price` nie renderowane wcale (DOM verification)
- [ ] AC-3: Catalog Manager → wszystkie pola jako inputs
- [ ] AC-4: Hover ReadOnlyField → tooltip explanation
- [ ] AC-5: Type-specific ReadOnlyField rendering (date, currency, list)
- [ ] AC-6: Form submit z restricted field attempt (jeśli somehow bypass) → 403 z field-level error toast

**Files affected:** `apps/admin/src/components/DynamicAttributeField.tsx`, `apps/admin/src/components/ReadOnlyField.tsx`, `apps/admin/src/hooks/useAttributeRestriction.ts`, `apps/admin/src/routes/products/[id].tsx`

**Testing requirements:**
- Unit: render snapshots per restriction state
- E2E: Magda otwiera produkt → assert price as text, cost_price not present

**DoD:** Standard + AC + E2E Magda flow.

---

## RBAC-P4-010: feat(admin): Mercure SSE permission invalidation

**Typ:** `feat` | **Phase:** 4 | **Estymacja:** **4-6h**

**Dependencies:** Blocked by RBAC-P4-001.

**Risk flags:**
- Mercure SSE connection lifecycle — reconnect on disconnect.
- Race condition — invalidation event arrives during in-flight request. Queue or ignore.

**Cel:** Subscribe do Mercure SSE channel `user.permissions.changed.{user_id}`. Po event → refresh `/api/me` + update store. User dostaje real-time permission changes (np. admin revokes role).

**Scope:**
- Mercure subscriber w identity store init:
  ```ts
  const eventSource = new EventSource(`/.well-known/mercure?topic=user.permissions.changed.${userId}`);
  eventSource.onmessage = () => refreshMe();
  ```
- Reconnect logic — on disconnect retry z exponential backoff
- Channel topic naming — `user.permissions.changed.{user_id}` per user
- Channel published przez backend (RBAC-P2-006 PermissionInvalidationListener)
- Toast notification — *„Twoje uprawnienia zostały zaktualizowane przez administratora."*
- 401 fallback — gdy refresh /me fails → logout flow (RBAC-P4-008)

**Acceptance criteria:**
- [ ] AC-1: Admin zmienia user role (przez API z innego browsera) → user widzi toast notification w 5s
- [ ] AC-2: Store refreshed z new permissions w 1-2s po toast
- [ ] AC-3: UI re-renders (sidebar items appear/disappear, action buttons toggle)
- [ ] AC-4: SSE disconnect → reconnect attempt w 5s
- [ ] AC-5: Multiple rapid changes → debounced refresh (max 1/5s)

**Files affected:** `apps/admin/src/services/mercure-subscriber.ts`, `apps/admin/src/stores/identity.ts` (subscription init)

**Testing requirements:**
- E2E: admin browser session + user browser session, change role → assertion w user browser
- Connection lifecycle tests

**DoD:** Standard + AC + E2E concurrent browser test.

---

## RBAC-P4-011: feat(admin): Cmd+K palette permission filtering

**Typ:** `feat` | **Phase:** 4 | **Estymacja:** **4-6h**

**Dependencies:** Blocked by RBAC-P4-004, existing Cmd+K palette infrastructure.

**Risk flags:**
- LLM tool calls musi być pre-filtered — agent NIE może zaproponować akcji bez permission. Frontend filtering + backend Voter check oba.
- Suggested commands per persona — kontekstualne, nie all-or-nothing.

**Cel:** Cmd+K palette filtruje suggested commands wg. user permissions. Komenda dla `products.delete` NIE pojawia się w sugestiach dla Marketing. Agent tool calls validate permissions przed wywołaniem.

**Scope:**
- Refactor Cmd+K palette suggestions list:
  ```ts
  const commands = [
    { id: "delete_selected", label: "Usuń zaznaczone", permission: "products.delete" },
    { id: "set_brand", label: "Ustaw markę", permission: "products.bulk_operations" },
    ...
  ];
  // Filter
  const visibleCommands = commands.filter(cmd => store.hasPermission(cmd.permission));
  ```
- LLM tool call validation — przed sending tool call do backend:
  ```ts
  if (!store.hasPermission(toolCall.required_permission)) {
    showError("Nie masz uprawnień do tej akcji. Skontaktuj się z administratorem.");
    return;
  }
  ```
- Recent commands history — filtered (commands which user no longer has permission → not shown)
- Per-context suggestions — gdy current page = `/products`, prioritize bulk product commands

**Acceptance criteria:**
- [ ] AC-1: Marketing otwiera Cmd+K → palette NIE zawiera *„Usuń zaznaczone"*
- [ ] AC-2: Marketing wpisuje *„usuń wszystkie produkty"* → LLM zwraca tool call dla `delete_bulk` → frontend valid permission check → reject z message
- [ ] AC-3: Recent commands po revoke permission → command removed z historii
- [ ] AC-4: Context-aware suggestions — `/products` page → bulk commands top
- [ ] AC-5: Schema-ops commands (Modeler) → niewidoczne dla Catalog Manager

**Files affected:** `apps/admin/src/components/CmdKPalette.tsx`, `apps/admin/src/services/llm-tool-validator.ts`

**Testing requirements:** E2E Cmd+K flows per persona.

**DoD:** Standard + AC.

---

## RBAC-P4-012: feat(admin): MFA setup wizard UI (email TOTP + Google Authenticator)

**Typ:** `feat` | **Phase:** 4 | **Estymacja:** **6-8h**

**Dependencies:** Blocked by RBAC-P2-010 + RBAC-P2-011 (backend MFA endpoints).

**Risk flags:**
- QR code SVG inline rendering — CSP must allow `'unsafe-inline'` dla SVG lub use blob URL.
- Recovery codes display once — user MUST save. Force checkbox *„Zapisałem recovery codes"* przed close.

**Cel:** UI wizard w Profile → Security dla MFA setup. Step-by-step z QR code dla app TOTP lub email setup. Generate 10 recovery codes po success.

**Scope:**
- Profile → Security page z toggle *„Włącz MFA"*
- Wizard steps:
  1. Choose method: email TOTP / Authenticator app
  2. Email TOTP path:
     - Click *„Wyślij kod testowy"* → POST /api/me/mfa/email/setup
     - Enter 6-digit code → POST /api/me/mfa/email/verify
  3. Authenticator app path:
     - Show QR code (inline SVG z otpauth_url) + manual entry secret
     - User scans w Google Authenticator/Authy
     - Enter 6-digit code → POST /api/me/mfa/app/verify
  4. Recovery codes display:
     - 10 codes pokazane raz
     - Buttons: *„Download as TXT"*, *„Print"*, *„Copy"*
     - Mandatory checkbox *„Zapisałem te kody w bezpiecznym miejscu"*
     - Confirm → mfa enabled status visible
- Disable MFA — modal z password re-auth + confirm
- View recovery codes status — *„7 z 10 dostępnych"* (used codes tracked)

**Acceptance criteria:**
- [ ] AC-1: Wizard z 2 methods choice → proceed do appropriate path
- [ ] AC-2: QR code renderable, scanowalny (manual test)
- [ ] AC-3: Verify code → success → recovery codes modal
- [ ] AC-4: Recovery codes NIE są zwracane drugi raz (test: refresh page → codes gone)
- [ ] AC-5: Mandatory checkbox enforced
- [ ] AC-6: Disable MFA wymaga password
- [ ] AC-7: Profile → MFA status updates correctly

**Files affected:** `apps/admin/src/routes/profile/security.tsx`, `apps/admin/src/components/MfaSetupWizard.tsx`, `apps/admin/src/components/RecoveryCodesModal.tsx`

**Testing requirements:**
- E2E: full MFA setup flow (email + app)
- Manual smoke: real Google Authenticator scan

**DoD:** Standard + AC + manual smoke z Google Authenticator.

---

## RBAC-P4-013: feat(admin): password reset UI flow

**Typ:** `feat` | **Phase:** 4 | **Estymacja:** **3-5h**

**Dependencies:** Blocked by RBAC-P2-009 (backend reset endpoints).

**Risk flags:**
- Reset page accessible without auth (public route).
- Token w URL param — must be cleared from history po use (`window.history.replaceState`).

**Cel:** UI dla password reset request + reset confirmation.

**Scope:**
- `/forgot-password` route — email input + submit
- POST `/api/auth/password-reset/request` → toast *„Jeśli konto istnieje, link wysłany"* (generic, no enumeration)
- `/reset-password?token=X` route — new password + confirm fields + submit
- POST `/api/auth/password-reset/confirm` z `{token, new_password}` → toast success + redirect `/login`
- Token validity check — przy page load GET `/api/auth/password-reset/verify?token=X` → 200 valid, 410 expired/used
- Password strength indicator (basic — length, mixed case)
- Auto-clear token z URL po submit

**Acceptance criteria:**
- [ ] AC-1: Forgot password form submits → generic success message
- [ ] AC-2: Reset page z valid token → form visible
- [ ] AC-3: Reset page z expired token → error message + link do `/forgot-password`
- [ ] AC-4: Submit new password → success → redirect login
- [ ] AC-5: Password strength visible
- [ ] AC-6: Token cleared z URL po submit (history.replaceState)

**Files affected:** `apps/admin/src/routes/forgot-password.tsx`, `apps/admin/src/routes/reset-password.tsx`

**Testing requirements:** E2E full reset flow.

**DoD:** Standard + AC.

---

## Phase 4 zakończony — deliverables

Po merge wszystkich 13 ticketów:
- ✅ Session bootstrap z /api/me
- ✅ httpOnly cookie + JWT refresh
- ✅ Refine route guards + 403 page
- ✅ `<PermissionGate>` + `useCanI()` foundation
- ✅ Sidebar nav filtering (DOM-level removal)
- ✅ Tenant-switch dropdown (multi-tenant)
- ✅ HTTP interceptor 403 + 401
- ✅ Field-level form rendering (text vs input vs hidden)
- ✅ Mercure SSE permission invalidation
- ✅ Cmd+K palette filtering
- ✅ MFA setup wizard UI
- ✅ Password reset UI

**Phase 4 → Phase 5 transition:** core frontend RBAC patterns established. Phase 5 builds Settings UI (Users, Roles, API tokens) + Super Admin operator panel.

**Estymacja Phase 4: ~70-90h. 13 ticketów. Realne tempo: 2-2.5 tygodnia.**
