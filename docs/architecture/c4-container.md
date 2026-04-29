# C4 Container — Inside the PIM platform

```mermaid
%%{init: {'theme':'neutral'}}%%
flowchart TB
    Caddy[Caddy edge - TLS + single-origin]:::infra

    subgraph Frontend[apps/admin]
        Vite[Vite 8 dev / dist]:::system
        Refine[Refine 5 + shadcn]:::system
    end

    subgraph Backend[apps/api]
        FP[FrankenPHP worker]:::system
        Sym[Symfony 7.4 + API Platform 4]:::system
        Worker[Messenger consumer]:::system
        Catalog[Catalog BC]:::bc
        Channel[Channel BC]:::bc
        Asset[Asset BC]:::bc
        Identity[Identity BC]:::bc
        Shared[Shared kernel - Tenant + AggregateRoot + middleware]:::bc
        Integration[Integration BC - Faza 1]:::bcEmpty
        Agent[Agent BC - Faza 2]:::bcEmpty
    end

    Postgres[(Postgres 16)]:::store
    Meili[(Meilisearch)]:::store
    Redis[(Redis)]:::store
    MinIO[(MinIO / S3)]:::store

    Caddy --> Vite
    Caddy --> FP
    Vite --> Refine
    FP --> Sym
    Sym --> Catalog
    Sym --> Channel
    Sym --> Asset
    Sym --> Identity
    Sym --> Shared
    Sym --> Integration
    Sym --> Agent
    Sym --> Worker

    Catalog --> Postgres
    Channel --> Postgres
    Asset --> Postgres
    Asset --> MinIO
    Identity --> Postgres
    Shared --> Postgres
    Worker --> Postgres
    Worker --> Meili
    Sym --> Meili
    Sym --> Redis

    classDef system fill:#fff3c4,stroke:#d6a20a,color:#8a6c0a;
    classDef store fill:#d6f5d6,stroke:#3aa83a,color:#225522;
    classDef infra fill:#eaeaff,stroke:#6c6cff,color:#33337a;
    classDef bc fill:#fff,stroke:#444,color:#222;
    classDef bcEmpty fill:#f5f5f5,stroke:#bbb,color:#888,stroke-dasharray:4 4;
```

**Caddy edge** terminates TLS, enforces the single-origin contract (`pim.localhost` for everything; no CORS), reverse-proxies `/api/*` to FrankenPHP and the rest to Vite (dev) or the SPA bundle (prod).

**FrankenPHP** runs the Symfony kernel in worker mode. `MAX_REQUESTS=1000` recycles the worker so PHP's known memory drift can't take the box down.

**Bounded contexts** live under `apps/api/src/`. Each carries its own `Domain / Application / Infrastructure / Contracts` ring — the cross-BC ringfence is enforced by Deptrac (ADR-0013).

**Messenger workers** consume the same Symfony Messenger bus the request thread uses. MVP routes everything synchronously; Faza 1 flips the Doctrine async DSN on without rewiring handlers.

**Storage**
- Postgres 16 — primary OLTP, JSONB + ltree, RLS pre-provisioned.
- Meilisearch — per-kind search indexes; reindexed by domain-event subscribers (epic 0.5).
- Redis — sessions, Messenger lock store, cache.app pool.
- MinIO / S3 — Asset binary storage via Flysystem; only Asset BC writes here.
