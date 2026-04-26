# Current Status

## Sub-faza: PRE-SPRINT-0 — backlog rozpisany na GitHub Issues, oczekiwanie na start ticketu 0.0.1

## Ostatnie 3 akcje
1. **Backlog rozpisany na GitHub Issues** — 112 ticketów (Sprint 0 + epiki 0.1–0.11) w 5 milestones, 29 labels (12 epików + 9 typów + 4 priorytet + 4 status), z body w formacie Cel / Zakres / Definicja Done / Powiązania (sekcja architektury + plan + Blocked by). Wszystkie przypisane do `@malipie`. Issue templates (`ticket.md`, `bug.md`) + `config.yml` w `.github/ISSUE_TEMPLATE/`. Lessons.md zaktualizowany o decyzję pomijania estymat godzinowych w issues (2026-04-26).
2. Stworzona "konstytucja projektu" `CLAUDE.md` (141 linii) + initial seed `agent/lessons.md` z twardymi wytycznymi z architektury (memory mgmt, single-origin, Shopify backoff, RLS gotchas).
3. `git init` + `.gitignore` + initial commit + push do prywatnego repo na GitHub (`malipie/PIM`).

## Bieżący stan
Repozytorium gotowe do startu Sprintu 0:
- 112 issues w GitHubie z pełnymi opisami, labelami, milestone, assignee, linkami `Blocked by` w kolejności topologicznej
- 5 milestones: Sprint 0 (#1, gate decision), MVP-Alpha (#2), MVP-Beta-Min (#3), MVP-Final (#4), MVP-Beta-Full (#5, opcjonalnie)
- GitHub Project (v2) `PIM MVP` (https://github.com/users/malipie/projects/1) — wszystkie 112 issues podpięte, default Status field z opcjami Todo / In Progress / Done
- Issue templates w `.github/ISSUE_TEMPLATE/`
- 29 labels (epiki, typy pracy, priorytet, status)
- CLAUDE.md + agent/lessons.md jako konstytucja
- Project Plan + Architektura zaktualizowane (1688 linii dokumentacji)

**Stack nie jest zainstalowany. Sprint 0 (40-55h) nie rozpoczęty.** Decyzja po Sprincie 0 będzie gate'em do dalszej pracy (sekcja 3.0 planu) — wynik zielony lub czerwony.

## Rozkład issues per milestone

| Milestone | Issues | Tickety |
|---|---|---|
| Sprint 0 — Vertical Slice | #1-#16 (16) | 0.0.1–0.0.16 |
| MVP-Alpha (epiki 0.1–0.6) | #17-#62 (46) | 0.1.1–0.1.7, 0.2.1–0.2.7, 0.3.1–0.3.10, 0.4.1–0.4.8, 0.5.1–0.5.5, 0.6.1–0.6.9 |
| MVP-Beta-Min (część 0.7) | #63-#71 (9) | 0.7.1, .2, .3, .4, .5, .7, .8a, .9a, .11 |
| MVP-Final (epiki 0.8–0.11) | #72-#107 (36) | 0.8.1–0.8.7, 0.9.1–0.9.11, 0.10.1–0.10.6, 0.11.1–0.11.12 |
| MVP-Beta-Full (część 0.7, opcjonalnie) | #108-#112 (5) | 0.7.6, .8b, .9b, .10, .12 |
| **Razem** | **112** | |

## Aktywne blokery
- **Decyzja operacyjna:** wybór trybu wykonania Sprintu 0 — rekomendowane 1-2 tygodnie urlopu/skupienia (sekcja 7 planu, mitigacja R-26 stack drift). Bez tego ryzyko że stack złoży się w pamięci agenta i operatora w niespójną całość.
- **Setup konta Shopify Partners** — potrzebny development store free na ticket 0.0.8 (#8) i pełen Epic 0.9.
- **Anthropic API key** — potrzebny dla agent layer (ticket 0.0.6, #6), z org-level cap $1000/mies. ustawionym w Anthropic Console przed pierwszym call (defence in depth, R-27).
- **Wybór hostingu / providera** — decyzja na pierwszy pilot (rekomendowane: OVH, Hetzner, mikr.us — sekcja 9.2 planu). Może być odłożone do MVP-Final.

## Następny krok
**Start ticketu #1 — `[0.0.1] Setup monorepo Turborepo + docker-compose minimum + Caddy single-origin`** (po potwierdzeniu operatora).

Krytyczne dla 0.0.1: **NIE konfigurujemy CORS, NIE wystawiamy frontend pod osobnym portem na host** — sekcja 3.10a architektury i `agent/lessons.md`. Caddyfile single-origin: `pim.localhost/api/*` → FrankenPHP/Symfony, `/.well-known/mercure` → Mercure, reszta → reverse proxy do `vite:5173` (HMR przez WebSocket upgrade).

## Postęp po sub-fazach (cumulative h, MVP Core)
- [ ] Sprint 0 (gate decision) — 0/40-55h — issues #1-#16
- [ ] MVP-Alpha (epiki 0.1–0.6) — 0/80-110h — issues #17-#62
- [ ] MVP-Beta-Min (część 0.7) — 0/12-16h — issues #63-#71
- [ ] MVP-Final (epiki 0.8–0.11) — 0/70-94h — issues #72-#107
- [ ] MVP-Beta-Full (część 0.7, opcjonalnie) — 0/13-19h — issues #108-#112

## Postęp epików (zerowy do czasu Sprintu 0)
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
2. Przeczytaj `agent/lessons.md` — szczególnie sekcje "Patterns to Avoid" i "Package Quirks".
3. Sprawdź `Project Plan/02-plan-projektu-pim.md` sekcja 3.0 (Sprint 0 zakres).
4. Lista wszystkich issues: `gh issue list --milestone "Sprint 0 — Vertical Slice"` lub na https://github.com/malipie/PIM/issues
5. Jeśli operator nie powiedział inaczej — zaczynamy od ticketu #1 (0.0.1 Setup monorepo).
