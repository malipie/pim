import { expect, test } from '@playwright/test';

import { ADMIN_EMAIL, ADMIN_PASSWORD, apiLogin } from './helpers/auth';

/**
 * #1359 — a categorizable custom ObjectType could be created without a
 * category, and the create form had no category picker at all. The
 * universal create page now renders a category selector for categorizable
 * types and blocks save until at least one category is assigned (same
 * rule /products/new enforces).
 *
 * Marked `fixme` in CI for the shared `storageState` rate-limiter reason.
 */
const CI_BLOCKED = 'Pending storageState rollout: spec exhausts 5/15min auth rate limiter';

test('categorizable custom object create requires a category', async ({ page }) => {
  test.fixme(!!process.env.CI, CI_BLOCKED);
  test.setTimeout(150_000);

  const loginResponse = await page.request.post('/api/auth/login', {
    data: { email: ADMIN_EMAIL, password: ADMIN_PASSWORD },
    headers: { accept: 'application/json' },
  });
  expect(loginResponse.status()).toBe(200);
  const { token } = (await loginResponse.json()) as { token: string };
  const bearer = { authorization: `Bearer ${token}` };
  const jsonHeaders = {
    ...bearer,
    accept: 'application/ld+json',
    'content-type': 'application/json',
  };

  await apiLogin(page);

  const stamp = Date.now().toString(36);
  const otCode = `catz_${stamp}`;

  // Custom, categorizable ObjectType.
  const otResp = await page.request.post('/api/object_types', {
    data: {
      code: otCode,
      label: { pl: 'Catz', en: 'Catz' },
      icon: '📦',
      color: '#10b981',
      hierarchical: false,
      hasVariants: false,
      abstract: false,
    },
    headers: jsonHeaders,
  });
  expect(otResp.status(), await otResp.text()).toBe(201);
  const ot = (await otResp.json()) as { id: string };
  const patchResp = await page.request.patch(`/api/object_types/${ot.id}`, {
    data: { isCategorizable: true },
    headers: {
      ...bearer,
      accept: 'application/ld+json',
      'content-type': 'application/merge-patch+json',
    },
  });
  expect(patchResp.status()).toBe(200);

  // A category in this ObjectType's tree.
  const catOtResp = await page.request.get('/api/object_types?itemsPerPage=200', {
    headers: { ...bearer, accept: 'application/ld+json' },
  });
  const catOt = (
    ((await catOtResp.json()) as { member?: Array<{ id: string; kind: string }> }).member ?? []
  ).find((t) => t.kind === 'category');
  if (catOt === undefined) throw new Error('No built-in category ObjectType.');
  const catResp = await page.request.post('/api/categories', {
    data: {
      code: `cat_${stamp}`,
      objectTypeId: catOt.id,
      categoryTargetObjectTypeId: ot.id,
      attributes: { name: `Kat ${stamp}` },
    },
    headers: jsonHeaders,
  });
  expect(catResp.status(), await catResp.text()).toBe(201);

  await page.goto(`/objects/${otCode}/new`);

  // Category picker is present for categorizable types.
  await expect(page.getByText(/przypisz kategorię|brak kategorii|kategorie/i).first()).toBeVisible({
    timeout: 15_000,
  });

  // Try to create without a category → blocked with a toast, no POST fires.
  // #1415 unified create: a code ("Kod") input + a name input.
  await page.getByPlaceholder(/^(kod|sku)$/i).fill(`OBJ-${stamp}`);
  await page.getByPlaceholder(/^nazwa$|nazwa produktu/i).fill(`Obj ${stamp}`);
  let posted = false;
  page.on('request', (r) => {
    if (r.url().endsWith('/api/objects') && r.method() === 'POST') posted = true;
  });
  await page.getByRole('button', { name: /^utwórz$/i }).click();
  await expect(page.getByText(/przypisz przynajmniej jedną kategorię/i)).toBeVisible({
    timeout: 10_000,
  });
  expect(posted).toBe(false);

  // Cleanup OT (cascades) — delete via API.
  await page.request.delete(`/api/object_types/${ot.id}`, { headers: bearer });
});
