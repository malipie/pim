import { expect, test } from '@playwright/test';

/**
 * Edge-side hardening (#98 / 0.11.3) — assert Caddy lays down the
 * full security-headers stack on every response served from the
 * single PIM origin. Prevents a future Caddyfile edit from silently
 * dropping a header without anyone noticing.
 */
test.describe('Security headers', () => {
  test('admin response carries the hardened header set', async ({ request }) => {
    const response = await request.get('/');
    expect(response.status()).toBe(200);

    const headers = response.headers();
    expect(headers['x-frame-options']).toBe('DENY');
    expect(headers['x-content-type-options']).toBe('nosniff');
    expect(headers['referrer-policy']).toBe('strict-origin-when-cross-origin');
    expect(headers['strict-transport-security']).toMatch(/max-age=\d+/);
    expect(headers['strict-transport-security']).toContain('includeSubDomains');
    expect(headers['cross-origin-opener-policy']).toBe('same-origin');
    expect(headers['cross-origin-resource-policy']).toBe('same-origin');

    const csp = headers['content-security-policy'];
    expect(csp).toContain("default-src 'self'");
    expect(csp).toContain("frame-ancestors 'none'");
    expect(csp).toContain("object-src 'none'");
    expect(csp).toContain("base-uri 'self'");

    const permissions = headers['permissions-policy'];
    expect(permissions).toContain('camera=()');
    expect(permissions).toContain('microphone=()');
    expect(permissions).toContain('geolocation=()');
  });

  test('api response inherits the same hardening', async ({ request }) => {
    const response = await request.get('/api');
    // Anonymous /api hits the entrypoint or the JWT firewall; either
    // way the headers stack must be present.
    expect([200, 401]).toContain(response.status());

    const headers = response.headers();
    expect(headers['content-security-policy']).toBeDefined();
    expect(headers['x-frame-options']).toBe('DENY');
    expect(headers['strict-transport-security']).toBeDefined();
  });

  test('server identifying header is stripped', async ({ request }) => {
    const response = await request.get('/');
    const headers = response.headers();
    expect(headers['server']).toBeUndefined();
  });
});
