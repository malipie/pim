import { expect, type Page } from '@playwright/test';

export const ADMIN_EMAIL = 'admin@demo.localhost';
export const ADMIN_PASSWORD = 'changeme';

/**
 * Log in through the actual /login form and wait for the products list to
 * render. Single happy-path login helper used by every protected-route test.
 */
export async function loginAsAdmin(
  page: Page,
  email: string = ADMIN_EMAIL,
  password: string = ADMIN_PASSWORD,
): Promise<void> {
  await page.goto('/login');
  await page.getByLabel(/e-?mail/i).fill(email);
  await page.getByLabel(/has[lł]o|password/i).fill(password);
  await page.getByRole('button', { name: /zaloguj|sign in/i }).click();
  await expect(page).toHaveURL(/\/products$/);
}

/**
 * Random SKU — the API enforces a unique (tenant_id, sku) constraint and the
 * dev DB is not reset between runs locally; suffix with timestamp + random
 * so successive runs of the create-product test never collide.
 */
export function uniqueSku(prefix = 'E2E'): string {
  const stamp = Date.now().toString(36).toUpperCase();
  const random = Math.floor(Math.random() * 1000)
    .toString()
    .padStart(3, '0');
  return `${prefix}-${stamp}-${random}`;
}
