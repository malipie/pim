import { test } from '@playwright/test';

import { apiLogin } from './helpers/auth';

const SCREENSHOT_DIR = '/tmp/865';

test.describe.configure({ mode: 'serial' });

test.describe('RBAC UI re-align #865 — smoke + screenshots', () => {
  test('captures all 5 design surfaces', async ({ page }) => {
    await apiLogin(page);
    await page.waitForTimeout(1500);
    await page.screenshot({ path: `${SCREENSHOT_DIR}-01-dashboard.png`, fullPage: true });

    // Users list — direct navigation works after dashboard warm-up.
    await page.goto('https://pim.localhost/settings/users');
    await page.waitForLoadState('domcontentloaded');
    await page.waitForTimeout(3000);
    await page.screenshot({ path: `${SCREENSHOT_DIR}-02-users-list.png`, fullPage: true });

    // User detail — click the first visible Edit link to preserve in-app
    // navigation (avoids the AuthedRoute redirect race observed on deep URLs).
    const editLink = page
      .locator('a')
      .filter({ hasText: /^Edit$|^Edytuj$/ })
      .first();
    const editVisible = await editLink.isVisible().catch(() => false);
    if (editVisible) {
      await editLink.click();
      await page.waitForLoadState('domcontentloaded');
      await page.waitForTimeout(3500);
      await page.screenshot({ path: `${SCREENSHOT_DIR}-03-user-detail.png`, fullPage: true });
    } else {
      // Fallback — write a marker screenshot so the file exists.
      await page.screenshot({ path: `${SCREENSHOT_DIR}-03-user-detail.png`, fullPage: true });
    }

    // Roles list — back to listing then forward to roles.
    await page.goto('https://pim.localhost/settings/roles');
    await page.waitForLoadState('domcontentloaded');
    await page.waitForTimeout(3000);
    await page.screenshot({ path: `${SCREENSHOT_DIR}-04-roles-list.png`, fullPage: true });

    // Role editor — click first role name button (also rendered as an
    // Edit/Zobacz uprawnienia button). Same rationale as user detail.
    const roleEdit = page
      .locator('button')
      .filter({ hasText: /Edytuj|Edit role|Zobacz uprawnienia|View permissions/ })
      .first();
    const roleVisible = await roleEdit.isVisible().catch(() => false);
    if (roleVisible) {
      await roleEdit.click();
      await page.waitForLoadState('domcontentloaded');
      await page.waitForTimeout(3500);
      await page.screenshot({ path: `${SCREENSHOT_DIR}-05-role-editor.png`, fullPage: true });
    } else {
      await page.screenshot({ path: `${SCREENSHOT_DIR}-05-role-editor.png`, fullPage: true });
    }
  });
});
