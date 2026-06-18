import { expect, test } from '@playwright/test';

import { ADMIN_EMAIL, ADMIN_PASSWORD, apiLogin } from './helpers/auth';

/**
 * #1349 — drag-and-drop reordering of attribute groups on the ObjectType
 * detail page (/modeling/object-types/{id}). The persisted `position`
 * drives the left-to-right tab order on the object detail page.
 *
 * The spec attaches two fresh AttributeGroups to the built-in Product
 * ObjectType, then keyboard-reorders the first one down via the dnd-kit
 * handle (Space = pick up, ArrowDown = move, Space = drop) and asserts a
 * `PATCH /groups/{id}` carrying `position` fires. Self-contained (creates
 * + cleans up its own groups) so it does not depend on the ambient seed.
 *
 * Marked `fixme` in CI for the shared `storageState` rate-limiter reason
 * (5 logins / 15 min) — dnd-kit keyboard sequences are also timing
 * sensitive on the shared runner. Local cold-cache runs pass; the BE
 * persistence is covered by ObjectTypeAttributeGroupDisplayModePatchApiTest.
 */

test('reordering attribute groups persists position via PATCH', async ({ page }) => {
  test.setTimeout(180_000);

  const loginResponse = await page.request.post('/api/auth/login', {
    data: { email: ADMIN_EMAIL, password: ADMIN_PASSWORD },
    headers: { accept: 'application/json' },
  });
  expect(loginResponse.status()).toBe(200);
  const { token } = (await loginResponse.json()) as { token: string };
  const bearer = { authorization: `Bearer ${token}` };

  await apiLogin(page);

  const objectTypesResponse = await page.request.get('/api/object_types?itemsPerPage=200', {
    headers: { ...bearer, accept: 'application/ld+json' },
  });
  const objectTypesBody = (await objectTypesResponse.json()) as {
    member?: Array<{ id: string; kind: string; codeImmutable: boolean }>;
  };
  const productType = (objectTypesBody.member ?? []).find(
    (row) => row.kind === 'product' && row.codeImmutable,
  );
  if (productType === undefined) {
    throw new Error('Built-in product ObjectType not seeded for demo tenant.');
  }
  const otId = productType.id;

  const stamp = Date.now().toString(36).toLowerCase();
  const codeA = `zz_reorder_a_${stamp}`;
  const codeB = `zz_reorder_b_${stamp}`;
  const createdGroupIds: string[] = [];

  for (const [code, label] of [
    [codeA, 'Reorder A'],
    [codeB, 'Reorder B'],
  ]) {
    const created = await page.request.post('/api/attribute_groups', {
      headers: { ...bearer, 'content-type': 'application/ld+json' },
      data: { code, label: { pl: label, en: label } },
    });
    expect(created.status()).toBe(201);
    const body = (await created.json()) as { id: string };
    createdGroupIds.push(body.id);
    const attached = await page.request.post(`/api/object_types/${otId}/groups/${body.id}`, {
      headers: bearer,
    });
    expect(attached.status()).toBe(204);
  }

  await page.goto(`/modeling/object-types/${otId}`);
  // Wait until the attached groups list resolves.
  await page.waitForResponse(
    (r) => r.url().includes(`/api/object_types/${otId}/attached_groups`) && r.ok(),
    { timeout: 30_000 },
  );

  const handles = page.getByRole('button', {
    name: /przeciągnij, aby zmienić kolejność|drag/i,
  });
  await expect(handles.first()).toBeVisible({ timeout: 15_000 });

  // Keyboard reorder: focus first handle, pick up, move down, drop.
  const patchPromise = page.waitForResponse(
    (r) =>
      /\/api\/object_types\/[0-9a-f-]+\/groups\/[0-9a-f-]+$/.test(r.url()) &&
      r.request().method() === 'PATCH',
    { timeout: 15_000 },
  );
  // dnd-kit KeyboardSensor needs a measuring tick between each step,
  // otherwise the move is dropped before the collision is computed.
  await handles.first().focus();
  await page.keyboard.press('Space');
  await page.waitForTimeout(250);
  await page.keyboard.press('ArrowDown');
  await page.waitForTimeout(250);
  await page.keyboard.press('Space');
  await page.waitForTimeout(150);

  const patch = await patchPromise;
  const sentBody = patch.request().postDataJSON() as { position?: number };
  expect(typeof sentBody.position).toBe('number');
  expect(patch.status()).toBe(204);

  // Cleanup — detach + delete the throwaway groups.
  for (const groupId of createdGroupIds) {
    await page.request.delete(`/api/object_types/${otId}/groups/${groupId}`, { headers: bearer });
    await page.request.delete(`/api/attribute_groups/${groupId}`, { headers: bearer });
  }
});
