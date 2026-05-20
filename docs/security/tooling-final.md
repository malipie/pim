# Cortex PIM — Final security tooling stack (Phase 6 lockdown)

> Closes Phase 6 RBAC ticket #722 (`chore(static-analysis): Semgrep custom rules + final tooling lockdown`). The stack below is what every PR must satisfy before merge after the Phase 7 launch gate.

## Static analysis

| Tool | Layer | Threshold | Gate |
| --- | --- | --- | --- |
| **PHPStan** (`phpstan/phpstan` v2) | type + custom-rule | level `max` ; baseline empty | CI `Quality (PHP)` workflow — required for merge to `main` |
| **`RequiresPermissionAnnotationRule`** | custom rule | every `/api/*` controller method carries `#[RequiresPermission]` or `#[NoPermissionRequired]` | PHPStan-driven; reinforced by Semgrep `cortex-requires-permission-attribute-missing` |
| **Biome** | TS lint + format | `strict` | CI `Quality (Frontend)` workflow |
| **TypeScript** | type | `noEmit` strict | CI `Quality (Frontend)` workflow |
| **PHP-CS-Fixer** | format (PSR-12 + project rules) | dry-run must be clean | CI `PHP-CS-Fixer (dry-run)` |
| **Deptrac** | architectural fitness | no boundary violation | CI `Deptrac (architectural fitness)` |
| **Semgrep** (`.semgrep/cortex-rbac.yml`) | Cortex-specific | 8 rules — see below | Recommended pre-commit + nightly full-repo scan |
| **`scripts/lint-raw-sql.sh`** | tenant-safe SQL grep | no untagged WHERE without tenant_id | CI `Raw SQL tenant-safe lint` |

### Semgrep rules — `.semgrep/cortex-rbac.yml`

1. `cortex-entity-missing-tenant-id` — every `#[ORM\Entity]` declares `tenantId` (INFRA_TABLES allow-listed)
2. `cortex-no-direct-role-string-check` — bans `hasRole('admin')` / `in_array('ROLE_X', getRoles())`; forces `$security->isGranted('PRD_code')`
3. `cortex-raw-sql-missing-tenant-filter` — flags DBAL `WHERE` queries without explicit `tenant_id` predicate
4. `cortex-no-plaintext-shopify-token` — catches `shpat_*` / `shpss_*` / `shppa_*` committed to repo
5. `cortex-no-plaintext-baselinker-token` — catches `X-BLToken: …` literal in YAML/JSON/PHP
6. `cortex-no-superglobals` — bans `$_GET` / `$_POST` / `$_REQUEST` / `$_COOKIE` in `apps/api/src` (Symfony Request only)
7. `cortex-sql-injection-string-interpolation` — bans string interpolation into `executeQuery` / `fetch*` SQL
8. `cortex-requires-permission-attribute-missing` — same shape as the PHPStan rule, earlier feedback loop

## Dependency security

| Tool | Layer | Cadence |
| --- | --- | --- |
| **`composer audit`** | PHP CVE | CI on every PR |
| **`pnpm audit`** | JS CVE | CI on every PR |
| **Roave Security Advisories** | composer-resolve-time CVE block | runtime via `composer.json` |
| **Dependabot** | dependency PR opener | weekly automerge for patch, manual review for minor/major |

## Secret scanning

| Tool | Layer | Cadence |
| --- | --- | --- |
| **TruffleHog** (entropy + verified) | git history + working tree | CI on every PR + pre-push hook |
| **GitLeaks** (regex-based) | git history + working tree | CI on every PR + pre-push hook |

## Tests

| Layer | Tool | Threshold |
| --- | --- | --- |
| Unit | PHPUnit | ≥80% line global, ≥95% in `Identity` bundle |
| Integration | PHPUnit (`ApiTestCase`) | every endpoint exercised in 3 scenarios: allowed / denied (403) / unauthenticated (401) |
| Cross-tenant isolation | PHPUnit (`Layer 3` suite) | 100% pass — every voter / policy / query refuses cross-tenant reads |
| E2E | Playwright | per-persona scenarios — Owner / Catalog Manager / Marketing / Modeler / API Integrator / Viewer / Magda / Approver / Auditor / Super Admin |
| Mutation | Infection PHP | ≥80% MSI in `Identity` bundle, ≥75% in Catalog/Modeling/Integration |

## Observability — RBAC dashboards (#721)

| Metric | Where | Alert |
| --- | --- | --- |
| `cortex_permission_denied_total` | Prometheus counter | `>10/min from single IP` → warning |
| `cortex_cross_tenant_access_total` | Prometheus counter | Always log to audit-grade panel |
| `cortex_api_token_created_total` | Prometheus counter | — |
| `cortex_mfa_enrollment_percentage` | Prometheus gauge | — |
| `cortex_failed_login_attempts_total` | Prometheus counter | `>50/5min from single IP` → critical |
| `cortex_super_admin_recovery_total` | Prometheus counter | Always notify Slack `#security` |

## CI lockdown order (Phase 6 → Phase 7)

1. **#714/#715/#716/#719** — every controller method tagged ✅
2. **#720** — branch protection requires every quality gate green
3. **#722** — Semgrep rules + tooling docs (this file)
4. **#721** — Prometheus + Grafana dashboards live
5. **Phase 7 #723–#728** — manual red-team checklist + optional external pentest + soft launch
6. **`EndpointGuardListener::$strictMode`** flipped to `true` — runtime gate locks down

## Local pre-commit hook (optional)

Add to `.husky/pre-commit` (or run as `pnpm exec semgrep` after editing PHP):

```sh
docker run --rm -v "$PWD:/src" semgrep/semgrep:latest \
  semgrep --config /src/.semgrep/cortex-rbac.yml --error /src/apps/api/src
```

CI runs the same command nightly against full repo (`make ci:semgrep`).

## Out-of-scope tooling (deferred to Phase 1+)

- **OWASP ZAP nightly scan** — depends on stable staging environment; ships with Phase 1 first pilot deployment
- **SOC 2 / ISO 27001 control coverage** — Phase 3 SaaS phase
- **Threat model (`docs/security/threat-model.md`)** — STRIDE write-up, Phase 6 ticket #720 follow-up or Phase 7 ticket
- **Security checklist for PR review** — `docs/security/security-checklist.md`, lands with #720 PR template
