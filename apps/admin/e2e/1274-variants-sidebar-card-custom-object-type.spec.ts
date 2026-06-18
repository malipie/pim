import { expect, test } from '@playwright/test';

import { ADMIN_EMAIL, ADMIN_PASSWORD, apiLogin, uniqueSku } from './helpers/auth';

/**
 * #1274 — the sidebar "Warianty" card (variant count + quick-nav) is present
 * on the universal custom-ObjectType detail page, mirroring the product card.
 * Depends on the #1273 basePath parametrization; here the card reads the
 * master's children through the poly-kind `/api/objects?parent_id=` route.
 */

test('#1274 — custom ObjectType sidebar lists variants + quick-nav', async ({ page }) => {
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
  const otCode = `uslugi_sb_${stamp}`;
  const groupCode = `wariant_sb_grp_${stamp}`;
  const axisCode = `wariant_sb_kolor_${stamp}`;

  const otResp = await page.request.post('/api/object_types', {
    data: {
      code: otCode,
      label: { pl: 'Usługi sidebar', en: 'Services sidebar' },
      icon: '🧩',
      color: '#0ea5e9',
      hierarchical: false,
      hasVariants: true,
      abstract: false,
    },
    headers: { ...bearer, accept: 'application/ld+json', 'content-type': 'application/json' },
  });
  expect(otResp.status(), await otResp.text()).toBe(201);
  const objectTypeId = ((await otResp.json()) as { id: string }).id;

  const groupResp = await page.request.post('/api/attribute_groups', {
    data: { code: groupCode, label: { pl: 'Konfiguracja', en: 'Configuration' } },
    headers: { ...bearer, accept: 'application/ld+json', 'content-type': 'application/json' },
  });
  expect(groupResp.status(), await groupResp.text()).toBe(201);
  const groupId = ((await groupResp.json()) as { id: string }).id;

  try {
    const attrResp = await page.request.post('/api/attributes', {
      data: {
        code: axisCode,
        type: 'select',
        label: { pl: 'Kolor wariantu', en: 'Variant colour' },
        required: false,
      },
      headers: { ...bearer, accept: 'application/ld+json', 'content-type': 'application/ld+json' },
    });
    expect(attrResp.status(), await attrResp.text()).toBe(201);

    for (const [code, pl, en] of [
      ['red', 'Czerwony', 'Red'],
      ['blue', 'Niebieski', 'Blue'],
    ] as const) {
      const optResp = await page.request.post(`/api/attributes/${axisCode}/options`, {
        data: { code, label: { pl, en } },
        headers: { ...bearer, accept: 'application/json', 'content-type': 'application/json' },
      });
      expect([200, 201]).toContain(optResp.status());
    }

    const attachAttr = await page.request.post(
      `/api/attribute_groups/${groupId}/attributes/bulk-attach`,
      {
        data: { attributeCodes: [axisCode] },
        headers: { ...bearer, accept: 'application/json', 'content-type': 'application/json' },
      },
    );
    expect(attachAttr.status(), await attachAttr.text()).toBe(200);

    const attachGroup = await page.request.post(
      `/api/object_types/${objectTypeId}/groups/${groupId}`,
      { headers: bearer },
    );
    expect(attachGroup.status()).toBe(204);

    const masterCode = uniqueSku('USL1274');
    const masterResp = await page.request.post('/api/objects', {
      headers: { ...bearer, accept: 'application/ld+json', 'content-type': 'application/ld+json' },
      data: { code: masterCode, objectTypeId, attributes: {} },
    });
    expect(masterResp.status(), await masterResp.text()).toBe(201);
    const masterId = ((await masterResp.json()) as { id: string }).id;

    // Generate two variants via the poly-kind endpoint (UI generation is
    // covered by #1273; this spec asserts the sidebar surfacing).
    const genResp = await page.request.post(`/api/objects/${masterId}/generate-variants`, {
      headers: { ...bearer, 'content-type': 'application/json' },
      data: { axes: { [axisCode]: ['red', 'blue'] } },
    });
    expect(genResp.status(), await genResp.text()).toBeLessThan(400);

    await page.goto(`/objects/${otCode}/${masterId}`);

    // Sidebar "Warianty" card renders with the variant count.
    const sidebarCard = page
      .getByRole('complementary')
      .filter({ hasText: /warianty|variants/i })
      .first();
    await expect(sidebarCard).toBeVisible();
    // Two generated variants → the count badge shows 2.
    await expect(sidebarCard.getByText('2', { exact: true })).toBeVisible();

    // Quick-nav: clicking a variant row routes to its detail page.
    const variantButton = sidebarCard
      .getByRole('button')
      .filter({ hasText: /red|blue/i })
      .first();
    await variantButton.click();
    await expect(page).toHaveURL(new RegExp(`/objects/${otCode}/[0-9a-f-]+`));
    // Landed on a different object than the master.
    expect(page.url()).not.toContain(masterId);
  } finally {
    await page.request.delete(`/api/object_types/${objectTypeId}`, { headers: bearer });
    await page.request.delete(`/api/attribute_groups/${groupId}`, { headers: bearer });
  }
});
