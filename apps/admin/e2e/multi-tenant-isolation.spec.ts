import { expect, test } from '@playwright/test';

/**
 * Multi-tenant isolation behavioral contract (audit / 0.11.5).
 *
 * The fixture ships two tenants — `demo` and `acme` — each with its own
 * admin user (`admin@demo.localhost` / `admin@acme.localhost`, both with
 * the seeded password `changeme`). `Doctrine\TenantFilter` +
 * `TenantAssignmentListener` enforce that one tenant cannot read another
 * tenant's catalog.
 *
 * The test exercises the contract at the API layer (Playwright's
 * `request` fixture, no DOM) so the assertion stays focused on the
 * cross-tenant leak surface itself rather than admin UI markup that is
 * still evolving.
 */
test.describe('Multi-tenant isolation', () => {
  test('demo and acme tenant catalogs do not leak across the JWT boundary', async ({ request }) => {
    const demoToken = await login(request, 'admin@demo.localhost', 'changeme');
    const acmeToken = await login(request, 'admin@acme.localhost', 'changeme');

    const demoSkus = await listSkus(request, demoToken);
    const acmeSkus = await listSkus(request, acmeToken);

    expect(demoSkus.length, 'demo fixture must contain at least one product').toBeGreaterThan(0);
    expect(acmeSkus.length, 'acme fixture must contain at least one product').toBeGreaterThan(0);

    // No ACME row may surface in demo's catalog and vice versa.
    expect(demoSkus.some((sku) => sku.startsWith('ACME-'))).toBe(false);
    expect(acmeSkus.some((sku) => sku.startsWith('DEMO-'))).toBe(false);

    // SKU sets must be fully disjoint.
    const overlap = demoSkus.filter((sku) => acmeSkus.includes(sku));
    expect(overlap, 'tenant catalogs leaked across the boundary').toEqual([]);
  });
});

async function login(
  request: import('@playwright/test').APIRequestContext,
  email: string,
  password: string,
): Promise<string> {
  const response = await request.post('/api/auth/login', {
    data: { email, password },
  });
  expect(response.status(), `login as ${email} must succeed`).toBe(200);

  const body = (await response.json()) as { token?: string };
  expect(body.token, 'login response must carry a JWT token').toBeDefined();

  return body.token as string;
}

async function listSkus(
  request: import('@playwright/test').APIRequestContext,
  token: string,
): Promise<string[]> {
  const response = await request.get('/api/products?itemsPerPage=200', {
    headers: { Authorization: `Bearer ${token}` },
  });
  expect(response.status()).toBe(200);

  const body = (await response.json()) as {
    'hydra:member'?: Array<{ code?: string }>;
    member?: Array<{ code?: string }>;
  };
  // AP4 ships JSON-LD response shape; the collection key was renamed
  // from `hydra:member` to `member` in the JSON-LD context update.
  // Read both for forward/backward compatibility while we settle on
  // the final contract.
  const items = body['hydra:member'] ?? body.member ?? [];
  return items.map((item) => item.code).filter((sku): sku is string => typeof sku === 'string');
}
