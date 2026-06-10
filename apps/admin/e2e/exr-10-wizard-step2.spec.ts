import { expect, test } from '@playwright/test';
import { apiLogin } from './helpers/auth';

/**
 * EXR-10 (#1386) — wizard step 2: format radio-cards (D1: only XLSX/CSV
 * active), reused AdvancedFilterPanel and the debounced preflight badge.
 * Preflight + profiles are mocked for determinism; the cross-check
 * against the product list count runs in the live smoke.
 */

let preflightCount = 42;

test.beforeEach(async ({ page }) => {
  preflightCount = 42;
  await page.route('**/api/exports/profiles', (route) =>
    route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({ items: [], total: 0 }),
    }),
  );
  await page.route('**/api/exports/preflight', (route) =>
    route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({
        count: preflightCount,
        mode: preflightCount >= 100 ? 'async' : 'sync',
        threshold: 100,
        soft_cap: 100_000,
        exceeds_cap: preflightCount > 100_000,
      }),
    }),
  );
  await apiLogin(page);
  await page.waitForTimeout(1200);
  await page.goto('/integrations/exports/new');
  await page.waitForTimeout(600);
  // step 1 → step 2 (products preselected)
  await page.getByRole('button', { name: /Dalej|Next/ }).click();
  await page.waitForTimeout(300);
});

test('format tiles: XLSX/CSV selectable, soon formats disabled', async ({ page }) => {
  const group = page.getByRole('radiogroup', { name: /Format pliku|File format/ });
  await expect(group.getByRole('radio')).toHaveCount(6);

  await group.getByRole('radio', { name: /^CSV/ }).click();
  await expect(group.getByRole('radio', { name: /^CSV/ })).toHaveAttribute('aria-checked', 'true');

  const pdf = group.getByRole('radio', { name: /PDF/ });
  await expect(pdf).toHaveAttribute('aria-disabled', 'true');
  await expect(pdf).toContainText(/wkrótce|soon/);
  await pdf.click();
  await expect(pdf).toHaveAttribute('aria-checked', 'false');
});

test('preflight badge shows the count and reacts to filter changes', async ({ page }) => {
  const badge = page.getByTestId('preflight-badge');
  await expect(badge).toContainText('42');

  // build a condition through the REUSED AdvancedFilterPanel
  preflightCount = 7;
  await page.getByRole('button', { name: /dodaj warunek/i }).click();
  await expect(page.getByLabel('Atrybut').first()).toBeVisible();
  await page
    .getByPlaceholder(/wpisz wartość/i)
    .first()
    .fill('Festo');
  await expect(badge).toContainText('7', { timeout: 5_000 });
});

test('exceeding the soft cap blocks Dalej', async ({ page }) => {
  preflightCount = 250_000;
  await page.getByRole('button', { name: /dodaj warunek/i }).click();
  await page
    .getByPlaceholder(/wpisz wartość/i)
    .first()
    .fill('x');

  await expect(page.getByTestId('preflight-badge')).toContainText(/Przekroczono|exceeded/, {
    timeout: 5_000,
  });
  await expect(page.getByRole('button', { name: /Dalej|Next/ })).toBeDisabled();
});

test('structural entity shows full-structure note instead of the panel', async ({ page }) => {
  // go back to step 1 and pick a structural entity
  await page
    .getByRole('button', { name: /Typ|Type/ })
    .first()
    .click();
  await page.getByRole('radio', { name: /Kategorie|Categories/ }).click();
  await page.getByRole('button', { name: /Dalej|Next/ }).click();

  await expect(page.getByText(/Eksport pełnej struktury|Full structure export/)).toBeVisible();
  await expect(page.getByRole('button', { name: /dodaj warunek/i })).not.toBeVisible();
});
