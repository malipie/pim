# PIM — Product Information Management

Agentic-first PIM platform. Konkurent PIMcore/Akeneo.

**Status:** Sprint 0 zamknięty 2026-04-28 (gate decision GREEN, 13/13 ticketów). Aktualnie: MVP-Alpha — patrz [`agent/current_status.md`](agent/current_status.md).

## Stack (MVP)

PHP 8.4 + Symfony 7.4 LTS + API Platform 4 + FrankenPHP 2.x worker mode · PostgreSQL 16 (JSONB+ltree+RLS) · Meilisearch · Redis 7 · React 19 + Vite + Refine.dev + shadcn/ui · Mercure (SSE) · Anthropic SDK PHP · monorepo Turborepo.

## Dokumentacja

- **`CLAUDE.md`** — konstytucja projektu (system prompt dla Claude Code)
- **`CONTRIBUTING.md`** + **`ONBOARDING.md`** — onboarding nowego developera
- **`CHANGELOG.md`** — release history per epic
- **`Project Plan/01-architektura-pim.md`** — pełna architektura, ADR, model danych
- **`Project Plan/02-plan-projektu-pim.md`** — fazy, milestones, backlog, ryzyka
- **`docs/adr/`** — Architecture Decision Records (ADR-0000..0016)
- **`docs/runbook/restore.md`** — pgBackRest PITR walkthrough
- **`docs/runbook/disaster-recovery.md`** — incident-response playbook (key rotation, breach forensics, async drift)
- **`docs/multi-tenancy.md`** + **`docs/rbac.md`** — security model deep-dive
- **`agent/current_status.md`** — aktualna sub-faza i postęp
- **`agent/lessons.md`** — patterns to follow / avoid, package quirks

## Struktura monorepo

```
.
├── apps/
│   ├── api/        Symfony 7.4 + API Platform 4 + FrankenPHP (PHP 8.4)
│   └── admin/      Vite + React 19 + TypeScript (Refine + shadcn dochodzą w 0.1.4)
├── packages/
│   └── shared-types/   TypeScript types generowane z OpenAPI spec (build step)
├── docker/
│   └── caddy/      Edge Caddyfile — single-origin proxy
├── docker-compose.yml  Cały stack dev: Caddy + FrankenPHP + Postgres + Redis +
│                       Meilisearch + MinIO + Mercure + Mailpit
└── Project Plan/   Architektura, plan, ADR-y
```

## Wymagania

- **Docker** (Desktop / OrbStack / colima) — daemon musi działać
- **Node** ≥22 + **pnpm** ≥10 (`npm install -g pnpm@latest` lub via corepack)
- **`pim.localhost`** — macOS rozwiązuje automatycznie (RFC 6761). Inne systemy: dodaj `127.0.0.1 pim.localhost` do `/etc/hosts`.

## Lokalny development

```bash
# 1. Sklonuj i zainstaluj zależności node (root + apps/admin + packages/*)
pnpm install

# 2. Skopiuj .env.example → .env (override hasła Postgres / Mercure / Meilisearch / MinIO)
cp .env.example .env

# 3. Wystartuj cały stack (build kontenerów przy pierwszym uruchomieniu trwa kilka minut)
pnpm dev          # foreground, Ctrl+C zatrzymuje
# albo:
pnpm stack:up     # detached, działa w tle
pnpm stack:logs   # tail logów

# 4. Sprawdź single-origin
curl -sk https://pim.localhost/api/docs.jsonld | head  # Hydra/JSON-LD API documentation
open https://pim.localhost/                            # Vite admin (HMR przez Caddy)
open https://pim.localhost/api/docs                    # Swagger UI (HTML)
open https://pim.localhost/api                         # API Platform entrypoint

# 5. Restart / reset
pnpm stack:down               # stop, zachowaj dane
pnpm stack:reset              # stop + wipe volumes
pnpm stack:rebuild            # rebuild obrazów (po zmianie Dockerfile / composer.json)
```

Caddy ma własny lokalny CA — przy pierwszym `pnpm dev` przeglądarka wymaga zaakceptowania certyfikatu. Można też zaufać CA na hoście: skopiuj `caddy_data` volume → `~/.local/share/caddy/pki/authorities/local/root.crt` i dodaj do System Keychain (macOS).

## Quality gates

