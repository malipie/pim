# 0000. Use Markdown Architectural Decision Records

- **Status:** accepted
- **Date:** 2026-04-29
- **Deciders:** Marcin Lipiec

## Context and Problem Statement

The PIM project's architectural rationale lived as a narrative in `Project Plan/01-architektura-pim.md`. As the codebase grows and decisions get re-litigated (e.g. RF refactor adding 6 new policies on top of the original 9), a single 80-page document becomes hard to cite and harder to keep correct.

## Decision Drivers

- need to cite individual decisions from PRs and code comments without linking to a section number that drifts;
- Sprint 0 finding (`Project Plan/06-sprint-0-findings.md`): "we should be able to point at one decision at a time";
- audit checklist DOC-001 calls for `docs/adr/` per-file MADR.

## Decision Outcome

Adopt MADR 4.0 (one decision per file, `NNNN-imperative-title.md`). The Project Plan keeps its narrative role; ADRs are the source of truth for individual decisions.

### Consequences

- **Positive:** PRs cite `ADR-0014`, not `Project Plan section 13.4`. Each ADR carries explicit Context / Decision / Consequences / Alternatives so a future reader does not need to read prior chapters first.
- **Negative:** dual write between Project Plan and ADRs until the back-fill (0001-0009) is done.
- **Follow-ups:** lift the existing nine decisions out of Project Plan section 13 into their own files in a separate ticket.

## Links

- [MADR 4.0 specification](https://adr.github.io/madr/)
- Audit checklist: DOC-001
