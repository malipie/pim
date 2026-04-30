# 0016. API Configurator — key format and Argon2id hashing

- **Status:** accepted
- **Date:** 2026-04-30
- **Deciders:** Marcin Lipiec, Senior Staff Engineer

## Context and Problem Statement

Epic 0.10 (API Configurator) introduces a second authentication path next to JWT: long-lived API keys issued from the admin to external integrators (storefronts, partners, ETL jobs). The keys gate `/api/*` access scoped to a curated `ApiProfile` (a preset of visible ObjectTypes + attributes + filters). Two open decisions had to be settled before #90 could land schema and migration:

1. **Key format** — what does the raw secret look like, and what does the admin display when the secret is no longer recoverable?
2. **At-rest representation** — how is the secret stored, and what algorithm proves a presented key without being a side channel for offline cracking?

Both choices ripple into #94 (Authenticator + Voter) and the rate-limit policy from #48 (per-key budget keyed on `keyHash` lookup).

## Decision Drivers

- Secret must be unforgeable at rest. A DB dump must not be a usable list of API keys, even with attacker-controlled compute budget.
- Verification cost has to fit a hot path — every authenticated request decodes the key.
- The admin must recognize a key in the UI long after it was generated. A secret echoed only at creation time still needs a stable, non-sensitive identifier for the integrator-side and the platform-side display.
- Format has to encode environment for forensics (`pim_live_…` vs `pim_dev_…`) without bleeding tenant identity.
- BYOK (per-tenant Anthropic key) is a separate concern (#102 / 0.11.12) and must not contaminate this format — these are platform-issued keys for the public API, not tenant-supplied.

## Considered Options

1. **HMAC-SHA256 of a random token + raw stored** — fast verify, but a leaked `secrets.api_key_signing_key` linearly compromises every key. Rejected: single-secret blast radius.
2. **bcrypt(raw key)** — battle-tested but capped at 72 bytes, lower memory hardness than Argon2 family, and no native Symfony tooling for non-user hashing.
3. **Argon2id via PHP `password_hash(…, PASSWORD_ARGON2ID)`** — memory-hard, native to PHP 8.4, identical algorithm Symfony uses for user passwords, bundled tuning knobs (`memory_cost`, `time_cost`, `threads`).
4. **SHA-256 with per-key salt** — fast, but offline GPU-crackable for any leaked DB. Rejected: not memory-hard.
5. **Format `<env>_<base64url(32 bytes)>`** vs **`pim_<env>_<base62(24 bytes)>`** — the latter is URL-safe without special chars, copy-pastes cleanly into Bash and Postman, and the `pim_` prefix makes accidental commits searchable.

## Decision Outcome

Chosen options:

- **Hashing: Argon2id via `password_hash(PASSWORD_ARGON2ID)`** with PHP defaults at issue time. Verify with `password_verify`. No custom tuning — defaults track the language-level recommendation as PHP releases and avoid a per-tenant cost table that would diverge between hosts.
- **Format: `pim_<env>_<32 chars base62>`** where `<env>` ∈ {`live`, `dev`, `test`} matched to `APP_ENV`. The base62 body is `random_bytes(24)` re-encoded — 192 bits of entropy, comfortably above the 128-bit secret floor, fits one screen line.
- **Display prefix: first 12 characters of the raw key** (e.g. `pim_live_a4f2`). This is what the admin sees in `GET /api/api-keys`. Twelve chars is enough to disambiguate two keys at a glance, while the remaining 28 base62 chars (~166 bits) keep the unrevealed remainder unforgeable even if the prefix leaks.
- **Raw key echoed once, never stored.** `pim:apikey:generate` prints the raw key on stdout exactly once and the CLI exits non-zero if stdout is not a TTY (forces deliberate capture into a credential store rather than a captured shell log).

### Consequences

- **Positive:**
  - DB dump is useless without spending Argon2id memory budget per guess. Memory hardness scales with hardware roadmap.
  - One algorithm for both user passwords and API keys — `password_needs_rehash` works the same way for both, so the rotation playbook in #105 (epic 0.11) is uniform.
  - Prefix is safe to log (rate limiter buckets, audit trails, error messages can mention `pim_live_a4f2` without attacker uplift).
  - Format is URL-safe, header-safe (`Authorization: Bearer pim_live_…`), and copy-paste-safe. No quoting in shell.

- **Negative:**
  - Per-request Argon2id verify costs ~5-50ms depending on PHP defaults. Mitigation: rate limiter + Symfony cache on `keyPrefix → ApiKey` lookup so the verify only fires after prefix match. Production benchmark in #94.
  - `password_hash` defaults change between PHP versions. Acceptable — `password_needs_rehash` rotates on next presentation, the cron in #105 sweeps stale ones.
  - 32-char body is longer than some integrators expect from "API keys". Documented in API Configurator README + the test endpoint in #95.

- **Follow-ups:**
  - #94 (0.10.5) wires `ApiKeyAuthenticator` with prefix-indexed lookup + `password_verify` slow path + Symfony rate limiter keyed on `apikey:<prefix>`.
  - #105 (epic 0.11.x) adds rotation policy: `password_needs_rehash` triggered re-hash on use; revoke-and-reissue UI for compromised keys.
  - BYOK (Anthropic per-tenant) keeps its own AES-256-GCM at-rest path under #102 — no overlap with this format.

## Alternatives Considered

- **HMAC-SHA256** — rejected: single-secret blast radius (one leaked signing key compromises every issued API key).
- **bcrypt** — rejected: 72-byte input cap forces truncation tricks, lower memory hardness, no advantage over Argon2id on PHP 8.4.
- **SHA-256 + salt** — rejected: not memory-hard, GPU-crackable in finite time on any leaked DB.
- **JWT-shaped API keys (HS256 signed claims)** — rejected: stateful revocation requires a denylist (rebuilds the lookup we already have); the wins (statelessness) don't apply when tenant scope and per-key rate limit must be looked up anyway.
- **UUIDv4 as raw key** — rejected: 122 bits of entropy is borderline, hyphens are awkward to copy-paste, and there is no environmental tag for forensics.
- **Per-tenant key prefix (`<tenantSlug>_…`)** — rejected: leaks tenant identity into request logs and any `Authorization` header captured by an upstream proxy.

## Links

- Project Plan section 6.3 (Konfigurator API)
- Project Plan section 8.5 (limits) — agent BYOK is a separate concern under #102
- Related ADRs: ADR-0014 (Tenant as shared kernel), ADR-0015 (cross-BC FK policy)
- Tickets: #90 (this ADR introduced), #94 (Authenticator), #95 (test endpoint), #102 (BYOK Anthropic — distinct path)
