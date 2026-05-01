import { expect, test } from '@playwright/test';

import { loginAsAdmin } from './helpers/auth';

/**
 * UI-08.9 (#264) — Modeling layout shell smoke.
 * META-UI v2 (#289) — sidebar reorg per `00-plan-ui.md` §3.1.
 * UI-03.1 (#356) — adds dashboard mock smoke + redirect / → /dashboard.
 *
 * Single test exercises the full surface (dashboard render, sidebar layout,
 * tablist render, tab switch, legacy redirect) with one login. The
 * auth-rate-limiter (5/IP/15min) is shared across the whole Playwright run;
 * splitting this into multiple `beforeEach`-driven tests would push the
 * cumulative login count past the limit on top of the multi-tenant
 * isolation suite — every flow we care about is therefore folded in here.
 */
test('Modeling shell + Dashboard mock — full handoff smoke', async ({ page }) => {
  await loginAsAdmin(page);

  // 0a. Login lands on /dashboard (UI-03.1) and renders the handoff hero
  //     headline. Dashboard is a static mock — there must be ZERO requests
  //     to /api/dashboard/* (those endpoints don't exist yet).
  await expect(page).toHaveURL(/\/dashboard$/);
  await expect(page.getByRole('heading', { level: 1 })).toBeVisible();

  const dashboardApiHits: string[] = [];
  page.on('request', (req) => {
    if (req.url().includes('/api/dashboard/')) dashboardApiHits.push(req.url());
  });
  // Re-open dashboard so the listener catches its requests.
  await page.goto('/dashboard');
  for (const heading of [
    /aktywno[sś]|activity/i,
    /najcz[eę][sś]ciej|most edited/i,
    /status synchronizacji|sync status/i,
    /alerty|alerts/i,
  ]) {
    await expect(page.getByText(heading).first()).toBeVisible();
  }
  expect(dashboardApiHits, `unexpected dashboard API hits: ${dashboardApiHits.join(', ')}`).toEqual(
    [],
  );

  // 0. Sidebar mirrors `00-plan-ui.md` §3.1 — 7 primary leaves followed
  //    by a separator and a single Modeling leaf at the bottom. Two of
  //    the primary leaves remain placeholders awaiting their epics
  //    (UI-03 Services / UI-06 Workflow) and render as `aria-disabled`
  //    spans. Dashboard is now wired (handoff mock — epik UI-03 #356).
  const sidebar = page.getByRole('navigation').first();
  await expect(sidebar).toBeVisible();

  for (const label of [
    /^dashboard$|^pulpit$/i,
    /^products$|^produkty$/i,
    /^services$|^usługi$/i,
    /^publications$|^publikacje$/i,
    /^multimedia$/i,
    /^workflow$/i,
    /^settings$|^ustawienia$/i,
    /^modeling$|^modelowanie$/i,
  ]) {
    await expect(sidebar.getByText(label)).toBeVisible();
  }

  for (const placeholder of [/^services$|^usługi$/i, /^workflow$/i]) {
    const item = sidebar.getByText(placeholder).first().locator('..');
    await expect(item).toHaveAttribute('aria-disabled', 'true');
  }

  // 1. /modeling lands on object-types and renders the 4-tab tablist.
  await page.goto('/modeling');
  await expect(page).toHaveURL(/\/modeling\/object-types$/);

  const tablist = page.getByRole('tablist', { name: /modeling sections|sekcje modelowania/i });
  await expect(tablist).toBeVisible();

  const tabNames = [
    /object types|typy obiektów/i,
    /attributes|atrybuty/i,
    /attribute groups|grupy atrybutów/i,
    /categories|kategorie/i,
  ];
  for (const name of tabNames) {
    await expect(tablist.getByRole('tab', { name })).toBeVisible();
  }
  await expect(tablist.getByRole('tab', { name: /object types|typy obiektów/i })).toHaveAttribute(
    'aria-selected',
    'true',
  );

  // 2. Clicking the Attributes tab updates the URL + active highlight.
  await page.getByRole('tab', { name: /^attributes$|^atrybuty$/i }).click();
  await expect(page).toHaveURL(/\/modeling\/attributes$/);
  await expect(page.getByRole('tab', { name: /^attributes$|^atrybuty$/i })).toHaveAttribute(
    'aria-selected',
    'true',
  );

  // 3. Legacy top-level URL redirects to its /modeling/... twin.
  await page.goto('/object-types');
  await expect(page).toHaveURL(/\/modeling\/object-types$/);
});
