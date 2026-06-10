import { expect, test } from '@playwright/test';

import { ADMIN_EMAIL, ADMIN_PASSWORD, apiLogin } from './helpers/auth';

/**
 * #1352 — attribute name + value-label forms were hardcoded to PL/EN even
 * when the tenant enabled more locales (e.g. DE). They must render an input
 * per configured locale, and the per-locale name must take effect.
 *
 * This spec enables DE on the workspace, creates an attribute carrying a DE
 * name, and asserts:
 *   1. the edit form's name field renders a DE locale tab (driven by the
 *      workspace's enabled locales, not a hardcoded pl/en pair),
 *   2. selecting the DE tab surfaces the DE translation,
 *   3. the backend accepts + round-trips the DE label key.
 *
 * Marked `fixme` in CI for the shared `storageState` rate-limiter reason
 * (5 logins / 15 min).
 */
const CI_BLOCKED = 'Pending storageState rollout: spec exhausts 5/15min auth rate limiter';

test('attribute name form honors all configured locales (PL/EN/DE)', async ({ page }) => {
  test.fixme(!!process.env.CI, CI_BLOCKED);
  test.setTimeout(120_000);

  const loginResponse = await page.request.post('/api/auth/login', {
    data: { email: ADMIN_EMAIL, password: ADMIN_PASSWORD },
    headers: { accept: 'application/json' },
  });
  expect(loginResponse.status()).toBe(200);
  const { token } = (await loginResponse.json()) as { token: string };
  const bearer = { authorization: `Bearer ${token}` };

  await apiLogin(page);

  // Ensure DE is enabled on the workspace (idempotent — tolerate the 4xx
  // the endpoint returns when the locale is already enabled).
  const wsBefore = await page.request.get('/api/workspaces/current', {
    headers: { ...bearer, accept: 'application/json' },
  });
  const enabled = ((await wsBefore.json()) as { enabledLocales?: string[] }).enabledLocales ?? [];
  if (!enabled.includes('de')) {
    await page.request.post('/api/workspaces/current/locales', {
      headers: { ...bearer, 'content-type': 'application/json' },
      data: { locale: 'de' },
    });
  }

  // Create an attribute with a full PL/EN/DE label — proves the backend
  // accepts an arbitrary-locale JSONB map (no pl/en restriction).
  const code = `zz_locale_${Date.now().toString(36).toLowerCase()}`;
  const created = await page.request.post('/api/attributes', {
    headers: { ...bearer, 'content-type': 'application/ld+json' },
    data: {
      code,
      type: 'text',
      label: { pl: 'Materiał', en: 'Material', de: 'Werkstoff' },
    },
  });
  expect(created.status()).toBe(201);
  const attribute = (await created.json()) as { id: string };

  // Backend round-trips the DE key.
  const reread = await page.request.get(`/api/attributes/${attribute.id}`, {
    headers: { ...bearer, accept: 'application/json' },
  });
  expect(reread.status()).toBe(200);
  expect((await reread.json()).label.de).toBe('Werkstoff');

  // Edit page: the name field renders a DE tab and shows the DE value.
  await page.goto(`/modeling/attributes/${attribute.id}`);
  const localeTabs = page.getByRole('tablist', { name: /wybór języka|language/i }).first();
  await expect(localeTabs).toBeVisible({ timeout: 15_000 });
  await expect(localeTabs.getByRole('tab', { name: /pl/i })).toBeVisible();
  await expect(localeTabs.getByRole('tab', { name: /en/i })).toBeVisible();
  const deTab = localeTabs.getByRole('tab', { name: /de/i });
  await expect(deTab).toBeVisible();

  await deTab.click();
  // Playwright has no getByDisplayValue — target the LocaleTabsField input
  // via its aria-label and assert the bound value.
  await expect(page.getByLabel(/wartość dla de/i).first()).toHaveValue('Werkstoff');

  await page.request.delete(`/api/attributes/${attribute.id}`, { headers: bearer });
});

