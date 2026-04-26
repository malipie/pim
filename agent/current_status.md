# Current Status

## Sub-faza: SPRINT-0 — ticket 0.0.1 zamknięty, oczekiwanie na wybór następnego ticketu

## Ostatnie 3 akcje
1. **Ticket #1 (0.0.1) zamknięty** (PR #113 zmergowany do main `0166366`). Postawione: Turborepo monorepo (apps/api, apps/admin, packages/shared-types), Symfony 7.4 LTS + API Platform 4.3 + FrankenPHP 2.x worker mode (bez `nelmio/cors-bundle`), Vite + React 19 + TS, docker-compose z 9 services (Caddy edge + api + admin + Postgres 16 + Redis 7 + Meilisearch v1.13 + MinIO + minio-init + Mercure + Mailpit), `docker/caddy/Caddyfile` single-origin (`/api*` → FrankenPHP, `/.well-known/mercure*` → hub, `/*` → Vite z HMR przez WebSocket upgrade). Smoke green: 4 endpointy 200, wszystkie services healthy.
2. **Backlog rozpisany na GitHub Issues** — 112 ticketów (Sprint 0 + epiki 0.1–0.11) w 5 milestones, 29 labels. GitHub Project (v2) `PIM MVP` z 112 items podpiętych.
3. Stworzona `CLAUDE.md` (konstytucja projektu) + initial seed `agent/lessons.md`. `git init` + initial commit + push do `malipie/PIM`.

## Bieżący stan
Sprint 0 w toku — 1/16 ticketów done.

Stack jest postawiony, działa, healthy. Można podjąć dowolny z odblokowanych ticketów Sprintu 0:
- **#11 (0.0.11)** — PHPStan max + Psalm strict + Biome strict + PHP-CS-Fixer w CI **(rekomendacja: pierwsze, bo każdy kolejny ticket dostaje gate)**
- **#15 (0.0.15)** — pgBackRest + WAL stub w docker-compose + restore test
- **#10 (0.0.10)** — Playwright E2E od dnia 1 (wymaga frontend ekranu — depend on #5, więc realnie po #5)
- **#2 (0.0.2)** — encja Product + tenant_id + Doctrine TenantFilter
- **#4 (0.0.4)** — Authentication minimalny (statyczny user + JWT LexikJWT)
- **#8 (0.0.8)** — klient Shopify GraphQL stub (blocker: wymaga konta Shopify Partners dev store)

Świadome odejścia od planu w 0.0.1 (do uzupełnienia w `06-sprint-0-findings.md` na koniec Sprintu 0):
- `api-platform/api-platform` z Packagist okazał się archiwalnym skeleton z 2018 — pivot do `symfony/skeleton 7.4` + `composer require api-platform/symfony:^4.3`.
- `/api/docs.json` nie odpowiada w API Platform 4 (tylko `.jsonld` + `.html`); healthchecki używają `/api`.

Lekcja: **pnpm via npm install -g**, nie corepack — corepack nie był aktywny w naszym Node 25 z Homebrew. (Do dodania w `lessons.md` jako Package Quirk.)

