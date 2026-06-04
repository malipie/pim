import { expect, test } from '@playwright/test';

import { loginAsAdmin } from './helpers/auth';

/**
 * #1263 — the option-label editor at /modeling/attributes/{id}/values shows
 * the tenant's enabled locales (pl + en for the demo tenant), not a
 * hardcoded pl/en/de list. The empty 'de' column is gone.
 *
 * `test.fixme` in CI for the shared auth-rate-limiter storageState gap.
 */
const CI_BLOCKED = 'Pending storageState rollout: spec exhausts 5/15min auth rate limiter';

test('option-label editor lists tenant locales, not a hardcoded pl/en/de', async ({ page }) => {
  test.fixme(!!process.env.CI, CI_BLOCKED);
  test.setTimeout(120_000);

  await loginAsAdmin(page);
  const refresh = await page.request.post('/api/auth/refresh');
  expect(refresh.status()).toBe(200);
  const token = ((await refresh.json()) as { token: string }).token;
  const bearer = { Authorization: `Bearer ${token}` };

  // The demo seeds `color` as a select with options.
  const attrResp = await page.request.get('/api/attributes?itemsPerPage=200', { headers: bearer });
  const attrBody = (await attrResp.json()) as {
    member?: Array<{ id: string; code: string; type: string }>;
  };
  const color = (attrBody.member ?? []).find((a) => a.code === 'color' && a.type === 'select');
  if (color === undefined) {
    throw new Error('Demo `color` select attribute not found — seeder did not run.');
  }

  await page.goto(`/modeling/attributes/${color.id}/values`);

  // Open the first option to reveal the per-locale label editor.
  await page
    .getByText(/^(Czerwony|Red|red)$/i)
    .first()
    .click();

  // The editor's locale tabs: pl + en present, de absent (demo tenant).
  const labelsSection = page
    .getByText(/^(Etykiety wyświetlane|Displayed labels)$/i)
    .locator('xpath=ancestor::div[1]');
  await expect(labelsSection.getByRole('button', { name: /\bpl\b/i })).toBeVisible();
  await expect(labelsSection.getByRole('button', { name: /\ben\b/i })).toBeVisible();
  await expect(labelsSection.getByRole('button', { name: /\bde\b/i })).toHaveCount(0);
});
