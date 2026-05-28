import { expect, test } from '@playwright/test';

import { apiLogin } from './helpers/auth';

const AUDIT_GROUP_CODE = 'audit';

/**
 * #1076 — audit group is no longer presented as permanently locked on
 * /modeling/attribute-groups. The spec is defensive: legacy databases may
 * still carry an `is_system_group=true` audit row, fresh installs have it
 * deleted by migration {@link Version20260527100000}. The legacy row is
 * created via the API so the assertion is deterministic.
 */
test('audit attribute group is not presented as locked in /modeling/attribute-groups', async ({
  page,
}) => {
  await apiLogin(page);

  // The browser context now carries the auth cookie. Using `page.request`
  // (rather than the top-level test `request`) inherits that cookie so the
  // POST does not 401 in CI. The BE accepts `code='audit'` irrespective of
  // is_system_group flag (#1078 keeps `audit` as a user-managed value).
  const create = await page.request.post('/api/attribute_groups', {
    data: { code: AUDIT_GROUP_CODE, label: { pl: 'Audyt', en: 'Audit' } },
    headers: { accept: 'application/ld+json', 'content-type': 'application/json' },
  });
  const createdId =
    create.status() === 201
      ? ((await create.json()) as { id: string }).id
      : await locateExistingAuditId(page);

  try {
    await page.goto('/modeling/attribute-groups');
    const auditRow = page.locator(`a[href="/modeling/attribute-groups/${createdId}"]`);
    await expect(auditRow).toBeVisible();

    // Lock badge text comes from BuiltInLockBadge ("Wbudowane" / "Built-in").
    // The whole row should NOT carry it; the audit group is removable.
    await expect(auditRow.getByText(/wbudowane|built-?in/i)).toHaveCount(0);

    // Detail page renders Save (=editable) and a DangerZone delete affordance.
    await auditRow.click();
    await expect(page).toHaveURL(new RegExp(`/modeling/attribute-groups/${createdId}$`));
    await expect(page.getByRole('button', { name: /zapisz zmiany|save changes/i })).toBeVisible();
    await expect(page.getByRole('button', { name: /usuń grupę|delete/i }).first()).toBeVisible();
  } finally {
    await page.request.delete(`/api/attribute_groups/${createdId}`);
  }
});

async function locateExistingAuditId(page: import('@playwright/test').Page): Promise<string> {
  const resp = await page.request.get('/api/attribute_groups?itemsPerPage=200', {
    headers: { accept: 'application/ld+json' },
  });
  const payload = (await resp.json()) as {
    'hydra:member'?: { id: string; code: string }[];
    member?: { id: string; code: string }[];
  };
  const rows = payload.member ?? payload['hydra:member'] ?? [];
  const match = rows.find((row) => row.code === AUDIT_GROUP_CODE);
  if (!match) throw new Error('audit attribute group not present and POST returned non-201');
  return match.id;
}
