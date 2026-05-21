import { expect, test } from '@playwright/test';

import { apiLogin, loginAsAdmin } from './helpers/auth';

const SCREENSHOT_DIR = '/tmp/867';

test.describe.configure({ mode: 'serial' });

// CI: skip the manual-user-create screenshot spec because it consumes
// 3 of the 5 per-IP / 15-min auth_login rate-limit tokens (admin login
// + new user login + new user re-login). When this spec runs in CI the
// shared bucket runs out before the later settings-* smoke specs
// finish, and Playwright reports timeouts there. This spec exists for
// PR-description screenshots — run locally with `pnpm exec playwright
// test 867-manual-user-create.spec.ts` and inspect /tmp/867-*.png.
test.skip(
  !!process.env.CI,
  'CI: 867 screenshot spec eats login budget — run locally for screenshots',
);

/**
 * Manual user creation (#867) — walks the full happy path:
 *   1. Admin opens /settings/users, clicks "Dodaj ręcznie" toolbar button.
 *   2. Submit modal with email + password + role + force-change ✓.
 *   3. Assert toast + new row in list.
 *   4. Logout admin, log back in as the new user with the admin-set password.
 *   5. Expect redirect to /first-login-password (not /dashboard).
 *   6. Submit change-password form → land on /dashboard with sidebar.
 *
 * Screenshots saved to /tmp/867-{01..05}.png for the PR description.
 */
test('manual-user-create #867 — full flow + screenshots', async ({ page }) => {
  const adaEmail = `ada+${Date.now()}@example.com`;
  const initialPassword = 'AdminSet1234!';
  const newPassword = 'AdaPicked5678!';

  // ── 1. Admin login + open users list ───────────────────────────────
  await apiLogin(page);
  // Warm-up wait so /api/auth/me lands in the identity cache before we
  // jump to a deeper route (same pattern as 865-rbac-ui-realign spec —
  // without it AuthedRoute can bounce us to /login while identity is
  // still in-flight).
  await page.waitForTimeout(2000);
  await page.goto('https://pim.localhost/settings/users');
  await page.waitForLoadState('domcontentloaded');
  await page.waitForTimeout(2500);
  await page.screenshot({ path: `${SCREENSHOT_DIR}-01-users-list-toolbar.png`, fullPage: false });

  // ── 2. Open modal + screenshot blank form ──────────────────────────
  await page.getByRole('button', { name: /Dodaj ręcznie|Add manually/ }).click();
  await page.waitForTimeout(500);
  await page.screenshot({ path: `${SCREENSHOT_DIR}-02-add-modal-blank.png`, fullPage: false });

  // ── 3. Fill form ──────────────────────────────────────────────────
  await page.locator('#add-email').fill(adaEmail);
  await page.locator('#add-display-name').fill('Ada Test');
  await page.locator('#add-role').selectOption({ label: 'Catalog Manager' });
  await page.locator('#add-password').fill(initialPassword);
  await page.waitForTimeout(300);
  await page.screenshot({ path: `${SCREENSHOT_DIR}-03-add-modal-filled.png`, fullPage: false });

  // Submit
  await page.getByRole('button', { name: /Utwórz konto|Create account/ }).click();
  await page.waitForTimeout(2500);
  await page.screenshot({
    path: `${SCREENSHOT_DIR}-04-users-list-after-create.png`,
    fullPage: true,
  });

  // Assert new row visible in the list. The email also lands in the
  // success toast, so we anchor on the first match (the table row).
  await expect(page.getByText(adaEmail).first()).toBeVisible({ timeout: 10_000 });

  // ── 4. Log out admin ──────────────────────────────────────────────
  await page.evaluate(async () => {
    await fetch('/api/auth/logout', { method: 'POST', credentials: 'include' });
  });

  // ── 5. Log in as the new user → expect /first-login-password ──────
  await loginAsAdmin(page, adaEmail, initialPassword);
  await page.waitForURL(/\/first-login-password$/, { timeout: 10_000 });
  await page.waitForTimeout(1500);
  await page.screenshot({ path: `${SCREENSHOT_DIR}-05-first-login-page.png`, fullPage: false });

  // ── 6. Change password → land on dashboard ─────────────────────────
  await page.locator('#first-current-password').fill(initialPassword);
  await page.locator('#first-new-password').fill(newPassword);
  await page.locator('#first-confirm-password').fill(newPassword);
  await page.getByRole('button', { name: /Zmień hasło|Change password/ }).click();
  await page.waitForURL(/\/dashboard$/, { timeout: 10_000 });
  await page.waitForTimeout(2000);
  await page.screenshot({
    path: `${SCREENSHOT_DIR}-06-dashboard-after-change.png`,
    fullPage: true,
  });
});
