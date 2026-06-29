import AxeBuilder from '@axe-core/playwright';
import { expect, type Route, test } from '@playwright/test';

import { loginAsAdmin } from './helpers/auth';

/**
 * APIC-P4-02 (#1792) — the tenant-wide sync monitor. Runs + logs + binding
 * actions are mocked, so the test is deterministic and offline: assert the KPI
 * strip, filter by status, drill into a run and re-run it.
 */
test('APIC-P4-02 — sync monitor: KPI + status filter + drill-down + re-run', async ({ page }) => {
  await loginAsAdmin(page);

  const runs = [
    {
      id: 'run-ok',
      bindingId: 'bind-1',
      direction: 'inbound',
      startedAt: '2026-06-28T10:00:00+00:00',
      finishedAt: '2026-06-28T10:01:00+00:00',
      status: 'success',
      createdCount: 5,
      updatedCount: 2,
      skippedCount: 0,
      failedCount: 0,
      cursorAfter: { state: 'a' },
    },
    {
      id: 'run-err',
      bindingId: 'bind-2',
      direction: 'outbound',
      startedAt: '2026-06-28T09:00:00+00:00',
      finishedAt: '2026-06-28T09:00:30+00:00',
      status: 'failed',
      createdCount: 0,
      updatedCount: 1,
      skippedCount: 0,
      failedCount: 3,
      cursorAfter: null,
    },
  ];
  let reran = false;

  await page.route('**/api/sync_runs**', (r: Route) =>
    r.fulfill({
      status: 200,
      contentType: 'application/ld+json',
      body: JSON.stringify({ member: runs, totalItems: runs.length }),
    }),
  );
  await page.route('**/api/sync_run_logs**', (r: Route) =>
    r.fulfill({
      status: 200,
      contentType: 'application/ld+json',
      body: JSON.stringify({
        member: [
          { id: 'log-1', runId: 'run-ok', matchKey: 'SKU-9', action: 'created', message: null },
        ],
        totalItems: 1,
      }),
    }),
  );
  await page.route('**/api/sync_bindings/**/run', (r: Route) => {
    reran = true;
    return r.fulfill({ status: 202, contentType: 'application/json', body: '{"dispatched":true}' });
  });
  await page.route('**/api/sync_bindings/**/pause', (r: Route) =>
    r.fulfill({ status: 200, contentType: 'application/json', body: '{"enabled":false}' }),
  );

  await page.goto('/integrations/api-configurator/monitor');

  // Monitor screen loaded.
  await expect(
    page.getByRole('heading', { name: /monitor synchronizacji|sync monitor/i }),
  ).toBeVisible();

  // Both runs listed initially (run rows carry a distinct aria-label, so they
  // are counted apart from the status filter buttons).
  const runRows = page.getByRole('button', { name: /otwórz szczegóły|open run details/i });
  await expect(runRows).toHaveCount(2);

  // Filter to errors → only the failed run remains in the table.
  await page.getByRole('button', { name: /^błędy$|^errors$/i }).click();
  await expect(runRows).toHaveCount(1);

  // Reset filter, drill into the successful run.
  await page.getByRole('button', { name: /^wszystkie$|^all$/i }).click();
  await expect(runRows).toHaveCount(2);
  await runRows.filter({ hasText: /success|sukces/i }).click();
  await expect(page.getByText('SKU-9', { exact: true })).toBeVisible();

  // Re-run from the drill footer.
  await page.getByRole('button', { name: /uruchom ponownie|re-run/i }).click();
  await expect.poll(() => reran).toBe(true);

  const a11y = await new AxeBuilder({ page }).analyze();
  expect(a11y.violations).toEqual([]);
});
