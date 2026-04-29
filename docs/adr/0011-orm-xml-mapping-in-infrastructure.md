# 0011. Doctrine ORM Mapping in XML Files Under Infrastructure

- **Status:** accepted
- **Date:** 2026-04-29 (RF-06..09)
- **Deciders:** Marcin Lipiec

## Context and Problem Statement

The pre-RF Domain entities were Doctrine-attribute-mapped: every aggregate declared `#[ORM\Entity]`, `#[ORM\Column]`, etc. inline. The audit (DDD-001 / DDD-006) called out the obvious tradeoff: a "Domain" class peppered with framework annotations is not actually framework-agnostic, and a port-and-adapter layout cannot be enforced when the port carries Doctrine semantics.

## Decision Drivers

- DDD invariant: Domain layer free of framework dependencies — testable without booting Symfony, deployable into a different persistence context if Faza 2/3 needs it.
- audit DDD-001 / DDD-006 (CRITICAL).
- Foundation for Deptrac's BC ringfence (ADR-0013): the ruleset in `Catalog_Internals` already permits Domain → Domain only, so external mapping locality matters.

## Decision Outcome

XML mappings live next to each BC's persistence adapter — `apps/api/src/<BC>/Infrastructure/Doctrine/Orm/Mapping/<Entity>.orm.xml`. `doctrine.yaml` switches every BC mapping from `type: attribute` to `type: xml` with the new `dir` and `prefix`. Domain entity classes contain no Doctrine imports.

### Consequences

- **Positive:** Domain stays a plain PHP graph. Listeners and validators that don't need Doctrine state are unit-testable without `KernelTestCase`. Mapping changes ride along in their own XML files (smaller blast radius than touching the entity class).
- **Negative:** Two-file editing for entity changes (PHP class + XML mapping). Doctrine ORM 3 with `report_fields_where_declared: true` requires the `<mapped-superclass>` declaration for `Shared\Domain\AggregateRoot` to keep the events buffer transient (RF-16).
- **Follow-ups:** Migration to attribute-mapping if Symfony / Doctrine ORM 4 changes the ergonomic balance — re-evaluate at the bump.

## Alternatives Considered

- **Stay on attribute mapping with `Project Plan` ADR-only documentation of the tradeoff:** rejected. The DDD invariant is load-bearing for the rest of the refactor (ADR-0014 Tenant move, ADR-0015 cross-BC FK policy).
- **Split into PHP-attribute base class + XML overlay:** Doctrine doesn't merge; you pick one. Rejected.

## Links

- AUDIT-REPORT-2026-04-29 §2 DDD-001 / DDD-006
- RF-06, RF-07, RF-08, RF-09 PRs
- Project Plan section 13 ADR-001 / ADR-002
