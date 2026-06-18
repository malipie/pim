import { expect, test } from '@playwright/test';

import { ADMIN_EMAIL, ADMIN_PASSWORD, apiLogin } from './helpers/auth';

/**
 * #1414 — modeling i18n fields must always render the full set of
 * workspace-enabled locales as tabs, with NO "+ Dodaj język" trigger.
 * The locale list is managed exclusively in Settings → Languages.
 *
 * Marked `fixme` in CI for the shared auth rate-limiter reason.
 */

test('locale tabs render all configured locales without an add-language button', async ({
  page,
}) => {
  test.setTimeout(90_000);

  const loginResponse = await page.request.post('/api/auth/login', {
    data: { email: ADMIN_EMAIL, password: ADMIN_PASSWORD },
    headers: { accept: 'application/json' },
  });
  expect(loginResponse.status()).toBe(200);
  const { token } = (await loginResponse.json()) as { token: string };
  const bearer = { authorization: `Bearer ${token}` };

  await apiLogin(page);

  const ws = await page.request.get('/api/workspaces/current', {
    headers: { ...bearer, accept: 'application/json' },
  });
  const enabled = ((await ws.json()) as { enabledLocales?: string[] }).enabledLocales ?? [];
  expect(enabled.length).toBeGreaterThan(0);

  for (const path of ['/modeling/attributes/new', '/modeling/attribute-groups/new']) {
    await page.goto(path);
    const tabs = page.getByRole('tablist', { name: /wybór języka|language/i }).first();
    await expect(tabs).toBeVisible({ timeout: 15_000 });
    // Every workspace locale renders as a tab…
    for (const code of enabled) {
      await expect(tabs.getByRole('tab', { name: new RegExp(`\\b${code}\\b`, 'i') })).toBeVisible();
    }
    // …and the add-language affordance is gone (#1414).
    await expect(page.getByRole('button', { name: /dodaj język|add language/i })).toHaveCount(0);
  }
});
