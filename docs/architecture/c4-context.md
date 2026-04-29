# C4 Context — PIM in its environment

```mermaid
%%{init: {'theme':'neutral'}}%%
flowchart LR
    User[Admin user]:::user
    Integrator[Integration partner]:::user

    subgraph PIM[PIM platform]
        direction TB
        Admin[Admin SPA - Refine + React]:::system
        API[API Platform - Symfony 7.4]:::system
        Worker[Messenger workers]:::system
    end

    Postgres[(Postgres 16 - JSONB + ltree + RLS)]:::store
    Meili[(Meilisearch)]:::store
    Redis[(Redis 7)]:::store
    MinIO[(MinIO / S3)]:::store
    Mercure[Mercure SSE]:::infra

    Shopify[Shopify API]:::external
    BaseLinker[BaseLinker API]:::external
    Anthropic[Anthropic Claude]:::external

    User --> Admin
    Admin --> API
    Integrator --> API

    API --> Postgres
    API --> Meili
    API --> Redis
    API --> MinIO
    API --> Mercure
    API --> Worker
    Worker --> Postgres
    Worker --> Meili

    API --> Shopify
    API --> BaseLinker
    API --> Anthropic

    classDef user fill:#cfe6ff,stroke:#3478c5,color:#1c4e8e;
    classDef system fill:#fff3c4,stroke:#d6a20a,color:#8a6c0a;
    classDef store fill:#d6f5d6,stroke:#3aa83a,color:#225522;
    classDef infra fill:#eaeaff,stroke:#6c6cff,color:#33337a;
    classDef external fill:#ffd6d6,stroke:#c54040,color:#8a2e2e;
```

**External actors**
- *Admin user* — runs the Refine SPA at `pim.localhost` / `pim.example.com`.
- *Integration partner* — calls `/api/*` directly with a per-tenant API key (epic 0.10 ApiConfigurator).

**Platform**
- *Admin SPA* — React 19 + Refine 5 + shadcn/ui served by Vite, single-origin behind Caddy.
- *API* — API Platform 4 on FrankenPHP worker mode. Hosts REST + GraphQL + JSON-LD, dispatches Messenger commands and domain events through the same bus.
- *Messenger workers* — sync transport in MVP, async (Doctrine queue) from Faza 1 — same bus, same handlers.

**Stores & infra**
- *Postgres 16* — every domain table carries `tenant_id`. RLS policies are pre-provisioned (epic 0.0.X), `ENABLE ROW LEVEL SECURITY` flips on at first multi-tenant deploy.
- *Meilisearch* — search index per kind (`products`, `categories`, `assets`).
- *Redis* — sessions + Messenger lock store + cache.
- *MinIO / S3* — Asset storage via Flysystem.
- *Mercure* — SSE channel for real-time admin updates (e.g. async indexing progress).

**External services**
- *Shopify API* — channel publisher (epic 0.9, deferred to Faza 1).
- *BaseLinker API* — channel publisher (epic 0.8, Faza 1).
- *Anthropic Claude API* — Agent layer (epic 0.7, Faza 2). Strict cost limits enforced by `Agent\Application\AgentRunBudgetEnforcer` (BC scaffolded, implementation in Faza 2).
