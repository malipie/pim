# 0012. CQRS Application Layer — Pragmatic Per-Use-Case

- **Status:** accepted
- **Date:** 2026-04-29 (RF-14, RF-15 — partially WONTFIX)
- **Deciders:** Marcin Lipiec

## Context and Problem Statement

The audit (DDD-005, MEDIUM) expected vertical-slice `Command/<UseCase>/{Command,Handler}` for every application-level operation. The pre-RF reality was service-style classes (`DemoCatalogSeeder`, `BuiltInObjectTypeSeeder`, `RbacSeeder`, `RefreshTokenService`) that hold the orchestration directly.

## Decision Drivers

- domain seeders run once, are idempotent, and have no user-facing dispatcher path — wrapping them in Messenger Command/Handler envelopes adds overhead without value;
- API Platform processors (epic 0.4) will provide the first real user-facing write surface; CQRS pays for itself there;
- the audit's MEDIUM ranking on DDD-005 already acknowledges this is a judgement call;
- TenantContext-style providers cannot become commands without re-architecting the Symfony security cycle.

## Decision Outcome

Pragmatic CQRS rollout, **per use case**:

- **Real CQRS:** new ApiResource processors land as `Application/Command/<UseCase>/{Command,Handler}` slices (epic 0.4+).
- **Existing seeders / batch builders / providers:** keep the service layout. They are testable, idempotent, fixture-only.
- **Domain events:** dispatched through Messenger via `Shared\Infrastructure\Messenger\DomainEventDispatcher` (RF-20). Subscribers carry `#[AsMessageHandler]` already.

The audit reopens this ADR if epic 0.4 surfaces drift (e.g. service-style code starts hosting controller logic).

### Consequences

- **Positive:** No 14-hour rewrite for code that has no operational benefit. Onboarding stays simple — services do what services do.
- **Negative:** Pure-CQRS purists will disagree with the seeder exception. Documented here so the inconsistency is intentional.
- **Follow-ups:** RF-14 / RF-15 closed as WONTFIX with link back to this ADR. Re-open if epic 0.4 changes the balance.

## Alternatives Considered

- **Full CQRS sweep across every existing service:** rejected — see Decision Drivers.
- **Full service layer:** rejected — would force ApiResource processors to hand-roll plumbing the bus already provides.

## Links

- AUDIT-REPORT-2026-04-29 §2 DDD-005
- Closed-as-WONTFIX issues #164 (RF-14) / #165 (RF-15)
