# Integration — Bounded Context

> **Status:** scaffolded in epic 0.1 (#19). Implementation deferred to **Faza 1** — BaseLinker (epic 0.8, [#72-#78](https://github.com/malipie/PIM/issues?q=label%3Aepik-0.8)) and Shopify (epic 0.9, [#79-#89](https://github.com/malipie/PIM/issues?q=label%3Aepik-0.9)). Magento + IdoSell + Allegro + WooCommerce in Faza 2.

Each external system gets its own sub-bundle under `Integration/{Name}/` with a
fixed contract:

```
Integration/{Name}/
├── Adapter/         implements IntegrationAdapter
├── Client/          implements IntegrationClient (HTTP, GraphQL, SOAP, …)
├── MessageHandler/  Symfony Messenger handlers (sync jobs, webhooks)
├── Webhook/         signature verification, event routing
└── ConfigForm/      tenant-facing configuration UI binding
```

## Why a bundle per integration

- **Isolation.** A bug in the Shopify throttle logic must not block BaseLinker.
- **Pluggability.** Adding Magento later means dropping in a new sub-bundle
  that satisfies the same interfaces — no edits to the rest of the system.
- **Per-integration ownership.** Each adapter team can iterate independently.

## Required interfaces (defined in `Domain/`)

- `IntegrationAdapter` — high-level "sync this product" / "import these orders".
- `IntegrationClient` — low-level HTTP/GraphQL transport with retry + telemetry.
- `AttributeMapper` — maps PIM `ObjectValue`s onto the external system's shape.

## Throttling

Shopify uses **exponential backoff only** in MVP — see
[`agent/lessons.md`](../../../../agent/lessons.md) "Throttling integracji" and
architecture §7.3. Do not introduce shared-state Leaky Bucket logic without
revisiting that decision.
