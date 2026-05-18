# RBAC — tickety Phase 1 (Foundation) — pilot rozpisania

**Typ dokumentu:** Backlog ticketów (pilot dla Phase 1 RBAC) — gotowe do utworzenia GitHub Issues
**Adresat:** Marcin (akceptacja formatu) + agent kodujący w VS Code (wykonanie)
**Data:** 2026-05-16
**Status:** Draft — pilot do walidacji formatu przed rozpisaniem Phase 2-7
**Powiązane dokumenty:**
- [`PRD/PRD-PIM-rbac.md`](PRD/PRD-PIM-rbac.md) — definicja scope i macierz uprawnień
- [`07-rbac-implementation-plan.md`](07-rbac-implementation-plan.md) — strategia, fazy, testing, security tooling

> **Cel dokumentu:** 10 ticketów Phase 1 (Foundation) rozpisanych w docelowym formacie. Po Twoim accept formatu i ewentualnych poprawkach — kontynuuję z Phase 2-7 (~70-110 ticketów). Po accept ticketów Phase 1 → tworzymy GitHub Issues z labelami `epik-0.X-rbac` + `phase-1` + start implementacji.
>
> **Harmonogram Phase 1:** tygodnie 1-2 sprintu RBAC, ~38-61h (mieści się w planie 50-70h Phase 1).

---

## Konwencje ticketów

**Numer ticketu:** `RBAC-P{phase}-{nr}` — np. `RBAC-P1-001`. Po utworzeniu w GitHub: dopisany numer issue (`RBAC-P1-001 / #123`).

**Pola każdego ticketu:**
- **Typ:** Conventional Commits type (`feat` / `fix` / `chore` / `docs` / `refactor` / `test`)
- **Epik:** `0.X Identity & RBAC` (numer epiku ustalimy gdy wprowadzimy do `02-plan-projektu-pim.md`)
- **Phase:** 1-7 (per `07-rbac-implementation-plan.md`)
- **Estymacja:** h (godziny)
- **Dependencies:** Blocks (co ten ticket blokuje) + Blocked by (czego potrzebuje)
- **Risk flags:** identyfikacja punktów krytycznych dla security
- **Cel ticketu:** 1-2 zdania o tym co i dlaczego
- **Scope:** lista konkretnych zmian
- **Acceptance criteria:** checkbox z testowalnymi kryteriami (`[ ] AC-1: ...`)
- **Files affected:** estymacja plików (agent kodujący doprecyzuje w task-level breakdown)
- **Testing requirements:** co testować, jakie warstwy (per `07-rbac-implementation-plan.md` §2)
- **Definition of Done:** standardowy + ticket-specific

**Language conventions** (per CLAUDE.md):
- Tytuł ticketu po angielsku (Conventional Commits format: `feat(identity): scaffold IdentityBundle skeleton`)
- Opis i AC po polsku
- Kod, file paths, function names po angielsku

---

## Kolejność i graf zależności Phase 1

```
RBAC-P1-001 (Phase 0 tooling setup) ──┐
                                       │ (paralelnie, niezależne)
RBAC-P1-002 (ADR-013) ─────────────────┤
                                       │
                                       ▼
RBAC-P1-003 (CLAUDE.md update) ─── RBAC-P1-008 (IdentityBundle skeleton)
                                       │
                                       ▼
                              RBAC-P1-004 (Schema migration 10 tables)
                                       │
                                       ▼
                              RBAC-P1-005 (Delta migrations: attributes, audit_logs)
                                       │
                                       ▼
                              RBAC-P1-006 (Permission seed fixtures)
                                       │
                                       ▼
                              RBAC-P1-007 (Role templates seed)
                                       │
                                       ▼
                              RBAC-P1-009 (Testcontainers Postgres setup)
                                       │
                                       ▼
                              RBAC-P1-010 (PHPStan custom rules)
                                       │
                                       ▼
                              Phase 1 complete → Phase 2 start
```

**Można zrobić w paralel** (jeśli czas pozwala): RBAC-P1-001 (tooling), RBAC-P1-002 (ADR), RBAC-P1-003 (CLAUDE update), RBAC-P1-008 (bundle skeleton).

---

## RBAC-P1-001: chore(ci): setup security tooling (Infection, Semgrep, OWASP ZAP, TruffleHog)

**Typ:** `chore`
**Epik:** 0.X Identity & RBAC
**Phase:** 0 (przygotowanie, paralel z Phase 1)
**Estymacja:** **5-8h**
**Dependencies:**
- Blocks: RBAC-P1-009 (testcontainers — share niektóre Docker patterns)
- Blocked by: brak

**Risk flags:**
- Tooling musi działać przed pierwszym PR z RBAC kodem, inaczej CI gates są ineffective.
- Pre-commit hooks mogą być slow — należy zoptymalizować staged-only checks.

**Cel ticketu:**
Setup full security tooling stack przed startem implementacji RBAC. Bez tego CI gates z `07-rbac-implementation-plan.md` §3 są nieaktywne i security policy ma 0 enforcement.

**Scope:**
- Instalacja Infection PHP (mutation testing) jako dev dependency w `composer.json` + base config `infection.json5` z thresholds (MSI 80% dla `src/Identity/`, 70% globalnie)
- Semgrep config w `.semgrep/` — ruleset dla PHP security patterns (SQL injection, hardcoded secrets, missing auth) + custom rules dla Cortex (TBD w Phase 2)
- OWASP ZAP w CI — nightly GitHub Action workflow scan staging environment
- TruffleHog jako pre-commit hook (Husky + lint-staged) — block commits z leaked secrets
- GitLeaks w CI jako backup do TruffleHog (różne regex patterns)
- Pre-commit hooks setup (Husky package):
  - TruffleHog staged scan
  - PHPStan analyse staged PHP files
  - Biome check staged TS files
  - Conventional commit message format check (commitlint)
  - Block `console.log`, `var_dump`, `die`, `TODO without issue link`
- Composer plugin Roave Security Advisories
- Symfony Security Checker dodać do CI workflow
- Dependabot config `.github/dependabot.yml` — automerge patch, manual review minor/major (per CLAUDE.md §"Zarządzanie zależnościami")
- README z tooling overview w `docs/security/tooling.md` (do utworzenia)

**Acceptance criteria:**
- [ ] AC-1: `composer test:mutation` uruchamia Infection na sample test class i zwraca MSI report
- [ ] AC-2: Semgrep scan w CI wykrywa intencjonalnie wstawiony `eval($_GET['x'])` test case → block PR
- [ ] AC-3: TruffleHog pre-commit blokuje commit zawierający intencjonalny dummy AWS key
- [ ] AC-4: Husky setup działa — `git commit` z `console.log` w staged file → block z message
- [ ] AC-5: Conventional commit `bad message` → reject; `feat(scope): valid` → accept
- [ ] AC-6: OWASP ZAP GitHub Action workflow uruchamia się nightly i zapisuje report do artifacts
- [ ] AC-7: Roave Security Advisories instalowany — próba `composer require` paczki z znanym CVE → block install
- [ ] AC-8: Dependabot tworzy PR dla patch update w sample dependency
- [ ] AC-9: Dokumentacja `docs/security/tooling.md` opisuje każdy tool + jak uruchomić lokalnie

**Files affected:**
- `composer.json` + `composer.lock` (modified — dodanie dev dependencies)
- `package.json` + `pnpm-lock.yaml` (modified — Husky, lint-staged, commitlint)
- `infection.json5` (new)
- `.semgrep/cortex-rules.yml` (new)
- `.github/workflows/security-zap.yml` (new)
- `.github/workflows/security-scans.yml` (new — TruffleHog + GitLeaks + Symfony checker)
- `.github/dependabot.yml` (new lub modified jeśli istnieje)
- `.husky/pre-commit` (new)
- `.husky/commit-msg` (new)
- `commitlint.config.js` (new)
- `docs/security/tooling.md` (new)

**Testing requirements:**
- Manual test każdego AC w PR description (screencast lub paste output)
- Negative test: intencjonalnie wstawione naruszenia (eval, AWS key dummy, bad commit msg) → upewnij się że tooling je wykrywa
- Positive test: typowy clean PR przechodzi wszystkie gates

**Definition of Done:**
- [ ] Acceptance criteria spełnione (manual smoke test każdy AC w PR description)
- [ ] CI green
- [ ] Conventional Commits format dla commit messages
- [ ] PR opis NIE używa słów *„działa"* / *„works"* bez manual smoke testu (per CLAUDE.md SMOKE TEST RULE)
- [ ] Dokumentacja `docs/security/tooling.md` reviewed i merged
- [ ] PR merged do main

