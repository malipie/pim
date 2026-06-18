import { expect, test } from '@playwright/test';

import { loginAsAdmin, uniqueSku } from './helpers/auth';

/**
 * VIEW-07.3 (#432) — variants tab: master attribute inheritance,
 * inline edit through global toggle, copy-to-others propagation,
 * default-collapsed cards with expand/collapse all, generator above
 * the list.
 *
 * Single test running the full happy path. Re-enabled in AUD-022 (the
 * historical "rate-limiter" gating was a false root cause — dev/CI override
 * is 200/15min); passes against a healthy stack.
 */

test('VIEW-07.3 variants tab — generate inherits attributes + inline edit + copy-to-others', async ({
  page,
}) => {
  test.setTimeout(180_000);

  await loginAsAdmin(page);

  // Cookie-only page.request calls intermittently 401 behind the auth
  // rate limiter — mint a bearer like the other API-seeded specs do.
  const refreshResponse = await page.request.post('/api/auth/refresh');
  expect(refreshResponse.status()).toBe(200);
  const accessToken = ((await refreshResponse.json()) as { token: string }).token;
  const authHeaders = { Authorization: `Bearer ${accessToken}` };

  // Create the master product through the REST API to skip the UI
  // create flow that VIEW-07 already covers.
  const sku = uniqueSku('V73');
  const objectTypesResponse = await page.request.get('/api/object_types?itemsPerPage=200', {
    headers: authHeaders,
  });
  const objectTypesBody = (await objectTypesResponse.json()) as {
    member?: Array<{ id: string; kind: string; isBuiltIn: boolean }>;
    'hydra:member'?: Array<{ id: string; kind: string; isBuiltIn: boolean }>;
  };
  const types = objectTypesBody.member ?? objectTypesBody['hydra:member'] ?? [];
  // JSON-LD omits `isBuiltIn`; exactly one product-kind OT exists (built-in).
  const productType = types.find((t) => t.kind === 'product');
  if (productType === undefined) {
    throw new Error('Built-in product ObjectType not found — demo seeder did not run.');
  }

  const masterResponse = await page.request.post('/api/products', {
    headers: { ...authHeaders, 'content-type': 'application/ld+json' },
    data: {
      code: sku,
      objectTypeId: productType.id,
      attributes: { brand: 'Playwright Inc.', name: `Master ${sku}` },
    },
  });
  expect(masterResponse.status()).toBe(201);
  const master = (await masterResponse.json()) as { id: string };

  // Open the detail page → Warianty tab.
  await page.goto(`/products/${master.id}`);
  await page.getByRole('tab', { name: /warianty|variants/i }).click();

  // Generator is rendered above the list.
  await expect(page.getByRole('button', { name: /generate variants/i })).toBeVisible();

  // The generator boots with a default `color` axis draft — add two
  // values via the value combobox. (Filling the code by placeholder is a
  // trap: getByPlaceholder('color') substring-matches the SKU-template
  // input '{master_sku}-{color}' and corrupts the template.)
  const axisValueInput = page.getByPlaceholder(/add value & press enter/i).first();
  await axisValueInput.fill('red');
  await axisValueInput.press('Enter');
  await axisValueInput.fill('blue');
  await axisValueInput.press('Enter');

  // Computed default is shown as the SKU template placeholder.
  const skuTemplateInput = page.locator('#variants-sku-template');
  await expect(skuTemplateInput).toHaveAttribute('placeholder', '{master_sku}-{color}');

  // Generate — backend copies brand from master + stamps `color=red|blue`.
  const generateResponse = page.waitForResponse(
    (response) =>
      response.url().includes('/generate-variants') && response.request().method() === 'POST',
  );
  await page.getByRole('button', { name: /generate variants/i }).click();
  const generated = await generateResponse;
  expect(generated.status()).toBe(201);
  const generatedBody = (await generated.json()) as { created_count: number };
  expect(generatedBody.created_count).toBe(2);

  // List re-fetches via reloadKey; both variants should appear collapsed by default.
  await expect(page.getByText(`${sku}-red`)).toBeVisible({ timeout: 10_000 });
  await expect(page.getByText(`${sku}-blue`)).toBeVisible();

  // Expand all to inspect the inherited attributes.
  await page.getByRole('button', { name: /rozwiń wszystkie|expand all/i }).click();
  // brand inherited from master visible in at least one variant card.
  await expect(page.getByText('Playwright Inc.').first()).toBeVisible({ timeout: 10_000 });

  // Toggle global edit mode → first variant's `name` becomes an input.
  await page.getByRole('button', { name: /^(edytuj warianty|edit variants)$/i }).click();
  await expect(page.getByRole('button', { name: /^(zapisz|save)$/i })).toBeVisible();

  // Smoke: at least one Copy button is rendered now (replaces ProvenanceBadge in variants).
  const copyButtons = page.getByRole('button', {
    name: /kopiuj wartość .* do innych wariantów|copy .* to other variants/i,
  });
  await expect(copyButtons.first()).toBeVisible();

  // Cancel — exits edit mode without saving. #1351: the page header also
  // carries an "Anuluj" (edit-first detail), so target the variants one.
  await page
    .getByRole('button', { name: /^(anuluj|cancel)$/i })
    .last()
    .click();
  await expect(
    page.getByRole('button', { name: /^(edytuj warianty|edit variants)$/i }),
  ).toBeVisible();

  // Validation: unknown axis code returns 400 from backend (fail-fast guard).
  const unknownAxisResponse = await page.request.post(
    `/api/products/${master.id}/generate-variants`,
    {
      headers: { ...authHeaders, 'content-type': 'application/json' },
      data: { axes: { mystery_axis_xyz: ['foo'] } },
    },
  );
  expect(unknownAxisResponse.status()).toBe(400);
});
