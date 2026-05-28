import { expect, test } from '@playwright/test';

import { ADMIN_EMAIL, ADMIN_PASSWORD, apiLogin } from './helpers/auth';

/**
 * #1096 / #1098 — `/objects/:slug/new` (UniversalCreatePage) must:
 *   1. Render AttributeGroups attached to the ObjectType regardless of
 *      the junction `display_mode` (#1096 — the wizard defaults to
 *      'tab', so every fresh custom ObjectType used to show
 *      "Ten typ obiektu nie ma jeszcze atrybutów").
 *   2. Split tab-mode groups into their own tabs (matching MODR-04 on
 *      the detail page) and refetch the preview on every mount so a
 *      newly attached group surfaces without a hard refresh (#1098).
 *
 * The spec creates two tab-mode AttributeGroups via API, asserts the
 * tab list renders both, asserts each tab reveals its own attribute
 * rows on click, and tears everything down in `finally`.
 */
test('UniversalCreatePage renders one tab per tab-mode AttributeGroup', async ({ page }) => {
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
  const groupSilnikCode = `silnik_e2e_${stamp}`;
  const groupWnetrzeCode = `wnetrze_e2e_${stamp}`;
  const attrMocCode = `moc_e2e_${stamp}`;
  const attrSpalanieCode = `spalanie_e2e_${stamp}`;
  const attrKierownicaCode = `kierownica_e2e_${stamp}`;
  const attrFoteleCode = `fotele_e2e_${stamp}`;

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

  // 2. Two AttributeGroups (default is_system_group=false; the
  //    junction display_mode defaults to 'tab' on the attach endpoint,
  //    exactly the case from the bug report).
  const silnikResp = await page.request.post('/api/attribute_groups', {
    data: { code: groupSilnikCode, label: { pl: 'Silnik', en: 'Engine' } },
    headers: { ...bearer, accept: 'application/ld+json', 'content-type': 'application/json' },
  });
  expect(silnikResp.status(), await silnikResp.text()).toBe(201);
  const silnikGroupId = ((await silnikResp.json()) as { id: string }).id;

  const wnetrzeResp = await page.request.post('/api/attribute_groups', {
    data: { code: groupWnetrzeCode, label: { pl: 'Wnętrze', en: 'Interior' } },
    headers: { ...bearer, accept: 'application/ld+json', 'content-type': 'application/json' },
  });
  expect(wnetrzeResp.status(), await wnetrzeResp.text()).toBe(201);
  const wnetrzeGroupId = ((await wnetrzeResp.json()) as { id: string }).id;

  try {
    // 3. Four text attributes — global library (no group ownership yet).
    for (const [code, labelPl, labelEn] of [
      [attrMocCode, 'Moc', 'Power'],
      [attrSpalanieCode, 'Spalanie', 'Consumption'],
      [attrKierownicaCode, 'Kierownica', 'Steering wheel'],
      [attrFoteleCode, 'Podgrzewane fotele', 'Heated seats'],
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

    // 4. Attach attributes to their groups.
    const silnikAttachResp = await page.request.post(
      `/api/attribute_groups/${silnikGroupId}/attributes/bulk-attach`,
      {
        data: { attributeCodes: [attrMocCode, attrSpalanieCode] },
        headers: { ...bearer, accept: 'application/json', 'content-type': 'application/json' },
      },
    );
    expect(silnikAttachResp.status(), await silnikAttachResp.text()).toBe(200);

    const wnetrzeAttachResp = await page.request.post(
      `/api/attribute_groups/${wnetrzeGroupId}/attributes/bulk-attach`,
      {
        data: { attributeCodes: [attrKierownicaCode, attrFoteleCode] },
        headers: { ...bearer, accept: 'application/json', 'content-type': 'application/json' },
      },
    );
    expect(wnetrzeAttachResp.status(), await wnetrzeAttachResp.text()).toBe(200);

    // 5. Attach both groups to the ObjectType with the default
    //    display_mode='tab'.
    for (const groupId of [silnikGroupId, wnetrzeGroupId]) {
      const attachResp = await page.request.post(
        `/api/object_types/${objectTypeId}/groups/${groupId}`,
        { headers: bearer },
      );
      expect(attachResp.status()).toBe(204);
    }

    // 6. Sanity check the preview endpoint returns both groups with
    //    display_mode='tab'.
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
    const previewCodes = previewBody.groups.map((g) => g.code).sort();
    expect(previewCodes).toEqual([groupSilnikCode, groupWnetrzeCode].sort());
    for (const group of previewBody.groups) expect(group.display_mode).toBe('tab');

    // 7. UI: navigate to the create page and assert the tab list +
    //    per-tab content.
    await page.goto(`/objects/${otCode}/new`);

    await expect(page.getByText(/ten typ obiektu nie ma jeszcze atrybut[óo]w/i)).toHaveCount(0);

    // Both tab buttons render in the tablist. Locale chooses the label
    // text but the regex covers PL and EN.
    const tablist = page.getByRole('tablist');
    await expect(tablist).toBeVisible();
    const silnikTab = tablist.getByRole('tab', { name: /silnik|engine/i });
    const wnetrzeTab = tablist.getByRole('tab', { name: /wn[eę]trze|interior/i });
    await expect(silnikTab).toBeVisible();
    await expect(wnetrzeTab).toBeVisible();

    // Default active tab — the first tab-mode group (BE position 0).
    await expect(silnikTab).toHaveAttribute('aria-selected', 'true');
    await expect(page.getByText(/^moc$|^power$/i).first()).toBeVisible();
    await expect(page.getByText(/^spalanie$|^consumption$/i).first()).toBeVisible();

    // Switch to the Wnętrze tab — its attribute rows take over.
    await wnetrzeTab.click();
    await expect(wnetrzeTab).toHaveAttribute('aria-selected', 'true');
    await expect(page.getByText(/^kierownica$|^steering/i).first()).toBeVisible();
    await expect(page.getByText(/podgrzewane fotele|heated seats/i).first()).toBeVisible();

    // Silnik fields are no longer in the DOM after the switch.
    await expect(page.getByText(/^moc$|^power$/i)).toHaveCount(0);
  } finally {
    await page.request.delete(`/api/object_types/${objectTypeId}`, { headers: bearer });
    await page.request.delete(`/api/attribute_groups/${silnikGroupId}`, { headers: bearer });
    await page.request.delete(`/api/attribute_groups/${wnetrzeGroupId}`, { headers: bearer });
  }
});
