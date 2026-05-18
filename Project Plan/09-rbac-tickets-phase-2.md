# RBAC — tickety Phase 2 (Backend auth + tenant context)

**Typ dokumentu:** Backlog ticketów Phase 2 RBAC — ready-to-paste GitHub Issues
**Status:** Draft — gotowe do utworzenia w GitHub po zakończeniu Phase 1
**Powiązane:** [`07-rbac-implementation-plan.md`](07-rbac-implementation-plan.md) §4.3, [`PRD/PRD-PIM-rbac.md`](PRD/PRD-PIM-rbac.md) §4.5

> **Cel Phase 2:** kompletny auth flow (email+password, MFA, SSO) + Tenant Context Resolver + Permission Resolver z Redis cache + `/api/me` endpoint. Po tej fazie user może się zalogować, system zna jego permissions, ale **endpointy domenowe nie są jeszcze chronione** (to Phase 3).
>
> **Harmonogram:** tygodnie 3-5, **~80-110h**. 14 ticketów.

---

## Graf zależności Phase 2

```
RBAC-P2-001 (JWT auth) ──┬── RBAC-P2-002 (email+password)
                          ├── RBAC-P2-003 (API token auth)
                          └── RBAC-P2-004 (Tenant Context)
                                         │
                                         ▼
                          RBAC-P2-005 (Postgres RLS)
                                         │
                                         ▼
                          RBAC-P2-006 (Permission Resolver + cache)
                                         │
                                         ▼
                          RBAC-P2-007 (/api/me endpoint)
                                         │
                                         ├── RBAC-P2-008 (Magic link invite)
                                         ├── RBAC-P2-009 (Password reset)
                                         ├── RBAC-P2-010 (MFA email TOTP)
                                         ├── RBAC-P2-011 (MFA Google Authenticator)
                                         ├── RBAC-P2-012 (SSO Google Workspace)
                                         ├── RBAC-P2-013 (SSO Microsoft 365)
                                         └── RBAC-P2-014 (SSO SAML 2.0)
```

---

## RBAC-P2-001: feat(identity): setup Lexik JWT bundle + JWT authentication

**Typ:** `feat` | **Phase:** 2 | **Estymacja:** **4-6h**

**Dependencies:** Blocks RBAC-P2-002, RBAC-P2-003, RBAC-P2-004. Blocked by Phase 1 complete.

**Risk flags:**
- JWT secret w git = total compromise. MUSI być w Symfony Secrets Vault, nie `.env`.
- Algorithm choice: `RS256` (asymmetric), NIE `HS256` (shared secret łatwiej leak).
- Token expiry: access token 15 min, refresh token 7 dni — krótkie access window.

**Cel:** Setup Lexik JWT Authentication Bundle z RS256, generuje JWT przy login, weryfikuje na każdym request, attach user do Symfony Security context.

**Scope:**
- Instalacja `lexik/jwt-authentication-bundle`
- Generate RS256 keypair przez `php bin/console lexik:jwt:generate-keypair`
- Klucze w Symfony Secrets Vault (NIE `.env.local`)
- Config: `lexik_jwt_authentication.yaml` — token_ttl=900s, refresh_token_ttl=604800s
- Custom JWT payload: `user_id`, `tenant_id`, `email`, `iat`, `exp`
- Refresh token endpoint (rotation pattern)
- Firewall config `security.yaml` — JWT authenticator dla `/api/*`

**Acceptance criteria:**
- [ ] AC-1: RS256 keypair generated, public key in code repo, private key in Secrets Vault
- [ ] AC-2: POST `/api/auth/login` z email+password zwraca `{token, refresh_token, user, tenant}`
- [ ] AC-3: GET `/api/me` z `Authorization: Bearer X` zwraca 200, bez tokena → 401
- [ ] AC-4: Token z `exp` w przeszłości → 401
- [ ] AC-5: Token z manipulated signature → 401
- [ ] AC-6: POST `/api/auth/refresh` z refresh_token zwraca nowy access token + rotation (stary refresh invalid)
- [ ] AC-7: JWT secret nie pojawia się w żadnym commit (TruffleHog scan pass)

**Files affected:** `config/packages/lexik_jwt_authentication.yaml`, `config/packages/security.yaml`, `src/Identity/Controller/AuthController.php`, `src/Identity/Service/JwtTokenService.php`

**Testing requirements:**
- Unit: `JwtTokenServiceTest::testGenerateValidToken()`, `testRejectExpiredToken()`, `testRejectManipulatedSignature()`
- Integration: pełen login → /me → refresh flow z testcontainers
- Manual smoke: forged token z DevTools → 401

**DoD:** Standard + AC + JWT scan pass + manual smoke z forged token.

---

## RBAC-P2-002: feat(identity): email + password authentication flow

**Typ:** `feat` | **Phase:** 2 | **Estymacja:** **5-7h**

**Dependencies:** Blocked by RBAC-P2-001.

