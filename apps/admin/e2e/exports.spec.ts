import { expect, test } from '@playwright/test';

import { loginAsAdmin } from './helpers/auth';

/**
 * EXP-15 (#594) — Exports hub MVP smoke.
 *
 * Single-login smoke that covers the three end-to-end behaviours
 * shipped in EXP-09..EXP-14 + EXP-11/12:
 *   - `/integrations/exports` renders the tab strip + CTA.
 *   - Sessions tab shows either the empty state or the polled grid.
 *   - "Nowy eksport" CTA opens the full-page form (ExportModal
 *     forced-open per EXP-12).
 *
 * Świadome odejścia:
 *   - Full 5-scenario E2E z PRD §15.1 (modal kontekstowy XLSX,
 *     central CSV, profile lifecycle, async + Mercure, round-trip
 *     reimport) jest follow-up. Round-trip scenario specifically
 *     blocked by IMP-16..IMP-19 (#602–#605) — variants flat /
 *     pipe-separated / asset URL / multi-locale columns nie są
 *     wspierane przez IMP-01..15 pipeline (EXP-02 audit).
 *   - Cross-tenant izolacja test (PHP ApiTestCase) follow-up —
 *     tenancy contract walidowany przez TenantAuditCommand
 *     (zielony od fix #607).
 */
test('exports hub MVP — tabs + new flow smoke', async ({ page }) => {
  await loginAsAdmin(page);
  await page.goto('/integrations/exports');

  // Hub heading + tab strip.
  await expect(page.getByRole('heading', { name: /eksporty|exports/i })).toBeVisible();
  await expect(page.getByRole('tab', { name: /sessions|sesje/i }).first()).toBeVisible();
  await expect(page.getByRole('tab', { name: /profiles|profile/i }).first()).toBeVisible();

  // Sessions empty state (fresh demo).
  await expect(page.getByText(/nie masz jeszcze eksportów|no exports yet/i)).toBeVisible();

  // "Nowy eksport" CTA opens the standalone full-page form.
  await page.getByRole('link', { name: /nowy eksport|new export/i }).click();
  await expect(page).toHaveURL(/\/integrations\/exports\/new$/);
  await expect(page.getByRole('dialog')).toBeVisible();
});
