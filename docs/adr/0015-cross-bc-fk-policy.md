# 0015. Cross-BC FK Policy: Uuid Columns + Contracts/Query Lookup

- **Status:** accepted
- **Date:** 2026-04-29 (RF-19)
- **Deciders:** Marcin Lipiec

## Context and Problem Statement

The pre-RF schema uses two cross-BC `targetEntity:` references:

- `Channel.categoryTreeRoot` → `Catalog\Domain\Entity\CatalogObject` (one-to-one, nullable);
- `Asset.object` → `Catalog\Domain\Entity\CatalogObject` (one-to-one, nullable).

Both let one BC reach into another's Domain through Doctrine's lazy-loaded entity graph. Acceptable when the project was a single bag, untenable for the modular monolith.

## Decision Drivers

- DDD-010 CRITICAL: every cross-BC dependency goes through Contracts.
- Domain entities must not import other BCs' Domain entities (Deptrac ringfence — ADR-0013).
- DB-level cascade behaviour (`ON DELETE SET NULL`) is what we actually want; Doctrine ORM-level cascade is incidental.

## Decision Outcome

Cross-BC links carry a bare `Uuid` column on the source entity, plus a `Catalog\Contracts\Query\ObjectSummary` projection that other BCs pull via `GetObjectSummaryHandler` for validation / labelling. Pattern:

- `Channel.categoryTreeRootId: ?Uuid` (XML `<field type="uuid" column="category_tree_root_object_id" nullable="true"/>`);
- `Asset.objectId: ?Uuid` (analogous);
- DB-level FK with `ON DELETE SET NULL` keeps orphans out — Postgres does it, Doctrine no longer needs to know.

`ChannelCategoryRootValidator` resolves the kind=category invariant by calling `GetObjectSummaryHandler($rootId)` and checking the returned DTO. A `null` summary now legitimately means "you pointed at an id that does not exist", which Doctrine's targetEntity flow could not surface.

`Channel.categoryTreeRootId` schema validate complains because Doctrine cannot see the FK constraint anymore. **Intentional.** `--skip-sync` for `doctrine:schema:validate` documents the drift.

### Consequences

- **Positive:** Channel and Asset Domain folders no longer depend on `Catalog\Domain`. Deptrac proves it.
- **Negative:** Schema validate drift is permanent until Doctrine learns to declare DB-level FKs without owning a relation.
- **Follow-ups:** `ChannelObjectTypeMapping` carries three cross-BC FKs (Channel + ObjectType + Attribute). RF-19 deferred its sweep — separate ticket. Three Deptrac baseline entries ride along.

## Alternatives Considered

- **Drop the DB-level FK entirely:** would lose orphan protection. Rejected.
- **Move the validator into Catalog:** the ownership belongs to Channel ("a channel must have a category root"). Rejected.
- **GraphQL-style data loader / Read Model service per BC:** over-engineering for two FKs in MVP. Pattern lands in epic 0.5 search projection if needed.

## Links

- AUDIT-REPORT-2026-04-29 §2 DDD-010
- RF-18 (Contracts/Query DTOs) PR #200
- RF-19 (the sweep itself) PR #201
- ADR-0013 (Deptrac baseline)