**Risk flags:**
- Password hashing: ONLY Argon2id (PHP `password_hash` default) — NIGDY bcrypt/MD5/SHA1.
- Rate limiting na login — 5 attempts/15min/IP (brute force protection).
- Timing attack — constant-time email lookup (zwracaj zawsze ten sam response time dla *„user not found"* i *„wrong password"*).
- Generic error message — *„Invalid credentials"*, NIE *„user not found"* / *„wrong password"* (account enumeration prevention).

**Cel:** Endpoint `/api/auth/login` z email + password, generuje JWT, obsługuje rate limiting, prevent account enumeration.

**Scope:**
- POST `/api/auth/login` endpoint w `AuthController`
- Argon2id password hashing przy register (Phase 2 invite flow) + verify przy login
- Rate limiter `symfony/rate-limiter` — 5 attempts / 15 min per IP, 10 / 1h per email
- Generic error response — `Problem Details RFC 7807` ze status 401 + `detail: "Invalid credentials"` (no info leak)
- Constant-time email lookup (zawsze fetch user even if not found, hash dummy password if user missing)
- Audit log entry per login attempt (success / failure)
- `User.failed_login_attempts` counter + auto-lockout po 10 fails w 1h

**Acceptance criteria:**
- [ ] AC-1: Login z valid credentials zwraca JWT + 200
- [ ] AC-2: Login z invalid password zwraca 401 + generic message
- [ ] AC-3: Login z nonexistent email zwraca 401 + same generic message (account enumeration prevented)
- [ ] AC-4: Login response time consistent dla user-exists vs user-missing (±50ms tolerance)
- [ ] AC-5: 6th failed attempt w 15min → 429 Too Many Requests
- [ ] AC-6: 10 fails w 1h → account locked + email notification + admin notification
- [ ] AC-7: Login success resetuje `failed_login_attempts` counter
- [ ] AC-8: Audit log entry per attempt z `permission_check_result=login_success|login_failed`

**Files affected:** `src/Identity/Controller/AuthController.php`, `src/Identity/Service/PasswordAuthService.php`, `config/packages/rate_limiter.yaml`

**Testing requirements:**
- Unit: PasswordAuthServiceTest (Argon2id hashing, constant-time)
- Integration: rate limit triggered po 5 fails
- Integration: timing test (constant-time comparison)

**DoD:** Standard + AC + manual smoke z `time curl /api/auth/login` x10.

---

## RBAC-P2-003: feat(identity): API token authentication (custom authenticator)

**Typ:** `feat` | **Phase:** 2 | **Estymacja:** **5-7h**

**Dependencies:** Blocked by RBAC-P2-001.

**Risk flags:**
- API token leaks w logs/screenshots — token MUSI być hashed w DB (BCrypt), plaintext zwracany tylko raz przy create.
- Token format prefix `cortex_` żeby gitleaks/TruffleHog mógł wykryć leak.
- Token scope check — token z `read-only` scope NIE może POST (Voter check).
- Last-used tracking — `last_used_at`, `last_used_ip` updateowane async (nie blocking request).

**Cel:** Custom Symfony authenticator dla API tokenów. Token jest separate authentication method (alternatywa do JWT). Token ma własne scopes.

**Scope:**
- `ApiTokenAuthenticator` extends `AbstractAuthenticator`
- Token format: `cortex_{tenant_short}_{random32}` (prefix dla scan tools)
- Storage: `api_tokens.token_hash` BCrypt, plaintext zwracany tylko raz w response na create
- Header detection: `Authorization: Token cortex_XXX` (różny prefix od JWT `Bearer`)
- Scope enforcement: token z scope `read-only` próbuje POST → 403
- Async update `last_used_at`, `last_used_ip` (Symfony Messenger)
- Revoke flow: `DELETE /api/api-tokens/{id}` → `revoked_at = NOW()`
- Revoked token → 401 z `detail: "Token revoked"`

**Acceptance criteria:**
- [ ] AC-1: Create token zwraca plaintext token raz, kolejny GET tokens nie zwraca plaintext (tylko hash + last_4_chars)
- [ ] AC-2: Request z `Authorization: Token cortex_XXX` (valid) → 200
- [ ] AC-3: Request z invalid token → 401
- [ ] AC-4: Token z `read-only` scope POST /api/products → 403
- [ ] AC-5: Token z `read-write-catalog` scope POST /api/products → 200 (jeśli endpoint allowed)
- [ ] AC-6: Revoked token → 401 z message *„Token revoked"*
- [ ] AC-7: `last_used_at`, `last_used_ip` updateowane async (test: query po requestach, sprawdza że updated)
- [ ] AC-8: Token expiry (jeśli set) — token po `expires_at` → 401

**Files affected:** `src/Identity/Security/ApiTokenAuthenticator.php`, `src/Identity/Service/ApiTokenService.php`, `src/Identity/Controller/ApiTokenController.php`, `src/Identity/Message/UpdateTokenLastUsedMessage.php`

**Testing requirements:**
- Unit: ApiTokenServiceTest — generate, hash, verify scope
- Integration: full create/use/revoke cycle z testcontainers
- Cross-tenant: token z tenant A nie może access tenant B (cross-tenant test)

