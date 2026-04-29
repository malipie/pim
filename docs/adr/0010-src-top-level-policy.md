# 0010. `apps/api/src/` Top-Level Layout

- **Status:** accepted
- **Date:** 2026-04-29 (RF-01)
- **Deciders:** Marcin Lipiec

## Context and Problem Statement

The audit (AUDIT-REPORT-2026-04-29) flagged 7 top-level directories under `apps/api/src/` that are not Bounded Contexts: `Benchmark`, `DataFixtures`, `Story`, `Maintenance`, `Messaging`, `Observability`, `ApiConfigurator`. Mixing tooling, BCs, and infra under one root makes the boundary fuzzy.

## Decision Drivers

- DDD invariant: top-level should be Bounded Contexts + Shared kernel.
- Symfony convention: standard locations for fixtures (`src/DataFixtures`) and Foundry stories (`src/Story`) ship with the framework recipes — moving them breaks the autoload + autoconfigure path without good reason.
- Tooling-only commands (Benchmark, Maintenance) are dev-time artefacts; pretending they are BCs is dishonest.

## Decision Outcome

Three groups under `apps/api/src/`:

1. **Bounded Contexts:** `Catalog`, `Channel`, `Asset`, `Identity`, `Integration`, `Agent`, `ApiConfigurator`.
2. **Shared kernel:** `Shared/{Domain,Application,Infrastructure,Contracts}/` — Tenant aggregate, AggregateRoot base, multi-tenancy plumbing, cross-cutting infra (Metrics, Maintenance commands).
3. **Tooling slot (Symfony conventions, no BC ringfence):** `Benchmark/`, `DataFixtures/`, `Story/`. Deptrac scopes them to a `Tooling` layer that may pull from any BC for fixture/benchmark purposes; nothing in the BCs may depend on this slot.

### Consequences

- **Positive:** Onboarding signal — new BCs go top-level, fixtures stay in DataFixtures, infra cross-cuts live under Shared. No surprise locations for `bin/console make:fixture` etc.
- **Negative:** Audit checklist v1.1 expects `Tooling/` to live under `tools/` outside of `src/`. We documented the deviation here rather than fight Symfony recipes.
- **Follow-ups:** Maintenance and Observability already migrated to Shared in RF-01.

## Alternatives Considered

- **Move fixtures to `apps/api/fixtures/`:** breaks the DoctrineFixturesBundle recipe and the Symfony Maker scaffolding. Rejected.
- **Move Benchmark to `apps/api/tools/benchmark/`:** would require a separate `composer.json` autoload entry. Cost outweighs the cosmetic win.

## Links

- AUDIT-REPORT-2026-04-29 §2 STR-003
- ADR-0013 (Deptrac ruleset references this layout)
