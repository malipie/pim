import { expect, test } from '@playwright/test';

/**
 * Anti-bruteforce rate limit behavioral contract (audit / 0.11.5).
 *
 * `framework.rate_limiter.auth_login`:
 *   policy: fixed_window
 *   limit:  5 attempts per IP per 15-minute window
 *
 * The 6th wrong-credentials attempt within the window must produce
 * HTTP 429 — verified via Playwright's API request fixture so the test
 * stays close to the contract integrators care about, not the UI's
 * error surface.
 *
 * Uses a deliberately bogus account that does NOT exist in the demo
 * fixture so successful authentications cannot interfere even if the
 * test runs concurrently with other suites (which it should not — the
 * config pins `workers: 1`, but defence-in-depth is cheap).
 */
test.describe('Authentication rate limit', () => {
  test('sixth login attempt with bad credentials returns 429', async ({ request }) => {
    const credentials = {
      email: `nonexistent-${Date.now()}@example.invalid`,
      password: 'wrong-password-on-purpose',
    };

    const responses: number[] = [];
    for (let attempt = 1; attempt <= 6; attempt++) {
      const response = await request.post('/api/auth/login', {
        data: credentials,
        failOnStatusCode: false,
      });
      responses.push(response.status());
    }

    // First five attempts: 401 unauthorized (or 400 validation).
    // The sixth: 429 from the rate limiter.
    expect(responses.slice(0, 5)).toEqual(
      responses.slice(0, 5).map((status) => {
        expect([400, 401]).toContain(status);
        return status;
      }),
    );
    expect(responses[5]).toBe(429);
  });
});