**DoD:** Standard + AC + scope enforcement smoke test (curl z różnymi scope tokens).

---

## RBAC-P2-004: feat(identity): tenant context resolver + Doctrine TenantFilter

**Typ:** `feat` | **Phase:** 2 | **Estymacja:** **6-8h**

**Dependencies:** Blocked by RBAC-P2-001.

**Risk flags:**
- **CROSS-TENANT LEAKAGE RISK** — najwyższy priorytet bezpieczeństwa. Każdy query bez TenantFilter = potential breach.
- Super Admin bypass — TenantFilter musi mieć tryb `disabled` dla Super Admin operations, ale z audit logging.
- Filter applied AUTOMATIC by Doctrine — programista nie może zapomnieć dodać WHERE clause.

**Cel:** Doctrine filter który automatycznie dodaje `WHERE tenant_id = :current_tenant` do każdego SELECT na tabelach domenowych. Plus service `TenantContext` który resolwuje current tenant z JWT/API token.

**Scope:**
- `TenantContext` service z `getCurrentTenantId(): ?string` (NULL dla Super Admin)
- `TenantFilter` extends `SQLFilter` — applied to każdy entity z `#[TenantAware]` attribute
- Doctrine config: filter enabled by default, disable explicit dla Super Admin operations
- Marker attribute `#[TenantAware]` na encjach (Product, Category, Asset, User, Role, etc.)
- Listener `RequestListener` setuje filter parameter `current_tenant` z `TenantContext` przy request start
- Super Admin context: `TenantContext::useSuperAdminMode()` disable filter + audit log entry `CROSS_TENANT_ACCESS`
- TenantAssignmentListener na `prePersist` — auto-set `tenant_id` z context (programista nigdy nie ustawia ręcznie)

**Acceptance criteria:**
- [ ] AC-1: Query `findAll()` na Product zwraca tylko produkty current tenant (assert SQL contains `WHERE tenant_id = ?`)
- [ ] AC-2: Tenant A user próbuje GET /api/products/{id-z-tenant-B} → 404 (NIE 403, żeby nie ujawnić istnienia)
- [ ] AC-3: Super Admin GET /api/admin/tenants/{B}/products → 200 + audit log entry z `cross_tenant_access=true`
- [ ] AC-4: `prePersist` na new Product auto-setuje `tenant_id` z context (test: create product bez explicit tenant_id, assert że ma poprawny)
- [ ] AC-5: Próba `Product->setTenantId('different-tenant')` w trakcie persist → exception (tenant_id immutable po create)
- [ ] AC-6: 10+ entity classes z `#[TenantAware]` listed in code (Product, Category, Asset, User, Role, etc.)
- [ ] AC-7: Cross-tenant isolation test suite (10+ scenarios) pass

**Files affected:** `src/Identity/Service/TenantContext.php`, `src/Identity/Doctrine/Filter/TenantFilter.php`, `src/Identity/EventListener/TenantAssignmentListener.php`, `src/Identity/EventListener/RequestListener.php`, `src/Identity/Attribute/TenantAware.php`

**Testing requirements:**
- Unit: TenantContextTest, TenantFilterTest
- Integration: pełen request cycle z filter applied
- **Cross-tenant suite (highest priority)** — 10+ scenarios

**DoD:** Standard + AC + **dedicated cross-tenant test suite pass** + manual smoke z 2 tenantami.

---

## RBAC-P2-005: feat(database): Postgres RLS activation — defence in depth

**Typ:** `feat` | **Phase:** 2 | **Estymacja:** **8-12h**

**Dependencies:** Blocked by RBAC-P2-004 (TenantFilter musi być pierwszą warstwą).

**Risk flags:**
- RLS na Postgres jest **niezależną** warstwą od Doctrine TenantFilter. Defence in depth.
- Migration RLS musi być **non-blocking** — `CREATE POLICY ... CONCURRENTLY` lub w off-hours.
- Performance impact: RLS dodaje overhead na każdy query. Benchmark wymagany.
- Connection pooling — Postgres RLS używa `current_setting('app.current_tenant')`, każde nowe connection musi mieć SET.

**Cel:** Aktywacja Postgres Row Level Security jako druga warstwa tenant boundary. Doctrine TenantFilter to pierwsza, RLS to defence-in-depth.

**Scope:**
- Migration `Version20260516_EnableRlsOnTenantTables.php`:
  - Per każda `#[TenantAware]` table: `ALTER TABLE x ENABLE ROW LEVEL SECURITY`
  - `CREATE POLICY tenant_isolation_x ON x USING (tenant_id = current_setting('app.current_tenant')::uuid)`
  - Bypass policy dla Super Admin role: `CREATE POLICY super_admin_bypass ON x USING (current_setting('app.is_super_admin', true) = 'true')`
- Doctrine connection setup — przed każdym request `SET LOCAL app.current_tenant = ?`
- Connection pool consideration — jeśli używamy pgBouncer, transaction mode (nie session mode) żeby SET LOCAL działało
- Performance benchmark — 100 sample queries z RLS vs bez, max 20% overhead acceptable
- Test: bypass attempt — disable Doctrine filter, try SELECT z różnym tenant_id → RLS blokuje

