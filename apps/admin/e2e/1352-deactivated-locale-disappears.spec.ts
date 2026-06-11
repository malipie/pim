import { expect, test } from '@playwright/test';

import { ADMIN_EMAIL, ADMIN_PASSWORD, apiLogin } from './helpers/auth';

/**
 * #1352 (reopen #2) — a locale deactivated in Settings → Languages must
 * disappear from every i18n form immediately. The strip used to read the
 * legacy `Tenant.enabledLocales` JSONB, which the LOC-07 lifecycle never
 * cleaned — a locale added once and later removed haunted the forms as a
 * ghost tab (operator's "IT").
 *
 * Flow: activate it_IT via /api/tenant-locales → the IT tab appears on
 * the attribute create form → deactivate it_IT → the tab is gone.
 * Self-cleaning: the locale is purged at the end (no values were written).
 *
 * `fixme` in CI for the shared auth rate-limiter reason.
 */
const CI_BLOCKED = 'Pending storageState rollout: spec exhausts 5/15min auth rate limiter';

test('deactivating a tenant locale removes its tab from i18n forms', async ({ page }) => {
  test.fixme(!!process.env.CI, CI_BLOCKED);
  test.setTimeout(120_000);

  const loginResponse = await page.request.post('/api/auth/login', {
    data: { email: ADMIN_EMAIL, password: ADMIN_PASSWORD },
    headers: { accept: 'application/json' },
  });
  expect(loginResponse.status()).toBe(200);
  const { token } = (await loginResponse.json()) as { token: string };
  const bearer = { authorization: `Bearer ${token}` };
  const json = { ...bearer, 'content-type': 'application/json' };

  await apiLogin(page);

  // Baseline: it must not be active (purge a leftover from failed runs).
  const before = await page.request.get('/api/workspaces/current', {
    headers: { ...bearer, accept: 'application/json' },
  });
  const baseline = ((await before.json()) as { enabledLocales?: string[] }).enabledLocales ?? [];
  if (baseline.includes('it')) {
    await page.request.delete('/api/tenant-locales/it_IT', { headers: bearer });
  }

  // 1. Activate it_IT through the LOC-07 lifecycle (Settings backend).
  const createResp = await page.request.post('/api/tenant-locales', {
    headers: json,
    data: { code: 'it_IT' },
  });
  expect([201, 409]).toContain(createResp.status());
  if (createResp.status() === 409) {
    const reactivate = await page.request.post('/api/tenant-locales/it_IT/reactivate', {
      headers: bearer,
    });
    expect(reactivate.status()).toBe(200);
  }

  try {
    // The workspace strip now carries `it`.
    const withIt = await page.request.get('/api/workspaces/current', {
      headers: { ...bearer, accept: 'application/json' },
    });
    expect(((await withIt.json()) as { enabledLocales: string[] }).enabledLocales).toContain('it');

    // …and the attribute create form renders an IT tab.
    await page.goto('/modeling/attributes/new');
    const tabs = page.getByRole('tablist', { name: /wybór języka|language/i }).first();
    await expect(tabs).toBeVisible({ timeout: 15_000 });
    await expect(tabs.getByRole('tab', { name: /\bit\b/i })).toBeVisible();

    // 2. Deactivate in Settings → the tab disappears from the form.
    const deleteResp = await page.request.delete('/api/tenant-locales/it_IT', {
      headers: bearer,
    });
    expect(deleteResp.status()).toBe(204);

    const without = await page.request.get('/api/workspaces/current', {
      headers: { ...bearer, accept: 'application/json' },
    });
    const strip = ((await without.json()) as { enabledLocales: string[] }).enabledLocales;
    expect(strip).not.toContain('it');

    await page.reload();
    const tabsAfter = page.getByRole('tablist', { name: /wybór języka|language/i }).first();
    await expect(tabsAfter).toBeVisible({ timeout: 15_000 });
    await expect(tabsAfter.getByRole('tab', { name: /\bit\b/i })).toHaveCount(0);
  } finally {
    // Self-clean: hard-remove the row so reruns start fresh (no values
    // were written under `it`, the purge is safe).
    await page.request.delete('/api/tenant-locales/it_IT/purge', {
      headers: { ...bearer, 'X-Confirm-Purge': 'it_IT' },
    });
  }
});
