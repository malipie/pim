import { expect, test } from '@playwright/test';

import { ADMIN_EMAIL, ADMIN_PASSWORD, apiLogin } from './helpers/auth';

/**
 * #1102 — relation attributes on UniversalCreatePage must render the
 * RelationCreateField picker (Combobox / MultiSelect), and the create
 * flow has to persist picks through a second-step PUT
 * `/api/objects/{newId}/relations/{attributeCode}` once the main POST
 * lands.
 *
 * Spec creates:
 *   - target ObjectType (kind=custom, slug salon-target-<ts>),
 *   - source ObjectType (kind=custom, slug source-ot-<ts>) attached with
 *     a relation Attribute pointing at the target type, cardinality=many,
 *   - one target object to pick.
 * Then drives the UI: open `/objects/source-ot-<ts>/new`, pick the
 * target via MultiSelect, save, and assert the new object exists with
 * the relation persisted (`/api/objects/{newId}/relations` reflects it).
 */
test('UniversalCreatePage relation attribute renders picker and persists targets via PUT', async ({
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
  const targetOtCode = `target_ot_${stamp}`;
  const sourceOtCode = `source_ot_${stamp}`;
  const groupCode = `wybor_${stamp}`;
  const attrCode = `picked_${stamp}`;
  const targetCode = `T_${stamp}`;

  // 1. Target OT.
  const targetOtResp = await page.request.post('/api/object_types', {
    data: {
      code: targetOtCode,
      label: { pl: 'Target', en: 'Target' },
      icon: '📦',
      color: '#10b981',
      hierarchical: false,
      hasVariants: false,
      abstract: false,
    },
    headers: { ...bearer, accept: 'application/ld+json', 'content-type': 'application/json' },
  });
  expect(targetOtResp.status(), await targetOtResp.text()).toBe(201);
  const targetOtId = ((await targetOtResp.json()) as { id: string }).id;

  // 2. Source OT.
  const sourceOtResp = await page.request.post('/api/object_types', {
    data: {
      code: sourceOtCode,
      label: { pl: 'Source', en: 'Source' },
      icon: '🔗',
      color: '#0ea5e9',
      hierarchical: false,
      hasVariants: false,
      abstract: false,
    },
    headers: { ...bearer, accept: 'application/ld+json', 'content-type': 'application/json' },
  });
  expect(sourceOtResp.status(), await sourceOtResp.text()).toBe(201);
  const sourceOtId = ((await sourceOtResp.json()) as { id: string }).id;

  // 3. AttributeGroup + relation Attribute.
  const groupResp = await page.request.post('/api/attribute_groups', {
    data: { code: groupCode, label: { pl: 'Wybór', en: 'Pick' } },
    headers: { ...bearer, accept: 'application/ld+json', 'content-type': 'application/json' },
  });
  expect(groupResp.status(), await groupResp.text()).toBe(201);
  const groupId = ((await groupResp.json()) as { id: string }).id;

  try {
    const attrResp = await page.request.post('/api/attributes', {
      data: {
        code: attrCode,
        type: 'relation',
        label: { pl: 'Picked', en: 'Picked' },
        required: false,
        relationTargetObjectTypeIds: [targetOtId],
        relationCardinality: 'many',
      },
      headers: {
        ...bearer,
        accept: 'application/ld+json',
        'content-type': 'application/ld+json',
      },
    });
    expect(attrResp.status(), await attrResp.text()).toBe(201);

    const attachAttrResp = await page.request.post(
      `/api/attribute_groups/${groupId}/attributes/bulk-attach`,
      {
        data: { attributeCodes: [attrCode] },
        headers: { ...bearer, accept: 'application/json', 'content-type': 'application/json' },
      },
    );
    expect(attachAttrResp.status()).toBe(200);

    const attachGroupResp = await page.request.post(
      `/api/object_types/${sourceOtId}/groups/${groupId}`,
      { headers: bearer },
    );
    expect(attachGroupResp.status()).toBe(204);

    // ObjectRelationController::list filters relation attributes by the
    // `object_type_attributes` junction (direct OT-attribute attach, NOT
    // via attribute groups). Mirror what the modeling wizard does
    // when it attaches relation attributes — bulk-attach so the GET
    // /relations endpoint surfaces the picked targets after PUT.
    // `?code=` is not an actual AP4 filter on /api/attributes (returns
    // the unfiltered page), so paginate and find the row by exact code
    // client-side.
    const attrLookup = await page.request.get('/api/attributes?itemsPerPage=500', {
      headers: { ...bearer, accept: 'application/ld+json' },
    });
    expect(attrLookup.status()).toBe(200);
    const attrLookupBody = (await attrLookup.json()) as {
      member?: { id: string; code: string }[];
      'hydra:member'?: { id: string; code: string }[];
    };
    const attrRows = attrLookupBody.member ?? attrLookupBody['hydra:member'] ?? [];
    const attrRow = attrRows.find((row) => row.code === attrCode);
    expect(attrRow, `attribute "${attrCode}" not in /api/attributes page`).toBeDefined();
    const directAttachResp = await page.request.post(
      `/api/object_types/${sourceOtId}/attributes/bulk-attach`,
      {
        data: { attributeIds: [attrRow?.id ?? ''] },
        headers: { ...bearer, accept: 'application/json', 'content-type': 'application/json' },
      },
    );
    // Endpoint returns 200 on success per AttachObjectTypeAttributeController,
    // but 204 No Content can land here when the attribute was already
    // attached to the OT (idempotent path). Accept both.
    expect([200, 204]).toContain(directAttachResp.status());

    // Sanity: object_type_attributes junction now carries the relation
    // attribute. If this list is empty the test is misconfigured before
    // the UI portion even starts.
    const attachedResp = await page.request.get(
      `/api/object_types/${sourceOtId}/attached_attributes`,
      { headers: { ...bearer, accept: 'application/json' } },
    );
    expect(attachedResp.status()).toBe(200);
    const attachedRaw = (await attachedResp.json()) as unknown;
    const attachedList = Array.isArray(attachedRaw)
      ? (attachedRaw as Array<{ code: string }>)
      : ((attachedRaw as { member?: Array<{ code: string }> }).member ?? []);
    const attachedCodes = attachedList.map((row) => row.code);
    expect(attachedCodes, JSON.stringify(attachedRaw)).toContain(attrCode);

    // 4. One target object so the picker has something to show.
    const targetObjResp = await page.request.post('/api/objects', {
      data: { code: targetCode, objectTypeId: targetOtId },
      headers: {
        ...bearer,
        accept: 'application/ld+json',
        'content-type': 'application/ld+json',
      },
    });
    expect(targetObjResp.status(), await targetObjResp.text()).toBe(201);
    const targetObjectId = ((await targetObjResp.json()) as { id: string }).id;

    try {
      // 5. Open the create page; pick the target via MultiSelect.
      await page.goto(`/objects/${sourceOtCode}/new`);

      // The relation attribute lives in a tab-mode group. When the OT
      // has additional stacked attributes (e.g. system created_at) the
      // header shows a tablist with Atrybuty + Pick; otherwise the
      // single Pick group renders inline without a tablist (the
      // `visibleTabs.length > 1` gate in UniversalCreatePage). Handle
      // both layouts.
      const pickTab = page.getByRole('tab', { name: /^pick$/i });
      if (await pickTab.isVisible().catch(() => false)) {
        await pickTab.click();
        await expect(pickTab).toHaveAttribute('aria-selected', 'true');
      }

      // RelationCreateField renders a MultiSelect — its trigger is the
      // only element on the page that exposes `aria-haspopup="listbox"`.
      const multiSelectTrigger = page.locator('[aria-haspopup="listbox"]').first();
      await expect(multiSelectTrigger).toBeVisible();
      await multiSelectTrigger.click();

      // Wait for the popover to render — its options are the only
      // <button> elements whose accessible name starts with `T_`.
      const targetOption = page.getByRole('button', { name: targetCode });
      await expect(targetOption).toBeVisible();
      await targetOption.click();

      // Trigger should now show a chip with the target code; assert
      // before moving on so a silent click failure is caught early.
      await expect(multiSelectTrigger.getByText(targetCode)).toBeVisible();

      // Click the page background to close the popover; Escape would
      // race the popover close with the next focus and occasionally
      // swallow the next keystroke.
      await page.locator('body').click({ position: { x: 10, y: 10 } });

      const newCarCode = `S_${stamp}`;
      await page.getByPlaceholder(/kod \(np\. car-001\)/i).fill(newCarCode);

      // Capture the relation PUT so we can fail loud if it never fires
      // and inspect the body if the PUT lands but the relation does not
      // persist downstream.
      const relationsPutPromise = page.waitForRequest(
        (request) => request.url().includes('/relations/') && request.method() === 'PUT',
        { timeout: 10_000 },
      );
      await page.getByRole('button', { name: /^utw[oó]rz$|^create$/i }).click();
      const relationsPutRequest = await relationsPutPromise;
      const putBody = relationsPutRequest.postDataJSON() as unknown;
      const putUrl = relationsPutRequest.url();
      const relationsPutResponse = await relationsPutRequest.response();
      const putStatus = relationsPutResponse?.status() ?? 0;
      expect(putStatus, `PUT ${putUrl}\nrequest body: ${JSON.stringify(putBody)}`).toBe(204);

      // Wait for the redirect to detail (.../<uuid>) — that's the proof
      // the POST + PUT both completed.
      await page.waitForURL(new RegExp(`/objects/${sourceOtCode}/[0-9a-f-]{36}`));
      const url = page.url();
      const newObjectId = url.split('/').at(-1) ?? '';
      expect(newObjectId).toMatch(/^[0-9a-f-]{36}$/);

      // 6. BE proof: the relation is persisted in `object_relations`.
      // Use the browser session (same cookie auth as the FE write
      // path) so any tenant-context drift between BE write + read does
      // not mask a successful PUT.
      // Use the same Bearer the setup steps used. Browser cookie auth
      // does not persist the in-memory JWT across a page navigation, so
      // a cookie-only fetch lands 401.
      const relationsResp = await page.request.get(`/api/objects/${newObjectId}/relations`, {
        headers: { ...bearer, accept: 'application/json' },
      });
      const relationsText = await relationsResp.text();
      expect(relationsResp.status(), `body: ${relationsText.slice(0, 400)}`).toBe(200);
      const typed = JSON.parse(relationsText) as {
        relationAttributes: {
          attribute: { code: string };
          relations: { targetObjectId: string }[];
        }[];
      };
      const link = typed.relationAttributes.find((a) => a.attribute.code === attrCode);
      expect(link, JSON.stringify(typed)).toBeDefined();
      expect(link?.relations.map((r) => r.targetObjectId)).toContain(targetObjectId);

      // Cleanup the freshly created source object.
      await page.request.delete(`/api/objects/${newObjectId}`, { headers: bearer });
    } finally {
      await page.request.delete(`/api/objects/${targetObjectId}`, { headers: bearer });
    }
  } finally {
    await page.request.delete(`/api/object_types/${sourceOtId}`, { headers: bearer });
    await page.request.delete(`/api/object_types/${targetOtId}`, { headers: bearer });
    await page.request.delete(`/api/attribute_groups/${groupId}`, { headers: bearer });
  }
});
