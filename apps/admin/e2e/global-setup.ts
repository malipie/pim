import { spawnSync } from 'node:child_process';

/**
 * HARD-09 — clear rate-limiter buckets before the Playwright suite runs.
 *
 * `auth_login` (5/IP/15min in prod, 50/IP/15min in the dev override) and
 * `auth_refresh` (30/IP/h) accumulate across dev sessions and bleed into
 * the next test run. modeling-shell.spec.ts is alphabetically late in
 * the suite and was getting hit by 429 every time the operator iterated
 * locally — the JWT in module-scope memory dies on every `page.goto`
 * (full reload), `authProvider.check()` calls `/api/auth/refresh`, and
 * if that returns 429 the user redirects to /login mid-spec. Lessons.md
 * "received string `https://pim.localhost/login`" reports were exactly
 * this pattern.
 *
 * The reset uses `docker compose exec api bin/console cache:pool:clear`
 * so it works in dev (Docker stack up) and is a no-op when the stack is
 * not running locally — the test will fail loudly anyway when the
 * services are down. CI runs against a separate compose stack with
 * its own buckets; the same command applies.
 */
export default async function globalSetup(): Promise<void> {
  if (process.env.PLAYWRIGHT_SKIP_RATE_LIMITER_RESET === '1') {
    return;
  }

  const result = spawnSync(
    'docker',
    ['compose', 'exec', '-T', 'api', 'bin/console', 'cache:pool:clear', 'cache.rate_limiter'],
    {
      encoding: 'utf8',
      stdio: 'pipe',
      cwd: process.cwd().replace(/\/apps\/admin$/, ''),
    },
  );

  if (result.status !== 0) {
    // Best-effort — surface the warning but do not block the suite.
    // Operators running tests against a remote stack do not have
    // docker access, and the helper.loginAsAdmin() retry-on-429
    // covers that case.
    // eslint-disable-next-line no-console
    console.warn(
      'globalSetup: cache:pool:clear cache.rate_limiter failed (likely docker compose unreachable). ' +
        'Tests will rely on loginAsAdmin retry-on-429.',
      result.stderr?.slice(0, 200),
    );
    return;
  }

  // eslint-disable-next-line no-console
  console.log('globalSetup: rate-limiter buckets cleared.');
}
