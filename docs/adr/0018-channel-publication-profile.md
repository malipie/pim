# 0018. ChannelPublicationProfile — per-channel attribute/locale allow-list

- **Status:** accepted
- **Date:** 2026-06-04
- **Deciders:** Marcin Lipiec, Senior Staff Engineer

## Context and Problem Statement

The export engine (epik EXP) and the API read path both need to know *which
attributes* and *which locales* are relevant for a given distribution channel.
Today every channel sees every attribute and every locale — correct for an
unconfigured MVP but wrong for real integrations:

- **Shopify** typically wants only 15-20 marketing attributes in EN+DE, not
  all 200 internal fields in all 5 locales.
- **Allegro** needs its own set of required attributes in PL only.
- **Export CSV** without a channel filter produces files with hundreds of
  columns that the operator must manually prune each time.

Three concrete decisions were open before tickets #1232–#1235 could land:

1. Where does the profile entity live (which Bounded Context)?
2. How is publication-filtered reading exposed in the API — new param or
   overloaded `?channel=`?
3. How are cross-BC relationships expressed (Catalog + Export reading Channel
   data)?

## Decision Drivers

- **Deptrac** enforces that `Catalog` and `Export` cannot import Channel
  internals — only `Channel\Contracts` is allowed.
- `?channel=` already has a well-established meaning: overlay per-channel
  ObjectValue rows (value scoping). Adding a second semantic would break
  all existing clients silently.
- The default profile must be publish-all so that existing tenants with no
  configured profiles see zero regression — all attributes, all locales.
- Bare-UUID cross-BC references are the established pattern (ADR-015);
  Doctrine associations between BCs are forbidden (ADR-015).

## Considered Options

1. **Option A — Entity in Channel BC, `?publication=<channel>` param**
   — dedicated profile entity in `Channel/Domain`, resolver interface in
   `Channel/Contracts`, `?publication=<channel>` as a distinct query param.

2. **Option B — Entity in Catalog BC, reuse `?channel=` param**
   — profile lives in Catalog next to ObjectType; channel filter and
   publication filter share the same query parameter, disambiguated by
   whether a profile row exists.

3. **Option C — Entity in a new Publication BC, new API version**
   — separate bounded context, versioned `/v2` API surface.

## Decision Outcome

Chosen option: **Option A**, because:

- The profile *describes a channel's configuration* — it belongs in Channel BC
  semantically and keeps Catalog clean.
- A distinct `?publication=<channel>` param is unambiguous, preserves the
  existing semantics of `?channel=`, and can evolve independently (e.g.
  adding `?publication=<channel>@v2` in the future is safe).
- A `Channel\Contracts` interface (`ChannelPublicationResolverInterface`) gives
  Catalog and Export a Deptrac-clean call site with no direct dependency on
  Channel internals.
- Option B conflates two orthogonal concerns (value overlay vs. attribute
  allow-list) under one parameter name — a recipe for subtle bugs.
- Option C adds a fourth BC for ~4 domain classes; the overhead is not
  justified at this stage.

### Consequences

- **Positive:**
  - `?channel=` keeps its existing single meaning (value overlay).
  - `?publication=<channel>` is additive — consumers that don't send it get
    the same full response as before (publish-all default).
  - `Channel\Contracts\ChannelPublicationResolverInterface` is Deptrac-safe
    and reusable by both Catalog (API read) and Export (column planner).
  - Auto-created default profile on `ChannelCreated` event → zero manual
    setup cost for new tenants.

- **Negative:**
  - Two separate parameters (`?locale=`, `?channel=`, `?publication=`) increase
    API surface; documented clearly in OpenAPI (#1230).
  - A channel-keyed profile must be back-filled for existing channels on
    migration (handled in the migration SQL for ticket #1232).

- **Follow-ups:**
  - #1232: entity + migration + seed
  - #1233: `ChannelPublicationResolverInterface` + impl
  - #1234: overlay provider reads `?publication=`, filters `attributes_indexed`
  - #1235: `PublicationColumnPlanner` for export
  - Faza 3: `?publication=<channel>` added to OpenAPI QueryParameter (#1230
    already reserves the slot; actual param added when #1234 lands)

## Entity shape (Channel BC)

```
channel_publication_profiles
  id              UUID PK (v7)
  tenant_id       UUID NOT NULL FK → tenants
  channel_id      UUID NOT NULL  (bare ref, not FK — ADR-015)
  object_type_id  UUID NOT NULL  (bare ref, not FK — ADR-015)
  published_attribute_codes  text[] | NULL   (NULL = publish-all)
  published_locales          text[] NOT NULL  (short codes; e.g. ['pl','en'])
  column_aliases             jsonb NOT NULL DEFAULT '{}'
  is_default      bool NOT NULL DEFAULT false
  created_at      timestamptz NOT NULL
  UNIQUE (tenant_id, channel_id, object_type_id)
```

`published_attribute_codes = NULL` means **publish-all** (the default profile
created automatically on `ChannelCreated`). An empty array `[]` means
publish-nothing — a valid but unusual configuration.

## Links

- ADR-009 — generic ObjectType (context for `object_type_id` bare ref)
- ADR-015 — cross-BC FK policy (bare UUID refs, no Doctrine association)
- ADR-011 — ORM XML mapping in Infrastructure
- Related tickets: #1231 (this ADR), #1232, #1233, #1234, #1235
- `Project Plan/01-architektura-pim.md` §5 Channel Context
