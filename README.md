# PIM — Product Information Management

Agentic-first PIM platform. Konkurent PIMcore/Akeneo.

**Status:** faza koncepcyjna zakończona, oczekiwanie na Sprint 0 (vertical slice walidujący architekturę).

## Stack (MVP)

PHP 8.4 + Symfony 7.4 LTS + API Platform 4 + FrankenPHP 2.x worker mode · PostgreSQL 16 (JSONB+ltree+RLS) · Meilisearch · Redis 7 · React 19 + Vite 6 + Refine.dev + shadcn/ui · Mercure · Anthropic SDK PHP · monorepo Turborepo.

## Dokumentacja

- **`CLAUDE.md`** — konstytucja projektu (system prompt dla Claude Code)
- **`Project Plan/01-architektura-pim.md`** — pełna architektura, ADR, model danych
- **`Project Plan/02-plan-projektu-pim.md`** — fazy, milestones, backlog, ryzyka
- **`agent/current_status.md`** — aktualna sub-faza i postęp
- **`agent/lessons.md`** — patterns to follow / avoid, package quirks

## Następny krok

Sprint 0, ticket 0.0.1: setup monorepo + docker-compose + Caddyfile single-origin (sekcja 3.0 planu).

## Licencja

Prywatne — wszystkie prawa zastrzeżone.