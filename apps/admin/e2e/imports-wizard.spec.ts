import { expect, test } from '@playwright/test';

import { loginAsAdmin } from './helpers/auth';

/**
 * VIEW-IMP-05 (#504) — wizard refactor smoke. Single login covers the
 * three behaviours (header eyebrow + title visible, new stepper has
 * 4 numbered pills, first step active) to stay inside the 5/IP/15min
 * auth rate-limiter.
 */
test('imports wizard — header + stepper + step 1 active', async ({ page }) => {
  await loginAsAdmin(page);
  await page.goto('/integrations/imports/new');

  // Header eyebrow + h2 title (NUI-10 — six steps).
  await expect(page.getByText(/self-service, 6 (kroków|steps)/i)).toBeVisible();
  await expect(page.getByRole('heading', { name: /^nowy import$|^new import$/i })).toBeVisible();

  // Stepper: aria-current step pinned to the first pill — it carries
  // the step number + label + description, so match a substring.
  const activePill = page.locator('[aria-current="step"]');
  await expect(activePill).toBeVisible();
  await expect(activePill).toContainText(/źródło|source/i);

  // All 6 step pills visible.
  const stepper = page.getByLabel('Wizard steps');
  for (const label of [
    /źródło|source/i,
    /wykrywanie|detection/i,
    /mapowanie|mapping/i,
    /reguły|rules/i,
    /podgląd|preview/i,
    /start/i,
  ]) {
    await expect(stepper.getByText(label).first()).toBeVisible();
  }
});
