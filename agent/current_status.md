# Current Status

## Sub-faza: PRE-SPRINT-0 — faza koncepcyjna zakończona, oczekiwanie na rozpoczęcie Sprintu 0

## Ostatnie 3 akcje
1. Zakończona faza koncepcyjna — zatwierdzona architektura (`Project Plan/01-architektura-pim.md`, v1.0, 2026-04-26) po trzech rundach review (Gemini/DeepSeek/Grok) + finalna polerka.
2. Zatwierdzony plan projektu (`Project Plan/02-plan-projektu-pim.md`, v1.0) — 4 sub-fazy MVP, estymacja 201-274h dla Fazy 0 (z opcją okrojenia do 172-235h), backlog 12 epików, rejestr ryzyk (R-01 do R-28).
3. Stworzona "konstytucja projektu" dla Claude Code (`CLAUDE.md`) + initial seed `agent/lessons.md` z twardymi wytycznymi z architektury (memory mgmt, single-origin, Shopify backoff, RLS gotchas).

## Bieżący stan
Projekt **przed pierwszym ticketem implementacyjnym**. Repozytorium git nie istnieje. Stack nie jest zainstalowany. Sprint 0 (40-55h) nie rozpoczęty.

Decyzja po Sprincie 0 będzie gate'em do dalszej pracy (sekcja 3.0 planu) — wynik zielony lub czerwony.

## Aktywne blokery
- **Decyzja operacyjna:** wybór trybu wykonania Sprintu 0 — rekomendowane 1-2 tygodnie urlopu/skupienia (sekcja 7 planu, mitigacja R-26 stack drift). Bez tego ryzyko że stack złoży się w pamięci agenta i operatora w niespójną całość.
- **Setup konta Shopify Partners** — potrzebny development store free na ticket 0.0.8 i pełen Epic 0.9.
- **Anthropic API key** — potrzebny dla agent layer (ticket 0.0.6), z org-level cap $1000/mies. ustawionym w Anthropic Console przed pierwszym call (defence in depth, R-27).
- **Wybór hostingu / providera** — decyzja na pierwszy pilot (rekomendowane: OVH, Hetzner, mikr.us — sekcja 9.2 planu). Może być odłożone do MVP-Final.

## Następny krok
Rozpocząć **Sprint 0, ticket 0.0.1**: setup monorepo Turborepo (`apps/api` + `apps/admin` + `packages/shared-types`) + docker-compose w minimalnej formie (FrankenPHP 2.x + Symfony 7.4 + Postgres 16 + Redis 7 + Meilisearch + MinIO + Mercure + Mailpit) + **Caddyfile single-origin** (`/api/*` → FrankenPHP, reszta → `vite:5173`).

Krytyczne dla 0.0.1: **NIE konfigurujemy CORS, NIE wystawiamy frontend pod osobnym portem na host** — sekcja 3.10a architektury i `agent/lessons.md`.

## Postęp po sub-fazach (cumulative h, MVP Core)
- [ ] Sprint 0 (gate decision) — 0/40-55h
- [ ] MVP-Alpha (epiki 0.1–0.6) — 0/80-110h
- [ ] MVP-Beta-Min (część 0.7) — 0/12-16h
- [ ] MVP-Final (epiki 0.8–0.11) — 0/70-94h
- [ ] MVP-Beta-Full (część 0.7, opcjonalnie) — 0/13-19h

## Postęp epików (zerowy do czasu Sprintu 0)
- [ ] 0.1 Infrastructure i fundamenty (16-22h)
- [ ] 0.2 Identity & Access (10-14h)
- [ ] 0.3 Domain model — Catalog (16-20h)
- [ ] 0.4 API Platform — exposing entities (10-14h)
- [ ] 0.5 Search — Meilisearch (6-8h)
- [ ] 0.6 Admin UI — core CRUD (20-26h)
- [ ] 0.7 Agent layer — schema-add (25-35h, podzielony Beta-Min + Beta-Full)
- [ ] 0.8 Integracja BaseLinker (12-16h)
- [ ] 0.9 Integracja Shopify (14-18h)
- [ ] 0.10 API Configurator (8-12h)
- [ ] 0.11 Hardening, a11y, analytics, backup, BYOK (24-34h)

## Notatka dla Claude Code (next session boot)
Po starcie sesji:
1. Przeczytaj `CLAUDE.md` (auto-loaded).
2. Przeczytaj `agent/lessons.md` — szczególnie sekcje "Patterns to Avoid" i "Package Quirks".
3. Sprawdź `Project Plan/02-plan-projektu-pim.md` sekcja 3.0 (Sprint 0 zakres).
4. Jeśli operator nie powiedział inaczej — zaczynamy od ticketu 0.0.1.
