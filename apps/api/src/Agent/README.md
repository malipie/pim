# Agent — Bounded Context

> **Status:** scaffolded in epic 0.1 (#19). Implementation deferred to **Faza 2** — Beta-Min (epic 0.7, [#63-#71](https://github.com/malipie/PIM/issues?q=label%3Aepik-0.7)) + Beta-Full ([#108-#112](https://github.com/malipie/PIM/issues?q=label%3Aepik-0.7)). Reason: scope revision 2026-04-27 prioritised "working catalog" over "talk to the catalog" for the first pilot.

Houses the LLM-driven schema-modification surface: chat-as-first-class
interaction, tool calls, pending changes (human approval flow), audit log.
Anthropic SDK PHP — Claude Sonnet by default, Claude Opus for schema-ops.

## Layer responsibilities (DDD)

- `Domain/` — `AgentRun`, `ToolCall`, `PendingChange`, value objects (`TokenBudget`,
  `ToolCallLimit`).
- `Application/` — orchestration, budget enforcement, approval workflow.
- `Infrastructure/` — Anthropic SDK client, tool dispatcher, audit persistence.
- `Presentation/` — `/api/agent/run` endpoint, SSE streaming, approval UI hooks.

## Hard limits (non-negotiable, architecture §8.5)

- 50 tool calls / hour / user
- 10 tool calls / agent run
- 100k tokens / run
- 500k tokens / day / user
- $20 / day / tenant, $300 / month / tenant
- Org-level monthly cap $1000 in Anthropic Console (independent hardstop)

After 100% — agent disabled until midnight UTC. BYOK for enterprise (key
encrypted AES-256-GCM).

## MVP hooks reserved here

The Sprint-0 / MVP work intentionally leaves three hooks for Faza 2 (4–6h
scope, candidate for ticket 0.3.11 / 0.11):

- `pending_changes` table — empty migration, schema reserved.
- `provenance` enum already includes `agent` (UI variant deferred).
- Doctrine lifecycle subscriber emitting `EntityChanged` for the future audit.

Those land outside this directory but exist *for* this context.
