# Current Status

## Sub-faza: SPRINT-0 — 2/16 ticketów done, oczekiwanie na wybór następnego ticketu

## Ostatnie 3 akcje
1. **Ticket #11 (0.0.11) zamknięty** (PR #114 zmergowany do main 2026-04-27). Quality gates automation-first wpięte: PHPStan max (level 10) + extensions (Symfony, Doctrine, strict-rules) + PHP-CS-Fixer Symfony+PHP84Migration; Biome strict zastąpił ESLint w apps/admin (single tool: lint+format z `noNonNullAssertion`, `noExplicitAny`, a11y); husky pre-commit (lint-staged: Biome + PHP-CS-Fixer w container) + commit-msg (commitlint enforced Conventional Commits); GitHub Actions: `quality-php.yml`, `quality-frontend.yml`, `audit.yml` (composer + pnpm audit nightly + PR). **Świadome odejście:** Psalm pominięty (vimeo/psalm:dev-master ma circular conflict z psalm-plugin-api) — PHPStan max + strict-rules daje równoważne pokrycie. **Świadome odejście:** `git config core.fileMode = false` (Synology Drive zmienia permissions między sync) — hooki + skrypty mają +x zarejestrowane przez `git update-index --chmod=+x`.
2. **Ticket #1 (0.0.1) zamknięty** (PR #113 zmergowany 2026-04-26). Postawione: Turborepo monorepo, Symfony 7.4 LTS + API Platform 4.3 + FrankenPHP 2.x worker mode (bez nelmio/cors), Vite + React 19 + TS, docker-compose z 9 services (Caddy edge + api + admin + Postgres 16 + Redis 7 + Meilisearch v1.13 + MinIO + minio-init + Mercure + Mailpit), `docker/caddy/Caddyfile` single-origin (`/api*` → FrankenPHP, `/.well-known/mercure*` → hub, `/*` → Vite z HMR przez WebSocket upgrade). Smoke green: 4 endpointy 200, wszystkie services healthy. Świadome odejścia: api-platform/api-platform z Packagist to archiwalny 2018, pivot do symfony/skeleton 7.4 + composer require api-platform/symfony:^4.3; healthchecki na `/api` (`.json` nie odpowiada w API Platform 4).
3. **Backlog rozpisany na GitHub Issues** (2026-04-26) — 112 ticketów (Sprint 0 + epiki 0.1–0.11) w 5 milestones, 29 labels. GitHub Project (v2) `PIM MVP` z 112 items podpiętych. Issue templates + lessons.md z decyzją pomijania estymat godzinowych w issues.