```bash
pnpm typecheck    # tsc --noEmit dla apps/admin + packages/*
pnpm build        # build production wszystkich workspace'ów

# PHP gates (w kontenerze api)
docker compose exec api composer phpstan        # PHPStan max
docker compose exec api composer cs-check       # PHP-CS-Fixer (dry-run)
docker compose exec -e APP_ENV=test api php bin/phpunit
```

CI: GitHub Actions na PR + push do main — `quality-php.yml`, `quality-frontend.yml` (Biome / typecheck / Vite build / **Playwright E2E**), `audit.yml` (composer + pnpm audit).

## Running E2E tests (Playwright)

E2E używa Chromium przeciwko full stackowi (`https://pim.localhost`). Przed pierwszym uruchomieniem:

```bash
pnpm stack:up                                       # Caddy + Postgres + API + admin Vite
docker compose exec api php bin/console doctrine:fixtures:load --no-interaction --env=dev
pnpm --filter @pim/admin exec playwright install chromium  # raz, host-side (Alpine container nie wspiera Playwright deps)
```

Następnie:

```bash
pnpm --filter @pim/admin e2e          # headless, pełna suite
pnpm --filter @pim/admin e2e:ui       # tryb interaktywny (debug)
```

Artefakty failure (screenshot / video / trace) lądują w `apps/admin/test-results/`. Reportowy HTML w `apps/admin/playwright-report/`. CI uploads obie ścieżki przez `actions/upload-artifact` przy failure.

## Stack components

| Service | Image | Purpose | Healthcheck |
|---|---|---|---|
| `caddy` | `caddy:2-alpine` | Edge — terminuje TLS, single-origin proxy | `wget` na `https://localhost/api` |
| `api` | `pim-api` (FrankenPHP 1 + PHP 8.4) | Symfony 7.4 + API Platform 4 worker mode | `curl http://127.0.0.1/api` |
| `admin` | `node:22-alpine` | Vite dev server (HMR przez WebSocket upgrade) | brak (process-based) |
| `database` | `pim-database:local` (postgres:16-alpine + pgbackrest 2.57 + dcron) | PostgreSQL 16 + WAL archiving + hourly backup do MinIO | `pg_isready` |
| `redis` | `redis:7-alpine` | Symfony Messenger transport + cache (AOF persistence) | `redis-cli ping` |
| `meilisearch` | `getmeili/meilisearch:v1.13` | Full-text search | `curl /health` |
| `minio` | `minio/minio:latest` | S3-compatible object storage (DAM + backups) | `curl /minio/health/live` |
| `minio-tls` | `caddy:2-alpine` | TLS terminator dla pgBackRest → MinIO (pgBackRest hard-coduje HTTPS) | brak |
| `minio-init` | `minio/mc:latest` | Init buckets `pim-assets` / `pim-backups` (one-shot, `restart: no`) | — |
| `mercure` | `dunglas/mercure:latest` | SSE hub real-time (events do admin frontend) | brak |
| `mailpit` | `axllent/mailpit:latest` | Dev mail catcher (SMTP + UI) | brak |
| `k6` | `grafana/k6:latest` | Perf load tester (`profile: ["perf"]`, nie startuje z `pnpm stack:up`) | — |

## Backup & restore

```bash
pnpm backup:run       # one-off pgBackRest backup do MinIO bucket pim-backups
pnpm backup:info      # status repo, lista backup'ów
pnpm backup:restore   # PITR — patrz docs/runbook/restore.md
pnpm backup:test      # DoD acceptance test (insert markery → backup → drop → restore)
```

Pełen runbook: [`docs/runbook/restore.md`](docs/runbook/restore.md). Production-grade schedule (full + diff + 5-min WAL + automated weekly restore test) dochodzi w 0.11.11.

## Następny krok

MVP-Alpha — epik 0.1 zamknięty. Następny: epik 0.2 (Identity & Access, [#24-#30](https://github.com/malipie/PIM/issues?q=is%3Aopen+label%3A%22epik-0.2%22)). Pełny plan w [`Project Plan/02-plan-projektu-pim.md`](Project%20Plan/02-plan-projektu-pim.md), aktualny status w [`agent/current_status.md`](agent/current_status.md).

## Licencja

Prywatne — wszystkie prawa zastrzeżone.