test('attribute create form renders a locale tab per configured locale', async ({ page }) => {
  test.fixme(!!process.env.CI, CI_BLOCKED);
  test.setTimeout(90_000);

  const loginResponse = await page.request.post('/api/auth/login', {
    data: { email: ADMIN_EMAIL, password: ADMIN_PASSWORD },
    headers: { accept: 'application/json' },
  });
  expect(loginResponse.status()).toBe(200);
  const { token } = (await loginResponse.json()) as { token: string };
  const bearer = { authorization: `Bearer ${token}` };

  await apiLogin(page);

  const wsBefore = await page.request.get('/api/workspaces/current', {
    headers: { ...bearer, accept: 'application/json' },
  });
  const enabled = ((await wsBefore.json()) as { enabledLocales?: string[] }).enabledLocales ?? [];
  if (!enabled.includes('de')) {
    await page.request.post('/api/workspaces/current/locales', {
      headers: { ...bearer, 'content-type': 'application/json' },
      data: { locale: 'de' },
    });
  }

  await page.goto('/modeling/attributes/new');
  const localeTabs = page.getByRole('tablist', { name: /wybór języka|language/i }).first();
  await expect(localeTabs).toBeVisible({ timeout: 15_000 });
  await expect(localeTabs.getByRole('tab', { name: /pl/i })).toBeVisible();
  await expect(localeTabs.getByRole('tab', { name: /en/i })).toBeVisible();
  await expect(localeTabs.getByRole('tab', { name: /de/i })).toBeVisible();
});

test('attribute group forms honor all configured locales (PL/EN/DE)', async ({ page }) => {
  test.fixme(!!process.env.CI, CI_BLOCKED);
  test.setTimeout(120_000);

  const loginResponse = await page.request.post('/api/auth/login', {
    data: { email: ADMIN_EMAIL, password: ADMIN_PASSWORD },
    headers: { accept: 'application/json' },
  });
  expect(loginResponse.status()).toBe(200);
  const { token } = (await loginResponse.json()) as { token: string };
  const bearer = { authorization: `Bearer ${token}` };

  await apiLogin(page);

  const wsBefore = await page.request.get('/api/workspaces/current', {
    headers: { ...bearer, accept: 'application/json' },
  });
  const enabled = ((await wsBefore.json()) as { enabledLocales?: string[] }).enabledLocales ?? [];
  if (!enabled.includes('de')) {
    await page.request.post('/api/workspaces/current/locales', {
      headers: { ...bearer, 'content-type': 'application/json' },
      data: { locale: 'de' },
    });
  }

  // Create form renders a tab per configured locale — no hardcoded pl/en.
  await page.goto('/modeling/attribute-groups/new');
  const createTabs = page.getByRole('tablist', { name: /wybór języka|language/i }).first();
  await expect(createTabs).toBeVisible({ timeout: 15_000 });
  await expect(createTabs.getByRole('tab', { name: /pl/i })).toBeVisible();
  await expect(createTabs.getByRole('tab', { name: /en/i })).toBeVisible();
  await expect(createTabs.getByRole('tab', { name: /de/i })).toBeVisible();

  // Backend accepts + round-trips a DE label on the group; the edit form
  // surfaces the DE tab with the stored translation.
  const code = `zz_grp_${Date.now().toString(36).toLowerCase()}`;
  const created = await page.request.post('/api/attribute_groups', {
    headers: { ...bearer, 'content-type': 'application/ld+json' },
    data: {
      code,
      label: { pl: 'Wymiary', en: 'Dimensions', de: 'Abmessungen' },
      position: 0,
    },
  });
  expect(created.status(), await created.text()).toBe(201);
  const group = (await created.json()) as { id: string };

  await page.goto(`/modeling/attribute-groups/${group.id}`);
  const editTabs = page.getByRole('tablist', { name: /wybór języka|language/i }).first();
  await expect(editTabs).toBeVisible({ timeout: 15_000 });
  const deTab = editTabs.getByRole('tab', { name: /de/i });
  await expect(deTab).toBeVisible();
  await deTab.click();
  await expect(page.getByLabel(/wartość dla de/i).first()).toHaveValue('Abmessungen');

  await page.request.delete(`/api/attribute_groups/${group.id}`, { headers: bearer });
});
