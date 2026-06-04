# Architecture Decision Records

Architectural decisions for the PIM project follow [MADR 4.0](https://adr.github.io/madr/).

Why per-file ADRs alongside the narrative architecture in `Project Plan/01-architektura-pim.md`:

- the Project Plan reads top-to-bottom for onboarding and tells a story;
- the ADR set is the authoritative source for "why is X this way" — each file pins a single decision with its context, alternatives, and consequences;
- post-RF refactor we cross-reference both: Project Plan section 13 lists the ADR numbers, the ADR files carry the actual reasoning.

## Index

- [adr-template.md](adr-template.md) — copy when starting a new ADR
- [0000-use-markdown-architectural-decision-records.md](0000-use-markdown-architectural-decision-records.md)
- [0010-src-top-level-policy.md](0010-src-top-level-policy.md)
- [0011-orm-xml-mapping-in-infrastructure.md](0011-orm-xml-mapping-in-infrastructure.md)
- [0012-cqrs-application-layer.md](0012-cqrs-application-layer.md)
- [0013-deptrac-rollout.md](0013-deptrac-rollout.md)
- [0014-tenant-as-shared-kernel.md](0014-tenant-as-shared-kernel.md)
- [0015-cross-bc-fk-policy.md](0015-cross-bc-fk-policy.md)

ADRs 0001-0009 codify the existing decisions narrated in `Project Plan/01-architektura-pim.md` section 13. Lift-and-shift to per-file MADR is a follow-up housekeeping ticket — the canonical record stays in the Project Plan until that lands.

- [0015-cross-bc-fk-policy.md](0015-cross-bc-fk-policy.md) — bare UUID references between BCs; no Doctrine cross-BC associations
- [0016-api-configurator-key-format.md](0016-api-configurator-key-format.md) — API key format + Argon2id hashing
- [0017-byok-encryption-strategy.md](0017-byok-encryption-strategy.md) — BYOK AES-256-GCM with versioned master key
- [0018-channel-publication-profile.md](0018-channel-publication-profile.md) — per-channel attribute/locale allow-list; `?publication=<channel>` distinct from `?channel=`
