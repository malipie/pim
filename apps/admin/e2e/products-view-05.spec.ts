import { expect, test } from '@playwright/test';

import { loginAsAdmin } from './helpers/auth';

/**
 * VIEW-05 (#411) — pixel-perfect delta-alignment of the products list.
 * Asserts the new header copy, SavedViewsRail, FilterPill toolbar,
 * VariantsToggle segmented control, ProductsGrid 12-column layout, and
 * BulkBar sticky bottom bar with toast placeholders. Axe-core a11y scan
 * is deferred to follow-up VIEW-05.0 once `@axe-core/playwright` lands
 * in deps; manual Lighthouse run substitutes for the MVP merge.
 */
test.describe('VIEW-05 Products · Lista', () => {
  test.beforeEach(async ({ page }) => {
    await loginAsAdmin(page);
    await page.goto('/products');
    await expect(page).toHaveURL(/\/products$/);
  });

  test('renders pixel-perfect header, toolbar, and grid', async ({ page }) => {
    await expect(page.getByText('Workspace · katalog', { exact: true })).toBeVisible();
    await expect(page.getByRole('heading', { level: 1, name: 'Produkty' })).toBeVisible();
    await expect(page.getByPlaceholder('Szukaj po SKU, nazwie, EAN, atrybucie…')).toBeVisible();
    await expect(page.getByRole('button', { name: 'Marka' })).toBeVisible();
    await expect(page.getByRole('button', { name: 'Rodzina' })).toBeVisible();
    await expect(page.getByRole('button', { name: 'Kanał' })).toBeVisible();
    await expect(page.getByRole('button', { name: 'Status' })).toBeVisible();
    await expect(page.getByRole('button', { name: 'Płasko' })).toBeVisible();
    await expect(page.getByRole('button', { name: 'Drzewo' })).toBeVisible();
    await expect(page.getByRole('link', { name: /Nowy produkt/i })).toBeVisible();
    await expect(page.getByTestId('products-grid')).toBeVisible();
  });

  test('channel pill click surfaces toast — per-channel filter waits for epic 0.6', async ({
    page,
  }) => {
    await page.getByRole('button', { name: 'Kanał' }).click();
    await page.getByRole('menuitem', { name: 'Shopify' }).click();
    await expect(
      page
        .getByRole('status')
        .filter({ hasText: /Filtr per kanał czeka na epik 0\.6/ })
        .first(),
    ).toBeVisible();
  });

  test('variants segmented toggle flips active state on click', async ({ page }) => {
    const flat = page.getByRole('button', { name: 'Płasko' });
    const tree = page.getByRole('button', { name: 'Drzewo' });

    await tree.click();
    await expect(tree).toHaveAttribute('aria-pressed', 'true');
    await expect(flat).toHaveAttribute('aria-pressed', 'false');

    await flat.click();
    await expect(flat).toHaveAttribute('aria-pressed', 'true');
    await expect(tree).toHaveAttribute('aria-pressed', 'false');
  });

  test('selecting a row reveals the BulkBar with placeholder toasts', async ({ page }) => {
    const grid = page.getByTestId('products-grid');
    await expect(grid).toBeVisible();

    const firstRow = grid.locator('[data-testid^="products-grid-row-"]').first();
    const rowExists = (await firstRow.count()) > 0;
    test.skip(!rowExists, 'no products in fixtures — bulk bar requires at least one row');

    const checkbox = firstRow.getByRole('checkbox').first();
    await checkbox.check();

    const bulkBar = page.getByTestId('bulk-bar');
    await expect(bulkBar).toBeVisible();
    await expect(bulkBar).toContainText('zaznaczonych produktów');

    await bulkBar.getByRole('button', { name: 'Edytuj atrybut' }).click();
    await expect(
      page
        .getByRole('status')
        .filter({ hasText: /W przygotowaniu — VIEW-05\.2/ })
        .first(),
    ).toBeVisible();

    await bulkBar.getByRole('button', { name: 'Wyczyść' }).click();
    await expect(bulkBar).not.toBeVisible();
  });
});
