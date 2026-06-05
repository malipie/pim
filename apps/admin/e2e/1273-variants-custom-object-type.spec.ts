import { expect, test } from '@playwright/test';

import { ADMIN_EMAIL, ADMIN_PASSWORD, apiLogin, uniqueSku } from './helpers/auth';

/**
 * #1273 — the full variants editor (axis generator + matrix) is available on
 * the universal custom-ObjectType detail page, not just the legacy product
 * card. The poly-kind backend (`POST /api/objects/{master}/generate-variants`,
 * gated only by `ObjectType.hasVariants`) shipped in UP-04 (#1021); this spec
 * proves the FE wiring: on a custom OT with `hasVariants=true` the "Warianty"
 * tab renders `VariantsTabHost` (read-only `ObjectVariantsPanel` removed) and
 * generation hits `/api/objects/...` (not `/api/products/...`).
 *
 * `test.fixme` in CI for the shared auth-rate-limiter storageState gap (same
 * rationale as 1226-variants-scope-aware.spec.ts); runs locally.
 */
const CI_BLOCKED = 'Pending storageState rollout: spec exhausts 5/15min auth rate limiter';

test('#1273 — custom ObjectType variants tab generates via /api/objects', async ({ page }) => {
  test.fixme(!!process.env.CI, CI_BLOCKED);
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
  const otCode = `uslugi_e2e_${stamp}`;
  const groupCode = `wariant_grp_${stamp}`;
  const axisCode = `wariant_kolor_${stamp}`;

  // 1. Custom ObjectType with variants enabled (kind defaults to 'custom').
  const otResp = await page.request.post('/api/object_types', {
    data: {
      code: otCode,
      label: { pl: 'Usługi E2E', en: 'Services E2E' },
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

  // 2. AttributeGroup so the axis attribute surfaces in effective-attribute-groups
  //    (the generator combobox reads that response).
  const groupResp = await page.request.post('/api/attribute_groups', {
    data: { code: groupCode, label: { pl: 'Konfiguracja', en: 'Configuration' } },
    headers: { ...bearer, accept: 'application/ld+json', 'content-type': 'application/json' },
  });
  expect(groupResp.status(), await groupResp.text()).toBe(201);
  const groupId = ((await groupResp.json()) as { id: string }).id;

  try {
    // 3. Select attribute with two options — the axis the generator iterates.
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

    // 4. Attach attr → group → ObjectType.
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

    // 5. Master object (poly-kind POST /api/objects).
    const masterCode = uniqueSku('USL1273');
    const masterResp = await page.request.post('/api/objects', {
      headers: { ...bearer, accept: 'application/ld+json', 'content-type': 'application/ld+json' },
      data: { code: masterCode, objectTypeId, attributes: {} },
    });
    expect(masterResp.status(), await masterResp.text()).toBe(201);
    const masterId = ((await masterResp.json()) as { id: string }).id;

    // 6. UI: open the custom OT detail and the Warianty tab → full generator.
    await page.goto(`/objects/${otCode}/${masterId}`);

    const variantsTab = page.getByRole('tab', { name: /^(warianty|variants)$/i });
    await expect(variantsTab).toBeVisible();
    await variantsTab.click();

    // The full host renders the generator (read-only panel had no button).
    const generateButton = page.getByRole('button', {
      name: /^(generate variants|wygeneruj warianty|generuj warianty|generate)/i,
    });
    await expect(generateButton).toBeVisible();

    // 7. Pick the axis attribute in the first axis-row combobox, then add both
    //    option values from the suggestion chips.
    await page.getByRole('button', { name: /wybierz atrybut osi|pick axis attribute/i }).click();
    await page.getByPlaceholder(/szukaj atrybutu|search/i).fill('Kolor wariantu');
    await page.getByRole('button', { name: /kolor wariantu|variant colour/i }).click();
    await page.getByRole('button', { name: '+red' }).click();
    await page.getByRole('button', { name: '+blue' }).click();

    // 8. Generate → the POST must go to the poly-kind /api/objects route.
    const genResponse = page.waitForResponse(
      (r) =>
        /\/api\/objects\/[^/]+\/generate-variants/.test(r.url()) && r.request().method() === 'POST',
    );
    await generateButton.click();
    const gen = await genResponse;
    expect(gen.status(), await gen.text()).toBeLessThan(400);

    // 9. The generated variant lands in the list below.
    await expect(page.getByText(/1 wariant|2 wariant|variants?/i).first()).toBeVisible();
    await expect(page.getByText(new RegExp(masterCode, 'i')).first()).toBeVisible();
  } finally {
    await page.request.delete(`/api/object_types/${objectTypeId}`, { headers: bearer });
    await page.request.delete(`/api/attribute_groups/${groupId}`, { headers: bearer });
  }
});
