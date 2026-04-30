# 0017. BYOK encryption — AES-256-GCM with versioned master key

- **Status:** accepted
- **Date:** 2026-04-30
- **Deciders:** Marcin Lipiec, Senior Staff Engineer

## Context and Problem Statement

Risk **R-27** ("agent cost runaway") in `Project Plan/02-plan-projektu-pim.md`
puts the worst-case at $1000–10000 if the platform-issued Anthropic key
leaks or an abuse loop hits the budget. Mitigation **(b)** in the
matrix: BYOK — enterprise tenants pay their own LLM bill against a
key they control. Operationalising BYOK forces three concrete
decisions:

1. How is the secret stored at rest?
2. How does the runtime resolver pick between tenant key and platform key?
3. What is the rotation contract when the master encryption key is
   compromised (or the cipher recommendation moves)?

This ADR settles (1) and (3). (2) is a runtime contract that lands
with the agent layer (epic 0.7 / Faza 2) — the storage shape proposed
here is forward-compatible with the resolver.

## Decision Drivers

- The platform must never store, log, or echo the plaintext key.
  Operators can submit and rotate; nobody — including a DB administrator
  with a read replica — should be able to recover an existing key from
  storage.
- A single dump of the database without the master key must be
  cryptographically useless against the BYOK rows.
- Master-key rotation must not require decrypt-then-re-encrypt of every
  row at the moment of rotation. Operators read at most one row per
  request; on-the-fly re-encryption on hot path is acceptable.
- The cipher choice must survive a future PHP / libsodium upgrade
  without forcing a forklift migration.

## Considered Options

1. **AES-256-GCM via `sodium_crypto_aead_aes256gcm_*`** — authenticated
   encryption with associated data (AEAD), 256-bit key, embedded
   tag detects tampering, native to libsodium on PHP 8.4.
2. **ChaCha20-Poly1305 via `sodium_crypto_aead_chacha20poly1305_ietf_*`**
   — same AEAD properties, software-friendly without AES-NI.
3. **AES-256-CBC + HMAC-SHA-256** — encrypt-then-MAC, requires two
   keys + manual MAC validation. Easy to get wrong.
4. **Plaintext + DB column-level encryption** (Postgres `pgcrypto`) —
   key sits next to data on disk, defeats the point.
5. **HashiCorp Vault transit engine** — proper KMS, but introduces a
   runtime dependency PIM does not own and a network round-trip per
   read.

## Decision Outcome

**Chosen: AES-256-GCM via libsodium (option 1)**, with a versioned master
key and on-read rehash semantics.

### Storage shape

- `tenant_agent_config` table (per-tenant 1:1):
  - `tenant_id` (FK)
  - `anthropic_api_key_encrypted` — bytea, the libsodium output
    (nonce ‖ ciphertext ‖ tag) base64-encoded for portability across
    pgsql client libraries.
  - `encryption_key_version` — int, points at a specific master key
    in the runtime configuration (`APP_BYOK_KEY_V1`,
    `APP_BYOK_KEY_V2`, …). Defaults to the latest version on insert.
  - `key_prefix` — first ~6 chars of the plaintext, stored alongside
    the ciphertext for display (`sk-ant-…`). Safe to log, useless on
    its own.
  - `enabled_at`, `disabled_at` — tenant can pause BYOK without
    deleting the row.
  - `last_used_at` — bumped by the resolver on every successful read,
    surfaces dormant rows for audit.

### Master key

- 32 bytes of random material per version (`sodium_crypto_aead_aes256gcm_keygen`),
  base64 in env: `APP_BYOK_KEY_V1`, `APP_BYOK_KEY_V2`, etc.
- The application boots with **all known versions** loaded — the
  resolver picks by `encryption_key_version` to decrypt, always
  encrypts new writes with the highest declared version.
- On a successful decrypt against an older version, the row is
  **lazily re-encrypted** with the latest version on the same write
  cycle — same cooldown shape as Argon2id rehash on auth.

### Rotation

- Compromise: bump `APP_BYOK_KEY_V<N+1>`, deploy. Existing rows
  decrypt with V<N>, lazy re-encrypt to V<N+1> on first read. After
  the soak window (~2 weeks) operators can sweep dormant rows by
  calling the resolver in a CLI (`pim:byok:sweep`).
- Cipher: same lazy path covers a hypothetical future migration to a
  successor cipher — `encryption_key_version` is opaque enough to
  carry both key generation AND cipher choice.

### Consequences

- **Positive:** AEAD tag detects tampered ciphertext on every read.
  Master key separation means a DB dump is useless to an attacker.
  Rotation is zero-downtime and bounded — no fleet-wide rewrite.
- **Negative:** AES-NI dependency for fast AES-GCM. Servers without
  AES-NI fall back to constant-time software AES which is ~5× slower
  but still well below network latency. The resolver runs once per
  agent call, not per request, so the cost is invisible.
- **Follow-ups:**
  - **#107 follow-up** — Anthropic client factory + admin UI for the
    BYOK form (lands together with epic 0.7 Beta-Min).
  - **`pim:byok:sweep`** CLI for proactive re-encrypt under a forced
    rotation window (Faza 2 hardening).
  - **Vault transit engine** as a follow-up swap when the operator
    stack grows past one host — the storage shape is compatible
    (`encryption_key_version` becomes a Vault key id).

## Alternatives Considered

- **ChaCha20-Poly1305** — equally secure, slightly faster on
  AES-NI-less hardware. Rejected: AES-GCM is the ecosystem default
  (TLS, SSH, JWT) so operators reach for tools that speak it. The
  rotation strategy generalises so a future migration to ChaCha is
  cheap.
- **AES-256-CBC + HMAC-SHA-256** — rejected: two-key invariants are
  easy to break (forget to MAC validate, MAC the wrong slice). AEAD
  primitive removes the foot-gun.
- **pgcrypto** — rejected: stores the plaintext or the key in the
  same place as the data, defeats the point.
- **Vault transit** — rejected for MVP: introduces a hard runtime
  dependency. Re-evaluate when ≥3 hosts share the application
  cluster (`Project Plan/02-plan-projektu-pim.md` §11.x Faza 2).

## Links

- Project Plan §8.5 (Bezpieczeństwo agenta — limits + BYOK)
- Project Plan §11 (security posture)
- R-27 (agent cost runaway) — Project Plan §6 risks table
- Related ADRs: ADR-0016 (API key Argon2id format — uses the same
  "lazy rehash on read" pattern)
- Tickets: #107 (this ADR introduced), epic 0.7 (Anthropic client
  factory consumer)
