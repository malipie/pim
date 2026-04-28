# Channel — Bounded Context

> **Status:** scaffolded in epic 0.1 (#19). Implementation lands in **epic 0.3** ([#36](https://github.com/malipie/PIM/issues/36) — `Channel + Locale + Currency + ChannelObjectTypeMapping`) and **epic 0.6** ([#59](https://github.com/malipie/PIM/issues/59) — admin Channels resource).

Owns the publication side of the catalog: which products go where, in which
locales, with which channel-specific overrides. After ADR-009 it carries
`ChannelObjectTypeMapping` (poly per `kind`) — the same channel can publish
products, categories and assets with different attribute subsets.

## Layer responsibilities (DDD)

- `Domain/` — `Channel`, `Locale`, `Currency` entities, value objects, domain events.
- `Application/` — `ChannelMappingService`, `ChannelExportPlanner` etc.
- `Infrastructure/` — Doctrine repositories, channel-specific adapters.
- `Presentation/` — API Platform resources, custom controllers, denormalizers.

Namespace: `App\Channel\{Domain|Application|Infrastructure|Presentation}\…`.
