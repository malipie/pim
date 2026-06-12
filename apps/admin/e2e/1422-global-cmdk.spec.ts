import { expect, test } from '@playwright/test';

import { loginAsAdmin } from './helpers/auth';

/**
 * NUI-03 (#1422) — global ⌘K palette: real navigation (static routes +
 * settings sub-pages), agent section mocked. The sidebar pill opens the
 * palette; on the universal list routes the list-scoped palette keeps
 * the shortcut (no double binding).
 */
test('NUI-03 — global palette navigates and mocks the agent section', async ({ page }) => {
  await loginAsAdmin(page);
  await page.goto('/dashboard');
  // Wait for the app shell to hydrate before firing the shortcut.
  await expect(
    page.getByRole('button', { name: /zapytaj agenta|ask the agent/i }).first(),
  ).toBeVisible();

  // Open via keyboard.
  await page.keyboard.press('ControlOrMeta+k');
  const dialog = page.getByRole('dialog');
  await expect(dialog).toBeVisible();

  // Agent section renders (mock suggestions + MOCK badge).
  await expect(dialog.getByText('Generuj opisy SEO')).toBeVisible();
  await expect(dialog.getByText('MOCK', { exact: true }).first()).toBeVisible();

  // Type to filter and navigate to settings users.
  await page.keyboard.type('Użytkow');
  const usersEntry = dialog.getByRole('button', { name: /użytkownicy|users/i }).first();
  const hasPl = await usersEntry.isVisible().catch(() => false);
  if (!hasPl) {
    // EN UI — retype the English label.
    await page.keyboard.press('ControlOrMeta+a');
    await page.keyboard.type('Users');
  }
  await dialog
    .getByRole('button', { name: /użytkownicy|users/i })
    .first()
    .click();
  await expect(page).toHaveURL(/\/settings\/users$/);

  // Sidebar pill re-opens the palette from any page.
  await page
    .getByRole('button', { name: /zapytaj agenta|ask the agent/i })
    .first()
    .click();
  await expect(page.getByRole('dialog')).toBeVisible();
  await page.keyboard.press('Escape');
});
