import { expect, test } from '@playwright/test';

import { loginAsAdmin } from './helpers/auth';

/**
 * #1675 — the import wizard's first step now lets the operator choose WHICH
 * ObjectType to import into, instead of being hardcoded to `product`. This
 * proves the FE wiring on the live stack:
 *   1. the "Co importujesz?" picker is the first control and defaults to a
 *      product (regression guard for the previously hardcoded type),
 *   2. switching to another built-in type updates the selection,
 *   3. the choice does not block the wizard flow (upload → step 2).
 *
 * The full import INTO a chosen custom ObjectType is covered by the live-stack
 * smoke on the issue — the backend pipeline is already type-generic
 * (StartImportController accepts any ObjectType), so this spec guards the FE
 * picker wiring without the flaky six-step commit into a freshly-created type.
 */
test('#1675 — import wizard exposes an object-type picker, default product', async ({ page }) => {
  test.setTimeout(120_000);

  await loginAsAdmin(page);
  await page.goto('/integrations/imports/new');

  // 1. The object-type picker is the first, most prominent control on step 1.
  await expect(page.getByText(/co importujesz\?|what are you importing\?/i)).toBeVisible({
    timeout: 20_000,
  });

  // 2. It defaults to a product (the type the wizard used to be locked to).
  const picker = page.locator('button[aria-haspopup="listbox"]').first();
  await expect(picker).toContainText(/produkt|product/i);

  // 3. Switching to another built-in type updates the selection. Filter the
  //    list down to the category type and click its label option.
  await picker.click();
  await page.getByPlaceholder(/szukaj typu|search types/i).fill('categ');
  await page
    .getByText(/^(Kategoria|Category)$/)
    .first()
    .click();
  await expect(picker).toContainText(/kategoria|category/i);

  // 4. The choice does not block the flow: upload a CSV and advance to step 2.
  const csv = 'sku;name\nCAT-1675-1;Smoke 1675\n';
  await page
    .locator('input[type="file"]')
    .first()
    .setInputFiles({ name: 'pick.csv', mimeType: 'text/csv', buffer: Buffer.from(csv, 'utf-8') });
  await page.getByRole('button', { name: /dalej|next/i }).click();
  await expect(page.getByText(/kodowanie|encoding/i).first()).toBeVisible({ timeout: 20_000 });
});
