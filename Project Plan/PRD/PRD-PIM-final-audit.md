# PRD — Cortex PIM: Final Audit (Security + Wydajność + Jakość kodu)

**Typ dokumentu:** Product Requirements Document — Final audit przed go-live
**Status:** 🔵 **Placeholder** — nie wykonujemy teraz, aktywujemy w określonym momencie cyklu (patrz §7 Trigger conditions)
**Data utworzenia:** 2026-05-16
**Wersja:** 1.0 (placeholder)
**Adresat:** Marcin (solo dev) — operator decyzji *„kiedy aktywujemy"*
**Powiązane dokumenty:**
- [`07-rbac-implementation-plan.md`](../07-rbac-implementation-plan.md) — istniejący plan tooling/testing dla RBAC (overlap §6)
- [`PRD/PRD-PIM-rbac.md`](PRD-PIM-rbac.md) — RBAC master spec
- [`PRD/PRD-PIM-list-advanced.md`](PRD-PIM-list-advanced.md), [`PRD/PRD-PIM-exports.md`](PRD-PIM-exports.md) — feature PRDs z own risk registers

> **Nota o scope.** To **placeholder PRD** — dokument który eksponuje świadomie odłożoną pracę żeby nie zniknęła z radaru. Nie generuje ticketów ani działań. Aktywacja = świadoma decyzja Marcina (Trigger conditions §7).

---

## 1. Cel dokumentu

Final audit jest **meta-warstwą** ponad istniejącym tools stack (`07-rbac-implementation-plan.md` §3). Eksponuje 3 elementy które nasz obecny plan **nie ma explicit**:

1. **Release Readiness Checklist** — jeden artefakt z must-pass gates per release
2. **SLO/SLA dokument** — twarde progi wydajności (p95/p99 latency, throughput, error budgets, memory limits)
3. **Risk Register master** — rolled-up rejestr ryzyk cross-PRD z status per risk

Plus **performance tooling** którego brakuje w obecnym RBAC plan (~7 narzędzi).

Razem: **świadomy ship-readiness layer** — przejście z *„tools stack done"* na *„release decision can be made"*.

## 2. Trzy warstwy audytu (industry standard)

Audit dzielimy na 3 warstwy zgodnie z best practices dla SaaS B2B przed go-live:

### 2.1 Jakość kodu

**Cel:** kod ma poziom *„maintained by team for 5 lat"*, nie *„LLM-generated tech debt"*.

**Co już mamy** (z `07-rbac-implementation-plan.md` §3.2):
- PHPStan max + 3 custom rules (RequiresPermissionAnnotationRule, FlushWithoutClearRule, HardcodedRoleCheckRule)
- Biome strict (TypeScript frontend)
- Semgrep + custom OWASP rules
- Infection PHP mutation testing (MSI 80%+ Identity)
- PHPUnit + ApiTestCase + Playwright

**Czego brakuje:**
- **Deptrac** — DDD bounded contexts enforcement (Catalog / Channel / Asset / Integration / Identity / Agent / ApiConfigurator). Bez tego po 6 miesiącach kod ma cross-context imports → architectural debt. *„Severity: HIGH"* (bo robimy DDD).
- **QueryCountAssertions w testach** — Doctrine N+1 detection automatic, bez tego N+1 wykryjemy tylko przez Blackfire lub manual review. *„Severity: MEDIUM"*.
- **Rector** — automated refactoring + Symfony major bumps (Symfony 7.4 → 8.0 ETA ~2027). *„Severity: MEDIUM"* (long-term maintainability).
- **Knip** — frontend dead code detection (TypeScript unused exports). *„Severity: MEDIUM"* (drobne, ale kumuluje się).

**Świadomie odrzucone:** Psalm strict (per `Project Plan/06-sprint-0-findings.md`), PHP-CS-Fixer/ECS (PHPStan + Biome pokrywają 95%).

### 2.2 Bezpieczeństwo

**Cel:** zero CRITICAL/HIGH findings przed onboardingiem pierwszego płacącego klienta. Pełen audit trail. Compliance-ready (RODO, future ISO 27001 / SOC 2).

