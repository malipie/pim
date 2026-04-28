# ApiConfigurator — Bounded Context

> **Status:** scaffolded in epic 0.1 (#19). Implementation lands in **epic 0.10** ([#90-#95](https://github.com/malipie/PIM/issues?q=label%3Aepik-0.10)).

The "API as a product" surface. Lets each integrator (or each pilot tenant)
build their own scoped API profile: pick which `ObjectType`s to expose, which
attributes to project, which channels to filter through, then mint a scoped
API key. Outputs an OpenAPI spec restricted to that profile.

## Layer responsibilities (DDD)

- `Domain/` — `ApiProfile`, `ApiKey`, `ProfileScope` (object types, attributes,
  channels), value objects.
- `Application/` — profile builder, OpenAPI projector, key issuance / rotation.
- `Infrastructure/` — Doctrine repositories, key hasher, OpenAPI emitter.
- `Presentation/` — admin UI binding (multiselect ObjectTypes), filtered
  response negotiator, `/api/docs/profile/{id}.jsonopenapi`.

## Why a bundle and not a config file

The integrator needs *runtime* control without touching code. ApiConfigurator
treats profiles as first-class entities — versionable, auditable, scoped per
tenant — rather than YAML the operator has to edit.
