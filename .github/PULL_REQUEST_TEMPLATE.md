<!--
Phase 6 RBAC-P6-008 (#720) — PR template.

Keep the body short and concrete: every reviewer should be able to read it
in 30 seconds and understand what changed, why, and how it was verified.
The CI gates enforce most automated checks; this template surfaces the
manual evidence that automation cannot see (live-stack smoke output,
threat-model deltas, persona walkthroughs).

Delete sections that do not apply. Keep section headings so a future
reviewer can grep for "Security review" / "Smoke test" across history.
-->

## Summary

<!-- 1-3 bullets. What changed and why. Link issues with `Refs #N` / `Closes #N`. -->

-
-

## Scope

<!-- Optional: which bounded contexts or features. Drop if obvious. -->

## Security review

<!--
Required when the PR touches: controllers, voters, security.yaml, RLS,
TenantFilter, API tokens, MFA, SSO, password reset, break-glass, or any
schema that holds `tenant_id` / PII.

Fill in or write "N/A — no security surface touched" with one line of why.
-->

- [ ] Every new `/api/*` controller method carries `#[RequiresPermission]` or `#[NoPermissionRequired(reason: ...)]`
- [ ] Tenant filter / RLS / `TenantAssignmentListener` covered for new domain entities (`tenantId` column present + listener wired)
- [ ] Cross-tenant test added or updated for new voters / queries / policies
- [ ] No plaintext secrets, no `$_GET` / `$_POST`, no SQL string interpolation (Semgrep `.semgrep/cortex-rbac.yml` clean)
- [ ] Permission strings cross-checked vs PRD-PIM-rbac §3.2 macierz uprawnień

## Quality gates

<!-- Tick what you have run / observed pass. CI will rerun these too. -->

- [ ] PHPStan max — green, baseline empty
- [ ] PHPUnit unit + integration suites — green
- [ ] Biome strict + TypeScript noEmit — green
- [ ] Playwright E2E — green (when the change touches UI flows)
- [ ] `composer audit` + `pnpm audit` — no `HIGH` / `CRITICAL`
- [ ] OpenAPI spec drift — re-exported `docs/api-spec/v0.json` if API surface changed

## Smoke test

<!--
CLAUDE.md SMOKE TEST RULE — before claiming "działa" / "works" / "ready",
exercise the feature on the live stack (`pnpm stack:up` or
`https://pim.localhost`) and paste the HTTP code + response body / 302
Location / Mailpit screenshot below.

For pure-backend changes: `curl -sk -H "Authorization: Bearer <jwt>"
https://pim.localhost/api/... -w "%{http_code}"`.

For UI changes: per-persona walkthrough — note which persona, which
button, what visible result.
-->

```
# Paste HTTP code + JSON body snippet, or screenshot link, or "N/A — refactor only".
```

## Test plan

- [ ]

## Notes

<!-- Optional: trade-offs, follow-ups, dependencies on other PRs. -->