**Acceptance criteria:**
- [ ] AC-1: 10+ tabel z RLS enabled (verify: `SELECT relname FROM pg_class WHERE relrowsecurity = true`)
- [ ] AC-2: Each table has `tenant_isolation_*` policy + `super_admin_bypass` policy
- [ ] AC-3: Even jeśli Doctrine TenantFilter disabled (sabotage test), query nie zwraca cross-tenant rows
- [ ] AC-4: Super Admin role może SELECT all tenants (policy bypass działa)
- [ ] AC-5: Benchmark: query latency wzrost <20% na production-like dataset (50k rows per tenant)
- [ ] AC-6: Connection setup test — `SET LOCAL app.current_tenant = 'X'` ustawia kontekst dla session
- [ ] AC-7: Migration rollback test (DROP POLICY + DISABLE RLS) pass

**Files affected:** `src/Identity/Doctrine/Migrations/Version20260516_EnableRls.php`, `src/Identity/EventListener/RlsContextListener.php`, `config/packages/doctrine.yaml`

**Testing requirements:**
- Integration: bypass Doctrine filter, query cross-tenant → 0 rows (RLS catch)
- Performance: benchmark suite z 50k rows
- Manual smoke: psql direct query z różnymi `SET LOCAL app.current_tenant`

**DoD:** Standard + AC + benchmark report w PR + cross-tenant bypass test pass.

---

## RBAC-P2-006: feat(identity): Permission Resolver service + Redis cache + event-driven invalidation

**Typ:** `feat` | **Phase:** 2 | **Estymacja:** **8-12h**

**Dependencies:** Blocked by RBAC-P2-004 (tenant context resolved).

**Risk flags:**
- Cache stale = user revoked but still has access. Event-driven invalidation OBOWIĄZKOWA.
- N+1 query risk — load permissions per role → per permission. Optimization: single JOIN query.
- Cache poisoning — cache key MUSI być scoped per (user_id, tenant_id) — nie tylko user_id.

**Cel:** `PermissionResolver` service który zwraca complete `PermissionSet` dla usera. Cached w Redis 5min TTL + event-driven invalidation przy role/permission change.

**Scope:**
- `PermissionResolver::resolve(User $user): PermissionSet`
- Single JOIN query — load user → roles → permissions w jednym SQL (avoid N+1)
- Redis cache key: `permissions:{tenant_id}:{user_id}` z TTL 300s
- `PermissionSet` value object — methods `has(string $code): bool`, `hasAll(array $codes): bool`, `getCodes(): array`, `getLocaleScope(): array`, `getChannelScope(): array`
- Event-driven invalidation — Doctrine listener na `UserRole`, `RolePermission`, `Role` changes → `$cache->delete("permissions:{tenant_id}:{user_id}")`
- Mercure event publish `user.permissions.changed` dla frontend (subscribed w Phase 4)
- Performance: load <50ms p95 dla user z 5 ról i 100 permissions

**Acceptance criteria:**
- [ ] AC-1: `PermissionResolver::resolve($user)` zwraca PermissionSet z prawidłowym union permissions z wszystkich ról
- [ ] AC-2: Second call (same user) → cache hit, <5ms latency
- [ ] AC-3: After `UserRole` change → cache invalidated, next call → fresh data
- [ ] AC-4: After `RolePermission` change → cache invalidated dla wszystkich users z tą rolą (test: 2 users w roli, change role permissions, oba widzą fresh)
- [ ] AC-5: Cache key includes tenant_id (test: 2 users w 2 tenantach z tym samym user.id przypadkiem — różne cache keys)
- [ ] AC-6: Mercure event `user.permissions.changed` published po invalidation
- [ ] AC-7: Performance test — 1000 resolves average <50ms (z cache misses random 20%)
- [ ] AC-8: PermissionSet ma `getLocaleScope()` zwracający `["*"]` lub konkretne locales z `user_roles.locale_scope`

**Files affected:** `src/Identity/Service/PermissionResolver.php`, `src/Identity/Value/PermissionSet.php`, `src/Identity/EventListener/PermissionInvalidationListener.php`

**Testing requirements:**
- Unit: PermissionResolverTest (union semantics, cache hit/miss)
- Integration: cache invalidation flow z real Redis
- Performance: 1000 resolves benchmark

**DoD:** Standard + AC + benchmark report + Mercure event verification.

---

## RBAC-P2-007: feat(identity): /api/me endpoint — session bootstrap

**Typ:** `feat` | **Phase:** 2 | **Estymacja:** **3-5h**

**Dependencies:** Blocked by RBAC-P2-006.

**Risk flags:**
- Response zawiera permissions list — większa response = większy bandwidth (50+ permissions ~3-5KB).
- Sensitive data — odpowiedź nie może zawierać MFA secret, password hash, czy session token.
- `attribute_restrictions` calculation może być slow przy 200+ atrybutach — pre-compute w cache.