---

## RBAC-P1-002: docs(architecture): add ADR-013 — Role-Based Access Control from day 1

**Typ:** `docs`
**Epik:** 0.X Identity & RBAC
**Phase:** 1 (Foundation)
**Estymacja:** **2-3h**
**Dependencies:**
- Blocks: RBAC-P1-003 (CLAUDE.md update referuje ADR-013), RBAC-P1-004 (schema implements ADR)
- Blocked by: brak

**Risk flags:**
- ADR jest source of truth dla decyzji architektonicznych — błędna treść = długoterminowe miscommunication.
- ADR musi explicit odnotować *„świadomy reverse decyzji ADR sprzed Sprint 0"* (wcześniej *„MVP brak gating, Faza 1 ADR-013"*).

**Cel ticketu:**
Dopisać ADR-013 do `Project Plan/01-architektura-pim.md` sekcja 13 — formalna decyzja architektoniczna o wdrożeniu pełnego RBAC w MVP zamiast w Fazie 1. Odnotowuje świadomy reverse z poprzedniej decyzji.

**Scope:**
- Sekcja **ADR-013 Role-Based Access Control od dnia 1 w MVP-Alpha** w `Project Plan/01-architektura-pim.md` (lub plik dedykowany jeśli istnieje convention `docs/adr/`)
- Format ADR (Context / Decision / Status / Consequences / Alternatives):
  - **Context:** wcześniejsza decyzja *„MVP brak gating, Faza 1 ADR-013"* z PRD §7. Sygnały które wymusiły reverse: API tokens scopes, Cmd+K agent rate limits, audit log compliance, field-level secrets, cross-tenant isolation.
  - **Decision:** pełen RBAC implementowany w MVP-Alpha z 10 rolami (Super Admin + 9 tenant), proper Voters + field-level filtering + workflow-state policy + per-attribute restrictions + per-locale/channel scope.
  - **Status:** Accepted 2026-05-16
  - **Consequences:** estymacja +330-445h (z 0h w v1 *„MVP brak gating"*). Acceptowane przez właściciela. Brak refactor risk + zero technical debt. Cross-cutting concern stosowany od dnia 1 dla każdego nowego endpoint + komponentu.
  - **Alternatives considered:**
    - Minimal RBAC w MVP + refactor w Fazie 1 — odrzucony, refactor cost na produkcji = 80-120h + breaking change + downtime
    - Hybrid (proper schema + 5 templates immutable + role builder Faza 1) — odrzucony, dług zaakceptowany jako *„suboptymalny middle"*
- Cross-reference do `PRD-PIM-rbac.md` + `07-rbac-implementation-plan.md`
- Lista ADR-y aktualizacja (jeśli sekcja 13 ma listę top): dodać ADR-013 z linkiem

**Acceptance criteria:**
- [ ] AC-1: ADR-013 jest dopisany do `Project Plan/01-architektura-pim.md` w sekcji 13 (lub `docs/adr/013-rbac-from-day-1.md` jeśli convention dedykowany folder)
- [ ] AC-2: ADR zawiera 5 wymaganych sekcji (Context, Decision, Status, Consequences, Alternatives)
- [ ] AC-3: Context section explicit odnotowuje *„reverse poprzedniej decyzji MVP brak gating"*
- [ ] AC-4: Decision section odwołuje się do PRD-PIM-rbac.md macierzy 3.2 (jako *„autoritative scope"*)
- [ ] AC-5: Consequences section zawiera estymację 330-445h (per PRD v2)
- [ ] AC-6: Alternatives section opisuje 2 rejected approaches z uzasadnieniem
- [ ] AC-7: Cross-reference do PRD + implementation plan działający link
- [ ] AC-8: Index ADR-y (jeśli istnieje) zawiera ADR-013

**Files affected:**
- `Project Plan/01-architektura-pim.md` (modified — dopisanie ADR-013 do sekcji 13)
- ewentualnie `docs/adr/013-rbac-from-day-1.md` (new) jeśli adoptowany convention osobnych plików ADR

**Testing requirements:**
- N/A (dokumentacja)
- Review: czy ADR jest spójny z PRD-PIM-rbac.md + implementation-plan
- Review: czy nie ma sprzeczności z innymi ADR (ADR-006 hybrid attribute, ADR-009 ObjectType, ADR-010 axis variants, ADR-011 locale fallback, ADR-012 AttributeGroup jeśli istnieje)

**Definition of Done:**
- [ ] Acceptance criteria spełnione
- [ ] PR opis zawiera link do PRD-PIM-rbac.md i implementation-plan
- [ ] PR review przez Marcina (analyst review, nie code)
- [ ] PR merged do main

---

## RBAC-P1-003: docs(claude): update priorities — move ADR-013 from Faza 1 to MVP-Alpha

**Typ:** `docs`
**Epik:** 0.X Identity & RBAC
**Phase:** 1 (Foundation)
**Estymacja:** **0.5-1h**
**Dependencies:**
- Blocks: RBAC-P1-004 (schema musi wiedzieć że jest w MVP scope)
- Blocked by: RBAC-P1-002 (ADR-013 musi istnieć żeby referować)

**Risk flags:**
- CLAUDE.md jest source of truth dla AUTONOMOUS_MODE i agent behavior. Błędna treść = agent może podjąć decyzje sprzeczne z RBAC strategy.

**Cel ticketu:**
Update sekcji *„Priorytety implementacyjne"* w obu plikach `CLAUDE.md` (`/Users/mlipieclocal/Library/CloudStorage/...` i `/Users/mlipieclocal/dev/PIM/`) — przesunąć ADR-013 z Fazy 1 do MVP-Alpha, dodać epik 0.X Identity & RBAC w listę MVP-Alpha epików.

**Scope:**
- Update sekcji **„Priorytety implementacyjne"** w `CLAUDE.md`:
  - Punkt 2 (MVP-Alpha): dodać *„+ epik 0.X Identity & RBAC (ADR-013) — full scope z `Project Plan/PRD/PRD-PIM-rbac.md`"*
  - Punkt 4 (Faza 1): usunąć wzmiankę *„ADR-013 per-role permissions"* (przesunięte do MVP)
  - Punkt 4 (Faza 1): usunąć wzmiankę *„RLS aktywacja"* (przesunięte do MVP per PRD §11.1)
- Update sekcji **„Pliki, które utrzymujesz atomowo"**: dodać:
  - `Project Plan/PRD/PRD-PIM-rbac.md` jako master spec
  - `Project Plan/07-rbac-implementation-plan.md` jako operational plan
  - `Project Plan/08-rbac-tickets-phase-1.md` (i kolejne phases) jako backlog
  - `docs/security/threat-model.md` (TBD Phase 6) — STRIDE threat model
  - `docs/security/security-checklist.md` (TBD Phase 6)
  - `docs/operations/break-glass-runbook.md` (TBD Phase 5)
- Update sekcji **„Hooks pod Fazę 2 zostają w MVP"**: explicit zaznaczyć że RBAC scope jest pełny, NIE *„hooks only"*
- Synchronizować oba pliki CLAUDE.md (są lustrzane per `claudeMd` context)

**Acceptance criteria:**
- [ ] AC-1: `CLAUDE.md` (oba pliki) sekcja *„Priorytety implementacyjne"* punkt 2 zawiera epik 0.X Identity & RBAC w MVP-Alpha
- [ ] AC-2: Punkt 4 Faza 1 NIE zawiera *„ADR-013 per-role permissions"* ani *„RLS aktywacja"*
- [ ] AC-3: Sekcja *„Pliki, które utrzymujesz atomowo"* zawiera 5 nowych plików (PRD, plan, tickets, threat-model, security-checklist, break-glass-runbook)
- [ ] AC-4: Oba pliki CLAUDE.md są zsynchronizowane (diff == 0)
- [ ] AC-5: Cross-reference do ADR-013 (RBAC-P1-002) w treści

**Files affected:**
- `CLAUDE.md` (oba pliki — projekt CloudStorage + dev/PIM)

**Testing requirements:**
- Manual review że oba pliki są identyczne
- Manual review czy agent (Claude Code) po update'cie respektuje nową strategy (test: zapytać *„w jakiej fazie jest RBAC"* — expected MVP-Alpha)

**Definition of Done:**
- [ ] Acceptance criteria spełnione
- [ ] Oba pliki CLAUDE.md zsynchronizowane (`diff` zero output)
- [ ] PR merged do main

---

## RBAC-P1-004: feat(identity): schema migrations — core RBAC tables (10 tables)

**Typ:** `feat`
**Epik:** 0.X Identity & RBAC
**Phase:** 1 (Foundation)
**Estymacja:** **8-12h**
**Dependencies:**
- Blocks: RBAC-P1-005, RBAC-P1-006, RBAC-P1-007, RBAC-P1-008, RBAC-P1-009
- Blocked by: RBAC-P1-002 (ADR-013), RBAC-P1-008 (IdentityBundle skeleton — może być w paralel)

**Risk flags:**
- **Cross-tenant leakage risk** — każda tabela musi mieć `tenant_id NOT NULL` (z wyjątkiem `super_admins` i globalnego `permissions`). Brak `tenant_id` w jednej tabeli = potencjalny privacy boundary breach.
- **Rollback test obligatoryjny** — migration musi mieć working rollback (każda `up()` ma odpowiednik `down()`), bo Phase 1 może wymagać schema iteration.
- **UNIQUE constraints precyzyjne** — `users.email UNIQUE per tenant` (NIE globally per CLAUDE.md analysis, ale jeśli przyjmujemy global email — wskazać w ticketcie).

**Cel ticketu:**
Utworzyć Doctrine migrations dla 10 core RBAC tabel w `src/Identity/` bundle. Schema zgodna z `PRD-PIM-rbac.md` §4.3.

**Scope:**

10 tabel do utworzenia (per PRD §4.3):

1. **`super_admins`** — cross-tenant operatorzy platformy (Marcin, DBA)
2. **`users`** — tenant-level users z MFA, SSO, deactivation
3. **`roles`** — role per tenant (system templates + custom)
4. **`permissions`** — atomic permissions globalne (~50 entries)
5. **`role_permissions`** — N:M assignment permissions do ról
6. **`user_roles`** — N:M assignment users do ról + locale_scope + channel_scope
7. **`api_tokens`** — API tokens z hashed value, scopes JSONB, expiry
8. **`invitations`** — magic link invitations z 7d TTL
9. **`user_tenant_memberships`** — N:M user-tenant od dnia 1 (per PRD §11.1)
10. **`sso_providers`** — SAML/Google Workspace/Microsoft 365 config per tenant

**Migration class structure:**

- `src/Identity/Doctrine/Migrations/Version20260516000001_CreateSuperAdmins.php`
- `src/Identity/Doctrine/Migrations/Version20260516000002_CreateUsers.php`
- `src/Identity/Doctrine/Migrations/Version20260516000003_CreateRolesAndPermissions.php` (3 tabele: roles, permissions, role_permissions)
- `src/Identity/Doctrine/Migrations/Version20260516000004_CreateUserRoles.php`
- `src/Identity/Doctrine/Migrations/Version20260516000005_CreateApiTokens.php`
- `src/Identity/Doctrine/Migrations/Version20260516000006_CreateInvitations.php`
- `src/Identity/Doctrine/Migrations/Version20260516000007_CreateUserTenantMemberships.php`
- `src/Identity/Doctrine/Migrations/Version20260516000008_CreateSsoProviders.php`

Każda migration zawiera `up()` + `down()` + indexes + UNIQUE constraints + FK constraints (z `ON DELETE CASCADE` gdzie sensible).

**Kluczowe decyzje schema** (do potwierdzenia w PR review):
- `users.email` — UNIQUE per tenant (`UNIQUE (tenant_id, email)`)? Czy UNIQUE globally? **Default: UNIQUE per tenant** (multi-tenant friendly, ten sam email może być w 2 tenantach jako różne accounty — choć z `user_tenant_memberships` schema ten approach jest redundant. Alternatywa: `users.email UNIQUE globally` + `user_tenant_memberships` jako primary relation. **Wybór: UNIQUE globally** — zgodne z N:M membership model + magic link invite by email.)
- `super_admins.email` — UNIQUE globally (osobny pool od `users`)
- `roles.tenant_id` NOT NULL (każda rola jest per-tenant, 9 starter templates kopiowane przy onboardingu)
- `permissions.tenant_id` NULL (globalne, immutable)
- `api_tokens.token_hash` UNIQUE (BCrypt hash, plaintext zwracany tylko raz przy create)
- Wszystkie `created_at`, `updated_at` jako `TIMESTAMPTZ DEFAULT NOW()`
- Wszystkie UUID PK (generowane przez `gen_random_uuid()` lub UUIDv7 — preferuj UUIDv7 dla index locality)
- JSONB columns z GIN index gdzie sensible (`scopes`, `locale_scope`, `channel_scope`, `role_ids` w invitations)

**Acceptance criteria:**
- [ ] AC-1: Wszystkie 10 tabel utworzone z `php bin/console doctrine:migrations:migrate`
- [ ] AC-2: `php bin/console doctrine:schema:validate` pass (Doctrine entity mapping match schema)
- [ ] AC-3: `php bin/console doctrine:migrations:migrate prev` rollback wszystkich 8 migrations bez errors
- [ ] AC-4: Wszystkie domain tabele mają `tenant_id` (z wyjątkiem `super_admins` i `permissions`)
- [ ] AC-5: `users.email` UNIQUE globally
- [ ] AC-6: UUIDv7 generation działa (test: `SELECT uuid_generate_v7()` lub PHP-level generator)
- [ ] AC-7: GIN indexes utworzone dla JSONB columns z search use case (scopes, locale_scope, channel_scope)
- [ ] AC-8: FK constraints z `ON DELETE CASCADE` dla N:M tables (role_permissions, user_roles) — usunięcie role kasuje membership
- [ ] AC-9: Migration UP + DOWN testowane w testcontainers Postgres
- [ ] AC-10: Tabele widoczne w `\dt` Postgres CLI + columns matchują PRD §4.3
- [ ] AC-11: Cross-tenant isolation test (placeholder w `tests/Identity/CrossTenantIsolationTest.php`): query do `roles` z tenant_id=A nie zwraca rows z tenant_id=B

**Files affected:**
- `src/Identity/Doctrine/Migrations/Version20260516000001_CreateSuperAdmins.php` (new)
- `src/Identity/Doctrine/Migrations/Version20260516000002_CreateUsers.php` (new)
- `src/Identity/Doctrine/Migrations/Version20260516000003_CreateRolesAndPermissions.php` (new)
- `src/Identity/Doctrine/Migrations/Version20260516000004_CreateUserRoles.php` (new)
- `src/Identity/Doctrine/Migrations/Version20260516000005_CreateApiTokens.php` (new)
- `src/Identity/Doctrine/Migrations/Version20260516000006_CreateInvitations.php` (new)
- `src/Identity/Doctrine/Migrations/Version20260516000007_CreateUserTenantMemberships.php` (new)
- `src/Identity/Doctrine/Migrations/Version20260516000008_CreateSsoProviders.php` (new)
- `tests/Identity/CrossTenantIsolationTest.php` (new — placeholder dla AC-11, rozszerzony w Phase 2)

**Testing requirements:**
- **Layer 1 — Unit:** każda Entity klasa (jeśli scaffolded w RBAC-P1-008) ma test `testEntityCreatesWithTenantId()`.
- **Layer 2 — Integration:** test `testMigrationUpDown()` — pełna sekwencja UP wszystkich 8, potem DOWN wszystkich 8, schema verification po każdym kroku.
- **Layer 3 — Cross-tenant isolation:** placeholder test (sekcja AC-11) z mock 2 tenants.

**Definition of Done:**
- [ ] Acceptance criteria spełnione
- [ ] PHPStan max ✓
- [ ] Test coverage (unit + integration) ≥ 90% dla migration code
- [ ] Manual smoke: `pnpm stack:up` + `php bin/console doctrine:migrations:migrate` → wszystkie tabele utworzone, brak errors
- [ ] Manual smoke: `php bin/console doctrine:migrations:migrate prev` → wszystkie tabele rolled back, brak errors
- [ ] CI green (PHPStan + unit + integration + cross-tenant isolation placeholder)
- [ ] Conventional Commits format
- [ ] PR opis zawiera output `\d users`, `\d roles`, `\d permissions` jako screenshot lub paste
- [ ] PR opis NIE używa słów *„działa"* / *„works"* bez manual smoke testu
- [ ] PR review (analyst — schema spójność z PRD §4.3)
- [ ] PR merged do main

---

## RBAC-P1-005: feat(catalog,identity,audit): delta migrations — attributes integration_visible + role_attribute_permissions tables + audit_logs extensions

**Typ:** `feat`
**Epik:** 0.X Identity & RBAC
**Phase:** 1 (Foundation)
**Estymacja:** **5-8h** (zwiększone z 2-4h po PRD v2.1 update §3.5)
**Dependencies:**
- Blocks: RBAC-P1-006 (permission seed używa attributes), Phase 3 (field-level filtering operuje na 3-state permissions)
- Blocked by: RBAC-P1-004 (audit_logs extension + roles table istnieje), istnienie `attributes` + `attribute_groups` tables z poprzednich epików

**Risk flags:**
- **Existing data preservation** — `attributes` istnieje z wartościami; migration musi default `integration_visible=true` dla wszystkich existing rows.
- **PRD v2.1 schema change** — `restricted_roles JSONB` z PRD v1 **zastąpione** przez 3-state positive grants w 2 nowych tabelach + kolumna `roles.default_attribute_permission`. Migration script konwertuje existing entries.
- **Composite FK CASCADE** — role_attribute_permissions / role_attribute_group_permissions z `ON DELETE CASCADE` na obu kierunkach — usunięcie roli lub atrybutu kasuje permission entries.

**Cel ticketu:**
Dodać `integration_visible` do `attributes` + 2 nowe tabele 3-state permissions (`role_attribute_permissions`, `role_attribute_group_permissions`) + kolumna `roles.default_attribute_permission` + rozszerzenie `audit_logs` (per PRD §3.5 i §4.3).

**Scope:**

**`attributes` delta** (per PRD §3.5 — only `integration_visible`, `restricted_roles` DROPPED):
```sql
ALTER TABLE attributes ADD COLUMN integration_visible BOOLEAN NOT NULL DEFAULT true;
CREATE INDEX idx_attributes_integration_visible ON attributes(integration_visible) WHERE integration_visible = false;
```

**`roles` delta** (default per rola):
```sql
ALTER TABLE roles ADD COLUMN default_attribute_permission VARCHAR(16) NOT NULL DEFAULT 'edit'
    CHECK (default_attribute_permission IN ('restricted', 'view', 'edit'));
```

**`role_attribute_permissions` — nowa tabela** (per-attribute override):
```sql
CREATE TABLE role_attribute_permissions (
    role_id UUID NOT NULL REFERENCES roles(id) ON DELETE CASCADE,
    attribute_id UUID NOT NULL REFERENCES attributes(id) ON DELETE CASCADE,
    permission VARCHAR(16) NOT NULL CHECK (permission IN ('restricted', 'view', 'edit')),
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    PRIMARY KEY (role_id, attribute_id)
);
CREATE INDEX idx_role_attribute_permissions_role ON role_attribute_permissions(role_id);
CREATE INDEX idx_role_attribute_permissions_attribute ON role_attribute_permissions(attribute_id);
```

**`role_attribute_group_permissions` — nowa tabela** (per-group override):
```sql
CREATE TABLE role_attribute_group_permissions (
    role_id UUID NOT NULL REFERENCES roles(id) ON DELETE CASCADE,
    attribute_group_id UUID NOT NULL REFERENCES attribute_groups(id) ON DELETE CASCADE,
    permission VARCHAR(16) NOT NULL CHECK (permission IN ('restricted', 'view', 'edit')),
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    PRIMARY KEY (role_id, attribute_group_id)
);
CREATE INDEX idx_role_attribute_group_permissions_role ON role_attribute_group_permissions(role_id);
```

**Migration script** (dla istniejących data z PRD v1 schema, jeśli applicable):
- Jeśli `attributes.restricted_roles` JSONB istnieje z PRD v1 (kolumna jeszcze nie wdrożona w MVP, ale migration idempotent na wypadek):
  - Per row z non-empty `restricted_roles` array: konwertuj na entries w `role_attribute_permissions` z permission `'view'` (semantyczne mapowanie: *„restricted z edit"* → *„view-only"*)
  - DROP COLUMN `attributes.restricted_roles` po migration
- Init `roles.default_attribute_permission`:
  - Owner/Admin/Catalog Manager → `'edit'`
  - Marketing/Modeler/Integration Manager/Channel Manager/Approver → `'edit'` (inherit z macierzy 3.2 broad permission)
  - Viewer → `'view'`
  - Custom roles → wartość z `roles.default_attribute_permission` default `'edit'` (klient explicit zmienia w UI)

**`audit_logs` delta** (per PRD §4.3):
```sql
ALTER TABLE audit_logs ADD COLUMN super_admin_id UUID REFERENCES super_admins(id);
ALTER TABLE audit_logs ADD COLUMN permission_check_result VARCHAR(32);  -- granted/denied/n_a/super_admin_bypass
ALTER TABLE audit_logs ADD COLUMN cross_tenant_access BOOLEAN NOT NULL DEFAULT false;
ALTER TABLE audit_logs ADD COLUMN special_flags JSONB DEFAULT '[]'::JSONB;
ALTER TABLE audit_logs ALTER COLUMN tenant_id DROP NOT NULL;  -- nullable dla cross-tenant Super Admin actions

CREATE INDEX idx_audit_logs_super_admin ON audit_logs(super_admin_id) WHERE super_admin_id IS NOT NULL;
CREATE INDEX idx_audit_logs_cross_tenant ON audit_logs(cross_tenant_access) WHERE cross_tenant_access = true;
CREATE INDEX idx_audit_logs_special_flags ON audit_logs USING GIN(special_flags);
```

**Migration classes:**
- `src/Catalog/Doctrine/Migrations/Version20260516000009_AddIntegrationVisibleToAttributes.php`
- `src/Identity/Doctrine/Migrations/Version20260516000010_CreateRoleAttributePermissions.php` (2 nowe tabele + roles delta)
- `src/Audit/Doctrine/Migrations/Version20260516000011_ExtendAuditLogsForRbac.php`
- `src/Identity/Doctrine/Migrations/Version20260516000012_SeedDefaultAttributePermissionsForBuiltInRoles.php` (data migration init values)

**Acceptance criteria:**
- [ ] AC-1: Kolumna `integration_visible` dodana do `attributes` z default `true`
- [ ] AC-2: Existing rows w `attributes` mają `integration_visible=true` po migration
- [ ] AC-3: Partial index `idx_attributes_integration_visible WHERE = false` utworzony
- [ ] AC-4: Tabela `role_attribute_permissions` utworzona z CHECK constraint `permission IN ('restricted', 'view', 'edit')`
- [ ] AC-5: Tabela `role_attribute_group_permissions` utworzona z analogicznym CHECK constraint
- [ ] AC-6: Kolumna `roles.default_attribute_permission` dodana z default `'edit'` + CHECK constraint
- [ ] AC-7: FK ON DELETE CASCADE — usunięcie roli lub atrybutu kasuje permission entries (test: insert + delete cascade)
- [ ] AC-8: 4 nowe indeksy utworzone (role_attr × 2 sides, role_attr_group, attributes integration_visible)
- [ ] AC-9: Built-in role templates (z RBAC-P1-007) mają sensible defaults: Owner/Admin/CatalogMgr → `'edit'`, Viewer → `'view'`
- [ ] AC-10: Kolumny `super_admin_id`, `permission_check_result`, `cross_tenant_access`, `special_flags` dodane do `audit_logs` (CHECK na permission_check_result)
- [ ] AC-11: `audit_logs.tenant_id` przekształcone na NULLABLE
- [ ] AC-12: Migration UP + DOWN testowane w testcontainers Postgres
- [ ] AC-13: Cross-tenant isolation: 2 tenanty z różnymi rolami + per-attribute permissions — query tenant A nie zwraca permissions tenant B
- [ ] AC-14: Doctrine entity classes scaffold z RBAC-P1-008 mapują nowe schema (RoleAttributePermission, RoleAttributeGroupPermission entities)

**Files affected:**
- `src/Catalog/Doctrine/Migrations/Version20260516000009_*.php` (new)
- `src/Identity/Doctrine/Migrations/Version20260516000010_*.php` (new — kluczowy)
- `src/Identity/Doctrine/Migrations/Version20260516000012_*.php` (new — data init)
- `src/Audit/Doctrine/Migrations/Version20260516000011_*.php` (new)
- `src/Catalog/Entity/Attribute.php` (modified — dodanie `integration_visible` property)
- `src/Identity/Entity/Role.php` (modified — dodanie `default_attribute_permission` property)
- `src/Identity/Entity/RoleAttributePermission.php` (new entity)
- `src/Identity/Entity/RoleAttributeGroupPermission.php` (new entity)
- `src/Audit/Entity/AuditLog.php` (modified)
- Test fixtures — update z default values

**Testing requirements:**
- **Layer 1 — Unit:** `AttributeTest::testHasIntegrationVisibleByDefault()`, `RoleTest::testDefaultAttributePermissionDefaultsToEdit()`, `RoleAttributePermissionTest::testCheckConstraintRejectsInvalidValue()`
- **Layer 2 — Integration:** test pełen resolution chain: insert per-attribute + per-group + role default → query Voter zwraca prawidłową wartość per priority (attribute > group > role default)
- **Layer 3 — Cross-tenant:** 2 tenanty z permissions na tych samych attribute_id → query tenant A nie zwraca entries tenant B (TenantFilter applied)
- **Manual smoke:** insert sample role + 5 attributes w 2 groups + per-attribute permission na 1 + per-group permission na inną → query verify resolution z różnymi wariantami

**Definition of Done:**
- [ ] Acceptance criteria spełnione
- [ ] PHPStan max ✓
- [ ] Test coverage ≥ 90%
- [ ] Manual smoke: insert/query attributes z różnymi flag combinations
- [ ] CI green
- [ ] PR opis NIE używa słów *„działa"* bez manual smoke
- [ ] PR merged do main

---

## RBAC-P1-006: feat(identity): seed atomic permissions fixtures (~50 entries)

**Typ:** `feat`
**Epik:** 0.X Identity & RBAC
**Phase:** 1 (Foundation)
**Estymacja:** **3-5h**
**Dependencies:**
- Blocks: RBAC-P1-007 (role templates wymagają permissions istnieć)
- Blocked by: RBAC-P1-004 (`permissions` table musi istnieć)

**Risk flags:**
- **Permission code naming convention** — musi być spójne. Format: `{module}.{action}` (np. `products.view`, `settings.users.manage`). Niespójność = bug w Voter matching.
- **Lista permissions = source of truth** dla Voter logic + frontend `useCanI()` hook. Każda permission musi pojawić się w macierzy 3.2 PRD-PIM-rbac.md.
- **Idempotent seed** — fixtures musi być re-runnable bez duplikatów (UPSERT zamiast INSERT).

**Cel ticketu:**
Seed wszystkich atomic permissions (~50 entries) do `permissions` table jako globalna, immutable pula. Każda permission ma `code` (unique), `module`, `action`, `description`, `is_system=true`.

**Scope:**

**Permissions list** (extracted z macierzy 3.2 PRD-PIM-rbac.md):

**Cross-tenant (Super Admin only):**
- `platform.tenants.list`
- `platform.tenants.manage`
- `platform.audit.view_all`
- `platform.break_glass_recovery`

**Produkty:**
- `products.view`
- `products.add`
- `products.edit`
- `products.delete`
- `products.bulk_operations`
- `products.approve_pending_changes`

**Kategorie:**
- `categories.view`
- `categories.add_edit`
- `categories.delete`

**Multimedia:**
- `multimedia.view`
- `multimedia.add_edit_own`
- `multimedia.add_edit_any`
- `multimedia.delete`

**Modelowanie:**
- `modeling.view`
- `modeling.attributes.add_edit`
- `modeling.attribute_groups.add_edit`
- `modeling.object_types.add`
- `modeling.delete_custom`
- `modeling.approve_schema_ops`
- `modeling.auto_grant_new_object_types`

**Publikacje:**
- `publications.view`
- `publications.publish_unpublish`

**Imports:**
- `imports.view_own`
- `imports.view_all`
- `imports.run`

**Exports:**
- `exports.view_own`
- `exports.view_all`
- `exports.run`

**Workflow:**
- `workflow.view`
- `workflow.approve_reject`
- `workflow.edit_any_state`

**Cmd+K agent:**
- `agent.schema_ops`
- `agent.bulk_actions`
- `agent.approve_pending`

**Settings:**
- `settings.users.manage`
- `settings.roles.manage`
- `settings.tenant.manage`
- `settings.billing.manage`
- `settings.integrations.manage`
- `settings.integration_secrets.read`

**API tokens:**
- `api_tokens.own.crud`
- `api_tokens.all.view_revoke`

**Audit:**
- `audit.view_own`
- `audit.view_cross_user`

**Tenant lifecycle:**
- `tenant.delete`

**Total: ~50 permissions** (dokładna lista do walidacji w PR review, ale ten zestaw matchuje macierz 3.2 PRD).

**Implementation:**
- `src/Identity/DataFixtures/PermissionFixtures.php` — Doctrine DataFixtures class
- UPSERT pattern (INSERT ON CONFLICT (code) DO NOTHING) — idempotent
- Trigger przy initial deploy + każdy nowy tenant onboarding (przez Doctrine event listener — implementacja Phase 2)

**Acceptance criteria:**
- [ ] AC-1: Plik `PermissionFixtures.php` zawiera ~50 atomic permissions (dokładna lista zgodna z macierzą 3.2 PRD)
- [ ] AC-2: `php bin/console doctrine:fixtures:load --append --group=identity` ładuje permissions bez errors
- [ ] AC-3: Wszystkie permissions mają unique `code` (test: `SELECT code, COUNT(*) FROM permissions GROUP BY code HAVING COUNT(*) > 1` zwraca 0 rows)
- [ ] AC-4: Każda permission ma `is_system=true` (nie można usunąć przez UI w Phase 5)
- [ ] AC-5: Re-run fixtures (UPSERT) nie tworzy duplikatów ani nie modyfikuje existing entries
- [ ] AC-6: Macierz 3.2 z PRD ma 1:1 mapping na permission codes (analyst review w PR)
- [ ] AC-7: Każda permission ma description w `name` JSONB (`{"pl": "...", "en": "..."}`) — przygotowanie dla UI Settings → Roles list

**Files affected:**
- `src/Identity/DataFixtures/PermissionFixtures.php` (new)
- `src/Identity/Repository/PermissionRepository.php` (modified — dodanie metody `findByCode()`)

**Testing requirements:**
- **Layer 1 — Unit:** `PermissionRepository::findByCode('products.view')` zwraca entity
- **Layer 2 — Integration:** test `PermissionFixturesTest::testFixturesAreIdempotent()` — load 2× → permissions count unchanged
- **Layer 2 — Integration:** test `PermissionFixturesTest::testAllMatrixPermissionsExist()` — assertion że każda permission z macierzy 3.2 jest w bazie

**Definition of Done:**
- [ ] Acceptance criteria spełnione
- [ ] PHPStan max ✓
- [ ] Test coverage ≥ 95% (kluczowe dla source of truth)
- [ ] Manual smoke: query `SELECT code FROM permissions ORDER BY module, action` matches macierz 3.2 PRD
- [ ] CI green
- [ ] PR opis zawiera full list of permissions w PR description (jako paste output)
- [ ] PR merged do main

---

## RBAC-P1-007: feat(identity): seed role templates (9 templates per tenant onboarding)

**Typ:** `feat`
**Epik:** 0.X Identity & RBAC
**Phase:** 1 (Foundation)
**Estymacja:** **4-6h**
**Dependencies:**
- Blocks: Phase 2 (auth flow assigns role to user przy invitation), Phase 5 (UI role list)
- Blocked by: RBAC-P1-006 (permissions istnieją)

**Risk flags:**
- **Owner role uniqueness** — `is_unique=true` flag musi być enforced przy assignment (max 1 user per tenant z rolą Owner).
- **`is_system=true` immutability** — system templates nie mogą być deleted przez UI (Phase 5 enforces).
- **Permission assignment correctness** — każda rola musi dostać dokładnie permissions wynikające z macierzy 3.2 PRD. Misassignment = security hole.

**Cel ticketu:**
Seed 9 role templates (1 Super Admin platform-level + 8 tenant-level) z odpowiednimi permission assignments per macierz 3.2 PRD. Templates seedowane:
- (a) Globalnie raz przy deploy (Super Admin role)
- (b) Per tenant przy onboarding (8 tenant role templates jako copy)

**Scope:**

**Super Admin role** (platform-level, seedowana raz, NIE per tenant):
- Code: `super_admin`
- Permissions: wszystkie `platform.*` (4 entries)
- `is_system=true`, `is_unique=false` (multiple super admins OK)
- `tenant_id=NULL` (cross-tenant)

**Tenant-level role templates** (8 templates seedowanych per nowy tenant przez onboarding listener):

1. **Tenant Owner** (`tenant_owner`)
   - Wszystkie tenant permissions + `tenant.delete`
   - `is_unique=true` (max 1 per tenant)
   - `auto_grant_new_object_types=true`

2. **Administrator** (`admin`)
   - Wszystko oprócz `tenant.delete` i `settings.billing.manage`
   - `is_unique=false`
   - `auto_grant_new_object_types=true`

3. **Catalog Manager** (`catalog_manager`)
   - `products.*`, `categories.*`, `multimedia.*` (CRUD)
   - `imports.*`, `exports.*` (own + all view)
   - `workflow.approve_reject`
   - `agent.bulk_actions`
   - `auto_grant_new_object_types=true`

4. **Content Editor (Marketing)** (`marketing`)
   - `products.view`, `products.add`, `products.edit`, `products.bulk_operations` (BEZ delete)
   - `categories.view`, `categories.add_edit`
   - `multimedia.view`, `multimedia.add_edit_own`
   - `exports.view_own`, `exports.run`
   - `imports.view_own`, `imports.run`
   - `workflow.view`
   - `agent.bulk_actions`
   - `auto_grant_new_object_types=true`
   - **Field-level restriction** (przygotowane dla Phase 3): `attributes.restricted_roles` zawiera `marketing` dla `price.*` i `cost_price`

5. **Information Architect (Modeler)** (`modeler`)
   - `modeling.*` (full)
   - `products.view`, `categories.view`, `multimedia.view`
   - `agent.schema_ops`, `agent.approve_pending` (schema-ops only)
   - `auto_grant_new_object_types=true`

6. **Integration Manager** (`integration_manager`)
   - `integrations.manage`, `integration_secrets.read`
   - `imports.run`, `imports.view_all`
   - `publications.publish_unpublish`
   - `products.view` (z `integration_visible=true` filter w Phase 3)
   - `api_tokens.all.view_revoke`
   - `auto_grant_new_object_types=false`

7. **Channel Manager** (`channel_manager`)
   - `publications.view`, `publications.publish_unpublish`
   - `products.view`, `products.edit` (z `channel_scope` restriction w Phase 3)
   - `categories.view`, `multimedia.view`
   - `exports.view_own`, `exports.run`
   - `auto_grant_new_object_types=false`

8. **Approver** (`approver`)
   - `products.view`, `products.approve_pending_changes`
   - `modeling.view`, `modeling.approve_schema_ops`
   - `agent.approve_pending`
   - `workflow.view`, `workflow.approve_reject`
   - `audit.view_own`, `audit.view_cross_user`
   - `auto_grant_new_object_types=true`

9. **Viewer** (`viewer`)
   - `*.view` dla wszystkich modułów
   - `audit.view_own`, `audit.view_cross_user`
   - `api_tokens.own.crud` (z scope read-only enforcement)
   - `auto_grant_new_object_types=true`

**Implementation:**
- `src/Identity/DataFixtures/SuperAdminRoleFixtures.php` — seed Super Admin role raz
- `src/Identity/DataFixtures/TenantRoleTemplatesFixtures.php` — definicja 8 templates jako *„blueprints"*
- `src/Identity/Service/TenantOnboardingService.php` (placeholder) — używa blueprints żeby copy templates do nowego tenanta
- Doctrine event listener `OnTenantCreatedListener` (Phase 2) automatycznie wywołuje seed templates dla nowego tenant — w MVP można triggerować ręcznie przez CLI command `cortex:tenant:seed-roles {tenant_id}`

**Acceptance criteria:**
- [ ] AC-1: Plik `SuperAdminRoleFixtures.php` zawiera definicję `super_admin` role z 4 platform permissions
- [ ] AC-2: Plik `TenantRoleTemplatesFixtures.php` zawiera 8 templates z permission assignments matching macierz 3.2 PRD
- [ ] AC-3: `php bin/console cortex:tenant:seed-roles {tenant_id}` seeduje 8 ról dla wybranego tenanta
- [ ] AC-4: Każda rola ma poprawny `is_system=true`, `is_unique` flag, `auto_grant_new_object_types`
- [ ] AC-5: Permission assignment correctness — test compares każdą rolę z macierzą 3.2 PRD (dedicated test class)
- [ ] AC-6: Re-seed (UPSERT) nie tworzy duplikatów ani nie nadpisuje custom changes (jeśli klient edytuje template w Phase 5, re-seed NIE revertuje)
- [ ] AC-7: Cross-tenant: seed dla tenant A nie tworzy rows w tenant B
- [ ] AC-8: Permission matrix 3.2 PRD validation — dedicated test `RoleTemplatesMatrixTest::testEachRoleHasCorrectPermissions()` z 8 sub-assertions per role

**Files affected:**
- `src/Identity/DataFixtures/SuperAdminRoleFixtures.php` (new)
- `src/Identity/DataFixtures/TenantRoleTemplatesFixtures.php` (new)
- `src/Identity/Service/TenantOnboardingService.php` (new — placeholder, full implementation Phase 2)
- `src/Identity/Command/SeedTenantRolesCommand.php` (new — CLI command)
- `tests/Identity/RoleTemplatesMatrixTest.php` (new)

**Testing requirements:**
- **Layer 1 — Unit:** `TenantOnboardingServiceTest` — sprawdza że copy templates tworzy 8 ról dla mock tenant
- **Layer 2 — Integration:** `RoleTemplatesMatrixTest::testCatalogManagerHasExpectedPermissions()` ✓ + 7 podobnych testów per rola
- **Layer 3 — Cross-tenant isolation:** seed dla tenant A, query dla tenant B → 0 rows

**Definition of Done:**
- [ ] Acceptance criteria spełnione
- [ ] PHPStan max ✓
- [ ] Test coverage ≥ 95% (source of truth dla security)
- [ ] Manual smoke: `cortex:tenant:seed-roles` na test tenant + manual review w Postgres (`SELECT r.name, COUNT(rp.permission_id) FROM roles r JOIN role_permissions rp ON r.id=rp.role_id WHERE r.tenant_id=':test' GROUP BY r.name`)
- [ ] CI green
- [ ] PR opis zawiera matrix review (paste expected vs actual permissions per role)
- [ ] PR merged do main

---

## RBAC-P1-008: feat(identity): scaffold IdentityBundle skeleton (entities, repositories, services)

**Typ:** `feat`
**Epik:** 0.X Identity & RBAC
**Phase:** 1 (Foundation)
**Estymacja:** **4-6h**
**Dependencies:**
- Blocks: RBAC-P1-004 (migrations create tables, entities mapują), RBAC-P1-006/007 (fixtures używają entities)
- Blocked by: brak (może być paralelnie z RBAC-P1-002/003)

**Risk flags:**
- **Bundle structure spójność z CLAUDE.md** — każdy bounded context = osobny Symfony bundle w `src/`. Identity bundle musi follow same pattern jak Catalog, Channel, Asset.
- **Autowiring na poziomie bundle** — services musi być wired automatically (zero manual service registration).
- **Doctrine mapping zgodne z migracjami** — entities musi 1:1 match schema z RBAC-P1-004.

**Cel ticketu:**
Utworzyć skeleton struktury `src/Identity/` bundle zgodny z konwencjami Cortex PIM (DDD bounded context, Symfony bundle). Entities, repositories, services interfaces (bez implementation logic — to Phase 2-3).

**Scope:**

**Bundle structure:**
```
src/Identity/
├── IdentityBundle.php                  (Symfony Bundle class)
├── DependencyInjection/
│   ├── IdentityExtension.php
│   └── Configuration.php
├── Entity/
│   ├── SuperAdmin.php
│   ├── User.php
│   ├── Role.php
│   ├── Permission.php
│   ├── UserRole.php
│   ├── ApiToken.php
│   ├── Invitation.php
│   ├── UserTenantMembership.php
│   └── SsoProvider.php
├── Repository/
│   ├── SuperAdminRepository.php
│   ├── UserRepository.php
│   ├── RoleRepository.php
│   ├── PermissionRepository.php
│   ├── ApiTokenRepository.php
│   ├── InvitationRepository.php
│   └── ...
├── Service/
│   ├── PermissionResolver.php          (placeholder, Phase 3 implementation)
│   ├── TenantOnboardingService.php     (z RBAC-P1-007)
│   ├── AuthenticationService.php       (placeholder, Phase 2)
│   ├── MfaService.php                  (placeholder, Phase 2)
│   ├── InvitationService.php           (placeholder, Phase 2)
│   └── ...
├── Voter/                              (Phase 3 implementation)
├── EventListener/                      (Phase 2-3 implementation)
├── Command/                            (z RBAC-P1-007 + Phase 2 commands)
├── Doctrine/
│   ├── Migrations/                     (z RBAC-P1-004)
│   └── Filter/
│       └── TenantFilter.php            (placeholder, refactor istniejący jeśli jest w innym bundle, Phase 2)
├── DataFixtures/                       (z RBAC-P1-006/007)
└── Resources/
    └── config/
        ├── services.yaml
        └── doctrine.yaml
```

**Entity scaffolding** (Doctrine ORM 3.x):
- Każda entity ma `#[ORM\Entity]` + `#[ORM\Table(name="...")]`
- UUID PK z `#[ORM\Id] #[ORM\Column(type="uuid")]`
- `tenant_id` jako `#[ORM\Column(type="uuid", nullable=false)]` (z wyjątkiem `SuperAdmin` i `Permission`)
- Standard timestamps `created_at`, `updated_at` z lifecycle callbacks
- Relationships (OneToMany, ManyToOne, ManyToMany) zgodne z migracjami RBAC-P1-004
- Property types strict (Doctrine ORM 3.x supports)
- Getters/setters z `declare(strict_types=1)`

**Repository scaffolding:**
- Każdy Repository extends `ServiceEntityRepository`
- Methods placeholder: `findByCode()`, `findByEmail()`, etc. (full implementations w Phase 2-3 jak są używane)

**Service skeleton (interfaces + empty implementations):**
- `PermissionResolverInterface` z method `resolve(User $user): PermissionSet` (placeholder return)
- `TenantOnboardingService::seedRoleTemplates(Tenant $tenant): void` (delegate do RBAC-P1-007 fixtures)

**Bundle registration:**
- `IdentityBundle` extends `Bundle`
- `IdentityExtension` extends `Extension` z `load()` method (registers services + Doctrine mapping)
- Dodać do `config/bundles.php`: `Cortex\Identity\IdentityBundle::class => ['all' => true]`

**Acceptance criteria:**
- [ ] AC-1: Struktura folderu `src/Identity/` matchuje template z scope sekcji
- [ ] AC-2: `IdentityBundle` registered w `config/bundles.php`
- [ ] AC-3: 9 entity classes scaffolded z Doctrine ORM annotations (Php 8.4 attributes preferred)
- [ ] AC-4: `php bin/console doctrine:schema:validate` pass — entity mapping matches schema z RBAC-P1-004
- [ ] AC-5: 9 repository classes scaffolded extending `ServiceEntityRepository`
- [ ] AC-6: 5+ service interfaces scaffolded (PermissionResolver, AuthenticationService, MfaService, InvitationService, TenantOnboardingService)
- [ ] AC-7: Symfony autowiring działa — `php bin/console debug:container` pokazuje IdentityBundle services
- [ ] AC-8: PSR-4 autoload z namespace `Cortex\Identity\` w `composer.json` (jeśli nie istnieje)
- [ ] AC-9: `composer dump-autoload` bez errors

**Files affected:**
- `src/Identity/**` (new — 25+ plików w sumie)
- `config/bundles.php` (modified)
- `composer.json` (modified — PSR-4 autoload entry jeśli brak)

**Testing requirements:**
- **Layer 1 — Unit:** `IdentityBundleTest::testBundleLoadsWithoutErrors()` ✓
- **Layer 1 — Unit:** Każdy Entity class ma `testCreateWithValidData()` ✓ + `testTenantIdIsRequired()` (z wyjątkiem SuperAdmin/Permission)
- **Layer 2 — Integration:** `php bin/console doctrine:schema:validate` exit code 0

**Definition of Done:**
- [ ] Acceptance criteria spełnione
- [ ] PHPStan max ✓
- [ ] Test coverage ≥ 80% (skeleton, pełne testy dochodzą per Phase)
- [ ] Manual smoke: `php bin/console debug:container | grep Identity` pokazuje services
- [ ] Manual smoke: utworzenie sample entity przez ORM, persist, find — bez errors
- [ ] CI green
- [ ] PR merged do main

---

## RBAC-P1-009: chore(tests): setup testcontainers Postgres for integration tests

**Typ:** `chore`
**Epik:** 0.X Identity & RBAC
**Phase:** 1 (Foundation)
**Estymacja:** **4-6h**
**Dependencies:**
- Blocks: Phase 2-7 (każdy integration test wymaga isolated Postgres)
- Blocked by: RBAC-P1-001 (Docker config patterns), RBAC-P1-004 (migrations do load), RBAC-P1-008 (fixtures z bundle)

**Risk flags:**
- **Test isolation** — każda test class musi mieć isolated Postgres (brak shared state między classes).
- **Performance** — testcontainer per class slow (10-30s per class spin up). Optymalizacja: shared container per suite + truncate w `setUp()`.
- **CI vs lokalny dev parity** — testy musi działać identycznie w GitHub Actions i lokalnie (`pnpm test` lub `composer test`).

**Cel ticketu:**
Setup testcontainers (Docker-based ephemeral Postgres) dla integration test suite Cortex PIM. Dedicated `IntegrationTestCase` base class która spawn'uje Postgres, ładuje migrations + fixtures, cleanup po teście.

**Scope:**

**Components:**
- `docker-compose.test.yml` — Postgres 16 + Redis 7 + MinIO + Mercure dla test environment
- `tests/IntegrationTestCase.php` — base class extends `ApiTestCase` z bootstrapping (start containers, run migrations, load fixtures)
- `tests/CrossTenantTestCase.php` (extension) — base dla cross-tenant isolation tests, creates 2 tenants z sample data
- Makefile targets:
  - `make test:integration` — run integration tests (assumes containers up)
  - `make test:integration:full` — spin up containers, run tests, tear down
  - `make test:cross-tenant` — dedicated suite cross-tenant isolation
- `.github/workflows/ci-tests.yml` — GitHub Actions workflow z testcontainers setup
- `phpunit.xml.dist` test suites:
  - `<testsuite name="unit">` — pure unit tests (fast, no DB)
  - `<testsuite name="integration">` — z testcontainers
  - `<testsuite name="cross-tenant">` — dedicated isolation suite

**Optymalizacja performance:**
- **Shared container per test suite** — `setUpBeforeClass()` spawnuje container raz, `tearDownAfterClass()` zatrzymuje. Per-test cleanup przez `TRUNCATE` zamiast container restart.
- **Migrations cached** — bazy template z migrations applied, każdy test clones template (Postgres native template DB feature).
- **Parallel test execution** — PHPUnit `--parallel` z multiple containers (max 4 parallel workers).

**Acceptance criteria:**
- [ ] AC-1: `docker-compose.test.yml` zawiera Postgres 16 + Redis 7 + MinIO + Mercure z health checks
- [ ] AC-2: `tests/IntegrationTestCase.php` spawn'uje Postgres + load migrations + load fixtures w `setUpBeforeClass()`
- [ ] AC-3: `tests/CrossTenantTestCase.php` tworzy 2 sample tenants z fixtures (`tenant_a`, `tenant_b`) w `setUp()`
- [ ] AC-4: `make test:integration` uruchamia sample integration test pass
- [ ] AC-5: `make test:cross-tenant` uruchamia sample isolation test pass
- [ ] AC-6: GitHub Actions workflow `ci-tests.yml` uruchamia integration tests w CI z testcontainers
- [ ] AC-7: Performance: pełna test suite (50+ test classes assumed) startuje w <60s overhead
- [ ] AC-8: Parallel execution działa — `phpunit --parallel=4` pass
- [ ] AC-9: Postgres template DB caching działa — drugi run testu w tej samej sesji nie wykonuje migrations again

**Files affected:**
- `docker-compose.test.yml` (new)
- `tests/IntegrationTestCase.php` (new)
- `tests/CrossTenantTestCase.php` (new)
- `Makefile` (modified — dodanie test targets)
- `phpunit.xml.dist` (modified — dodanie test suites)
- `.github/workflows/ci-tests.yml` (new lub modified)
- `composer.json` (modified — dodanie `behat/testcontainers-php` lub equivalent dev dependency)
- `docs/testing/integration-tests.md` (new — guide dla devów)

**Testing requirements:**
- Sample integration test `tests/Identity/SampleIntegrationTest.php` — query do `permissions` table → expected results
- Sample cross-tenant test `tests/Identity/SampleCrossTenantTest.php` — assertion że tenant A nie widzi data tenant B
- CI green for both samples

**Definition of Done:**
- [ ] Acceptance criteria spełnione
- [ ] PHPStan max ✓
- [ ] Manual smoke: `make test:integration:full` (full lifecycle) → tests pass
- [ ] CI green w GitHub Actions
- [ ] Dokumentacja `docs/testing/integration-tests.md` opisuje jak uruchamiać + debug
- [ ] PR merged do main

---

## RBAC-P1-010: chore(static-analysis): custom PHPStan rules — RBAC pattern enforcement

**Typ:** `chore`
**Epik:** 0.X Identity & RBAC
**Phase:** 1 (Foundation)
**Estymacja:** **6-10h**
**Dependencies:**
- Blocks: Phase 2-7 (każdy nowy endpoint będzie sprawdzany przez te rules)
- Blocked by: RBAC-P1-001 (PHPStan setup z tooling)

**Risk flags:**
- **False positives** — overly aggressive rule może blokować legitimne kod. Target <5% false positive rate.
- **Performance** — custom rules slow down PHPStan analysis. Acceptable: 2x slowdown over baseline.
- **Rule maintenance** — custom rules wymagają update gdy Symfony / API Platform major bump (regenerate AST patterns).

**Cel ticketu:**
Napisać 3 custom PHPStan rules które enforce'ują RBAC patterns w kodzie Cortex PIM. Bez tych rules dev może akcydentalnie deploy'ować endpoint bez permission check.

**Scope:**

**Rule 1: `RequiresPermissionAnnotationRule`**

Wymaga że każda public method w Symfony controller (z `#[Route]` attribute) ma jedno z:
- `#[RequiresPermission(module: '...', action: '...')]`
- `#[NoPermissionRequired]` (white-list dla `/login`, `/password-reset`, `/api/me`, etc.)

Detection: AST traversal po metodach z `#[Route]`, sprawdzenie attributes per method.

**Rule 2: `FlushWithoutClearRule`**

Wymaga że w Symfony Messenger handler w `messageHandler.handle()`:
- Jeśli wewnątrz loop jest `$entityManager->flush()`
- → wymagany follow-up `$entityManager->clear()` w tym samym scope (per CLAUDE.md §"Memory management — FrankenPHP worker mode")
- Lub class extends `AbstractBatchHandler` (który auto-clear robi)

Detection: AST traversal po `MessageHandlerInterface` / `#[AsMessageHandler]` classes, search for `flush()` w loop body.

**Rule 3: `HardcodedRoleCheckRule`**

Forbidden patterns w controllers / services:
- `if ($user->hasRole('ROLE_ADMIN')) { ... }`
- `if (in_array('admin', $user->getRoles())) { ... }`
- `$user->isAdmin()`

Required pattern (przekierowanie do Voter):
- `if ($this->security->isGranted('products.edit', $product)) { ... }`
- `#[IsGranted('products.edit', subject: 'product')]` attribute

Detection: AST string match w controller/service classes, exclude w Voter classes (gdzie hasRole jest legit).

**Implementation:**
- `phpstan/Rules/RequiresPermissionAnnotationRule.php`
- `phpstan/Rules/FlushWithoutClearRule.php`
- `phpstan/Rules/HardcodedRoleCheckRule.php`
- `phpstan/services.neon` — register rules dla PHPStan
- `phpstan.neon` modified — include `phpstan/services.neon`

**Documentation:**
- `docs/static-analysis/custom-rules.md` — opis każdej rule + przykład allowed/disallowed

**Acceptance criteria:**
- [ ] AC-1: Rule 1 — sample controller z `#[Route]` ale bez `#[RequiresPermission]` → PHPStan error
- [ ] AC-2: Rule 1 — sample controller z `#[NoPermissionRequired]` → PHPStan pass
- [ ] AC-3: Rule 2 — sample handler z `flush()` w loop bez `clear()` → PHPStan error
- [ ] AC-4: Rule 2 — sample handler extends `AbstractBatchHandler` → PHPStan pass
- [ ] AC-5: Rule 3 — sample controller z `if ($user->hasRole('ROLE_ADMIN'))` → PHPStan error
- [ ] AC-6: Rule 3 — sample Voter class z `hasRole('admin')` → PHPStan pass (exclusion)
- [ ] AC-7: PHPStan baseline (existing codebase) NIE ma false positives z 3 nowych rules (analyst review)
- [ ] AC-8: Performance: `phpstan analyse` baseline + 3 custom rules <2x slower than baseline
- [ ] AC-9: Dokumentacja `docs/static-analysis/custom-rules.md` z 3 przykładami allowed/disallowed per rule

**Files affected:**
- `phpstan/Rules/RequiresPermissionAnnotationRule.php` (new)
- `phpstan/Rules/FlushWithoutClearRule.php` (new)
- `phpstan/Rules/HardcodedRoleCheckRule.php` (new)
- `phpstan/services.neon` (new)
- `phpstan.neon` (modified)
- `docs/static-analysis/custom-rules.md` (new)
- `tests/StaticAnalysis/CustomRulesTest.php` (new — z fixtures dla positive/negative cases)

**Testing requirements:**
- **Layer 1 — Unit:** `CustomRulesTest` z fixture files (intencjonalnie złe patterns) — assert że PHPStan zwraca expected errors
- **Layer 1 — Unit:** `CustomRulesTest` z fixture files (poprawne patterns) — assert że PHPStan pass
- Manual smoke: dodać intencjonalnie złą metodę do istniejącego controller w sample branch → run PHPStan → expected error

**Definition of Done:**
- [ ] Acceptance criteria spełnione
- [ ] Test coverage 95%+ dla custom rules
- [ ] Manual smoke: 3 violations w sample branch → PHPStan blocks
- [ ] CI green
- [ ] PR merged do main

---

## Phase 1 zakończony — co dalej

**Phase 1 deliverables (po merge wszystkich 10 ticketów):**

- ADR-013 w architekturze
- CLAUDE.md zaktualizowany
- Schema RBAC w bazie (10 tabel + delta 2 tabele)
- 50 permissions seedowanych
- 9 role templates seedowanych
- IdentityBundle skeleton
- Testcontainers Postgres setup
- Security tooling stack (Infection, Semgrep, OWASP ZAP, TruffleHog, pre-commit)
- 3 custom PHPStan rules

**Manual smoke test Phase 1 completion:**
1. `pnpm stack:up`
2. `make test:integration:full` — wszystkie integration tests pass
3. `composer test:mutation:rbac` — MSI report dla `src/Identity/`
4. `php bin/console doctrine:schema:validate` — pass
5. `php bin/console cortex:tenant:seed-roles {test_tenant_id}` — 8 ról dla test tenanta
6. Postgres CLI: `SELECT r.name, COUNT(p.id) FROM roles r JOIN role_permissions rp ON r.id=rp.role_id JOIN permissions p ON p.id=rp.permission_id WHERE r.tenant_id=':test_tenant' GROUP BY r.name` — wyniki matchują macierz 3.2 PRD

**Phase 2 start (po Phase 1 zakończony):**
- Następne 12-15 ticketów: backend auth (JWT, magic link, MFA, SSO), tenant context resolver, permission resolver z Redis cache, `/api/me` endpoint
- Estymacja Phase 2: 80-110h (3 tygodnie)

---

## Pytania do Marcina przed startem implementacji Phase 1

1. **Format ticketów** — OK czy chcesz coś zmienić? (zbyt długie / brakuje sekcji / inny układ)
2. **Estymacje per ticket** — realistyczne czy zbyt optymistyczne / pesymistyczne? (Marcin solo dev tempo)
3. **Numeracja `RBAC-P1-XXX`** — OK czy preferujesz inną convention (np. issue numbers GitHub jako primary)?
4. **Plik docelowy** — czy tickety przenosimy do `02-plan-projektu-pim.md` (master backlog) czy zostają w `08-rbac-tickets-phase-1.md` jako separate?
5. **Phase 2-7 tickets** — po zaakceptowaniu Phase 1 formatu, czy rozpisać wszystkie 70-110 ticketów na raz w jednym dokumencie, czy phase-by-phase (kolejna Phase rozpisana gdy zaczynamy Phase 1 last ticket)?

Po Twojej odpowiedzi:
- Update'uję 10 ticketów Phase 1 z poprawkami (jeśli są)
- Tworzę GitHub Issues z tymi ticketami (lub Ty tworzysz, dostarczam ready-to-paste markdown)
- Rozpisuję Phase 2-7 zgodnie z preferowanym tempem
