import { expect, test } from '@playwright/test';

import { loginAsAdmin, uniqueSku } from './helpers/auth';

/**
 * #1350 (reopened) — an attribute flagged "Wymagany" in the attribute
 * editor must actually be required on the entry card:
 *   1. the row shows the red asterisk (is_required now serialized),
 *   2. saving with the field empty is blocked client-side — both
 *      "Zapisz zmiany" and "Zapisz i wróć do listy", no PATCH fires,
 *      the row shows "Pole wymagane",
 *   3. filling the field unblocks the save (PATCH 200),
 *   4. the backend rejects an explicit emptying with 422 (defence in
 *      depth — covered by RequiredAttributeValidationApiTest too).
 *
 * Runs on a CUSTOM ObjectType — proves the unified detail page (#1434)
 * enforces the rule beyond /products.
 *
 * `fixme` in CI for the shared auth rate-limiter reason.
 */
const CI_BLOCKED = 'Pending storageState rollout: spec exhausts 5/15min auth rate limiter';

test('required attribute blocks saving an empty value', async ({ page }) => {
  test.fixme(!!process.env.CI, CI_BLOCKED);
  test.setTimeout(150_000);

  await loginAsAdmin(page);
  const refreshResponse = await page.request.post('/api/auth/refresh');
  expect(refreshResponse.status()).toBe(200);
  const accessToken = ((await refreshResponse.json()) as { token: string }).token;
  const bearer = { Authorization: `Bearer ${accessToken}` };
  const json = { ...bearer, 'content-type': 'application/json' };
  const ld = { ...bearer, 'content-type': 'application/ld+json' };

  const ts = uniqueSku('REQ')
    .toLowerCase()
    .replace(/[^a-z0-9]/g, '_');
  const slug = `req_${ts}`;

  // Custom ObjectType + a REQUIRED text attribute.
  const otResp = await page.request.post('/api/object_types', {
    headers: json,
    data: { code: slug, label: { pl: `Required ${ts}`, en: `Required ${ts}` } },
  });
  expect(otResp.status(), await otResp.text()).toBe(201);
  const otId = ((await otResp.json()) as { id: string }).id;

  const attrResp = await page.request.post('/api/attributes', {
    headers: ld,
    data: {
      code: `req_note_${ts}`,
      type: 'text',
      label: { pl: 'Notatka obowiązkowa', en: 'Mandatory note' },
      required: true,
    },
  });
  expect(attrResp.status(), await attrResp.text()).toBe(201);
  const attrId = ((await attrResp.json()) as { id: string }).id;
  const attach = await page.request.post(`/api/object_types/${otId}/attributes/${attrId}`, {
    headers: bearer,
  });
  expect([200, 201, 204]).toContain(attach.status());

  // A dirty legacy object — created via API without the required value.
  const objResp = await page.request.post('/api/objects', {
    headers: ld,
    data: { code: `REQ-${ts}`, objectTypeId: otId, attributes: { name: `Dirty ${ts}` } },
  });
  expect(objResp.status(), await objResp.text()).toBe(201);
  const objId = ((await objResp.json()) as { id: string }).id;

  await page.goto(`/objects/${slug}/${objId}`);
  const saveButton = page.getByRole('button', { name: /^(zapisz zmiany|save changes)$/i });
  await expect(saveButton).toBeVisible({ timeout: 20_000 });

  // 1. The required row carries the asterisk.
  const requiredLabel = page.getByText(/^(Notatka obowiązkowa|Mandatory note)$/).first();
  await requiredLabel.scrollIntoViewIfNeeded();
  const requiredRow = requiredLabel.locator(
    'xpath=ancestor::div[contains(@class, "grid-cols")][1]',
  );
  await expect(requiredRow.getByText('*')).toBeVisible();

  // 2. Save with the field empty → blocked, no PATCH, inline error.
  let patched = false;
  page.on('request', (r) => {
    if (r.url().includes(`/api/objects/${objId}`) && r.method() === 'PATCH') patched = true;
  });
  await saveButton.click();
  await expect(page.getByText(/pole wymagane/i).first()).toBeVisible({ timeout: 10_000 });
  expect(patched).toBe(false);

  // "Zapisz i wróć do listy" is blocked the same way (stays on the page).
  await page.getByRole('button', { name: /zapisz i wróć do listy|save and return/i }).click();
  expect(patched).toBe(false);
  await expect(page).toHaveURL(new RegExp(`/objects/${slug}/${objId}`));

  // 3. Filling the field unblocks the save.
  await requiredRow.locator('input[type="text"], textarea').first().fill('uzupełnione');
  const patchResponse = page.waitForResponse(
    (r) => r.url().includes(`/api/objects/${objId}`) && r.request().method() === 'PATCH',
  );
  await saveButton.click();
  expect((await patchResponse).status()).toBe(200);

  // 4. Defence in depth: explicit emptying via API → 422.
  const emptied = await page.request.patch(`/api/objects/${objId}`, {
    headers: { ...bearer, 'content-type': 'application/merge-patch+json' },
    data: { attributes: { [`req_note_${ts}`]: '' } },
  });
  expect(emptied.status()).toBe(422);

  await page.request.delete(`/api/object_types/${otId}`, { headers: bearer });
});