**Cel:** Single endpoint który zwraca complete user context dla frontend bootstrap. Front używa response do permissions check w UI.

**Scope:**
- GET `/api/me` (authenticated)
- Response structure (per PRD §5.1):
  ```json
  {
    "user": { id, email, name, locale, mfa_enabled, sso_provider },
    "tenant": { id, name, locales, channels },
    "memberships": [{ tenant_id, tenant_name, role_names }],
    "roles": [{ name, code, locale_scope, channel_scope }],
    "permissions": ["products.view", ...],
    "attribute_restrictions": { "price": { can_view, can_edit, reason } },
    "features": { can_switch_tenant, has_super_admin_access }
  }
  ```
- Serializer groups — exclude password_hash, mfa_secret, sso_secrets
- `attribute_restrictions` computed by iterating attributes + checking against user roles
- Cache full response 60s (less aggressive niż PermissionResolver, bo response composite)

**Acceptance criteria:**
- [ ] AC-1: GET /api/me bez auth → 401
- [ ] AC-2: GET /api/me z valid JWT → 200 + full structure
- [ ] AC-3: Response NIE zawiera password_hash, mfa_secret (field-level scrubbing test)
- [ ] AC-4: `permissions` array matches `PermissionResolver::resolve($user)` codes
- [ ] AC-5: `attribute_restrictions` correctly identifies restricted attributes per user role
- [ ] AC-6: User z 2 memberships zwraca obie w `memberships` array
- [ ] AC-7: Super Admin user → `features.has_super_admin_access = true`
- [ ] AC-8: Response size <10KB nawet dla power user z 50+ permissions (test: assert byte length)

**Files affected:** `src/Identity/Controller/MeController.php`, `src/Identity/Service/MeResponseBuilder.php`, `src/Identity/Serializer/MeNormalizer.php`

**Testing requirements:**
- Integration: per role test (10 ról x assertion że response matches macierz 3.2 PRD)
- Field-level scrubbing test — sensitive fields not in response
- Performance: 100 GET /api/me <500ms total

**DoD:** Standard + AC + field scrubbing test pass.

---

## RBAC-P2-008: feat(identity): magic link invitation flow

**Typ:** `feat` | **Phase:** 2 | **Estymacja:** **8-12h**

**Dependencies:** Blocked by RBAC-P2-007.

**Risk flags:**
- Magic link token leaks — token w email może być scrapped. Token MUST be single-use + 7d TTL + hashed in DB.
- Email content sanitization — XSS w email body jeśli tenant name zawiera HTML.
- Email delivery failures — token created ale email nie doszedł → user stuck. Need resend flow.

**Cel:** Admin invituje user'a przez email + role assignment. User dostaje magic link, klika, ustawia password + opcjonalnie MFA, zalogowany.

**Scope:**
- POST `/api/invitations` — admin creates invitation (name, email, role_ids, locale_scope, channel_scope)
- Server: generuje token (32 bytes random), hash w `invitations.token`, plaintext w email link
- Email send via Symfony Mailer + Twig template — magic link `https://pim.localhost/accept-invitation?token=X`
- 7-day TTL — `expires_at = NOW() + 7 days`
- GET `/accept-invitation?token=X` — frontend page (Phase 5, tu tylko endpoint)
- POST `/api/invitations/{token}/accept` — body: `password`, `password_confirm`, opt. MFA setup
- Single-use: po accept `accepted_at = NOW()`, drugi accept → 410 Gone
- Resend flow: POST `/api/invitations/{id}/resend` — generuje new token (invalidates old)
- Cancel: DELETE `/api/invitations/{id}` — soft delete `expires_at = NOW()`

**Acceptance criteria:**
- [ ] AC-1: Admin POST `/api/invitations` z valid data → 201 + email sent (check mailhog)
- [ ] AC-2: User otwiera magic link → frontend redirects do accept page
- [ ] AC-3: POST `/api/invitations/{token}/accept` z valid password → user created, JWT returned, role assigned
- [ ] AC-4: Reused token (already accepted) → 410 Gone
- [ ] AC-5: Expired token (>7d) → 410 Gone
- [ ] AC-6: Token NIE jest w plaintext w DB (verify `invitations.token` is hash)
- [ ] AC-7: Resend flow generuje nowy token, stary invalid
- [ ] AC-8: Cancelled invitation → token invalid

**Files affected:** `src/Identity/Controller/InvitationController.php`, `src/Identity/Service/InvitationService.php`, `templates/email/invitation.html.twig`, `templates/email/invitation.txt.twig`

**Testing requirements:**
- Integration: full flow create → email → accept → user logged in
- Security: reused/expired/cancelled token edge cases
- Email: XSS test (tenant name z HTML chars)

**DoD:** Standard + AC + email manual smoke (mailhog verification).

---

## RBAC-P2-009: feat(identity): password reset flow

**Typ:** `feat` | **Phase:** 2 | **Estymacja:** **5-7h**

**Dependencies:** Blocked by RBAC-P2-008 (shared magic link infrastructure).

