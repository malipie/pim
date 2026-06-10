import { expect, test } from '@playwright/test';
import { apiLogin } from './helpers/auth';

/**
 * EXR-08 (#1384) — Exports page (v2): KPI strip, active empty state,
 * history table with segments/search, navigation to detail. Sessions
 * payload is mocked for deterministic table content; the live-stack
 * smoke (PR) covers the real backend.
 */

const NOW = Date.now();

function session(overrides: Record<string, unknown>): Record<string, unknown> {
  return {
    id: '00000000-0000-0000-0000-000000000001',
    entity_type: 'product',
    object_type_id: null,
    format: 'xlsx',
    target_scope: 'all',
    target_count: 100,
    success_count: 100,
    status: 'done',
    source: 'central_tab',
    started_at: new Date(NOW - 60 * 60 * 1000).toISOString(),
    completed_at: new Date(NOW - 59 * 60 * 1000).toISOString(),
    profile_name: 'Pełny katalog',
    file_path: 'exports/products_2026-06-10.xlsx',
    duration_ms: 64_000,
    error_message: null,
    ...overrides,
  };
}

const SESSIONS = [
  session({}),
  session({
    id: '00000000-0000-0000-0000-000000000002',
    status: 'error',
    success_count: 40,
    error_message: 'Timeout while writing XLSX',
    file_path: 'exports/products_failed.xlsx',
    profile_name: null,
  }),
];

test.beforeEach(async ({ page }) => {
  await page.route('**/api/exports/sessions', (route) =>
    route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({ items: SESSIONS, total: SESSIONS.length }),
    }),
  );
  await page.route('**/api/exports/profiles', (route) =>
    route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({ items: [], total: 0 }),
    }),
  );
});

test('exports page renders KPI strip, empty active state and history', async ({ page }) => {
  await apiLogin(page);
  await page.getByRole('button', { name: /Integracje|Integrations/ }).click();
  await page.getByRole('link', { name: /^Eksporty|^Exports/ }).click();

  // KPI strip
  await expect(page.getByText(/Sukces · 30 dni|Success · 30 days/)).toBeVisible();
  // active section — empty state with CTA
  await expect(page.getByText(/Brak aktywnych eksportów|No active exports/)).toBeVisible();
  // history renders both mocked sessions
  await expect(page.getByText('products_2026-06-10.xlsx')).toBeVisible();
  await expect(page.getByText('products_failed.xlsx')).toBeVisible();
  // tabs with counts
  await expect(page.getByRole('tab', { name: /Sesje|Sessions/ })).toContainText('2');
});

test('history segment and search filter rows', async ({ page }) => {
  await apiLogin(page);
  await page.goto('/integrations/exports/sessions', { waitUntil: 'commit' });
  await page.waitForTimeout(1500);

  await expect(page.getByText('products_2026-06-10.xlsx')).toBeVisible();

  // segment: błędy
  await page.getByRole('button', { name: /^błędy$|^errors$/ }).click();
  await expect(page.getByText('products_failed.xlsx')).toBeVisible();
  await expect(page.getByText('products_2026-06-10.xlsx')).not.toBeVisible();

  // back to all + search
  await page.getByRole('button', { name: /^wszystkie$|^all$/ }).click();
  await page.getByRole('searchbox').fill('2026-06-10');
  await expect(page.getByText('products_2026-06-10.xlsx')).toBeVisible();
  await expect(page.getByText('products_failed.xlsx')).not.toBeVisible();
});

test('chevron navigates to the session detail page', async ({ page }) => {
  await page.route('**/api/exports/sessions/00000000-0000-0000-0000-000000000001', (route) =>
    route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({
        ...session({}),
        encoding: null,
        selected_columns: ['sku', 'name'],
        selected_object_ids: null,
        filter_snapshot: null,
        locales: ['pl'],
        channels: null,
        include_variants: true,
        file_size_bytes: 204_800,
      }),
    }),
  );
  await apiLogin(page);
  await page.goto('/integrations/exports/sessions', { waitUntil: 'commit' });
  await page.waitForTimeout(1500);

  await page
    .getByRole('link', { name: /Szczegóły|Details/ })
    .first()
    .click();
  await expect(page).toHaveURL(/\/integrations\/exports\/sessions\/00000000/);
  await expect(page.getByText('sku, name')).toBeVisible();
});
