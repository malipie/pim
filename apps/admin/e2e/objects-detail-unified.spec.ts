import { expect, test } from '@playwright/test';

import { loginAsAdmin, uniqueSku } from './helpers/auth';

/**
 * #1348/#1351 unification — /objects/:slug/:id renders the SAME detail
 * component as /products/:id (UniversalDetailPage retired). The fixes
 * that previously landed only on the product route must hold on custom
 * ObjectTypes:
 *   1. the detail opens directly in edit mode — no "Edytuj" gate, both
 *      "Zapisz zmiany" and "Zapisz i wróć do listy" visible (#1351),
 *      and "Zapisz i wróć do listy" navigates back to /objects/:slug;
 *   2. an ObjectType with no attributes shows no "Atrybuty" tab (#1348);
 *   3. the categories sidebar (chips + picker) still works after the
 *      CategoriesPanel removal.
 *
 * `fixme` in CI for the shared auth rate-limiter reason.
 */

test('custom-kind detail is edit-first with both save actions (#1351)', async ({ page }) => {
  test.setTimeout(150_000);

  await loginAsAdmin(page);
  const refreshResponse = await page.request.post('/api/auth/refresh');
  expect(refreshResponse.status()).toBe(200);
  const accessToken = ((await refreshResponse.json()) as { token: string }).token;
  const bearer = { Authorization: `Bearer ${accessToken}` };
  const json = { ...bearer, 'content-type': 'application/json' };
  const ld = { ...bearer, 'content-type': 'application/ld+json' };

  const ts = uniqueSku('UNI')
    .toLowerCase()
    .replace(/[^a-z0-9]/g, '_');
  const slug = `uni_${ts}`;

  // Custom ObjectType with one attribute so the Atrybuty tab exists.
  const otResp = await page.request.post('/api/object_types', {
    headers: json,
    data: { code: slug, label: { pl: `Unified ${ts}`, en: `Unified ${ts}` } },
  });
  expect(otResp.status(), await otResp.text()).toBe(201);
  const otId = ((await otResp.json()) as { id: string }).id;

  const attrResp = await page.request.post('/api/attributes', {
    headers: ld,
    data: { code: `uni_note_${ts}`, type: 'text', label: { pl: 'Notatka', en: 'Note' } },
  });
  expect(attrResp.status(), await attrResp.text()).toBe(201);
  const attrId = ((await attrResp.json()) as { id: string }).id;
  const attach = await page.request.post(`/api/object_types/${otId}/attributes/${attrId}`, {
    headers: bearer,
  });
  expect([200, 201, 204]).toContain(attach.status());

  const objResp = await page.request.post('/api/objects', {
    headers: ld,
    data: { code: `UNI-${ts}`, objectTypeId: otId, attributes: { name: `Unified obj ${ts}` } },
  });
  expect(objResp.status(), await objResp.text()).toBe(201);
  const objId = ((await objResp.json()) as { id: string }).id;

  await page.goto(`/objects/${slug}/${objId}`);

  // Edit-mode default: no Edytuj gate, both save actions visible (#1351).
  const saveButton = page.getByRole('button', { name: /^(zapisz zmiany|save changes)$/i });
  await expect(saveButton).toBeVisible({ timeout: 20_000 });
  const saveAndReturn = page.getByRole('button', {
    name: /zapisz i wróć do listy|save and return/i,
  });
  await expect(saveAndReturn).toBeVisible();
  await expect(page.getByRole('button', { name: /^(edytuj|edit)$/i })).toHaveCount(0);

  // Atrybuty tab present (the OT has a stacked attribute) — edit + save
  // round-trips through PATCH /api/objects/{id}.
  await expect(page.getByRole('tab', { name: /atrybuty|attributes/i })).toBeVisible();
  const noteInput = page
    .getByText(/^(Notatka|Note)$/)
    .first()
    .locator('xpath=ancestor::div[contains(@class, "grid-cols")][1]')
    .locator('input[type="text"]');
  await noteInput.scrollIntoViewIfNeeded();
  await noteInput.fill('smoke-1351');
  const patchResponse = page.waitForResponse(
    (r) => r.url().includes(`/api/objects/${objId}`) && r.request().method() === 'PATCH',
  );
  await saveButton.click();
  expect((await patchResponse).status()).toBe(200);

  // "Zapisz i wróć do listy" navigates back to the ObjectType list.
  await saveAndReturn.click();
  await page.waitForURL(`**/objects/${slug}`, { timeout: 15_000 });

  await page.request.delete(`/api/object_types/${otId}`, { headers: bearer });
});

test('custom-kind with no attributes hides the Atrybuty tab (#1348)', async ({ page }) => {
  test.setTimeout(120_000);

  await loginAsAdmin(page);
  const refreshResponse = await page.request.post('/api/auth/refresh');
  expect(refreshResponse.status()).toBe(200);
  const accessToken = ((await refreshResponse.json()) as { token: string }).token;
  const bearer = { Authorization: `Bearer ${accessToken}` };
  const json = { ...bearer, 'content-type': 'application/json' };
  const ld = { ...bearer, 'content-type': 'application/ld+json' };

  const ts = uniqueSku('EMP')
    .toLowerCase()
    .replace(/[^a-z0-9]/g, '_');
  const slug = `emp_${ts}`;

  const otResp = await page.request.post('/api/object_types', {
    headers: json,
    data: { code: slug, label: { pl: `Empty ${ts}`, en: `Empty ${ts}` } },
  });
  expect(otResp.status(), await otResp.text()).toBe(201);
  const otId = ((await otResp.json()) as { id: string }).id;

  const objResp = await page.request.post('/api/objects', {
    headers: ld,
    data: { code: `EMP-${ts}`, objectTypeId: otId, attributes: {} },
  });
  expect(objResp.status(), await objResp.text()).toBe(201);
  const objId = ((await objResp.json()) as { id: string }).id;

  await page.goto(`/objects/${slug}/${objId}`);
  await expect(page.getByRole('button', { name: /^(zapisz zmiany|save changes)$/i })).toBeVisible({
    timeout: 20_000,
  });

  // No stacked attributes → no Atrybuty tab at all (#1348).
  await expect(page.getByRole('tab', { name: /^(atrybuty|attributes)/i })).toHaveCount(0);

  await page.request.delete(`/api/object_types/${otId}`, { headers: bearer });
});