**Co już mamy** (z `07-rbac-implementation-plan.md` §3.1, §3.3, §3.5, RBAC-P7):
- Dependency scanning: Roave Security Advisories, Symfony Security Checker, Dependabot, OWASP Dependency-Check, npm audit, Snyk free tier
- Secret scanning: TruffleHog + GitLeaks (pre-commit + CI)
- Multi-tenancy isolation: Doctrine TenantFilter + Postgres RLS + dedicated cross-tenant test suite (Layer 3 z `07-plan` §2.2)
- Cross-tenant fuzz testing (property-based 100 users × 10 tenants)
- DAST: OWASP ZAP nightly w CI
- Manual red-team Marcina (RBAC-P7-001, 15-point checklist)
- External pentest preparation + execution (RBAC-P7-002, P7-003)
- Encryption: Postgres at-rest, MinIO at-rest, TLS 1.3 in-transit, AES-256-GCM dla secretów (MFA, integration tokens)
- API token scopes + auto-rotation reminders

**Czego brakuje:**
- **BYOK Anthropic credit encryption review** — wzmiankowane w PRD ale brak dedykowanego ticketu z verification (kto ma dostęp do kluczy klientów, jak rotation działa). *„Severity: HIGH"* (gdy mamy klientów Pro/Enterprise z BYOK).
- **Compliance gap analysis** — RODO checklist (Right to be Forgotten, Data Portability), future ISO 27001 / SOC 2 gap assessment. *„Severity: MEDIUM"* (post-launch, ale przed enterprise sales).

### 2.3 Wydajność

**Cel:** SLA achievable dla pierwszych design partners. Performance benchmarki na **realistic dataset** (100k-500k SKU per industry standard PIM mid-market).

**Co już mamy** (z `07-rbac-implementation-plan.md` §3.4):
- Prometheus alert `frankenphp_worker_memory_bytes > 256MB`
- Grafana RBAC-specific dashboards (RBAC-P6-009)
- Performance targets w ticketach (np. RBAC-P5-007 export <30s dla 50k SKU)
- Per-permission cache w Redis (PermissionResolver TTL 5min + event invalidation)
- Memory safety patterns (FrankenPHP worker mode — `EntityManager::clear()` po batch flush)

**Czego brakuje (NAJWIĘKSZA LUKA):**
- **Blackfire lub Tideways** — production profiler dla PHP. Bez tego performance debugging w produkcji = guess work. Prometheus mówi *„memory wzrosło"* — Blackfire mówi *„w funkcji X, wiersz Y, na zapytaniu Z"*. *„Severity: HIGH"* — wymóg dla FrankenPHP worker mode + long-running operations.
- **k6 lub Gatling load testing** — zero ticketów dla load testów. Bez tego nie wiemy czy SaaS wytrzyma 100 concurrent users × 5 tenantów. *„Severity: HIGH"* — przed soft launch MUSI być.
- **Realistic dataset fixtures (100k-500k SKU)** — wzmiankowane w PRD ale brak setup. Bez tego benchmarki są symboliczne (50k MVP target jest skromny vs industry 100k-500k). *„Severity: HIGH"* — prereq dla pentest + load testing + performance benchmarks.
- **EXPLAIN ANALYZE strategy + Postgres `auto_explain`** — implicit pokryte przez Blackfire, ale dedicated strategy wartościowa. *„Severity: LOW"* (covered indirectly).

## 3. Trzy artefakty meta-procesowe

Oprócz tooling gaps, audit potrzebuje **3 dokumentów decyzyjnych** które obecny plan nie ma explicit:

### 3.1 Release Readiness Checklist

**Lokalizacja:** `docs/release/release-readiness-checklist.md` (do utworzenia)

**Cel:** jeden master checklist must-pass dla każdego release/phase milestone. Konkretne progi + kto akceptuje + artefakty.

**Treść (template):**