**Risk flags:**
- Reset token może zostać wykorzystany do account takeover. Single-use + 1h TTL + hashed.
- Account enumeration — request reset dla nonexistent email NIE może zwracać różnego response.
- Reset token revokes existing sessions (logout from all devices).

**Cel:** User zapomina password, request reset, dostaje email z magic link, ustawia new password.

**Scope:**
- POST `/api/auth/password-reset/request` z body `{email}` — generuje token, hash w `password_reset_tokens` table (new mini-table lub reuse `invitations` z type=password_reset)
- Generic response — zawsze 200 *„Jeśli konto istnieje, link wysłany"*. NIE info czy email exists.
- Email send z magic link `https://pim.localhost/reset-password?token=X`
- 1-hour TTL (krótsze niż invite)
- POST `/api/auth/password-reset/confirm` z body `{token, new_password}` — verify token, hash new password, save
- Side effect: revoke all existing JWT refresh tokens dla tego usera (logout from all devices)
- Audit log entry per request + per confirm
- Rate limit — 3 requests / 1h / IP (anti-spam)

**Acceptance criteria:**
- [ ] AC-1: Request z valid email → 200 generic + email sent
- [ ] AC-2: Request z nonexistent email → 200 generic (same response, no enumeration)
- [ ] AC-3: Reset confirm z valid token + valid password → success, password updated
- [ ] AC-4: Reset confirm z expired token (>1h) → 410
- [ ] AC-5: Reset confirm z used token → 410
- [ ] AC-6: Post-reset existing refresh tokens revoked — user logged out from other devices
- [ ] AC-7: Rate limit 4th request w 1h → 429
- [ ] AC-8: Audit log entries dla każdego request + confirm

**Files affected:** `src/Identity/Controller/PasswordResetController.php`, `src/Identity/Service/PasswordResetService.php`, `templates/email/password-reset.*.twig`

**Testing requirements:**
- Integration: full reset cycle
- Security: account enumeration test (timing + response equality)
- Session revocation test

**DoD:** Standard + AC + manual smoke.

---

## RBAC-P2-010: feat(identity): MFA setup — email TOTP method

**Typ:** `feat` | **Phase:** 2 | **Estymacja:** **6-8h**

**Dependencies:** Blocked by RBAC-P2-002.

**Risk flags:**
- MFA secret w plaintext = MFA bypass. Secret MUST be encrypted at rest.
- Recovery codes — 10 codes one-time use, hashed in DB (BCrypt).
- Email TOTP code (6-digit) z 60s validity window — krótkie żeby brute force impossible.
- MFA bypass route — disable MFA flow wymaga password re-auth (defence przeciw session hijacking).

**Cel:** User w Profile → Security włącza MFA przez email TOTP. Po każdym login następuje second step: enter 6-digit code z email.

**Scope:**
- POST `/api/me/mfa/email/setup` — generate secret, send first code do email, expect verify
- POST `/api/me/mfa/email/verify` z `{code}` — confirm setup, `User.mfa_enabled=true`, `mfa_method='email_totp'`
- Login flow modification — po valid password, jeśli `mfa_enabled` → wymaga MFA challenge before JWT issue
- POST `/api/auth/mfa/challenge` z body `{login_session_token, mfa_code}` — verify code, issue JWT
- Generate 10 recovery codes po setup — hashed in `user_recovery_codes` table, plaintext zwracany raz w UI
- POST `/api/auth/mfa/recovery` z `{login_session_token, recovery_code}` — use one-time recovery code
- MFA disable flow — POST `/api/me/mfa/disable` z `{password}` re-auth
- Time window — code valid 60s, allow ±30s clock drift
- TOTP secret encrypted w DB (`mfa_secret` z Symfony Encryptor)

**Acceptance criteria:**
- [ ] AC-1: User setup MFA email → first code received, verify → mfa_enabled=true
- [ ] AC-2: Login z password → response `{requires_mfa: true, login_session_token}` (no JWT yet)
- [ ] AC-3: MFA challenge with valid code → JWT issued
- [ ] AC-4: MFA challenge with invalid code → 401, retry allowed (max 5 attempts then lockout 15min)
- [ ] AC-5: Recovery code used (one of 10) → JWT issued, recovery code marked used
- [ ] AC-6: Disable MFA requires password re-auth
- [ ] AC-7: `mfa_secret` encrypted in DB (raw SELECT shows encrypted value)
- [ ] AC-8: Time drift ±30s tolerated

**Files affected:** `src/Identity/Controller/MfaController.php`, `src/Identity/Service/EmailTotpService.php`, `src/Identity/Service/RecoveryCodeService.php`, `templates/email/mfa-code.*.twig`

**Testing requirements:**
- Integration: full setup → login → challenge cycle
- Security: brute force lockout test
- Recovery code single-use test

**DoD:** Standard + AC + manual smoke (real email delivery).

---

## RBAC-P2-011: feat(identity): MFA setup — Google Authenticator (app TOTP)

