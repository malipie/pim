import { expect, test } from '@playwright/test';

import { loginAsAdmin } from './helpers/auth';

/**
 * NUI-10 (#1429) — six-step import wizard happy path on the live stack:
 * Źródło (upload CSV) → Wykrywanie (parse-preview detection + sample
 * table) → Mapowanie (auto-map rows) → Reguły (truth card, mocked
 * controls) → Podgląd (dry-run KPIs) → Start (commit → session page).
 * Endpoints stay identical to the 4-step flow.
 */
test('NUI-10 — wizard walks six steps and commits an import session', async ({ page }) => {
  test.setTimeout(120_000);
  await loginAsAdmin(page);
  await page.goto('/integrations/imports/new');

  // Stepper renders all six steps.
  const stepper = page.getByLabel('Wizard steps');
  for (const label of [
    /źródło|source/i,
    /wykrywanie|detect/i,
    /mapowanie|mapping/i,
    /reguły|rules/i,
    /podgląd|preview/i,
    /start/i,
  ]) {
    await expect(stepper.getByText(label).first()).toBeVisible();
  }

  // Step 1 — Źródło: upload a tiny CSV.
  const sku = `NUI10-${Date.now()}`;
  const csv = `sku;name\n${sku};Wizard smoke product\n`;
  await page
    .locator('input[type="file"]')
    .first()
    .setInputFiles({ name: 'nui10.csv', mimeType: 'text/csv', buffer: Buffer.from(csv, 'utf-8') });
  await page.getByRole('button', { name: /dalej|next/i }).click();

  // Step 2 — Wykrywanie: detection table + preview from parse-preview.
  await expect(page.getByText(/kodowanie|encoding/i).first()).toBeVisible({ timeout: 20_000 });
  await expect(page.getByText(/kolumn wykrytych|columns detected/i)).toBeVisible();
  await expect(page.getByText(sku)).toBeVisible();
  await page.getByRole('button', { name: /dalej|next/i }).click();

  // Step 3 — Mapowanie: auto-map produces rows; computed-column modal is a mock.
  await expect(page.getByRole('button', { name: /kolumna obliczona|computed/i })).toBeVisible({
    timeout: 20_000,
  });
  await expect(page.getByText('sku', { exact: true }).first()).toBeVisible();
  const nextOnMapping = page.getByRole('button', { name: /dalej|next/i });
  await expect(nextOnMapping).toBeEnabled({ timeout: 20_000 });
  await nextOnMapping.click();

  // Step 4 — Reguły: truth card + disabled mode tiles.
  await expect(page.getByText(/upsert (po identyfikatorze|by identifier)/i)).toBeVisible();
  await expect(page.getByText('UPSERT', { exact: true })).toBeVisible();
  await page.getByRole('button', { name: /dalej|next/i }).click();

  // Step 5 — Podgląd: dry-run resolves with KPIs.
  await expect(page.getByText(/ok/i).first()).toBeVisible({ timeout: 30_000 });
  await page.getByRole('button', { name: /dalej|next/i }).click();

  // Step 6 — Start: summary + run.
  await expect(page.getByText(/podsumowanie|summary/i)).toBeVisible();
  await page.getByRole('button', { name: /uruchom import|run import/i }).click();

  // Commit redirects to the session show page.
  await expect(page).toHaveURL(/\/integrations\/imports\/[0-9a-f-]{8,}/, { timeout: 30_000 });
});
