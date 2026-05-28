import { expect, test } from '@playwright/test';

import { ADMIN_EMAIL, ADMIN_PASSWORD, apiLogin } from './helpers/auth';

const RELATIONS_GROUP_CODE = 'relations';

/**
 * #1081 (MODRC-02) — relations group is no longer presented as permanently
 * locked on /modeling/attribute-groups. The spec is defensive: legacy DBs
 * may still carry an `is_system_group=true` relations row, fresh installs
 * have it deleted by migration Version20260528100000. The legacy row is
 * created via SQL-equivalent API path so the assertion is deterministic.
 *
 * Mirrors `1076-audit-group-no-lock.spec.ts` — same JWT helper pattern.
 */
test('relations attribute group is not presented as locked in /modeling/attribute-groups', async ({
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

  const create = await page.request.post('/api/attribute_groups', {
    data: { code: RELATIONS_GROUP_CODE, label: { pl: 'Powiązania', en: 'Relations' } },
    headers: { ...bearer, accept: 'application/ld+json', 'content-type': 'application/json' },
  });
  const createdId =
    create.status() === 201
      ? ((await create.json()) as { id: string }).id
      : await locateExistingRelationsId(page, bearer);

  try {
    await page.goto('/modeling/attribute-groups');
    const row = page.locator(`a[href="/modeling/attribute-groups/${createdId}"]`);
    await expect(row).toBeVisible();

    // Lock badge text comes from BuiltInLockBadge ("Wbudowane" / "Built-in").
    // The relations group must NOT carry it — it's removable.
    await expect(row.getByText(/wbudowane|built-?in/i)).toHaveCount(0);

    // Detail page exposes Save (=editable) and a DangerZone delete affordance.
    await row.click();
    await expect(page).toHaveURL(new RegExp(`/modeling/attribute-groups/${createdId}$`));
    await expect(page.getByRole('button', { name: /zapisz zmiany|save changes/i })).toBeVisible();
    await expect(page.getByRole('button', { name: /usuń grupę|delete/i }).first()).toBeVisible();
  } finally {
    await page.request.delete(`/api/attribute_groups/${createdId}`, { headers: bearer });
  }
});

async function locateExistingRelationsId(
  page: import('@playwright/test').Page,
  bearer: { authorization: string },
): Promise<string> {
  const resp = await page.request.get('/api/attribute_groups?itemsPerPage=200', {
    headers: { ...bearer, accept: 'application/ld+json' },
  });
  const payload = (await resp.json()) as {
    'hydra:member'?: { id: string; code: string }[];
    member?: { id: string; code: string }[];
  };
  const rows = payload.member ?? payload['hydra:member'] ?? [];
  const match = rows.find((row) => row.code === RELATIONS_GROUP_CODE);
  if (!match) throw new Error('relations attribute group not present and POST returned non-201');
  return match.id;
}
