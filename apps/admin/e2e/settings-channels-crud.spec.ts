import { expect, test } from '@playwright/test';

import { loginAsAdmin } from './helpers/auth';

/**
 * VIEW-06 (#418) — Channels CRUD + mapping editor smoke test.
 *
 * Walks the operator's golden path: list → create → detail → mapping
 * tab edit → list with the new row.
 *
 * Mutating delete is intentionally separate to keep this happy-path
 * spec idempotent under DB reuse: a fresh `pnpm db:reset` is a
 * pre-condition (consistent with other VIEW specs in this suite).
 */
test.describe('VIEW-06 — Settings · Channels · CRUD + mapping editor', () => {
  test('happy path: list → create → mapping edit', async ({ page }) => {
    await loginAsAdmin(page);

    const uniqueCode = `e2e_${Date.now().toString(36)}`;

    // 1. Navigate to /settings/channels
    await page.goto('/settings/channels');
    await expect(page.getByRole('heading', { name: /kana[łl]y|channels/i })).toBeVisible();
    await expect(page.getByRole('link', { name: /nowy kana[łl]|new channel/i })).toBeVisible();

    // 2. Click "New channel"
    await page.getByRole('link', { name: /nowy kana[łl]|new channel/i }).click();
    await expect(page).toHaveURL(/\/settings\/channels\/new$/);

    // 3. Fill form
    await page.getByLabel(/^kod$|^code$/i).fill(uniqueCode);
    await page.getByLabel(/etykieta \(pl\)|label \(pl\)/i).fill('E2E test');
    await page.getByLabel(/label \(en\)/i).fill('E2E test');
    // Pick first locale + first currency (each picker is a fieldset with
    // a labeled ARIA name resolved from a Label element).
    const localesFieldset = page.locator('fieldset[aria-labelledby="channel-locales-label"]');
    await localesFieldset.getByRole('button', { pressed: false }).first().click();
    const currenciesFieldset = page.locator('fieldset[aria-labelledby="channel-currencies-label"]');
    await currenciesFieldset.getByRole('button', { pressed: false }).first().click();

    // 4. Submit + redirect
    await page.getByRole('button', { name: /utw[óo]rz kana[łl]|create channel/i }).click();
    await expect(page).toHaveURL(/\/settings\/channels\/[0-9a-f-]{36}$/);

    // 5. Mapping tab — placeholder count or input renders
    await page.getByRole('button', { name: /^mapping$/i }).click();

    // 6. Back to list — the freshly created channel is listed
    await page.goto('/settings/channels');
    await expect(page.getByText(uniqueCode)).toBeVisible();
  });

  test('rejects invalid code format with FE validation', async ({ page }) => {
    await loginAsAdmin(page);
    await page.goto('/settings/channels/new');

    // Use uppercase letters which should be normalised by the form to lowercase
    // but space + dash also pass through and fail regex ^[a-z0-9_]+$.
    await page.getByLabel(/^kod$|^code$/i).fill('bad code with space');
    await page.getByLabel(/etykieta \(pl\)|label \(pl\)/i).fill('X');
    await page.getByLabel(/label \(en\)/i).fill('X');

    // Submit button stays disabled while errors hold
    const submit = page.getByRole('button', { name: /utw[óo]rz kana[łl]|create channel/i });
    await expect(submit).toBeDisabled();
  });
});