```markdown
## Release Readiness Checklist — Cortex PIM v{X.Y}

### Quality gates (must-pass — blocker if red)
- [ ] PHPStan max ✓ (0 violations w wszystkich bundles)
- [ ] Biome strict ✓ (0 errors frontend)
- [ ] Test coverage ≥ 95% Identity bundle, ≥ 80% global
- [ ] Mutation Score Indicator (MSI) ≥ 80% Identity, ≥ 70% global
- [ ] Cross-tenant isolation suite — 100% pass (0 skips allowed)
- [ ] Field-level scrubbing suite — 100% pass
- [ ] Playwright E2E RBAC scenarios — 100% pass
- [ ] Deptrac DDD enforcement — 0 cross-context violations

### Security gates (must-pass)
- [ ] Zero CRITICAL/HIGH vuln w `composer audit` + `npm audit`
- [ ] Zero CRITICAL OWASP ZAP findings w nightly scan
- [ ] Manual red-team Marcina (15-point) — 100% pass lub fix
- [ ] External pentest (jeśli budżet) — 0 CRITICAL open
- [ ] Secret scan (TruffleHog + GitLeaks) — 0 leaked secrets w repo
- [ ] Custom PHPStan rules — 0 violations w całym repo

### Performance gates (must-pass — wymagają SLO doc §3.2)
- [ ] p95 API latency < SLO threshold na realistic dataset
- [ ] FrankenPHP worker memory < 256MB sustained
- [ ] Bulk operations spełniają SLO thresholds
- [ ] Zero N+1 queries w hot paths (Blackfire profile pass)
- [ ] k6 load test — meeting throughput SLO bez 5xx errors

### Process gates
- [ ] `agent/lessons.md` updated z lessons z tej fazy
- [ ] `agent/current_status.md` reflects current state
- [ ] Risk register (§3.3) reviewed — żadne unaddressed CRITICAL
- [ ] Change log updated
- [ ] User-facing docs updated (Privacy Policy, Terms, Security Overview)

### Sign-off (solo dev = self-checkpoint)
- [ ] Marcin self-review — checklist 100% green lub explicit waiver z uzasadnieniem
- [ ] Soft launch design partner #1 (po Phase 7 RBAC) — zero CRITICAL incidents w 4-week window
```

**Estymacja stworzenia:** ~3-4h. Bardzo wysokie ROI.

### 3.2 SLO/SLA dokument

**Lokalizacja:** `docs/operations/slo-sla.md` (do utworzenia)

**Cel:** twarde performance thresholds + availability targets per pricing tier. Wymagane przed pierwszą rozmową sales z design partner.

**Treść (template):**

```markdown
## Cortex PIM — SLO/SLA Document

### Availability SLO per tier
- Free / Trial: 99% uptime
- Starter: 99.5%
- Pro: 99.9%
- Enterprise: 99.95% + dedicated read replicas

### API Performance SLO (p95/p99 latency)
| Endpoint | Dataset | p95 | p99 |
|---|---|---|---|
| GET /api/products (paginated list) | 50k SKU | <300ms | <500ms |
| GET /api/products (paginated list) | 200k SKU | <400ms | <700ms |
| GET /api/products/{id} | N/A | <100ms | <200ms |
| PATCH /api/products/{id} | bulk session impact | <500ms | <1s |
| POST bulk-actions/edit-attribute (1000 produktów sync) | N/A | <30s | <60s |
| Cmd+K agent tool call | 50 calls/h limit | <3s | <5s |

### Throughput SLO
- Concurrent users per tenant: 50 sustained, 100 burst (1min)
- Bulk operations queue: max 3 concurrent per tenant (Pro), unlimited (Enterprise)
- Imports throughput: 5000 SKU/min dla XLSX

### Memory limits
- FrankenPHP worker: <256MB sustained, <512MB peak
- Redis cache: <1GB per tenant
- MinIO bucket: 50GB included (Pro), unlimited (Enterprise)

### Error rate budgets
- 5xx errors: <0.1% requests/dzień
- 401/403 (security denial): tracked w Grafana, alert > 10/min from single IP

### Performance test thresholds (release-blocking)
- p95 latency w SLO + 10% tolerance
- Throughput SLO must be met under load
- Zero N+1 w hot paths (Blackfire profile)
- Zero memory leaks (worker stable po 1h sustained load)
```

**Estymacja stworzenia + walidacja benchmarkami:** ~6-10h.

### 3.3 Risk Register master (cross-PRD)

**Lokalizacja:** `docs/risk-register.md` (do utworzenia)

**Cel:** rolled-up rejestr ryzyk z wszystkich PRD (RBAC §14, list-advanced §14, exports §14) z status per risk + monthly review.

