import { expect, test } from '@playwright/test';

import { loginAsAdmin } from './helpers/auth';

/**
 * #1280 — the "Add locale" modal scroll container actually scrolls: the
 * catalog overflows its box (`scrollHeight > clientHeight`) and a deep row
 * past the popular section is reachable.
 *
 * (The original channel-form locale-picker check was dropped in #1318 when
 * per-channel locales were removed — channels no longer carry a locale set.)
 *
 * `test.fixme` in CI for the shared auth-rate-limiter storageState gap (same
 * pattern as #1263 / settings-channels-crud).
 */
const CI_BLOCKED = 'Pending storageState rollout: spec exhausts 5/15min auth rate limiter';

test.describe('#1280 — add-locale modal scroll', () => {
  test('add-locale modal catalog scrolls past the popular section', async ({ page }) => {
    test.fixme(!!process.env.CI, CI_BLOCKED);
    test.setTimeout(120_000);

    await loginAsAdmin(page);
    await page.goto('/settings/locales');

    await page.getByRole('button', { name: /dodaj lokalizacj|add locale/i }).click();

    const scroll = page.getByTestId('locale-catalog-scroll');
    await expect(scroll).toBeVisible();

    // Wait for the real catalog rows to render (not the loading skeletons),
    // otherwise the height check races the async `/api/locales` fetch.
    await expect(scroll.getByRole('button').first()).toBeVisible();

    // Catalog overflows its container → the box is genuinely scrollable.
    await expect
      .poll(() => scroll.evaluate((el) => el.scrollHeight - el.clientHeight))
      .toBeGreaterThan(0);

    // The last catalog row is reachable by scrolling (the bug: it was clipped).
    const lastRow = scroll.getByRole('button').last();
    await lastRow.scrollIntoViewIfNeeded();
    await expect(lastRow).toBeInViewport();
  });
});
