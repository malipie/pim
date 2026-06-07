# Channel — Bounded Context

> **Status:** scaffolded in epic 0.1 (#19). Implementation lands in **epic 0.3** ([#36](https://github.com/malipie/PIM/issues/36) — `Channel + Locale + Currency + ChannelObjectTypeMapping`) and **epic 0.6** ([#59](https://github.com/malipie/PIM/issues/59) — admin Channels resource).

Owns the publication side of the catalog: which products go where, in which
locales, with which channel-specific overrides. Publication scope per
ObjectType lives in `ChannelPublicationProfile`; the per-channel navigation
tree + master→node category mapping live in `ChannelCategoryNode` /
`ChannelCategoryNodeMapping` (CHC-01/06).

> Per-channel attribute→target-field mapping (`ChannelObjectTypeMapping`) was
> scaffolded in epic 0.3 but removed in #1312 — it was never wired and belongs
> to the API integration configuration (Faza 1). It will be re-added there.

## Layer responsibilities (DDD)

- `Domain/` — `Channel`, `Locale`, `Currency` entities, value objects, domain events.
- `Application/` — `ChannelMappingService`, `ChannelExportPlanner` etc.
- `Infrastructure/` — Doctrine repositories, channel-specific adapters.
- `Presentation/` — API Platform resources, custom controllers, denormalizers.

Namespace: `App\Channel\{Domain|Application|Infrastructure|Presentation}\…`.
