import { expect, test } from '@playwright/test';

import { loginAsAdmin } from './helpers/auth';

/**
 * VIEW-06 (#418) — Channels CRUD + mapping editor smoke test.
 *
 * Walks the operator's golden path: list → create → mapping tab →
 * back to list with the new row visible. Single test to keep the
 * auth rate-limit budget under the 5/IP/15min CI cap (lessons z
 * VIEW-01 #373 — multi-test specs blow past the limiter).
 *
 * FE validation (regex `^[a-z0-9_]+$` + required label/locales/
 * currencies) is covered by typing inside ChannelForm; smoke
 * walks the happy path only.
 */
test.describe('VIEW-06 — Settings · Channels · CRUD + mapping editor', () => {
  test('happy path: list → create → mapping tab → back to list', async ({ page }) => {
    await loginAsAdmin(page);

    const uniqueCode = `e2e_${Date.now().toString(36)}`;

    // 1. Navigate to /settings/channels — list page renders with CTA.
    await page.goto('/settings/channels');
    await expect(page.getByRole('heading', { name: /kana[łl]y|channels/i })).toBeVisible();
    await expect(page.getByRole('link', { name: /nowy kana[łl]|new channel/i })).toBeVisible();

    // 2. Click "New channel" — fullscreen-routed form lands at /new.
    await page.getByRole('link', { name: /nowy kana[łl]|new channel/i }).click();
    await expect(page).toHaveURL(/\/settings\/channels\/new$/);

    // 3. Fill form — code, label PL+EN, pick first locale + currency.
    await page.getByLabel(/^kod$|^code$/i).fill(uniqueCode);
    await page.getByLabel(/etykieta \(pl\)|label \(pl\)/i).fill('E2E test');
    await page.getByLabel(/label \(en\)/i).fill('E2E test');
    const localesFieldset = page.locator('fieldset[aria-labelledby="channel-locales-label"]');
    await localesFieldset.getByRole('button', { pressed: false }).first().click();
    const currenciesFieldset = page.locator('fieldset[aria-labelledby="channel-currencies-label"]');
    await currenciesFieldset.getByRole('button', { pressed: false }).first().click();

    // 4. Submit + redirect to detail page (UUID v7).
    await page.getByRole('button', { name: /utw[óo]rz kana[łl]|create channel/i }).click();
    await expect(page).toHaveURL(/\/settings\/channels\/[0-9a-f-]{36}$/);

    // 5. Mapping tab loads (might be empty without seeded mappings —
    //    listener is out-of-scope per BC boundary).
    await page.getByRole('button', { name: /^mapping$/i }).click();

    // 6. Back to list — the freshly created channel is listed.
    await page.goto('/settings/channels');
    await expect(page.getByText(uniqueCode)).toBeVisible();
  });
});
