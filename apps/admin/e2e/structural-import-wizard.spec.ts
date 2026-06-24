import { expect, test } from '@playwright/test';

import { loginAsAdmin } from './helpers/auth';

/**
 * Structural import wizard happy path on the live stack — the mirror of the
 * `attributes` / `attribute_groups` exports. Picking the "Atrybuty" tile
 * collapses the wizard to the simplified 4-step flow (Dane → Źródło →
 * Wykrywanie → Start): mapping, rules and dry-run are skipped (the columns are
 * the export's own fixed headers). The Start step POSTs the file to
 * /api/structural-import-sessions and redirects to the session page.
 */
test('structural attribute import walks the 4-step flow and commits', async ({ page }) => {
  test.setTimeout(120_000);
  await loginAsAdmin(page);
  await page.goto('/integrations/imports/new');

  // Step 1 — Dane: pick the "Atrybuty"/"Attributes" tile; the stepper collapses
  // to 4 steps. ("Attribute groups" never matches /attributes/ — no trailing s.)
  await page
    .getByRole('radio', { name: /atrybuty|attributes/i })
    .first()
    .click();
  const stepper = page.getByLabel('Wizard steps');
  await expect(stepper.getByText(/mapowanie|mapping/i)).toHaveCount(0);
  await expect(stepper.getByText(/reguły|rules/i)).toHaveCount(0);

  const dataNext = page.getByRole('button', { name: /dalej|next/i });
  await expect(dataNext).toBeEnabled({ timeout: 10_000 });
  await dataNext.click();

  // Step 2 — Źródło: upload a tiny attribute-definition CSV.
  const code = `e2e_attr_${Date.now()}`;
  const csv = `code;type;label.pl;label.en;object_types\n${code};text;Pole E2E;E2E field;product\n`;
  await page
    .locator('input[type="file"]')
    .first()
    .setInputFiles({ name: 'attrs.csv', mimeType: 'text/csv', buffer: Buffer.from(csv, 'utf-8') });
  await page.getByRole('button', { name: /dalej|next/i }).click();

  // Step 3 — Wykrywanie: parse-preview detection + sample row with the code.
  await expect(page.getByText(/kodowanie|encoding/i).first()).toBeVisible({ timeout: 20_000 });
  await expect(page.getByText(code)).toBeVisible();
  await page.getByRole('button', { name: /dalej|next/i }).click();

  // Step 4 — Start: summary + run → POST to the structural endpoint.
  await expect(page.getByText(/podsumowanie|summary/i)).toBeVisible();
  const [commitResponse] = await Promise.all([
    page.waitForResponse(
      (response) =>
        response.url().includes('/api/structural-import-sessions') &&
        response.request().method() === 'POST',
      { timeout: 30_000 },
    ),
    page.getByRole('button', { name: /uruchom import|run import/i }).click(),
  ]);
  test.skip(
    commitResponse.status() === 409,
    'PROD-05 bulk lock held by a concurrent suite operation',
  );
  const body = await commitResponse.text().catch(() => '<unreadable>');
  expect(
    commitResponse.ok(),
    `POST /api/structural-import-sessions -> ${commitResponse.status()}: ${body.slice(0, 500)}`,
  ).toBeTruthy();
  expect(body).toContain('"structural_kind":"attributes"');
  expect(body).toContain('"status":"success"');

  await expect(page).toHaveURL(/\/integrations\/imports\/[0-9a-f-]{8,}/, { timeout: 30_000 });
});
