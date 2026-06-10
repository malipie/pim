import { expect, test } from '@playwright/test';
import { apiLogin } from './helpers/auth';

/**
 * EXR-09 (#1385) — export wizard shell + step 1. Object types are
 * mocked so the custom-module flow is deterministic.
 */

const CUSTOM_OT = {
  id: '11111111-1111-1111-1111-111111111111',
  code: 'producers',
  kind: 'custom',
  builtIn: false,
  label: { pl: 'Producenci', en: 'Producers' },
};

test.beforeEach(async ({ page }) => {
  await page.route('**/api/object_types', (route) =>
    route.fulfill({
      status: 200,
      contentType: 'application/ld+json',
      body: JSON.stringify({ member: [CUSTOM_OT], totalItems: 1 }),
    }),
  );
  await apiLogin(page);
  await page.waitForTimeout(1200);
  await page.goto('/integrations/exports/new');
  await page.waitForTimeout(800);
});

test('every entity tile is selectable and Dalej advances', async ({ page }) => {
  const group = page.getByRole('radiogroup');
  await expect(group.getByRole('radio')).toHaveCount(5);

  // products selected by default with the WYBRANE badge
  const products = group.getByRole('radio', { name: /Produkty|Products/ });
  await expect(products).toHaveAttribute('aria-checked', 'true');
  await expect(products).toContainText(/wybrane|selected/);

  for (const name of [/Schemat|Module schema/, /Atrybuty|Attributes/, /Kategorie|Categories/]) {
    await group.getByRole('radio', { name }).click();
    await expect(group.getByRole('radio', { name })).toHaveAttribute('aria-checked', 'true');
  }

  await page.getByRole('button', { name: /Dalej|Next/ }).click();
  await expect(page.getByText(/EXR-10\/11\/12/)).toBeVisible();

  // stepper: step 1 done, step 2 active
  await expect(page.getByRole('button', { name: /Zakres|Scope/ })).toHaveAttribute(
    'aria-current',
    'step',
  );
});

test('custom module requires an ObjectType before Dalej', async ({ page }) => {
  await page.getByRole('radio', { name: /Moduły własne|Custom modules/ }).click();

  await expect(page.getByText(/Wybierz moduł własny|Choose a custom module/)).toBeVisible();
  await expect(page.getByRole('button', { name: /Dalej|Next/ })).toBeDisabled();

  await page.getByLabel(/Moduł własny|Custom module/).selectOption(CUSTOM_OT.id);
  await expect(page.getByRole('button', { name: /Dalej|Next/ })).toBeEnabled();
  await page.getByRole('button', { name: /Dalej|Next/ }).click();
  await expect(page.getByText(/EXR-10\/11\/12/)).toBeVisible();
});

test('cancel asks for confirmation when dirty and returns to sessions', async ({ page }) => {
  await page.getByRole('radio', { name: /Kategorie|Categories/ }).click();

  page.once('dialog', (dialog) => void dialog.dismiss());
  await page.getByRole('button', { name: /Anuluj|Cancel/ }).click();
  await expect(page).toHaveURL(/\/integrations\/exports\/new/);

  page.once('dialog', (dialog) => void dialog.accept());
  await page.getByRole('button', { name: /Anuluj|Cancel/ }).click();
  await expect(page).toHaveURL(/\/integrations\/exports\/sessions/);
});
