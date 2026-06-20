import { expect, test } from '@playwright/test';

import { ADMIN_EMAIL, ADMIN_PASSWORD, apiLogin } from './helpers/auth';

/**
 * #1673 — an attribute required only WITHIN ITS GROUP (the
 * `is_required_in_group` junction flag, not the global `is_required` one) must
 * block saving an empty value on the entry card, exactly like a globally
 * required attribute. Before #1673 the red asterisk showed but the save guard
 * (collectRequiredViolations) ignored group-level requiredness, so a dirty
 * imported entry saved with the field still empty.
 *
 * Mirrors the #1350 flow, but the requiredness comes from the
 * AttributeGroupAttribute junction:
 *   POST   /api/attribute_groups/{g}/attributes/bulk-attach   (attach)
 *   PATCH  /api/attribute_groups/{g}/attributes/{a}           (isRequiredInGroup=true)
 */
test('#1673 — a group-required attribute blocks saving an empty value', async ({ page }) => {
  test.setTimeout(180_000);

  const loginResponse = await page.request.post('/api/auth/login', {
    data: { email: ADMIN_EMAIL, password: ADMIN_PASSWORD },
    headers: { accept: 'application/json' },
  });
  expect(loginResponse.status()).toBe(200);
  const { token } = (await loginResponse.json()) as { token: string };
  const bearer = { authorization: `Bearer ${token}` };
  await apiLogin(page);

  const stamp = Date.now().toString(36);
  const otCode = `req_grp_${stamp}`;
  const groupCode = `req_grp_g_${stamp}`;
  const attrCode = `req_grp_note_${stamp}`;

  // 1. Custom ObjectType.
  const otResp = await page.request.post('/api/object_types', {
    data: {
      code: otCode,
      label: { pl: `Wymagane w grupie ${stamp}`, en: `Group-required ${stamp}` },
    },
    headers: { ...bearer, accept: 'application/ld+json', 'content-type': 'application/json' },
  });
  expect(otResp.status(), await otResp.text()).toBe(201);
  const objectTypeId = ((await otResp.json()) as { id: string }).id;

  // 2. AttributeGroup.
  const groupResp = await page.request.post('/api/attribute_groups', {
    data: { code: groupCode, label: { pl: 'Opis grupy', en: 'Group description' } },
    headers: { ...bearer, accept: 'application/ld+json', 'content-type': 'application/json' },
  });
  expect(groupResp.status(), await groupResp.text()).toBe(201);
  const groupId = ((await groupResp.json()) as { id: string }).id;

  try {
    // 3. Attribute that is NOT globally required.
    const attrResp = await page.request.post('/api/attributes', {
      data: {
        code: attrCode,
        type: 'text',
        label: { pl: 'Notatka grupowa', en: 'Group note' },
        required: false,
      },
      headers: { ...bearer, accept: 'application/ld+json', 'content-type': 'application/ld+json' },
    });
    expect(attrResp.status(), await attrResp.text()).toBe(201);
    const attributeId = ((await attrResp.json()) as { id: string }).id;

    // 4. Attach attr → group, then flag it required WITHIN THE GROUP.
    const attach = await page.request.post(
      `/api/attribute_groups/${groupId}/attributes/bulk-attach`,
      {
        data: { attributeCodes: [attrCode] },
        headers: { ...bearer, accept: 'application/json', 'content-type': 'application/json' },
      },
    );
    expect([200, 204], await attach.text()).toContain(attach.status());

    const flagRequired = await page.request.patch(
      `/api/attribute_groups/${groupId}/attributes/${attributeId}`,
      {
        data: { isRequiredInGroup: true },
        headers: { ...bearer, 'content-type': 'application/json' },
      },
    );
    expect([200, 204], await flagRequired.text()).toContain(flagRequired.status());

    // 5. Attach group → ObjectType so it surfaces in effective-attribute-groups.
    const attachGroup = await page.request.post(
      `/api/object_types/${objectTypeId}/groups/${groupId}`,
      { headers: bearer },
    );
    expect(attachGroup.status()).toBe(204);

    // 6. A dirty entry — created without the (group-)required value, like an import.
    const objResp = await page.request.post('/api/objects', {
      headers: { ...bearer, accept: 'application/ld+json', 'content-type': 'application/ld+json' },
      data: { code: `GRP-${stamp}`, objectTypeId, attributes: {} },
    });
    expect(objResp.status(), await objResp.text()).toBe(201);
    const objId = ((await objResp.json()) as { id: string }).id;

    // 7. Open the unified detail page.
    await page.goto(`/objects/${otCode}/${objId}`);
    const saveButton = page.getByRole('button', { name: /^(zapisz zmiany|save changes)$/i });
    await expect(saveButton).toBeVisible({ timeout: 20_000 });

    // The group-required row carries the asterisk.
    const requiredLabel = page.getByText(/^(Notatka grupowa|Group note)$/).first();
    await requiredLabel.scrollIntoViewIfNeeded();
    const requiredRow = requiredLabel.locator(
      'xpath=ancestor::div[contains(@class, "grid-cols")][1]',
    );
    await expect(requiredRow.getByText('*')).toBeVisible();

    // Save with the field empty → blocked client-side, no PATCH, inline error.
    let patched = false;
    page.on('request', (r) => {
      if (r.url().includes(`/api/objects/${objId}`) && r.method() === 'PATCH') patched = true;
    });
    await saveButton.click();
    await expect(page.getByText(/pole wymagane/i).first()).toBeVisible({ timeout: 10_000 });
    expect(patched).toBe(false);

    // Filling the field unblocks the save (PATCH 200).
    await requiredRow.locator('input[type="text"], textarea').first().fill('uzupełnione');
    const patchResponse = page.waitForResponse(
      (r) => r.url().includes(`/api/objects/${objId}`) && r.request().method() === 'PATCH',
    );
    await saveButton.click();
    expect((await patchResponse).status()).toBe(200);
  } finally {
    await page.request.delete(`/api/attribute_groups/${groupId}`, { headers: bearer });
    await page.request.delete(`/api/object_types/${objectTypeId}`, { headers: bearer });
  }
});