**Status legend:**
- 🔴 **Open** — unaddressed, blocking release
- 🟡 **Mitigated** — fix in plan, tracked w ticket
- 🟢 **Accepted** — świadomie zaakceptowane (out of scope), reviewed quarterly
- ⚫ **Closed** — resolved, regression test in place

**Treść (template):**

```markdown
## Risk Register — Cortex PIM (Master)

| ID | Risk | Source PRD | Severity | Status | Mitigation | Owner | Review date |
|---|---|---|---|---|---|---|---|
| R-30 | List feature scope creep | list-advanced §14 | High | 🟡 | RBAC Phase 5-6 + cuts | Marcin | 2026-Q3 |
| R-40 | Multi-value serialization convention | exports §14 | Medium | 🟡 | Sprint 1 POC | Marcin | Phase 2 RBAC |
| R-42 | Performance 50k SKU benchmark | exports §14 | Medium | 🟡 | Blackfire profile Phase 6 | Marcin | Phase 6 RBAC |
| R-45 | Self-audit only w MVP | exports §14 | Medium | 🟡 | Cross-user audit deferred | Marcin | Post-launch |
| R-50 | RBAC scope creep +24-33h po 3-state | rbac §14 + v2.1 | Medium | 🟢 | Świadoma akceptacja | Marcin | Quarterly |
| ... | ... | ... | ... | ... | ... | ... | ... |
```

**Estymacja stworzenia (consolidacja):** ~2-3h + monthly maintenance ~30min.

## 4. Estymacja epiku Final Audit

Łączny scope (gdy aktywujemy):

| Element | Estymacja |
|---|---|
| **Tooling backend** | |
| Deptrac setup (DDD enforcement) | 6-10h |
| Blackfire profiler integration | 4-6h |
| QueryCountAssertions helper + tests | 3-5h |
| Rector setup | 2-4h |
| **Tooling frontend** | |
| Knip dead code detection | 2-3h |
| **Performance testing** | |
| k6 load testing setup + scenarios | 10-15h |
| Realistic dataset fixtures (100k+ SKU) | 4-6h |
| **Process artefakty** | |
| Release Readiness Checklist (§3.1) | 3-4h |
| SLO/SLA dokument + walidacja (§3.2) | 6-10h |
| Risk Register master (§3.3) | 2-3h |
| **Security deep-dive** | |
| BYOK Anthropic credit encryption review | 4-6h |
| Compliance gap analysis (RODO + ISO 27001 prep) | 4-8h |
| **TOTAL** | **~50-80h** |

~10-12 ticketów, **1.5-2 tygodnie solo dev**.

## 5. Sequencing opcje

**(A) Parallel z RBAC Phase 5-6** — uruchomienie ~tydzień 12-13 (po Phase 4 RBAC complete), żeby być ready na soft launch (RBAC-P7-006, tydzień 18-19). **Optymalne pod release timing.**

**(B) Po RBAC Phase 7 zakończonej** — start tydzień 19-20, rozszerzenie pełnego harmonogramu MVP do 21-23 tygodni. **Czystszy scope separation, ale opóźnia soft launch.**

**(C) Demand-driven** — start gdy pierwszy design partner zapyta o SLA / pentest report. Reaktywne. **Najtaniej teraz, najdroższy fix later.**

**Rekomendacja (gdy aktywujemy):** **(A) Parallel** — performance benchmarki są **prereq dla soft launch credibility** z design partners. Wbicie ich w równoległy tor zachowuje go-live timing.

## 6. Cross-cutting z istniejącymi planami

Audit **NIE duplikuje** istniejących planów — dopełnia je:

| Element audytu | Status w RBAC plan | Status w final audit |
|---|---|---|
| Tooling tests/lint/security | ✅ Full coverage | (zostaje w RBAC plan) |
| Pentest manual red-team | ✅ RBAC-P7-001 | (zostaje w RBAC plan) |
| External pentest | ✅ RBAC-P7-002, P7-003 | (zostaje w RBAC plan) |
| Cross-tenant isolation suite | ✅ Layer 3 | (zostaje w RBAC plan) |
| Deptrac, Blackfire, k6 | ❌ Brak | **➕ Tu dodajemy** |
| Realistic dataset 100k+ SKU | 🟡 Częściowo (50k MVP target) | **➕ Tu rozszerzamy** |
| Release Readiness Checklist | ❌ Brak (per-PR DoD only) | **➕ Tu dodajemy** |
| SLO/SLA | 🟡 SLA tabela w `docs/legal/security-overview.md` (RBAC-P7-005) | **➕ Tu rozszerzamy z performance SLO** |
| Risk register | 🟡 Per-PRD §14 (RBAC, list, exports) | **➕ Tu konsolidujemy** |