**Typ:** `feat` | **Phase:** 2 | **Estymacja:** **5-7h**

**Dependencies:** Blocked by RBAC-P2-010 (shared infrastructure).

**Risk flags:**
- QR code rendering w UI musi być secure (CSP allow inline SVG).
- Otpauth URL format must match RFC 6238 — kompatybilność z Google Authenticator, Authy, 1Password.

**Cel:** Alternative MFA method — Google Authenticator app generuje 6-digit codes z TOTP secret.

**Scope:**
- Reuse `scheb/2fa-bundle` lub similar PHP TOTP library (`spomky-labs/otphp`)
- POST `/api/me/mfa/app/setup` — generate TOTP secret, return `{secret, otpauth_url, qr_code_svg}`
- User scans QR code z Google Authenticator/Authy app
- POST `/api/me/mfa/app/verify` z `{code}` — confirm setup
- Login flow — analogiczne do email TOTP, ale code z app zamiast email
- Recovery codes — shared mechanism z RBAC-P2-010
- TOTP standard: 30s window, 6 digits, SHA1, ±1 period drift

**Acceptance criteria:**
- [ ] AC-1: Setup endpoint zwraca `{secret, otpauth_url, qr_code_svg}` — otpauth_url format `otpauth://totp/Cortex:user@email?secret=X&issuer=Cortex`
- [ ] AC-2: QR code SVG renderable, scanowalny przez Google Authenticator app (manual test)
- [ ] AC-3: Verify with code from app → mfa_enabled=true
- [ ] AC-4: Login → MFA challenge accepts code from app
- [ ] AC-5: Code drift ±1 window accepted (kompensacja zegara)
- [ ] AC-6: User może mieć MFA app lub email, NIE oba jednocześnie (mfa_method enum)
- [ ] AC-7: Switching method (email → app) wymaga re-setup

**Files affected:** `src/Identity/Service/AppTotpService.php`, `src/Identity/Controller/MfaController.php` (extension)

**Testing requirements:**
- Unit: TOTP code generation/validation
- Manual: real Google Authenticator app test

**DoD:** Standard + AC + manual smoke z Google Authenticator.

---

## RBAC-P2-012: feat(identity): SSO Google Workspace OAuth integration

**Typ:** `feat` | **Phase:** 2 | **Estymacja:** **6-8h**

**Dependencies:** Blocked by RBAC-P2-007.

**Risk flags:**
- OAuth redirect URI MUST match registered exactly — jeden niezgodny char = redirect blocked.
- State param dla CSRF protection — random token w session, verify on callback.
- Subject ID matching — `sso_subject_id` jest Google's unique ID, NIE email (email może się zmienić).
- Tenant assignment — który tenant dostaje user'a po SSO login? Domain matching (`@firma.pl` → tenant Firma).

**Cel:** Self-serve sign-in dla users z Google Workspace account. Click *„Sign in with Google"*, OAuth flow, account auto-created lub matched.

**Scope:**
- Symfony OAuth bundle (`knpuniversity/oauth2-client-bundle` lub equivalent)
- Config: Google OAuth Client ID + Secret w Secrets Vault per tenant
- GET `/api/auth/sso/google/start` — redirect do Google z state param
- GET `/api/auth/sso/google/callback?code=X&state=Y` — verify state, exchange code for token, fetch user info, create/match user
- Domain → tenant mapping — config `sso_providers.config.email_domain` (np. `firma.pl` → tenant ID)
- Account matching: jeśli user z `sso_subject_id` istnieje → login. Jeśli nie, jeśli email matches existing user → link account. Jeśli nie, jeśli `auto_create_users=true` w config → create new user z default role (Viewer)
- `enforce_for_users=true` config — password login disabled, tylko SSO
- Per-tenant SSO config — different OAuth credentials per tenant

**Acceptance criteria:**
- [ ] AC-1: Klick *„Sign in with Google"* redirect do Google consent screen
- [ ] AC-2: Successful auth → callback → user created/matched, JWT issued
- [ ] AC-3: State param mismatch (CSRF) → 400 Bad Request
- [ ] AC-4: Email domain matching — user z `marcin@firma.pl` zostaje w tenant Firma
- [ ] AC-5: Re-login → existing user matched by `sso_subject_id`, NIE email
- [ ] AC-6: `enforce_for_users=true` — password login zwraca 403 z message *„SSO required"*
- [ ] AC-7: `auto_create_users=false` + new user → 403 *„No account, contact admin"*
- [ ] AC-8: Audit log entry per SSO login z provider info

**Files affected:** `src/Identity/Controller/SsoController.php`, `src/Identity/Service/GoogleOAuthService.php`, `config/packages/oauth.yaml`

**Testing requirements:**
- Integration: full OAuth flow z mock Google endpoints
- Security: CSRF state mismatch test

**DoD:** Standard + AC + manual smoke z real Google Workspace account.

---

## RBAC-P2-013: feat(identity): SSO Microsoft 365 OAuth integration

**Typ:** `feat` | **Phase:** 2 | **Estymacja:** **5-7h**

