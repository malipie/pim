import { expect, test } from '@playwright/test';
import { apiLogin } from './helpers/auth';

/**
 * EXR-03 (#1379) — second-level sidebar menu under "Integracje" + topbar
 * breadcrumb. Counters are mocked via route interception so the spec
 * stays independent from seeded data volume.
 */

test('integracje expands to second-level children and navigates', async ({ page }) => {
  await apiLogin(page);

  const integrations = page.getByRole('button', { name: /Integracje|Integrations/ });
  await expect(integrations).toBeVisible();
  await integrations.click();
  await expect(integrations).toHaveAttribute('aria-expanded', 'true');

  // Integracje → Eksporty
  await page.getByRole('link', { name: /^Eksporty|^Exports/ }).click();
  await expect(page).toHaveURL(/\/integrations\/exports\/sessions/);

  // Integracje → Importy
  await page.getByRole('link', { name: /^Importy|^Imports/ }).click();
  await expect(page).toHaveURL(/\/integrations\/imports\/sessions/);

  // breadcrumb shows Workspace / Integracje / Importy
  const breadcrumb = page.getByRole('navigation', { name: 'breadcrumb' });
  await expect(breadcrumb).toContainText(/Integracje|Integrations/);
  await expect(breadcrumb).toContainText(/Importy|Imports/);
});

test('deep link onto exports opens the Integracje parent with active child', async ({ page }) => {
  await apiLogin(page);
  // Let the post-login token bootstrap settle before a hard navigation.
  // Navigating immediately aborts the in-flight refresh rotation after the
  // backend marked the token used → next refresh trips theft detection and
  // bounces to /login (RefreshTokenService family burn).
  await page.waitForTimeout(1200);
  await page.goto('/integrations/exports/sessions');

  const integrations = page.getByRole('button', { name: /Integracje|Integrations/ });
  await expect(integrations).toHaveAttribute('aria-expanded', 'true');

  const exportsLink = page.getByRole('link', { name: /^Eksporty|^Exports/ });
  await expect(exportsLink).toBeVisible();
  await expect(exportsLink).toHaveClass(/bg-zinc-100/);
});

test('sidebar renders nav counters from list endpoints (mocked)', async ({ page }) => {
  await page.route('**/api/objects?*itemsPerPage=1*', (route) =>
    route.fulfill({
      status: 200,
      contentType: 'application/ld+json',
      body: JSON.stringify({ 'hydra:member': [], totalItems: 12847 }),
    }),
  );
  await apiLogin(page);

  await expect(page.locator('aside').getByText('12 847', { exact: true }).first()).toBeVisible();
});
