import { expect, test } from '@playwright/test';
import { apiLogin } from './helpers/auth';

/**
 * EXR-16 (#1392) — wizard happy path for every non-product entity type
 * (D2: all five working). Endpoints mocked for CI determinism; the
 * real-file assertions run in the live smoke + benchmark.
 */

const CUSTOM_OT = {
  id: '11111111-1111-1111-1111-111111111111',
  code: 'producers',
  kind: 'custom',
  builtIn: false,
  label: { pl: 'Producenci', en: 'Producers' },
};

test.beforeEach(async ({ page }) => {
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
        count: 42,
        mode: 'sync',
        threshold: 100,
        soft_cap: 100000,
        exceeds_cap: false,
      }),
    }),
  );
  await page.route('**/api/object_types', (route) =>
    route.fulfill({
      status: 200,
      contentType: 'application/ld+json',
      body: JSON.stringify({ member: [CUSTOM_OT], totalItems: 1 }),
    }),
  );
  await page.route('**/api/exports/columns?*', (route) => {
    const url = new URL(route.request().url());
    if (url.searchParams.get('entity_type') === 'custom_module') {
      route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({ entity_type: 'custom_module', attribute_codes: ['name'] }),
      });
      return;
    }
    route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({
        entity_type: url.searchParams.get('entity_type'),
        columns: ['code', 'label.pl', 'label.en', 'position'],
      }),
    });
  });
  await page.route('**/api/attributes?*', (route) =>
    route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify([
        { id: 'a1', code: 'name', label: { pl: 'Nazwa' }, type: 'text' },
        { id: 'a2', code: 'only_products', label: { pl: 'Tylko produkty' }, type: 'text' },
      ]),
    }),
  );
  for (const url of ['**/api/attribute_groups?*', '**/api/channels?*'] as const) {
    await page.route(url, (route) =>
      route.fulfill({ status: 200, contentType: 'application/json', body: '[]' }),
    );
  }
  await page.route('**/api/workspaces/current', (route) =>
    route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify({ enabledLocales: ['pl'], primaryLocale: 'pl' }),
    }),
  );

  await apiLogin(page);
  await page.waitForTimeout(1200);
  await page.goto('/integrations/exports/new');
  await page.waitForTimeout(500);
});

for (const entity of [
  { radio: /Schemat modułów|Module schema/, payload: 'module_schema' },
  { radio: /Atrybuty i grupy|Attributes & groups/, payload: 'attributes_groups' },
  { radio: /Kategorie|Categories/, payload: 'categories' },
]) {
  test(`structural happy path: ${entity.payload}`, async ({ page }) => {
    await page.getByRole('radio', { name: entity.radio }).click();
    await page.getByRole('button', { name: /Dalej|Next/ }).click();

    // step 2: no query builder, full-structure note with the preflight count
    await expect(page.getByText(/Eksport pełnej struktury|Full structure export/)).toBeVisible();
    await expect(page.getByRole('button', { name: /dodaj warunek/i })).toHaveCount(0);
    await page.getByRole('button', { name: /Dalej|Next/ }).click();

    // step 3: builder columns preselected (all)
    await expect(page.getByText(/Wybrane atrybuty \(4\)|Selected attributes \(4\)/)).toBeVisible({
      timeout: 5_000,
    });
    await page.getByRole('button', { name: /Dalej|Next/ }).click();

    // step 4: summary with full-structure scope; payload check on run
    await expect(page.getByText(/Pełna struktura|Full structure/)).toBeVisible();
    let sentEntity: string | null = null;
    await page.route('**/api/products/export', (route) => {
      const body = route.request().postDataJSON() as { entity_type: string };
      sentEntity = body.entity_type;
      route.fulfill({
        status: 202,
        contentType: 'application/json',
        body: JSON.stringify({ id: 'sess-x', status: 'pending' }),
      });
    });
    await page.route('**/api/exports/sessions', (route) =>
      route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({ items: [], total: 0 }),
      }),
    );
    await page.getByTestId('run-export').click();
    await expect(page).toHaveURL(/\/integrations\/exports\/sessions/);
    expect(sentEntity).toBe(entity.payload);
  });
}

test('custom_module happy path: ObjectType select + junction-narrowed columns', async ({
  page,
}) => {
  await page.getByRole('radio', { name: /Moduły własne|Custom modules/ }).click();
  await page.getByLabel(/Moduł własny|Custom module/).selectOption(CUSTOM_OT.id);
  await page.getByRole('button', { name: /Dalej|Next/ }).click();

  // step 2: query builder available for custom modules
  await expect(page.getByRole('button', { name: /dodaj warunek/i })).toBeVisible();
  await page.getByRole('button', { name: /Dalej|Next/ }).click();

  // step 3: junction narrowing — 'name' offered, 'only_products' not
  await expect(page.getByRole('checkbox', { name: /Nazwa/ })).toBeVisible();
  await expect(page.getByRole('checkbox', { name: /Tylko produkty/ })).toHaveCount(0);
  // locked natural key for custom modules = code
  await expect(page.getByText(/klucz|key/).first()).toBeVisible();
});
