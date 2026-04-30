# Changelog

All notable changes to PIM. Format follows [Keep a Changelog](https://keepachangelog.com/),
versioning per epic milestones (`0.X.Y` matches ticket numbering in
`Project Plan/02-plan-projektu-pim.md`).

## [Unreleased]

### Added — epic 0.11 hardening

- **0.11.4** Audit log MVP via DH Auditor for catalog schema (ObjectType /
  Attribute / AttributeGroup / AttributeOption / AssociationType) +
  Channel / Asset / Identity (User / Role / Permission / Tenant) +
  API Configurator (ApiProfile / ApiKey). 365-day retention via
  `pim:audit:cleanup` CLI. (#99 / PR #241)
- **0.11.2** Rate limiting hardening — sliding-window on
  `/api/auth/refresh` (30/h/IP) + per-key budget on `X-API-Key`
  (1000/h/key). `pim:security:unblock-ip` operator escape hatch.
  (#97 / PR #242)
- **0.11.3** Full security-headers stack at the Caddy edge — CSP,
  HSTS, X-Frame-Options, Permissions-Policy, COOP, CORP, Server header
  stripped. Playwright contract pin to prevent silent regressions.
  (#98 / PR #243)
- **0.11.7** Renovate config — patch / pin / digest auto-merge,
  minor + major manual review with ecosystem grouping (Symfony /
  Doctrine / API Platform / React+Refine / Vite stack / Playwright /
  PHPStan). Vulnerability alerts auto-merge regardless of update type.
  (#102 / PR #244)
- **0.11.8** This changelog + `docs/runbook/disaster-recovery.md`.
  (#103 / this PR)

### Audit findings (2026-04-29) closed

- **HIGH-001** End-to-end test for `pim.catalog.enable_custom_object_types`
  feature flag spinning every layer (API guard / service guard /
  REST surface). (PR #239)
- **HIGH-002** Tenant context rebinding on async Messenger handlers.
  `TenantStamp` + `TenantAwareMessage` interface +
  `TenantContextRebindingMiddleware`. Defensive pre-emptive fix
  before Faza 1 async transports. (PR #240)

### Earlier (epic 0.10 — API Configurator MVP)

- **0.10.1** ApiProfile + ApiKey entities + Argon2id hashing + ADR-0016
  documenting key format. CLI `pim:apikey:generate`. (PR #233)
- **0.10.2** Admin UI list / create / edit + AP4 CRUD endpoints + Voters.
  Serializer XML excludes `keyHash` from every group. (PR #234)
- **0.10.3** Form 4-tab structure (Basic / Attributes / Filters /
  Preview) + ObjectType / Attribute multiselect + JSON preview.
  (PR #235)
- **0.10.4** Webhook config — URL / events / Test / Rotate-secret.
  HMAC-signed delivery via `WebhookDeliverySubscriber`. (PR #236)
- **0.10.5** `X-API-Key` authenticator + per-profile serializer
  context. `Shared\Application\Auth\ApiKeyPrincipal` cross-BC
  marker. (PR #237)
- **0.10.6** `/api/profiles/{code}/test` + `/api/profiles/{code}/openapi.json`
  per-profile OpenAPI export. (PR #238)

For older history (epics 0.1 — 0.9), see git log + `agent/current_status.md`.

[Unreleased]: https://github.com/malipie/PIM/compare/main...HEAD
