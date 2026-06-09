import { expect, test } from '@playwright/test';

import { ADMIN_EMAIL, ADMIN_PASSWORD, apiLogin } from './helpers/auth';

/**
 * #1361 — the custom-object create form (/objects/{slug}/new) labelled
 * its single identifier field "Kod (np. CAR-001)" with a "must be unique
 * … SKU" hint. For custom object types that field is the human-readable
 * name (e.g. a service "Wniesienie"), so it now reads "Nazwa" and the
 * code/uniqueness hint is gone.
 *
 * Marked `fixme` in CI for the shared `storageState` rate-limiter reason;
 * it also needs a custom (kind!=product/category/asset) ObjectType, which
 * only exists in operator dev data — skipped when none is present.
 */
const CI_BLOCKED = 'Pending storageState rollout: spec exhausts 5/15min auth rate limiter';

test('custom object create form labels the identifier field "Nazwa"', async ({ page }) => {
  test.fixme(!!process.env.CI, CI_BLOCKED);
  test.setTimeout(120_000);

  const loginResponse = await page.request.post('/api/auth/login', {
    data: { email: ADMIN_EMAIL, password: ADMIN_PASSWORD },
    headers: { accept: 'application/json' },
  });
  expect(loginResponse.status()).toBe(200);
  const { token } = (await loginResponse.json()) as { token: string };

  await apiLogin(page);

  const types =
    (
      (await (
        await page.request.get('/api/object_types?itemsPerPage=200', {
          headers: { authorization: `Bearer ${token}`, accept: 'application/ld+json' },
        })
      ).json()) as { member?: Array<{ code: string; kind: string }> }
    ).member ?? [];
  const custom = types.find((t) => !['product', 'category', 'asset'].includes(t.kind));
  test.skip(custom === undefined, 'No custom ObjectType seeded in this environment.');
  if (custom === undefined) return;

  await page.goto(`/objects/${custom.code}/new`);

  await expect(page.getByPlaceholder(/^nazwa$/i)).toBeVisible({ timeout: 15_000 });
  // The old "Kod (np. CAR-001)" placeholder + uniqueness hint are gone.
  await expect(page.getByPlaceholder(/kod \(np\. CAR-001\)/i)).toHaveCount(0);
  await expect(page.getByText(/kod musi być unikalny/i)).toHaveCount(0);
});
