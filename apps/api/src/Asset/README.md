# Asset — Bounded Context

> **Status:** scaffolded in epic 0.1 (#19). Implementation lands in **epic 0.3** ([#37](https://github.com/malipie/PIM/issues/37) — `Asset + AssetVariant`) and **epic 0.6** ([#60](https://github.com/malipie/PIM/issues/60) — admin Assets resource).

DAM (Digital Asset Management). After ADR-009 an asset is a predefined
`ObjectType kind='asset'` instance — user-defined metadata lives in
`object_values`, while binary storage details (path, MIME, size, checksum,
variants) sit in a dedicated `assets` table linked back to the `Object` row.

## Layer responsibilities (DDD)

- `Domain/` — `Asset`, `AssetVariant`, value objects (`StoragePath`, `Checksum`).
- `Application/` — upload pipeline, variant generation, MIME validation.
- `Infrastructure/` — Flysystem adapter (MinIO / S3), Doctrine repositories.
- `Presentation/` — upload endpoints, signed URL controllers.

Namespace: `App\Asset\{Domain|Application|Infrastructure|Presentation}\…`.
