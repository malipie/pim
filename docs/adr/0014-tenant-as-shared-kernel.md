# 0014. Tenant as Shared Kernel

- **Status:** accepted
- **Date:** 2026-04-29 (RF-02..04)
- **Deciders:** Marcin Lipiec

## Context and Problem Statement

The pre-RF Tenant aggregate lived in `App\Identity\Domain\Entity\Tenant`. Every BC (Catalog, Channel, Asset, plus tooling) imported it: 30+ cross-BC `use` statements, plus the Doctrine `targetEntity:` references in 11 mapping files. Identity owned a class that was structurally shared.

## Decision Drivers

- multi-tenancy is a cross-cutting invariant — every aggregate carries `tenant_id`;
- DDD-010 CRITICAL would never go to zero with Tenant in Identity;
- Identity should own User / Role / Permission / RefreshToken — runtime auth — not the platform-wide tenant concept.

## Decision Outcome

Tenant is the single Shared Kernel of the modular monolith. It moves to `App\Shared\Domain\Tenant` with XML mapping in `Shared/Infrastructure/Doctrine/Orm/Mapping/Tenant.orm.xml`, repository port + Doctrine adapter in `Shared/Domain/Repository` + `Shared/Infrastructure/Doctrine/Repository`. The whole tenant-isolation infrastructure (TenantContext, TenantScoped, TenantAware, TenantFilter, TenantFilterConfigurator, TenantAssignmentListener, RequestTenantSubscriber) follows it into Shared.

User / Role / Permission / RefreshToken stay in Identity.

### Consequences

- **Positive:** Cross-BC import count for Tenant drops to zero. Every BC depends on Shared, which is allowed by Deptrac. Identity becomes the auth context, not "the place where Tenant happens to live".
- **Negative:** Cross-BC sweep touched 47 files in one commit — large blast radius for the migration PR. Mitigated by atomic `git mv` and PHPStan/PHPUnit/Deptrac coverage.
- **Follow-ups:** Migration to `class_alias` bridge was infeasible (Symfony FileLoader + PHP 8.4 lazy type resolution rejected it). The big-sweep approach was the only path forward — recorded here so the next aggregate-extraction ticket starts there.

## Alternatives Considered

- **Keep Tenant in Identity, declare cross-BC import an exception:** rejected, the whole point of modular monolith breaks down.
- **`class_alias` bridge during migration:** attempted in RF-02 first commits, rolled back when CI surfaced two failure modes.

## Links

- AUDIT-REPORT-2026-04-29 §2 DDD-010
- RF-02 / RF-03 / RF-04 PR #187 (combined sweep) and #188
- ADR-0013 (Deptrac baseline references this move)