## Bieżący stan
Sprint 0 = 2/16 ticketów done (#1 setup monorepo, #11 quality gates).

Stack postawiony, zatrzymany (po sesji 2026-04-27). Aby uruchomić: `pnpm stack:up` (lub `pnpm dev` w foreground).

Quality gates aktywne:
- **Lokalnie**: pre-commit hook + commit-msg hook (husky) — sprawdza Biome + PHP-CS-Fixer + Conventional Commits przed commit.
- **CI**: GitHub Actions na PR + push do main: quality-php (PHPStan + PHP-CS-Fixer), quality-frontend (Biome + tsc + Vite build), audit (composer + pnpm, nightly).

**Akcje po stronie operatora (do zrobienia w wolnej chwili, nie blocker):**
- Branch protection na `main` (Settings → Branches → Add rule):
  - Require status checks: `phpstan`, `php-cs-fixer`, `biome`, `typecheck`, `build`, `composer-audit`, `pnpm-audit`
  - Require branch up to date before merge
  - (Opcjonalnie) Require 1 approval (pominąć przy single-developer)
- Po pull: `pnpm install` żeby husky `prepare` script zainstalował hooki na świeżo sklonowanym repo.

Świadome odejścia od planu (do uzupełnienia w `06-sprint-0-findings.md` na koniec Sprintu 0):
1. `api-platform/api-platform` z Packagist to archiwalny skeleton z 2018 — pivot do `symfony/skeleton 7.4` + `composer require api-platform/symfony:^4.3`. (#1)
2. `/api/docs.json` nie odpowiada w API Platform 4 (tylko `.jsonld` + `.html`); healthchecki używają `/api`. (#1)
3. Psalm strict pominięty — `vimeo/psalm:dev-master` ma conflict z `psalm/psalm-plugin-api 0.1.0`. PHPStan max + strict-rules pokrywa zakres. (#11)
4. `git config core.fileMode = false` ustawione lokalnie (Synology Drive zmienia bits 644→755 między sync). (#11)

## Aktywne blokery
- **Setup konta Shopify Partners** — potrzebny development store free na ticket #8 (0.0.8) i pełen Epic 0.9.
- **Anthropic API key** — potrzebny dla agent layer (ticket #6 / 0.0.6), z org-level cap $1000/mies. ustawionym w Anthropic Console przed pierwszym call.
- **Wybór hostingu / providera** — decyzja na pierwszy pilot (rekomendowane: OVH, Hetzner, mikr.us). Może być odłożone do MVP-Final.
- **Decyzja operacyjna:** wybór trybu wykonania Sprintu 0 — rekomendowane 1-2 tygodnie urlopu/skupienia (sekcja 7 planu).

## Następny krok
**Czeka na decyzję operatora który ticket podejmujemy następny.** Topologicznie odblokowane (wszystkie tylko depend on #1):

| # | Ticket | Komentarz |
|---|---|---|
| **#2 (0.0.2)** | Encja Product + tenant_id + Doctrine TenantFilter | **Rekomendacja** — fundament domain model, blocker dla #3 (ApiResource), #5 (admin lista), #12 (smoke izolacji multi-tenant), #13 (benchmark memory) |
| #4 (0.0.4) | Auth minimalny (statyczny user + JWT LexikJWT) | Niezależny od #2; potrzebny przed #5 (admin login) i #6 (agent endpoint) |
| #15 (0.0.15) | pgBackRest + WAL stub w docker-compose + restore test | Niezależny |
| #8 (0.0.8) | Klient Shopify GraphQL + Backoff stub | **Blocker:** wymaga konta Shopify Partners dev store |

## Postęp po sub-fazach (cumulative h, MVP Core)
- [ ] Sprint 0 (gate decision) — **2/16 ticketów done** — issues #1-#16
- [ ] MVP-Alpha (epiki 0.1–0.6) — 0/46 — issues #17-#62
- [ ] MVP-Beta-Min (część 0.7) — 0/9 — issues #63-#71
- [ ] MVP-Final (epiki 0.8–0.11) — 0/36 — issues #72-#107
- [ ] MVP-Beta-Full (część 0.7, opcjonalnie) — 0/5 — issues #108-#112

## Postęp Sprint 0 ticketów
- [x] **#1 / 0.0.1** — Setup monorepo Turborepo + docker-compose + Caddy single-origin (PR #113 merged 2026-04-26)
- [ ] #2 / 0.0.2 — Encja Product + tenant_id + Doctrine TenantFilter
- [ ] #3 / 0.0.3 — ApiResource Product → /api/products
- [ ] #4 / 0.0.4 — Authentication minimalny + JWT
- [ ] #5 / 0.0.5 — Admin Refine + shadcn lista produktów
- [ ] #6 / 0.0.6 — Agent endpoint + tool create_attribute + limity 8.5
- [ ] #7 / 0.0.7 — Cmd+K placeholder UI
- [ ] #8 / 0.0.8 — Klient Shopify GraphQL + Backoff stub
- [ ] #9 / 0.0.9 — Manualny E2E Sprintu 0 + screencast
- [ ] #10 / 0.0.10 — Playwright E2E od dnia 1
- [x] **#11 / 0.0.11** — PHPStan max + PHP-CS-Fixer + Biome + husky + CI (PR #114 merged 2026-04-27)
- [ ] #12 / 0.0.12 — Smoke izolacji multi-tenant
- [ ] #13 / 0.0.13 — Benchmark FrankenPHP worker memory
- [ ] #14 / 0.0.14 — Profilowanie Blackfire/Tideways
- [ ] #15 / 0.0.15 — pgBackRest + WAL stub
- [ ] #16 / 0.0.16 — Audit CLAUDE.md + 06-sprint-0-findings.md

## Postęp epików (poza Sprintem 0 — zerowy)
- [ ] 0.1 Infrastructure i fundamenty — #17-#23
- [ ] 0.2 Identity & Access — #24-#30
- [ ] 0.3 Domain model — Catalog — #31-#40
- [ ] 0.4 API Platform — exposing entities — #41-#48
- [ ] 0.5 Search — Meilisearch — #49-#53
- [ ] 0.6 Admin UI — core CRUD — #54-#62
- [ ] 0.7 Agent layer — schema-add — #63-#71 (Beta-Min) + #108-#112 (Beta-Full)
- [ ] 0.8 Integracja BaseLinker — #72-#78
- [ ] 0.9 Integracja Shopify — #79-#89
- [ ] 0.10 API Configurator — #90-#95
- [ ] 0.11 Hardening, a11y, analytics, backup, BYOK — #96-#107

## Notatka dla Claude Code (next session boot)
Po starcie sesji:
1. Przeczytaj `CLAUDE.md` (auto-loaded).
2. Przeczytaj `agent/lessons.md` — szczególnie "Patterns to Avoid", "Package Quirks", "Toolchain quirks".
3. Sprawdź `Project Plan/02-plan-projektu-pim.md` sekcja 3.0 (Sprint 0 zakres) jeśli zaczynasz nowy ticket Sprint 0.
4. Lista pozostałych issues Sprint 0: `gh issue list --milestone "Sprint 0 — Vertical Slice" --state open`
5. Stack: **`pnpm stack:up`** (lub `pnpm dev` foreground), `https://pim.localhost` po akceptacji Caddy local CA.
6. Przed commit: hooki husky odpalą się automatycznie. Jeśli hook zfailuje przy pierwszym commit po pull, odpal `pnpm install` żeby `prepare` script zarejestrował hooki.
7. Quality gates są aktywne — każdy commit i PR przechodzi przez PHPStan max, PHP-CS-Fixer, Biome strict, tsc, composer/pnpm audit. Nie pomijaj `--no-verify`.
8. Jeśli operator nie powiedział inaczej — rekomendacja na następny ticket: **#2 (0.0.2 Product entity + tenant_id + TenantFilter)**.
