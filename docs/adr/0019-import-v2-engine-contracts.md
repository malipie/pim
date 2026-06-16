# 0019. Import v2 — engine contracts (modes, match key, cell semantics, JSONB canon)

- **Status:** accepted
- **Date:** 2026-06-12
- **Deciders:** Marcin Lipiec, Senior Staff Engineer

## Context and Problem Statement

The import module is being rebuilt across ~39 tickets (epic IMP2, issues
#1460–#1498; plan: `Project Plan/UI/feature-imports-v2.md`). Multiple agent
sessions will implement it. Today the repo holds three contradictory versions
of the truth about import modes (docblock says "always upserts", spec v1 says
"ADD-only", the code does create-only with a blocking duplicate check), and
`docs/api/jsonb-schemas.md` contradicts the value shapes the export serializer
and the search flattener actually read. Every contract an implementer could
guess differently must be pinned down here, once.

Operator decisions recorded 2026-06-12 (plan §9) are binding inputs.

## Decisions

### D1 — Match key

Row→object matching uses `objects.code` (SKU) by default; an import profile
may select one attribute of type `identifier` (e.g. `ean`) instead. Matching
is **case-sensitive** after `trim()` on the cell value. Duplicate match keys
inside one file: the **first occurrence wins**, subsequent rows are skipped
with a warning during pre-flight — never left for a DB unique constraint to
explode mid-batch (Akeneo's historical failure mode).

### D2 — Cell semantics (three states)

| State | Behaviour |
|---|---|
| column absent from file/mapping | attribute untouched |
| cell empty | attribute untouched (**default**) |
| cell empty + `clear_if_empty` opt-in per column | value cleared (delete ObjectValue + rebuild caches) |

Collections (multiselect, categories, galleries): `replace` is the default
when the column is present; `append` is per-column opt-in. `clear_if_empty`
does not reach the UI until the D7 migration is complete (legacy select rows
export as empty cells — clearing on that signal would mass-delete data).
Dry-run must show a "will clear N values" bucket; >20% of existing non-empty
values cleared in one column requires an explicit confirm.

### D3 — Modes

`ImportMode` = `CREATE` | `UPDATE` | `UPSERT`, default **UPSERT**.
MERGE/INCREMENT/DELETE are removed from the enum (they never had an
implementation); existing `import_profiles.mode` rows are migrated
(`ADD→CREATE`, `MERGE|INCREMENT|DELETE→UPSERT`) in the same PR that shrinks
the enum (#1465). The session stores the mode chosen at start; profiles only
pre-fill it.

### D4/D5 — Round-trip scope and identifier resolution

Baseline (etap 1) round-trips: all 17 attribute-type values, multi-categories,
status/enabled, variants (`parent_sku`, two-pass), relations (as
`ObjectRelation`, resolved by code), asset references (same-tenant
`asset_id`). Structural exports (module_schema / attributes_groups /
categories) stay one-way. Cross-environment relation/select resolution is by
`code`; assets resolve by id (URL/path resolution arrives with media tickets).

### D6 — Unknown select / multiselect option codes

An import row whose select/multiselect cell references an option code that does
not exist on the attribute is **rejected with a row error** by default. A
per-profile opt-in (`auto_create_options`) instead **auto-creates** the missing
option and records it in the run report ("created N options"). Auto-created
options are **not removed by rollback** — other rows may already reference them;
this is surfaced in both the dry-run preview and the rollback preview. Realised
across the modes engine (#1465), the rollback preview's non-removable-options
bucket (#1480), and dry-run v2 (#1492).

### D7 — JSONB value canon (object_values.value per AttributeType)

| Types | Canonical shape |
|---|---|
| text, textarea, wysiwyg, number, date, datetime, boolean, color, email, identifier | `{"value": <scalar>}` |
| select | `{"option_code": "<code>"}` |
| multiselect | `{"option_codes": ["<code>", …]}` |
| price | `{"amount": <number>, "currency": "<ISO>"}` |
| metric | `{"value": <number>, "unit": "<code>"}` |
| asset | `{"asset_id": "<uuid>"}` |
| relation, reference | `{"object_id": "<uuid>"}` |

Known legacy deviations to be migrated by #1464: admin-written selects as
`{"value": code}` (blind `wrapValue`), variant axes stamped as `{"value": x}`
by GenerateVariants, legacy price `{"value": n}` without currency. Writers
normalise to canon; readers may keep a tolerant fallback only until the
migration lands, then fallbacks are removed.

### D8/D10 — Execution and limits

Messenger transport `import` (doctrine, own queue) with a worker service in
dev **and** prod compose; `redeliver_timeout` greater than the worst-case
import duration. Inline sync path stays for files **≤ 50 rows** (counted in
rows, not bytes). Dry-run is two-level: synchronous sample (~1000 rows) in
the wizard + full async run with a `dryRun` flag. File caps: 100 MB / 200k
rows per file (tenant-configurable). `.xls` (BIFF) is accepted read-only via
PhpSpreadsheet up to 20 MB (D13); one sheet per session (D14).

### D9/D12 — Profiles and mapping identity

Profiles stay **per-user** (operator decision; tenant-sharing rejected for
now). `columnMapping` v2 is a versioned list keyed by **column index** —
`[{column_index, header_display, target, locale?, channel?, policy?,
transforms?}]` — because real supplier files contain duplicate and empty
headers (Bosch/IdoSell, Avapax `foto`×8). A v1 (header→code) compatibility
reader must keep existing sessions/profiles loadable.

### D11 — Session marker and concurrency

`objects.import_session_id` means **created-by-this-session only**; it is
never stamped on updated objects (a delete-rollback of an upsert session must
not touch pre-existing catalog). Updates are tracked by the undo-log (#1480)
+ `provenance_meta.session_id` on values. `object_values` rows carry no
optimistic lock: last-writer-wins is accepted and documented; the undo-log
replay guards against clobbering manual edits via provenance/updated_at.

### Column grammar

`code` | `code.locale` | `code.channel` | `code.locale.channel`. A one-segment
suffix is resolved against the tenant registries (active locales, channel
codes). On a code collision (channel named like a locale) the **locale wins**
and the import surfaces a warning; creating a channel whose code collides
with an active locale (or activating such a locale) is rejected with 422.
Attribute codes must match `[a-z0-9_]+` — the dot is reserved as the grammar
separator. Unknown suffix = column-level validation error, never a silent
`locale='<garbage>'` row.

### Golden-test normalisation rules (v1)

The golden round-trip test (#1467, #1473) compares envelopes after a minimal,
versioned normalisation. **v1 allows only:**

1. numeric string ↔ number for `number`/`price.amount`/`metric.value`
   (`"1.50"` ≡ `1.5`),
2. date/datetime rendered as ISO 8601 (timezone offset preserved),
3. surrounding whitespace trimmed by the default `trim` transform.

Anything else (key order aside — comparison is structural) is a failure.
Extending this list requires bumping the rule version here and in the test.

## Consequences

- One authoritative document for every implementer; the conflicting docblock
  (`ImportMode`), spec v1 statements and `jsonb-schemas.md` examples are
  corrected to point here (#1474 sweeps the leftovers).
- Deptrac gains `Import` and `Export` layers allowed to depend only on
  `*_Contracts` + `Shared`; today's direct uses of `Catalog\Domain\Entity\*`
  enter the baseline (`skip_violations`) and are burned down by #1466.
- The wizard "karta prawdy" (`Project Plan/UI/imports-v2-karta-prawdy.md`)
  tracks which UI affordances are real at any point of the rebuild.
- The IMP2-1.4 (#1466) shared writer core ships as the **concrete**
  `App\Catalog\Application\ValueWriteCore`, not the `ValueWriteCoreInterface` +
  `ValueWriteResult` / `ValueWriteIssue` DTOs sketched in that ticket's
  acceptance criteria. Deliberate (YAGNI, #1558): one implementation is shared
  by all three write paths (`ObjectAttributesUpserter`, `BatchValueWriter`,
  `ImportUndoLogger`) and per-rule violations travel as plain message
  strings / arrays that each consumer maps to its own context (HTTP exceptions
  vs import result issues). An interface + result DTO would add indirection
  with no second implementation to justify it. Revisit if a second validator
  core (e.g. a remote/agent one) appears.

## Links

- Plan + decisions: `Project Plan/UI/feature-imports-v2.md` (§4.4 D1–D14, §9)
- Tickets: #1463 (this ADR), #1464 (D7 migration), #1465 (modes), #1466
  (shared writer core), #1467/#1473 (golden tests), #1480 (undo-log),
  #1485 (D11 concurrency)
- D11 concurrency matrix (per-tenant bulk lock + collision surfaces):
  `docs/architecture/concurrency-matrix.md`
- Related ADRs: 0014 (ObjectRelation), 0015 (category trees), 0018
  (publication profiles)
