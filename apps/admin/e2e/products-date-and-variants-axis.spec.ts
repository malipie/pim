import { expect, test } from '@playwright/test';

import { loginAsAdmin, uniqueSku } from './helpers/auth';

/**
 * Operator follow-up to the predefined-value attrs PR (#512) on
 * 2026-05-12:
 *  1. `release_date` (Data premiery, type=date) renders a plain text
 *     input — no date picker.
 *  2. The Variants tab axis selector is a generic <input + datalist>
 *     that requires deleting the field content before suggestions
 *     show up, and surfaces every attribute (incl. system-only) as
 *     candidate axis.
 *
 * This spec exercises both fixes:
 *  - AttrRow now renders `<input type="date">` for `date`. Pick a
 *    date, save, reload → read-only display shows the picked date.
 *  - The Variants axis selector is now a Combobox restricted to
 *    select/multiselect attrs. Click → list opens → pick `color` →
 *    suggested option codes (red/green/blue) appear under the row.
 *
 * Marked `fixme` in CI for the same `storageState` reason the other
 * UI-seeded specs use.
 */
const CI_BLOCKED = 'Pending storageState rollout: spec exhausts 5/15min auth rate limiter';

test('product detail date picker + variants axis combobox', async ({ page }) => {
  test.fixme(!!process.env.CI, CI_BLOCKED);
  test.setTimeout(180_000);

  await loginAsAdmin(page);

  const refreshResponse = await page.request.post('/api/auth/refresh');
  expect(refreshResponse.status()).toBe(200);
  const accessToken = ((await refreshResponse.json()) as { token: string }).token;
  const authHeaders = { Authorization: `Bearer ${accessToken}` };

  const sku = uniqueSku('DATE');

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

  // The demo-seeded `release_date` attribute drifted out of the product
  // ObjectType during operator UAT — create a dedicated date attribute so
  // the spec is self-sufficient (mirrors products-channel-switch).
  const ts = sku.toLowerCase().replace(/[^a-z0-9]/g, '_');
  const dateAttrResp = await page.request.post('/api/attributes', {
    headers: { ...authHeaders, 'content-type': 'application/ld+json' },
    data: {
      code: `reldate_${ts}`,
      type: 'date',
      label: { pl: 'Data premiery', en: 'Release date' },
    },
  });
  expect(dateAttrResp.status(), await dateAttrResp.text()).toBe(201);
  const dateAttrId = ((await dateAttrResp.json()) as { id: string }).id;
  const attachResp = await page.request.post(
    `/api/object_types/${productType.id}/attributes/${dateAttrId}`,
    { headers: authHeaders },
  );
  expect([200, 201, 204]).toContain(attachResp.status());

  // Same drift story for the seeded `color` axis attribute — create a
  // dedicated select attribute with three options instead.
  const axisAttrResp = await page.request.post('/api/attributes', {
    headers: { ...authHeaders, 'content-type': 'application/ld+json' },
    data: {
      code: `kolor_${ts}`,
      type: 'select',
      label: { pl: 'Kolor osi', en: 'Axis color' },
    },
  });
  expect(axisAttrResp.status(), await axisAttrResp.text()).toBe(201);
  const axisAttrId = ((await axisAttrResp.json()) as { id: string }).id;
  for (const value of ['red', 'green', 'blue']) {
    const optionResp = await page.request.post(`/api/attributes/kolor_${ts}/options`, {
      headers: { ...authHeaders, 'content-type': 'application/json' },
      data: { code: value, label: { pl: value, en: value } },
    });
    expect([200, 201]).toContain(optionResp.status());
  }
  const axisAttachResp = await page.request.post(
    `/api/object_types/${productType.id}/attributes/${axisAttrId}`,
    { headers: authHeaders },
  );
  expect([200, 201, 204]).toContain(axisAttachResp.status());

  const createResponse = await page.request.post('/api/products', {
    headers: { ...authHeaders, 'content-type': 'application/ld+json' },
    data: {
      code: sku,
      objectTypeId: productType.id,
      attributes: { name: `Date+Axis spec ${sku}` },
    },
  });
  expect(createResponse.status()).toBe(201);
  const product = (await createResponse.json()) as { id: string };

  const groupsResponse = page.waitForResponse(
    (response) =>
      response.url().includes('/effective-attribute-groups') &&
      response.request().method() === 'GET',
    { timeout: 15_000 },
  );
  await page.goto(`/products/${product.id}`);
  await groupsResponse;
  // #1351 — detail opens directly in edit mode; no "Edytuj" click needed.
  await expect(page.getByRole('button', { name: /^(zapisz zmiany|save changes)$/i })).toBeVisible();

  // ---------- Part 1: date picker -----------
  await page.evaluate(() => window.scrollTo(0, document.body.scrollHeight));
  const dateLabel = page.getByText(/^(Data premiery|Release date)$/).first();
  await dateLabel.scrollIntoViewIfNeeded();
  const dateRow = dateLabel.locator('xpath=ancestor::div[contains(@class, "grid-cols")][1]');
  // Native HTML5 date input — Playwright introspects type=date.
  const dateInput = dateRow.locator('input[type="date"]');
  await expect(dateInput).toBeVisible();
  await dateInput.fill('2027-03-15');

  const datePatch = page.waitForResponse(
    (response) =>
      response.url().includes(`/api/objects/${product.id}`) &&
      response.request().method() === 'PATCH',
  );
  await page.getByRole('button', { name: /^(zapisz zmiany|save changes)$/i }).click();
  const datePatched = await datePatch;
  expect(datePatched.status()).toBe(200);

  // #1351 — the page reopens in edit mode, so the date renders as an
  // editable input holding the saved value (no read-only text display).
  await page.reload();
  await page.evaluate(() => window.scrollTo(0, document.body.scrollHeight));
  const reloadedDateLabel = page.getByText(/^(Data premiery|Release date)$/).first();
  await reloadedDateLabel.scrollIntoViewIfNeeded();
  await expect(
    reloadedDateLabel
      .locator('xpath=ancestor::div[contains(@class, "grid-cols")][1]')
      .locator('input[type="date"]'),
  ).toHaveValue('2027-03-15');

  // ---------- Part 2: variants axis combobox ----------
  await page.getByRole('tab', { name: /^(warianty|variants)$/i }).click();

  // The previous datalist required the user to clear the field; the
  // Combobox opens its option list on a single click instead.
  const axisTrigger = page.locator('button[aria-haspopup="listbox"]').first();
  await expect(axisTrigger).toBeVisible();
  // #1360 — the selector explains which attribute types qualify as an axis.
  await expect(page.getByText(/osią wariantów mogą być tylko atrybuty/i)).toBeVisible();
  await axisTrigger.click();

  // The popover shows attribute labels for select/multiselect attrs
  // only — system attrs (created_at, updated_by, ...) MUST NOT appear.
  // Each option button's accessible name combines label + code
  // (`Kolor color`, `Tagi tags`).
  const axisOption = page.getByRole('button', { name: `Kolor osi kolor_${ts}` });
  await expect(axisOption).toBeVisible();
  await expect(page.getByRole('button', { name: /created_at/ })).toHaveCount(0);

  await axisOption.click();

  // After the pick the trigger reflects the chosen attribute label
  // and the suggested option codes appear under the row.
  await expect(axisTrigger).toContainText(/Kolor osi|Axis color/);
  await expect(page.getByRole('button', { name: '+red' })).toBeVisible();
  await expect(page.getByRole('button', { name: '+green' })).toBeVisible();
  await expect(page.getByRole('button', { name: '+blue' })).toBeVisible();

  // Operator follow-up: picking one suggestion must NOT collapse the
  // rest of the pool — the remaining options should stay clickable
  // until every option is consumed. Picking +red removes ONLY +red.
  await page.getByRole('button', { name: '+red' }).click();
  await expect(page.getByRole('button', { name: '+red' })).toHaveCount(0);
  await expect(page.getByRole('button', { name: '+green' })).toBeVisible();
  await expect(page.getByRole('button', { name: '+blue' })).toBeVisible();
});
