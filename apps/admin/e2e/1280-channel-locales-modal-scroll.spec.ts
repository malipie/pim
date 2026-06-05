import { expect, test } from '@playwright/test';

import { loginAsAdmin } from './helpers/auth';

/**
 * #1280 — two settings-localization UX fixes:
 *
 *  1. The channel create form locale picker offers only the tenant's ACTIVE
 *     locales (pl_PL + en_US for the demo tenant), not the global ISO catalog
 *     (`de_DE` and friends must be absent).
 *  2. The "Add locale" modal scroll container actually scrolls — the catalog
 *     overflows its box (`scrollHeight > clientHeight`) and a deep row past the
 *     popular section is reachable.
 *
 * `test.fixme` in CI for the shared auth-rate-limiter storageState gap (same
 * pattern as #1263 / settings-channels-crud).
 */
const CI_BLOCKED = 'Pending storageState rollout: spec exhausts 5/15min auth rate limiter';

test.describe('#1280 — channel locale picker + add-locale modal scroll', () => {
  test('channel form locale picker lists only active tenant locales', async ({ page }) => {
    test.fixme(!!process.env.CI, CI_BLOCKED);
    test.setTimeout(120_000);

    await loginAsAdmin(page);
    await page.goto('/settings/channels/new');

    const localesFieldset = page.locator('fieldset[aria-labelledby="channel-locales-label"]');
    await expect(localesFieldset).toBeVisible();

    // Demo tenant has pl_PL + en_US active.
    await expect(localesFieldset.getByRole('button', { name: /pl_PL/ })).toBeVisible();
    await expect(localesFieldset.getByRole('button', { name: /en_US/ })).toBeVisible();

    // The global ISO catalog (de_DE, it_IT, fr_FR…) must NOT leak into the
    // picker — only the tenant's activated subset is offered.
    await expect(localesFieldset.getByRole('button', { name: /de_DE/ })).toHaveCount(0);
    await expect(localesFieldset.getByRole('button', { name: /it_IT/ })).toHaveCount(0);
  });

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
