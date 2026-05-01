import { expect, test } from '@playwright/test';

import { loginAsAdmin } from './helpers/auth';

/**
 * UI-03.1 (#356) — Dashboard handoff mock smoke.
 *
 * Dashboard is a static handoff mock: 9 blocks fed by hard-coded data in
 * features/dashboard/mock-data.ts. No backend endpoints are wired yet, so
 * Network must show ZERO requests to /api/dashboard/*. Console must be
 * clean — any red error means a translation key, font import, or accent
 * Tailwind class regressed.
 */
test('Dashboard mock — 9 blocks render, no /api/dashboard requests, console clean', async ({
  page,
}) => {
  const consoleErrors: string[] = [];
  page.on('console', (msg) => {
    if (msg.type() === 'error') consoleErrors.push(msg.text());
  });

  const dashboardApiHits: string[] = [];
  page.on('request', (req) => {
    if (req.url().includes('/api/dashboard/')) dashboardApiHits.push(req.url());
  });

  await loginAsAdmin(page);
  await expect(page).toHaveURL(/\/dashboard$/);

  // Hero CTA (Zapytaj agenta) renders, even though it is disabled.
  await expect(page.getByRole('heading', { level: 1 })).toBeVisible();

  // 9 distinct dashboard blocks — assert each headline is in the DOM.
  const headings = [
    /produkty|products/i,
    /atrybuty|attributes/i,
    /aktywno[sś]|activity/i,
    /najcz[eę][sś]ciej|most edited/i,
    /status synchronizacji|sync status/i,
    /kompletno[sś]|completeness/i,
    /aktywno[sś].*agenta|agent activity/i,
    /alerty|alerts/i,
    /dystrybucja|channel distribution/i,
  ];
  for (const heading of headings) {
    await expect(page.getByText(heading).first()).toBeVisible();
  }

  // Mock-only: no backend endpoints under /api/dashboard/* should fire.
  expect(dashboardApiHits, `unexpected dashboard API hits: ${dashboardApiHits.join(', ')}`).toEqual(
    [],
  );

  // Console clean (warnings are fine; red errors are not).
  expect(consoleErrors, `console errors: ${consoleErrors.join('\n')}`).toEqual([]);
});
