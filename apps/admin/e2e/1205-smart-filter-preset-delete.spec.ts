import { expect, test } from '@playwright/test';

import { loginAsAdmin, uniqueSku } from './helpers/auth';

/**
 * #1205 — deleting a user-created Smart Filter preset from the list view.
 *
 * The backend DELETE endpoint and the `useSmartPresets().remove` hook
 * already existed; this proves the (previously missing) UI: the chip's
 * hover/focus-revealed × → confirmation dialog → DELETE → chip gone.
 *
 * Deterministic: the preset is seeded via API (POST), then the spec drives
 * the delete UI on `/products`, which renders the same shared
 * `SmartFilterPresetsRow` + `DeletePresetDialog` as the universal list.
 *
 * `fixme` in CI for the same auth rate-limiter reason as the other
 * UI-seeded specs.
 */
const CI_BLOCKED = 'Pending storageState rollout: spec exhausts 5/15min auth rate limiter';

test('a user Smart Filter preset can be deleted from the list view', async ({ page }) => {
  test.fixme(!!process.env.CI, CI_BLOCKED);
  test.setTimeout(120_000);

  await loginAsAdmin(page);

  const refreshResponse = await page.request.post('/api/auth/refresh');
  expect(refreshResponse.status()).toBe(200);
  const accessToken = ((await refreshResponse.json()) as { token: string }).token;
  const bearer = { Authorization: `Bearer ${accessToken}` };

  const presetName = `E2E ${uniqueSku('PRESET')}`;

  // Seed a user-created (non-built-in) preset scoped to the products list.
  // `/products` is the universal list for the built-in product ObjectType,
  // which loads presets by its OT code `product` (singular) — not the legacy
  // `products` resource string.
  const createResp = await page.request.post('/api/smart-filter-presets', {
    headers: { ...bearer, 'content-type': 'application/json' },
    data: {
      name: { pl: presetName, en: presetName },
      icon: '⭐',
      query: { attr: 'main_image', op: 'IS EMPTY' },
      resource: 'product',
    },
  });
  expect(createResp.status(), await createResp.text()).toBe(201);

  const presetsRow = page.getByRole('tablist', { name: /smart filtry/i });
  await page.goto('/products');
  await expect(presetsRow).toBeVisible();

  const chip = presetsRow.getByRole('tab', { name: new RegExp(presetName, 'i') });
  await expect(chip).toBeVisible();

  // The delete affordance is revealed on hover/focus-within of the chip.
  await chip.hover();
  const deleteButton = page.getByRole('button', {
    name: new RegExp(`usuń preset ${presetName}`, 'i'),
  });
  await deleteButton.click();

  // Confirmation dialog → confirm → DELETE 204 → chip disappears.
  const dialog = page.getByRole('dialog');
  await expect(dialog).toBeVisible();

  const deleteResponse = page.waitForResponse(
    (response) =>
      response.url().includes('/api/smart-filter-presets/') &&
      response.request().method() === 'DELETE',
  );
  await dialog.getByRole('button', { name: /^(usuń preset|delete preset)$/i }).click();
  expect((await deleteResponse).status()).toBe(204);

  await expect(chip).toBeHidden();
});
