# EXP-04 — Export performance benchmark log

> **Ticket:** [#583](https://github.com/malipie/PIM/issues/583)
>
> Append-only run log produced by `bin/console pim:export:benchmark`.
> Each row is a single benchmark run captured from the developer or CI
> environment. PRD §11.2 target: <30s for 50k SKU × 30 columns sync
> write. The "extrapolated 50k" column projects the per-row latency to
> that scale so trends are visible even when the local dataset is
> smaller than production.
>
> **How to interpret:**
> - `rows`: actual objects walked (demo dataset is small; expect a few
>   hundred until the synthetic seeder lands).
> - `elapsed_ms`, `ms_per_row`: builder + repo cost only — XLSX writer
>   lands in EXP-05.
> - `peak_mb`: `memory_get_usage(true)` peak after `EntityManager::clear()`
>   at the chunk cadence. Should stay flat as rows grow — CLAUDE.md
>   §3.10 worker-mode guardrail.
> - `growth_mb`: peak minus baseline. Anything >10 MB on a small dataset
>   is a smell.
> - `extrap_50k_s`: pessimistic projection assuming linear scaling.

| timestamp | tenant | rows | chunk | cols | elapsed_ms | ms_per_row | peak_mb | growth_mb | extrap_50k_s |
|---|---|---|---|---|---|---|---|---|---|
| 2026-05-15T00:11:04+00:00 | demo | 100 | 50 | 10 | 97.54 | 0.975 | 34.00 | 6.00 | 48.77 |
