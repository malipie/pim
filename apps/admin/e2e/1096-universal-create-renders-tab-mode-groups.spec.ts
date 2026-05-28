import { expect, test } from '@playwright/test';

import { ADMIN_EMAIL, ADMIN_PASSWORD, apiLogin } from './helpers/auth';

/**
 * #1096 — `/objects/:slug/new` (UniversalCreatePage) must render
 * AttributeGroups attached to the ObjectType regardless of the junction
 * `display_mode`. The wizard defaults to `'tab'`, so every fresh custom
 * ObjectType used to show "Ten typ obiektu nie ma jeszcze atrybutów"
 * until the operator manually toggled display_mode to `'stacked'` per
 * group.
 *
 * The spec creates everything via API (mirrors the
 * 1076-audit-group-no-lock pattern), navigates to the create page, and
 * asserts the attribute fields render. Cleanup tears down the
 * ObjectType and the AttributeGroup; the attributes stay in the global
 * library (they are not exclusively owned by the group).
 */
test('UniversalCreatePage renders attribute groups attached with display_mode="tab"', async ({
  page,
}) => {
  const loginResponse = await page.request.post('/api/auth/login', {
    data: { email: ADMIN_EMAIL, password: ADMIN_PASSWORD },
    headers: { accept: 'application/json' },
  });
  expect(loginResponse.status()).toBe(200);
  const { token } = (await loginResponse.json()) as { token: string };
  const bearer = { authorization: `Bearer ${token}` };

  await apiLogin(page);

  const stamp = Date.now().toString(36);
  const otCode = `samochody_e2e_${stamp}`;
  const groupCode = `silnik_e2e_${stamp}`;
  const attrMocCode = `moc_e2e_${stamp}`;
  const attrSpalanieCode = `spalanie_e2e_${stamp}`;

  // 1. Custom ObjectType (kind defaults to 'custom' for POSTs that do
  //    not collide with built-in product/category/asset codes).
  const otResp = await page.request.post('/api/object_types', {
    data: {
      code: otCode,
      label: { pl: 'Samochody E2E', en: 'Cars E2E' },
      icon: '🚗',
      color: '#0ea5e9',
      hierarchical: false,
      hasVariants: false,
      abstract: false,
    },
    headers: { ...bearer, accept: 'application/ld+json', 'content-type': 'application/json' },
  });
  expect(otResp.status(), await otResp.text()).toBe(201);
  const objectTypeId = ((await otResp.json()) as { id: string }).id;

  // 2. AttributeGroup (default is_system_group=false, display_mode is
  //    set on the junction not on the group itself).
  const groupResp = await page.request.post('/api/attribute_groups', {
    data: { code: groupCode, label: { pl: 'Silnik', en: 'Engine' } },
    headers: { ...bearer, accept: 'application/ld+json', 'content-type': 'application/json' },
  });
  expect(groupResp.status(), await groupResp.text()).toBe(201);
  const groupId = ((await groupResp.json()) as { id: string }).id;

  try {
    // 3. Two text attributes — global library (no group ownership yet).
    for (const [code, labelPl, labelEn] of [
      [attrMocCode, 'Moc', 'Power'],
      [attrSpalanieCode, 'Spalanie', 'Consumption'],
    ] as const) {
      const attrResp = await page.request.post('/api/attributes', {
        data: { code, type: 'text', label: { pl: labelPl, en: labelEn }, required: false },
        headers: {
          ...bearer,
          accept: 'application/ld+json',
          'content-type': 'application/ld+json',
        },
      });
      expect(attrResp.status(), await attrResp.text()).toBe(201);
    }

    // 4. Attach both attributes to the group.
    const attachResp = await page.request.post(
      `/api/attribute_groups/${groupId}/attributes/bulk-attach`,
      {
        data: { attributeCodes: [attrMocCode, attrSpalanieCode] },
        headers: { ...bearer, accept: 'application/json', 'content-type': 'application/json' },
      },
    );
    expect(attachResp.status(), await attachResp.text()).toBe(200);

    // 5. Attach the group to the ObjectType. Default display_mode='tab'
    //    — exactly the case the bug report describes.
    const attachGroupResp = await page.request.post(
      `/api/object_types/${objectTypeId}/groups/${groupId}`,
      { headers: bearer },
    );
    expect(attachGroupResp.status()).toBe(204);

    // 6. Sanity check the preview endpoint actually returns the group
    //    with display_mode='tab' — pre-fix verification, also helps
    //    diagnose if the BE contract ever drifts.
    const previewResp = await page.request.post(
      `/api/object_types/${objectTypeId}/effective-attribute-groups/preview`,
      {
        data: { categoryIds: [] },
        headers: { ...bearer, accept: 'application/json', 'content-type': 'application/json' },
      },
    );
    expect(previewResp.status()).toBe(200);
    const previewBody = (await previewResp.json()) as {
      groups: { code: string; display_mode: string; attributes: { code: string }[] }[];
    };
    const silnikGroup = previewBody.groups.find((g) => g.code === groupCode);
    expect(silnikGroup, JSON.stringify(previewBody.groups)).toBeDefined();
    expect(silnikGroup?.display_mode).toBe('tab');
    expect(silnikGroup?.attributes.map((a) => a.code).sort()).toEqual(
      [attrMocCode, attrSpalanieCode].sort(),
    );

    // 7. UI assertion — the create page must render the group card and
    //    the attribute rows, NOT the empty-state hint.
    await page.goto(`/objects/${otCode}/new`);

    await expect(page.getByText(/ten typ obiektu nie ma jeszcze atrybut[óo]w/i)).toHaveCount(0);

    // Group card label — match Polish or English (Playwright suite runs
    // in either locale depending on the browser language).
    await expect(page.getByText(/silnik|engine/i).first()).toBeVisible();

    // Counter renders regardless of locale: `<filled> / <total> filled · 0%`.
    await expect(page.getByText(/0\s*\/\s*2/).first()).toBeVisible();

    // Both attribute labels render as field rows.
    await expect(page.getByText(/^moc$|^power$/i).first()).toBeVisible();
    await expect(page.getByText(/^spalanie$|^consumption$/i).first()).toBeVisible();
  } finally {
    await page.request.delete(`/api/object_types/${objectTypeId}`, { headers: bearer });
    await page.request.delete(`/api/attribute_groups/${groupId}`, { headers: bearer });
  }
});
