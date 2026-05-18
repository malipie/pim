# RBAC — tickety Phase 6 (Refactor existing + hardening)

**Typ dokumentu:** Backlog ticketów Phase 6 RBAC — ready-to-paste GitHub Issues
**Status:** Draft — gotowe do utworzenia po zakończeniu Phase 5
**Powiązane:** [`07-rbac-implementation-plan.md`](07-rbac-implementation-plan.md) §4.7

> **Cel Phase 6:** Refactor istniejących endpointów (~60) i komponentów UI z RBAC integration. Plus final tooling lockdown — CI gates, mutation testing thresholds, Semgrep custom rules, Prometheus dashboards, OWASP ZAP.
>
> **Harmonogram:** tygodnie 14-16, **~60-90h**. 10 ticketów.

---

## Graf zależności Phase 6

```
RBAC-P6-001 (Audit existing endpoints — checklist creation)
        │
        ▼
RBAC-P6-002..004 (Add @RequiresPermission per module) — parallel
        │
        ▼
RBAC-P6-005 (Wrap UI components w PermissionGate)
        │
        ▼
RBAC-P6-006 (Regenerate OpenAPI spec)
        │
        ▼
RBAC-P6-007 (Update existing tests z permission scenarios)
        │
        ▼
RBAC-P6-008..010 (CI gates + dashboards + final tooling)
```

---

## RBAC-P6-001: chore(audit): audit existing endpoints — utworzenie checklist + tracking

**Typ:** `chore` | **Phase:** 6 | **Estymacja:** **3-5h**

**Dependencies:** Blocked by Phase 5 complete.

**Risk flags:** Forgotten endpoint = unguarded = security hole. Comprehensive scan z PHPStan + manual review.

**Cel:** Pełna inventaryzacja istniejących endpointów (~60 z epików 0.1-0.6). Lista per endpoint: name, file path, current permission status, expected permission, ownership status, risk level. Czeka na RBAC-P6-002..004 do egzekucji.

**Scope:**
- Run PHPStan z custom rule RBAC-P1-010 (RequiresPermissionAnnotationRule) → lista violations
- Run grep dla wszystkich `#[Route]` attributes w `src/**/*.php`
- Manual review każdy endpoint:
  - Module mapping (catalog, modeling, integration, etc.)
  - Expected action (view/add/edit/delete based on HTTP verb)
  - Ownership semantics (own vs all)
  - Risk level (HIGH = destructive lub sensitive data, MEDIUM = data write, LOW = read)
- Output: `docs/rbac-audit/existing-endpoints-checklist.md` z table:
  ```
  | Endpoint | File | Module | Action | Owner check | Risk | Status |
  | POST /api/products | ProductController::create | products | add | N/A | HIGH | TODO |
  | GET /api/products/{id} | ProductController::show | products | view | N/A | MEDIUM | TODO |
  ...
  ```
- Plus list wszystkich UI components z imports `useList`, `useCreate`, `useDelete` (Refine) — potrzebują PermissionGate

**Acceptance criteria:**
- [ ] AC-1: Checklist zawiera 100% endpoints (verify: grep `#[Route]` count matches list)
- [ ] AC-2: Per endpoint: module + action + risk level
- [ ] AC-3: PHPStan custom rule violations 100% pokryte
- [ ] AC-4: UI components inventory (komponenty bez `<PermissionGate>`)

**Files affected:** `docs/rbac-audit/existing-endpoints-checklist.md` (new), `docs/rbac-audit/existing-ui-components-checklist.md` (new)

**DoD:** Standard + AC + manual review pass.

---

## RBAC-P6-002: feat(catalog): add #[RequiresPermission] to existing Product endpoints

**Typ:** `feat` | **Phase:** 6 | **Estymacja:** **6-10h**

**Dependencies:** Blocked by RBAC-P6-001 (checklist) + Phase 3 (`#[RequiresPermission]` infrastructure).

**Risk flags:** Migration risk — endpoint nagle wymaga permission, klient z bez permission → 403 break existing workflow. Backward-compatibility check critical.

**Cel:** Dodać `#[RequiresPermission]` do wszystkich existing Product/ProductValue/ProductVariant endpoints. Plus ProductVoter z RBAC-P3-002 integration.

