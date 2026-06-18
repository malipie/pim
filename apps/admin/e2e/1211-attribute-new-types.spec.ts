import { expect, test } from '@playwright/test';

import { loginAsAdmin } from './helpers/auth';

/**
 * #1210 follow-up — the standalone /modeling/attributes/new page lagged the
 * create dialogs on the new attribute types (textarea/datetime/color/email/
 * identifier, shipped #1177–#1179). It now shares CREATABLE_ATTRIBUTE_TYPES,
 * so the data-type grid lists every creatable type.
 *
 * `fixme` in CI for the same auth rate-limiter reason as the other UI specs.
 */

test('the new-attribute page lists the recently added data types', async ({ page }) => {
  test.setTimeout(60_000);

  await loginAsAdmin(page);
  await page.goto('/modeling/attributes/new');

  const typeSection = page.getByText(/typ danych/i);
  await expect(typeSection).toBeVisible();

  // The types that were missing before this fix.
  for (const type of ['textarea', 'datetime', 'identifier', 'color', 'email']) {
    await expect(page.getByRole('button', { name: type, exact: true })).toBeVisible();
  }

  // `reference` is system-only and must NOT be offered for creation.
  await expect(page.getByRole('button', { name: 'reference', exact: true })).toHaveCount(0);
});
