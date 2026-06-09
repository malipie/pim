import { expect, test } from '@playwright/test';

import { loginAsAdmin, uniqueSku } from './helpers/auth';

/**
 * #1348 — the "Atrybuty" tab is the bucket for stacked/ungrouped
 * attributes (synthetic default group + display_mode='stacked' groups).
 * The built-in Product ObjectType ships only tab-mode groups (Grupa
 * Uniwersalna, Logistyka, …) and no ungrouped attributes, so the
 * "Atrybuty" tab used to render empty (just the ad-hoc adder). After
 * the fix it must be dropped entirely while the tab-mode groups keep
 * their own dedicated tabs.
 *
 * Marked `fixme` in CI for the shared `storageState` rate-limiter reason
 * (5 logins / 15 min); local cold-cache runs pass.
 */
const CI_BLOCKED = 'Pending storageState rollout: spec exhausts 5/15min auth rate limiter';

test('attributes tab is hidden when the object type has no stacked attributes', async ({
  page,
}) => {
  test.fixme(!!process.env.CI, CI_BLOCKED);
  test.setTimeout(180_000);

  await loginAsAdmin(page);

  const refreshResponse = await page.request.post('/api/auth/refresh');
  expect(refreshResponse.status()).toBe(200);
  const accessToken = ((await refreshResponse.json()) as { token: string }).token;
  const authHeaders = { Authorization: `Bearer ${accessToken}` };

  const objectTypesResponse = await page.request.get('/api/object_types', {
    headers: authHeaders,
  });
  const objectTypesBody = (await objectTypesResponse.json()) as {
    member?: Array<{ id: string; kind: string; codeImmutable: boolean }>;
    'hydra:member'?: Array<{ id: string; kind: string; codeImmutable: boolean }>;
  };
  const types = objectTypesBody.member ?? objectTypesBody['hydra:member'] ?? [];
  const productType = types.find((t) => t.kind === 'product' && t.codeImmutable);
  if (productType === undefined) {
    throw new Error('Built-in product ObjectType not found — demo seeder did not run.');
  }

  const sku = uniqueSku('TAB1348');
  const createResponse = await page.request.post('/api/products', {
    headers: { ...authHeaders, 'content-type': 'application/ld+json' },
    data: {
      code: sku,
      objectTypeId: productType.id,
      attributes: { name: `Attr-tab spec ${sku}` },
    },
  });
  expect(createResponse.status()).toBe(201);
  const created = (await createResponse.json()) as { id: string };

  const groupsResponse = page.waitForResponse(
    (response) =>
      response.url().includes(`/api/products/${created.id}/effective-attribute-groups`) &&
      response.request().method() === 'GET',
    { timeout: 30_000 },
  );
  await page.goto(`/products/${created.id}`);
  await groupsResponse;

  const tablist = page.getByRole('tablist', { name: /zakładki produktu|product tabs/i });
  await expect(tablist).toBeVisible();

  // A tab-mode group keeps its dedicated tab (label falls back to the
  // group code when the active UI locale has no translated label).
  await expect(tablist.getByRole('tab', { name: /grupa[_ ]uniwersalna/i })).toBeVisible();

  // The empty "Atrybuty" tab is gone.
  await expect(tablist.getByRole('tab', { name: /^(atrybuty|attributes)$/i })).toHaveCount(0);

  // Cleanup — keep the demo DB tidy (attributes/products leak into pickers).
  await page.request.delete(`/api/products/${created.id}`, { headers: authHeaders });
});