**Konsekwencja:** epik *„Final Audit"* nie wymaga refactor RBAC plan. Add-on tylko.

## 7. Trigger conditions (kiedy aktywujemy)

Plik jest **placeholder** dopóki:

- ❌ NIE — gdy RBAC Phase 1-4 trwa (priorytetowo cross-tenant isolation + auth + permission engine)
- ✅ TAK — gdy RBAC Phase 5 (Settings UI) startuje **i** harmonogram pokazuje że soft launch jest <8 tygodni od teraz
- ✅ TAK — gdy pierwszy design partner sign-off pre-trial wymaga SLA dokumentu
- ✅ TAK — gdy Marcin świadomie decyduje *„kończę MVP, idę na hardening sprint"*
- ✅ TAK — gdy first paying customer onboard się — wtedy compliance + audit register staje się legal requirement
- ✅ TAK — gdy pierwsze CRITICAL bug w produkcji (post-launch reactive) — wskazuje brakujący profilera/load test

**Default**: aktywujemy **równolegle z RBAC Phase 5** (tydzień 11-13) per recommendation (A) z §5.

## 8. Open questions (do walidacji gdy aktywujemy)

1. **Blackfire vs Tideways** — wybór profilera. Blackfire free tier 100 profiles/miesiąc wystarczy dla solo dev MVP. Tideways tańszy w paid tier długoterminowo. Decision Sprint 1 audytu.
2. **k6 vs Gatling** — k6 nowszy, JavaScript-based, lepsza UX. Gatling battle-tested, Scala-based. **Default k6** (lower learning curve).
3. **External pentest budget** — synced z RBAC-P7-002. Jeśli budżet 5-10k PLN przyznany dla RBAC pentest, można rozszerzyć scope o performance + compliance audit za dodatkowe 3-5k PLN.
4. **Dataset source 100k+ SKU** — synthetic generation vs real-world (kosztem licencji)? Default: synthetic z realistic distribution (Faker + custom rules dla brand/category mix).
5. **Compliance scope** — RODO checklist obowiązkowo, ISO 27001 + SOC 2 jako post-MVP candidates. Decision: kiedy zaczynamy ISO 27001 prep (typowo 12-18 miesięcy proces)?

## 9. Co dalej

Plik jest **placeholder** — nic nie wykonujemy teraz.

**Co Marcin robi z tym dokumentem:**

1. **Trzyma w `Project Plan/PRD/`** jako reference dla *„odłożona praca, nie zapomniana"*.
2. **Reviewuje monthly** czy któryś z trigger conditions §7 się aktywował.
3. **Aktywuje** świadomą decyzją gdy timing pasuje:
   - Generuje `15-quality-perf-tickets.md` z ~10-12 ticketów per §4 estymacja
   - Update `02-plan-projektu-pim.md` z dodaniem epiku *„Final Audit"*
   - Update `07-rbac-implementation-plan.md` z reference do `15-quality-perf-tickets.md`
   - Status placeholder → `Draft` → `In Progress` → `Done`

**Co NIE robimy teraz:**
- Tickets w `15-quality-perf-tickets.md` (nie istnieje)
- Tooling install (Deptrac, Blackfire, k6)
- Dataset generation
- SLO/SLA dokument finalize
- Risk register consolidacja

**Świadomy out-of-scope** w sensie *„planujemy, ale nie wykonujemy"*. To **rejestrujemy** żeby nie zniknęło z radaru przed pierwszym design partnerem / pierwszym pentest-em / pierwszym performance incident.

---

*Plik wygenerowany 2026-05-16 jako placeholder. Status: 🔵 Placeholder — aktywuje się świadomą decyzją per Trigger conditions §7. Po aktywacji → zmiana statusu na `Draft` + generacja ticketów + update referencji w innych planach.*