## Aktywne blokery
- **Setup konta Shopify Partners** — potrzebny development store free na ticket #8 (0.0.8) i pełen Epic 0.9.
- **Anthropic API key** — potrzebny dla agent layer (ticket #6 / 0.0.6), z org-level cap $1000/mies. ustawionym w Anthropic Console przed pierwszym call (defence in depth, R-27).
- **Wybór hostingu / providera** — decyzja na pierwszy pilot (rekomendowane: OVH, Hetzner, mikr.us — sekcja 9.2 planu). Może być odłożone do MVP-Final.
- **Decyzja operacyjna:** wybór trybu wykonania Sprintu 0 — rekomendowane 1-2 tygodnie urlopu/skupienia (sekcja 7 planu, mitigacja R-26 stack drift).

## Następny krok
**Czeka na decyzję operatora który ticket podejmujemy następny.** Topologicznie odblokowane (po zamknięciu #1): #2, #4, #8, #10, #11, #15.

Rekomendacja: **#11 (0.0.11)** — quality gates w CI. Każdy kolejny commit będzie miał automatyczne sprawdzenie PHPStan max + Psalm strict + Biome strict + PHP-CS-Fixer. Eliminuje regresje z early ticketów (operator nie udaje code review LLM-kodu — automatyzacja jest jedyną realną warstwą walidacji per `agent/lessons.md`).

Alternatywa: **#15 (0.0.15)** — pgBackRest stub. Niezależny od pozostałych, samodzielny ticket.

## Postęp po sub-fazach (cumulative h, MVP Core)
- [ ] Sprint 0 (gate decision) — 1/16 ticketów done — issues #1-#16
- [ ] MVP-Alpha (epiki 0.1–0.6) — 0/46 — issues #17-#62
- [ ] MVP-Beta-Min (część 0.7) — 0/9 — issues #63-#71
- [ ] MVP-Final (epiki 0.8–0.11) — 0/36 — issues #72-#107
- [ ] MVP-Beta-Full (część 0.7, opcjonalnie) — 0/5 — issues #108-#112

## Postęp Sprint 0 ticketów
- [x] #1 / 0.0.1 — Setup monorepo Turborepo + docker-compose + Caddy single-origin (PR #113 merged)
- [ ] #2 / 0.0.2 — Encja Product + tenant_id + Doctrine TenantFilter
- [ ] #3 / 0.0.3 — ApiResource Product → /api/products
- [ ] #4 / 0.0.4 — Authentication minimalny + JWT
- [ ] #5 / 0.0.5 — Admin Refine + shadcn lista produktów
- [ ] #6 / 0.0.6 — Agent endpoint + tool create_attribute + limity 8.5
- [ ] #7 / 0.0.7 — Cmd+K placeholder UI
- [ ] #8 / 0.0.8 — Klient Shopify GraphQL + Backoff stub
- [ ] #9 / 0.0.9 — Manualny E2E Sprintu 0 + screencast
- [ ] #10 / 0.0.10 — Playwright E2E od dnia 1
- [ ] #11 / 0.0.11 — PHPStan + Psalm + Biome + PHP-CS-Fixer w CI
- [ ] #12 / 0.0.12 — Smoke izolacji multi-tenant
- [ ] #13 / 0.0.13 — Benchmark FrankenPHP worker memory
- [ ] #14 / 0.0.14 — Profilowanie Blackfire/Tideways
- [ ] #15 / 0.0.15 — pgBackRest + WAL stub
- [ ] #16 / 0.0.16 — Audit CLAUDE.md + 06-sprint-0-findings.md

## Postęp epików (poza Sprintem 0 — zerowy)
- [ ] 0.1 Infrastructure i fundamenty (16-22h) — #17-#23
- [ ] 0.2 Identity & Access (10-14h) — #24-#30
- [ ] 0.3 Domain model — Catalog (16-20h) — #31-#40
- [ ] 0.4 API Platform — exposing entities (10-14h) — #41-#48
- [ ] 0.5 Search — Meilisearch (6-8h) — #49-#53
- [ ] 0.6 Admin UI — core CRUD (20-26h) — #54-#62
- [ ] 0.7 Agent layer — schema-add (25-35h, podzielony Beta-Min #63-#71 + Beta-Full #108-#112)
- [ ] 0.8 Integracja BaseLinker (12-16h) — #72-#78
- [ ] 0.9 Integracja Shopify (14-18h) — #79-#89
- [ ] 0.10 API Configurator (8-12h) — #90-#95
- [ ] 0.11 Hardening, a11y, analytics, backup, BYOK (24-34h) — #96-#107

## Notatka dla Claude Code (next session boot)
Po starcie sesji:
1. Przeczytaj `CLAUDE.md` (auto-loaded).
2. Przeczytaj `agent/lessons.md` — szczególnie "Patterns to Avoid" i "Package Quirks".
3. Sprawdź `Project Plan/02-plan-projektu-pim.md` sekcja 3.0 (Sprint 0 zakres).
4. Lista pozostałych issues Sprint 0: `gh issue list --milestone "Sprint 0 — Vertical Slice" --state open`
5. Jeśli operator nie powiedział inaczej — zacznij od rekomendacji w "Następny krok" (zwykle ticket #11 quality gates albo następny topologicznie odblokowany).
6. **Stack jest już postawiony** — `docker compose up -d` startuje wszystko, `https://pim.localhost` odpowiada (pierwsza wizyta wymaga akceptacji Caddy local CA cert).