**Scope:**
- Per checklist RBAC-P6-001 — iterate Product endpoints (~12-15 endpoints):
  - GET /api/products → `#[RequiresPermission(module: 'products', action: 'view')]`
  - POST /api/products → `#[RequiresPermission(module: 'products', action: 'add')]`
  - PATCH /api/products/{id} → `#[RequiresPermission(module: 'products', action: 'edit', subject: 'product')]`
  - DELETE /api/products/{id} → `#[RequiresPermission(module: 'products', action: 'delete', subject: 'product')]`
  - POST /api/products/bulk-actions/* → `#[RequiresPermission(module: 'products', action: 'bulk_operations')]`
  - POST /api/products/{id}/publish → `#[RequiresPermission(module: 'publications', action: 'publish_unpublish')]`
- Per endpoint dodać integration test scenarios (Layer 2):
  - Allowed role → success
  - Denied role → 403
  - Unauthenticated → 401
- Resource policy delegation — subject parameter triggers ProductVoter
- Update istniejące tests które używały hardcoded role checks → użyj Voter

**Acceptance criteria:**
- [ ] AC-1: Wszystkie Product endpoints (~12-15) mają `#[RequiresPermission]`
- [ ] AC-2: Custom PHPStan rule RBAC-P1-010 zwraca 0 violations dla Product module
- [ ] AC-3: Integration tests pokrywają 3 scenarios per endpoint (allowed/denied/unauthenticated)
- [ ] AC-4: Smoke test: Magda próba DELETE product → 403 z RFC 7807 message
- [ ] AC-5: Smoke test: Catalog Manager DELETE product → 200

**Files affected:** `src/Catalog/Controller/ProductController.php`, `src/Catalog/Controller/ProductValueController.php`, `src/Catalog/Controller/ProductVariantController.php`, related test files.

**Testing requirements:**
- Integration: 3 scenarios per endpoint
- E2E: per persona Playwright

**DoD:** Standard + AC + CI green.

---

## RBAC-P6-003: feat(catalog,asset,modeling): add #[RequiresPermission] to Category/Asset/Modeling endpoints

**Typ:** `feat` | **Phase:** 6 | **Estymacja:** **6-10h**

**Dependencies:** Blocked by RBAC-P6-001.

**Risk flags:** Shared pattern z RBAC-P6-002 — same migration risk.

**Cel:** Dodać `#[RequiresPermission]` do Category, Asset, ObjectType, Attribute, AttributeGroup endpoints. Plus respective Voters z Phase 3.

**Scope:**
- Category endpoints (~6-8): CRUD + tree operations
- Asset endpoints (~5-7): CRUD + upload + ownership
- ObjectType endpoints (~5-6): CRUD + is_built_in protection
- Attribute endpoints (~6-8): CRUD + restricted_roles management
- AttributeGroup endpoints (~4-5): CRUD
- Integration tests per scenarios
- Ownership check dla Asset endpoints — `subject` parameter triggers AssetVoter z ownership logic

**Acceptance criteria:**
- [ ] AC-1: Wszystkie 26-34 endpoints mają `#[RequiresPermission]`
- [ ] AC-2: Custom PHPStan rule 0 violations dla Category/Asset/Modeling
- [ ] AC-3: Integration tests pokrywają scenarios
- [ ] AC-4: Modeler może DELETE custom ObjectType, NIE built-in (test)
- [ ] AC-5: Marketing edit Asset own upload OK, edit cudzego → 403

**Files affected:** Multiple controllers w `src/Catalog/`, `src/Asset/`, `src/Modeling/`.

**DoD:** Standard + AC + CI green.

---

## RBAC-P6-004: feat(import,export,workflow,channel): add #[RequiresPermission] to remaining endpoints

**Typ:** `feat` | **Phase:** 6 | **Estymacja:** **6-10h**

**Dependencies:** Blocked by RBAC-P6-001.

**Cel:** Pokryć remaining endpoints: Imports, Exports, Workflow, Channel/Integrations, Audit logs.

**Scope:**
- Imports endpoints (~5-6): run, view sessions, status
- Exports endpoints (~6-8): run, view sessions (own/all), saved profiles CRUD
- Workflow endpoints (~4-6): state transitions, approve/reject
- Channel + Integrations endpoints (~6-10): config, publish, webhooks
- Audit log endpoints (~3-4): view (own/cross-user/platform)
- Cmd+K agent endpoints (~2-3): execute, history

**Acceptance criteria:**
- [ ] AC-1: Wszystkie ~26-37 endpoints mają `#[RequiresPermission]`
- [ ] AC-2: Custom PHPStan rule 0 violations w całym projekcie
- [ ] AC-3: Integration tests scenarios
- [ ] AC-4: Approver może approve workflow transition, Marketing → 403

**Files affected:** Multiple controllers w `src/Import/`, `src/Export/`, `src/Workflow/`, `src/Integration/`, `src/Audit/`.

**DoD:** Standard + AC + CI green + final PHPStan zero violations.

---

## RBAC-P6-005: feat(admin): wrap existing UI components in <PermissionGate>

**Typ:** `feat` | **Phase:** 6 | **Estymacja:** **8-12h**

**Dependencies:** Blocked by RBAC-P6-001 (UI components checklist), RBAC-P4-004 (`<PermissionGate>` exists).

**Risk flags:** UI feature blackouts po refactor jeśli permission code nie matches macierz. Smoke test per persona crucial.

**Cel:** Refactor istniejące UI components z RBAC integration. Sidebar, toolbars, action menus, bulk operations, modals — wszystko gated.

**Scope:**
- Sidebar nav refactor (RBAC-P4-005 already covered, but verify all items)
- Per epik 0.6 Admin UI components:
  - Product list toolbar — `+ New`, `Bulk edit`, `Delete selected`, `Export`, `Import` buttons gated
  - Product detail page — Save, Delete, Publish, Duplicate buttons gated
  - Bulk wizard — actions filtered per permission
  - Category tree — Add child, Edit, Delete actions gated
  - Modeling — Attribute Add/Edit/Delete buttons gated
  - Workflow — Approve/Reject buttons gated
  - Integrations list — Configure, Test, Disable actions gated
- Plus form field gating — Phase 4 RBAC-P4-009 dla product detail forms
- Plus Cmd+K palette integration (RBAC-P4-011 already covered, verify)

**Acceptance criteria:**
- [ ] AC-1: Per checklist RBAC-P6-001 — wszystkie UI components updated
- [ ] AC-2: E2E test per persona — Magda widzi expected buttons, Marketing NIE widzi Delete
- [ ] AC-3: Cmd+K palette filtered (already covered, verify in E2E)
- [ ] AC-4: Product detail form fields per attribute_restrictions (RBAC-P4-009 verify)

**Files affected:** Multiple components w `apps/admin/src/`.

**Testing requirements:**
- E2E Playwright per persona — comprehensive UI flow test

**DoD:** Standard + AC + Playwright per-persona test pass.

---

## RBAC-P6-006: chore(api-spec): regenerate OpenAPI spec z permission annotations

**Typ:** `chore` | **Phase:** 6 | **Estymacja:** **3-5h**

**Dependencies:** Blocked by RBAC-P6-002..004.

**Cel:** API Platform OpenAPI export uwzględnia `#[RequiresPermission]` annotations w docs. Integration partners widzą required permission per endpoint w `/api/docs.jsonopenapi`.

**Scope:**
- API Platform 4 OpenAPI generator extension — read `#[RequiresPermission]` attribute z controller, add to OpenAPI `x-cortex-permission` extension per operation
- Export `docs/api-spec/v1.0.jsonopenapi` (per CLAUDE.md convention) — versionowany snapshot
- Update CI step — regenerate OpenAPI przy każdym release tag
- Documentation:
  - Per endpoint w OpenAPI: `x-cortex-permission: "products.edit"`
  - Optional `x-cortex-permission-description`: human-readable explanation

**Acceptance criteria:**
- [ ] AC-1: OpenAPI spec eksportowany z permission extension
- [ ] AC-2: `/api/docs.jsonopenapi` zawiera `x-cortex-permission` per operation
- [ ] AC-3: API Platform Swagger UI pokazuje permission info
- [ ] AC-4: CI regeneration step works

**Files affected:** `src/Identity/OpenApi/PermissionExtension.php`, `docs/api-spec/v1.0.jsonopenapi`

**DoD:** Standard + AC.

---

## RBAC-P6-007: chore(tests): update existing tests z permission scenarios

**Typ:** `chore` | **Phase:** 6 | **Estymacja:** **8-12h**

**Dependencies:** Blocked by RBAC-P6-002..004.

**Risk flags:** Existing tests may break gdy endpointy teraz wymagają auth + permissions. Refactor + extend.

**Cel:** Update existing tests (post epik 0.6 = ~200+ test classes) — dodać auth setup + permission scenarios. Standardize auth fixture w `IntegrationTestCase` (RBAC-P1-009).

**Scope:**
- `IntegrationTestCase::loginAs($persona)` helper method — accepts persona string ('owner', 'marketing', 'modeler'), creates user z roles, logs in, returns JWT
- Refactor existing tests:
  - Replace anonymous endpoint calls → `loginAs('owner')` then call
  - Add denied scenario per endpoint where applicable
  - Update fixtures z user/role setup
- Coverage thresholds w `phpunit.xml`:
  - Identity bundle: 95% line, 90% branch, 80% MSI (mutation)
  - Catalog/Modeling/Integration: 85% line, 75% MSI
  - Reszta: 80% line, 60% MSI

**Acceptance criteria:**
- [ ] AC-1: Wszystkie existing tests pass po update
- [ ] AC-2: Coverage thresholds met per bundle
- [ ] AC-3: Permission denied scenarios pokryte (sample 30% endpoints — random selection)
- [ ] AC-4: `loginAs()` helper documented w `docs/testing/integration-tests.md`

**Files affected:** Multiple test files w `tests/`, `tests/IntegrationTestCase.php` extension.

**DoD:** Standard + AC + CI green z thresholds.

---

## RBAC-P6-008: chore(ci): final CI gates — coverage thresholds + mutation testing thresholds

**Typ:** `chore` | **Phase:** 6 | **Estymacja:** **3-5h**

**Dependencies:** Blocked by RBAC-P6-007.

**Cel:** Finalize CI configuration z hard gates: PR nie merge bez coverage 95%+ Identity, MSI 80%+ Identity, mutation testing pass, cross-tenant isolation suite pass, Playwright E2E RBAC suite pass.

**Scope:**
- GitHub Actions workflow `ci-rbac-gates.yml`:
  - Job: PHPStan max (existing)
  - Job: Coverage Identity ≥95% line, ≥90% branch
  - Job: Coverage global ≥80%
  - Job: Mutation testing Identity ≥80% MSI
  - Job: Cross-tenant isolation suite (must pass 100%, no skip)
  - Job: Playwright E2E RBAC scenarios
  - Job: composer audit (no HIGH/CRITICAL CVE)
  - Job: npm audit
  - Job: secret scan (TruffleHog + GitLeaks)
- Branch protection rule — main branch wymaga wszystkie te jobs pass
- PR template (`.github/PULL_REQUEST_TEMPLATE.md`) z checklistą security:
  - [ ] AC criteria met
  - [ ] Coverage thresholds
  - [ ] Cross-tenant test pass
  - [ ] Security smoke test executed
  - [ ] PR description includes evidence (output paste)

**Acceptance criteria:**
- [ ] AC-1: CI workflow zawiera 8+ jobs z gates
- [ ] AC-2: Branch protection enforced (PR z fail nie merge)
- [ ] AC-3: PR template visible przy każdym new PR
- [ ] AC-4: Intencjonalne fail (drop coverage poniżej threshold) blokuje merge

**Files affected:** `.github/workflows/ci-rbac-gates.yml`, `.github/PULL_REQUEST_TEMPLATE.md`, repo settings.

**DoD:** Standard + AC + test fail/pass scenarios verified.

---

## RBAC-P6-009: chore(observability): Prometheus + Grafana RBAC dashboards

**Typ:** `chore` | **Phase:** 6 | **Estymacja:** **5-7h**

**Dependencies:** Blocked by Phase 3 (audit log z `permission_check_result`).

**Risk flags:** Alert fatigue — over-aggressive alerts ignored. Tune thresholds carefully.

**Cel:** RBAC-specific dashboards w Grafana z metrics z audit log + Prometheus. Plus alerts dla critical events.

**Scope:**
- Prometheus metrics expose (via Symfony Messenger event subscribers):
  - `cortex_permission_denied_total` (counter, labels: tenant, role, permission)
  - `cortex_cross_tenant_access_total` (counter, labels: super_admin_id)
  - `cortex_api_token_created_total` (counter, labels: tenant, scope)
  - `cortex_mfa_enrollment_percentage` (gauge, labels: tenant)
  - `cortex_failed_login_attempts_total` (counter, labels: tenant)
  - `cortex_super_admin_recovery_total` (counter)
- Grafana dashboard `RBAC Overview`:
  - Panel: 403 denials rate (5min window)
  - Panel: Cross-tenant access events
  - Panel: MFA enrollment % per tenant
  - Panel: Failed login spikes
  - Panel: Super Admin recovery actions (audit-grade visibility)
- Alerts:
  - `HighRateOf403Denials` — >10/min from single IP, severity warning
  - `SuperAdminRecoveryUsed` — info-level, always log
  - `AnomalousFailedLogins` — >50 failed logins in 5min from single IP, severity critical (potential brute force)

**Acceptance criteria:**
- [ ] AC-1: 6+ Prometheus metrics expose
- [ ] AC-2: Grafana dashboard z 5+ panels
- [ ] AC-3: 3+ alerts configured z PagerDuty/Slack integration
- [ ] AC-4: Manual smoke: trigger 403 spike, alert fires

**Files affected:** `config/packages/prometheus.yaml`, `docs/operations/grafana-dashboards/rbac.json`, alert rules config.

**DoD:** Standard + AC + manual alert test.

---

## RBAC-P6-010: chore(static-analysis): Semgrep custom rules + final tooling lockdown

**Typ:** `chore` | **Phase:** 6 | **Estymacja:** **5-8h**

**Dependencies:** Blocked by RBAC-P1-001 (initial Semgrep setup).

**Cel:** Cortex-specific Semgrep custom rules + final review tooling stack przed go-live.

**Scope:**
- Custom Semgrep rules w `.semgrep/cortex-rbac.yml`:
  - Detect missing `tenant_id` w nowych encjach (pattern: `class.*Entity` bez `private string $tenantId`)
  - Detect direct role string check w controllers (pattern: `if ($user->hasRole('admin'))`)
  - Detect direct DB query bez TenantFilter (pattern: raw SQL z `WHERE` ale bez `tenant_id`)
  - Detect missing `#[RequiresPermission]` (redundant z PHPStan, ale Semgrep catches earlier)
  - Detect plaintext secrets w config (`access_token: "shpat_..."`)
  - Detect `$_GET` lub `$_POST` direct usage (should use Symfony Request)
  - Detect SQL injection patterns w raw queries
- Run Semgrep w CI:
  - PR check — block on CRITICAL findings
  - Nightly scan — full repo, alert on new findings
- Final review:
  - Verify Infection PHP installed + thresholds met
  - Verify OWASP ZAP nightly running
  - Verify TruffleHog + GitLeaks in pre-commit + CI
  - Verify Dependabot config
  - Verify Roave Security Advisories installed
- Documentation `docs/security/tooling-final.md` z complete tool list + thresholds.

**Acceptance criteria:**
- [ ] AC-1: 7+ Semgrep custom rules w `.semgrep/cortex-rbac.yml`
- [ ] AC-2: CI Semgrep check pass dla current codebase (no false positives)
- [ ] AC-3: Intencjonalne violation (sample bad pattern) → Semgrep catches
- [ ] AC-4: Tooling final review checklist completed (10+ tools verified active)
- [ ] AC-5: Documentation `tooling-final.md` z complete list

**Files affected:** `.semgrep/cortex-rbac.yml`, `docs/security/tooling-final.md`, CI workflows.

**DoD:** Standard + AC.

---

## Phase 6 zakończony — deliverables

Po merge 10 ticketów:
- ✅ Audit existing endpoints — 100% pokrycie checklist
- ✅ ~60 existing endpoints z `#[RequiresPermission]`
- ✅ UI components wrapped w `<PermissionGate>` per checklist
- ✅ OpenAPI spec z permission annotations
- ✅ Existing tests updated z permission scenarios + coverage thresholds met
- ✅ CI gates final — 8+ jobs z block-on-fail
- ✅ Prometheus + Grafana RBAC dashboards + alerts
- ✅ Semgrep custom rules + final tooling lockdown

**Phase 6 → Phase 7:** wszystko zaimplementowane + protected. Phase 7 = manual + external pentest + go-live preparations.

**Estymacja Phase 6: ~60-90h. 10 ticketów. Tempo: 2 tygodnie.**
