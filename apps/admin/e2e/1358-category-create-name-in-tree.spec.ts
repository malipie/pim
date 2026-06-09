import { expect, test } from '@playwright/test';

import { ADMIN_EMAIL, ADMIN_PASSWORD, apiLogin } from './helpers/auth';

/**
 * #1358 — the category tree showed the raw snake_case `code` because the
 * create form captured a name but never sent it (the VIEW-04b upserter
 * wiring was never finished). The create POST now carries
 * `attributes.name`, so a freshly-created category renders by its name in
 * the tree.
 *
 * Marked `fixme` in CI for the shared `storageState` rate-limiter reason.
 */
const CI_BLOCKED = 'Pending storageState rollout: spec exhausts 5/15min auth rate limiter';

test('creating a category persists its name and shows it in the tree', async ({ page }) => {
  test.fixme(!!process.env.CI, CI_BLOCKED);
  test.setTimeout(120_000);

  const loginResponse = await page.request.post('/api/auth/login', {
    data: { email: ADMIN_EMAIL, password: ADMIN_PASSWORD },
    headers: { accept: 'application/json' },
  });
  expect(loginResponse.status()).toBe(200);
  const { token } = (await loginResponse.json()) as { token: string };
  const bearer = { authorization: `Bearer ${token}` };

  await apiLogin(page);

  const types =
    (
      (await (
        await page.request.get('/api/object_types?itemsPerPage=200', {
          headers: { ...bearer, accept: 'application/ld+json' },
        })
      ).json()) as { member?: Array<{ id: string; kind: string; codeImmutable: boolean }> }
    ).member ?? [];
  const productType = types.find((t) => t.kind === 'product' && t.codeImmutable);
  if (productType === undefined) throw new Error('Built-in product ObjectType not seeded.');

  const code = `zz_cat_${Date.now().toString(36).toLowerCase()}`;
  const name = `Kategoria E2E ${code}`;

  // Wait for the object_types list so the create handler can resolve the
  // built-in category ObjectType before we submit.
  const objectTypesLoaded = page.waitForResponse(
    (r) => r.url().includes('/api/object_types') && r.request().method() === 'GET',
    { timeout: 30_000 },
  );
  await page.goto(`/modeling/categories/new?targetObjectTypeId=${productType.id}`);
  await objectTypesLoaded;
  // Let Refine commit the object_types list to state so the create
  // handler resolves the built-in category ObjectType.
  await page.waitForTimeout(2_500);
  await page.locator('#cat-code').fill(code);
  await page.locator('#cat-name-pl').fill(name);

  const postPromise = page.waitForResponse(
    (r) => r.url().endsWith('/api/categories') && r.request().method() === 'POST',
    { timeout: 15_000 },
  );
  await page.getByRole('button', { name: /utwórz kategorię|create category/i }).click();
  const post = await postPromise;
  expect(post.status()).toBe(201);
  const sent = post.request().postDataJSON() as { attributes?: { name?: string } };
  expect(sent.attributes?.name).toBe(name);

  // Back on the tree — the new node renders by its name, not its code.
  await expect(page).toHaveURL(/\/modeling\/categories\?/, { timeout: 15_000 });
  await expect(page.getByText(name).first()).toBeVisible({ timeout: 15_000 });

  // Cleanup — find the created category by code and delete it.
  const created = (await (
    await page.request.get('/api/categories?itemsPerPage=200', {
      headers: { ...bearer, accept: 'application/json' },
    })
  ).json()) as { member?: Array<{ id: string; code: string }> };
  const row = (created.member ?? []).find((c) => c.code === code);
  if (row) await page.request.delete(`/api/categories/${row.id}`, { headers: bearer });
});
