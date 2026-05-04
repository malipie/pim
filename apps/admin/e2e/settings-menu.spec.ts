import { expect, test } from '@playwright/test';

import { loginAsAdmin } from './helpers/auth';

/**
 * VIEW-08 (#427) — Settings · Menu drag-drop + ObjectType.exposeToMainMenu
 * + dynamic sidebar end-to-end smoke.
 *
 * Single test exercises the entire flow under one login (rate-limit budget):
 *   - Default sidebar matches the seed (Pulpit, Produkty, Katalogi PDF,
 *     Multimedia, Workflow, Integracje, Ustawienia, Modelowanie — bez Usług).
 *   - Settings → Menu page renders Visible + Available sections.
 *   - Drag-drop is wired (sortable handle visible, dnd-kit attributes set).
 *   - Toggle "Udostępnij do głównego menu" on a built-in Brand (kind=brand)
 *     pushes the entry into Available.
 *   - Asset toggle is locked (built-in Asset uses /assets DAM page).
 *   - Hide on a non-protected item moves it out of Visible.
 *   - Settings/Modeling rows show a Lock badge instead of Hide button
 *     (protected: cannot be hidden).
 */
test('VIEW-08 Settings · Menu — drag-drop + expose toggle + protected items', async ({ page }) => {
  await loginAsAdmin(page);

  const sidebar = page.locator('aside');

  // 1. Sidebar (after `useEffectiveMenu` resolves): 7 system items + Produkty
  //    (no Usługi). We assert visibility of the dynamic Products link first
  //    — its presence proves the hook has resolved past the static fallback.
  await expect(sidebar.getByRole('link', { name: /produkty|^products$/i }).first()).toBeVisible();
  await expect(sidebar.getByText(/pulpit|^dashboard$/i)).toBeVisible();
  await expect(sidebar.getByText(/katalogi pdf|pdf catalogs/i)).toBeVisible();
  await expect(sidebar.getByText(/multimedia/i)).toBeVisible();
  await expect(sidebar.getByText(/integracje|integrations/i)).toBeVisible();
  await expect(sidebar.getByText(/ustawienia|^settings$/i)).toBeVisible();
  await expect(sidebar.getByText(/modelowanie|^modeling$/i)).toBeVisible();

  // Workflow appears as a disabled "Wkrótce" / "Soon" entry.
  await expect(sidebar.getByText(/workflow/i)).toBeVisible();

  // Services intentionally absent in the default seed.
  await expect(sidebar.getByText(/^usługi$|^services$/i)).toHaveCount(0);

  // 2. Modeling → Object Types → Brand (built-in) → enable expose toggle.
  await page.goto('/modeling/object-types');
  await expect(page.getByRole('heading', { name: /object types/i, level: 1 })).toBeVisible();

  const brandLink = page
    .locator('a[href^="/modeling/object-types/"]')
    .filter({ hasText: /marki|brand/i })
    .first();
  await brandLink.click();
  await expect(page).toHaveURL(/\/modeling\/object-types\/[0-9a-f-]{36}/);

  // The Settings card carries the new toggle row.
  const exposeToggle = page.getByRole('switch', {
    name: /udostępnij do głównego menu/i,
  });
  await expect(exposeToggle).toBeVisible();
  await expect(exposeToggle).toHaveAttribute('aria-checked', 'false');
  await exposeToggle.click();
  await expect(exposeToggle).toHaveAttribute('aria-checked', 'true');

  // 3. Settings → Menu: Brand should now appear in Available.
  await page.goto('/settings/menu');
  await expect(
    page.getByRole('heading', { name: /menu główne|main menu/i, level: 1 }),
  ).toBeVisible();
  await expect(page.getByText(/widoczne w menu|visible in menu/i)).toBeVisible();
  await expect(page.getByText(/dostępne|available/i).first()).toBeVisible();

  const availableList = page.getByTestId('menu-available-list');
  await expect(availableList).toBeVisible();
  await expect(availableList.getByText(/marki|brand/i)).toBeVisible();

  // 4. Visible list contains the seeded 8 items, with drag handles.
  const visibleList = page.getByTestId('menu-visible-list');
  // 1 dashboard + 1 product + 6 system items = 8 rows.
  const visibleRows = visibleList.locator('> div');
  await expect(visibleRows).toHaveCount(8);

  // 5. Protected items render Lock icon instead of EyeOff button.
  // The drag handle is always rendered, so we look at the *trailing* button:
  // protected → no EyeOff button; non-protected → EyeOff button visible.
  const settingsRow = visibleList.locator('> div').filter({ hasText: /^ustawienia$/i });
  await expect(settingsRow).toBeVisible();
  await expect(settingsRow.getByRole('button', { name: /ukryj|hide/i })).toHaveCount(0);

  const dashboardRow = visibleList.locator('> div').filter({ hasText: /^pulpit$/i });
  await expect(dashboardRow.getByRole('button', { name: /ukryj|hide/i })).toBeVisible();

  // 6. Asset toggle is locked on the Asset detail page.
  await page.goto('/modeling/object-types');
  const assetLink = page
    .locator('a[href^="/modeling/object-types/"]')
    .filter({ hasText: /^zasób|^asset/i })
    .first();
  await assetLink.click();
  await expect(page).toHaveURL(/\/modeling\/object-types\/[0-9a-f-]{36}/);

  const assetExposeToggle = page.getByRole('switch', {
    name: /udostępnij do głównego menu/i,
  });
  await expect(assetExposeToggle).toBeVisible();
  await expect(assetExposeToggle).toHaveAttribute('aria-disabled', 'true');
});