**Dependencies:** Blocked by RBAC-P2-012 (shared OAuth infrastructure).

**Risk flags:**
- Microsoft tenant restriction — config `allowed_microsoft_tenants` per Cortex tenant (only employees z org X mogą login).
- Microsoft Graph API rate limits — fetch user info conservatively.

**Cel:** Analogicznie do Google — *„Sign in with Microsoft"* dla users z Microsoft 365 account.

**Scope:**
- Reuse OAuth flow infrastructure z RBAC-P2-012
- Microsoft endpoint: `https://login.microsoftonline.com/{tenant}/oauth2/v2.0/authorize`
- Microsoft Graph API dla user info: `https://graph.microsoft.com/v1.0/me`
- Per-tenant config Microsoft tenant ID restriction
- Account matching same as Google (subject ID first, email second)

**Acceptance criteria:**
- [ ] AC-1: Click *„Sign in with Microsoft"* → redirect do Microsoft consent
- [ ] AC-2: Successful auth → JWT issued
- [ ] AC-3: User z innego Microsoft tenant niż allowed → 403
- [ ] AC-4: Account matching by Microsoft `oid` claim
- [ ] AC-5: Audit log per login

**Files affected:** `src/Identity/Service/MicrosoftOAuthService.php`, `src/Identity/Controller/SsoController.php` (extension)

**Testing requirements:** Integration z mock Microsoft endpoints + manual smoke.

**DoD:** Standard + AC + manual smoke.

---

## RBAC-P2-014: feat(identity): SSO SAML 2.0 integration (enterprise)

**Typ:** `feat` | **Phase:** 2 | **Estymacja:** **10-14h**

**Dependencies:** Blocked by RBAC-P2-007.

**Risk flags:**
- SAML XML signature verification — błędna implementacja = SAML bypass attack.
- IdP metadata trust — must support `signature` w metadata, encryption optional.
- XML attacks — XXE, XSW — używamy battle-tested library (LightSAML), nie own implementation.
- SP entity ID + ACS URL — per-tenant unique, config carefully.

**Cel:** Enterprise SSO przez SAML 2.0 — klient ma własny IdP (Okta, Azure AD, ADFS), Cortex jako Service Provider.

**Scope:**
- Library: `lightsaml/lightsaml-symfony-bridge` (battle-tested PHP SAML library)
- Per-tenant SP config — entity ID `https://pim.cortex.pl/saml/{tenant_slug}`, ACS URL `https://pim.cortex.pl/saml/{tenant_slug}/acs`
- IdP metadata upload UI (Phase 5) — config `sso_providers.config` zawiera IdP metadata XML, encryption cert, signing cert
- GET `/api/auth/sso/saml/{tenant_slug}/start` — redirect z SAML AuthnRequest
- POST `/api/auth/sso/saml/{tenant_slug}/acs` — handle SAML Response (parse, verify signature, extract attributes)
- Attribute mapping — `email`, `name`, optional `groups` (auto-assign roles)
- Account matching: `sso_subject_id` from NameID assertion
- Replay attack prevention — InResponseTo, NotOnOrAfter validation

**Acceptance criteria:**
- [ ] AC-1: Upload IdP metadata XML → parsed, valid, certs stored
- [ ] AC-2: Login start → SAML AuthnRequest redirect do IdP
- [ ] AC-3: ACS callback z valid signed Response → user matched, JWT issued
- [ ] AC-4: ACS callback z manipulated signature → 401
- [ ] AC-5: Replay attack (same Response submitted 2×) → second rejected
- [ ] AC-6: XXE attack test (malicious XML entity) → blocked by library
- [ ] AC-7: Audit log per SAML login

**Files affected:** `src/Identity/Service/SamlService.php`, `src/Identity/Controller/SamlController.php`, `config/packages/lightsaml.yaml`

**Testing requirements:**
- Integration: mock SAML IdP (samltest.id lub similar)
- Security: signature manipulation, replay, XXE tests
- Manual smoke: real Okta dev account integration

**DoD:** Standard + AC + manual smoke z real IdP (Okta dev tenant).

---

## Phase 2 zakończony — deliverables

Po merge wszystkich 14 ticketów:
- ✅ JWT auth (RS256, 15min access + 7d refresh)
- ✅ Email + password z rate limiting + account lockout
- ✅ API token auth z scope enforcement
- ✅ Tenant Context Resolver + Doctrine TenantFilter (auto)
- ✅ Postgres RLS aktywacja (defence in depth)
- ✅ Permission Resolver z Redis cache + event invalidation
- ✅ /api/me endpoint
- ✅ Magic link invitation flow
- ✅ Password reset flow
- ✅ MFA email TOTP + Google Authenticator
- ✅ SSO Google Workspace + Microsoft 365 + SAML 2.0

**Phase 2 → Phase 3 transition:** auth complete, ale **endpointy domenowe nie chronione**. Phase 3 dodaje Voters i field-level filtering.

**Estymacja Phase 2: ~80-110h. 14 ticketów. Realne tempo: 3 tygodnie solo dev.**
