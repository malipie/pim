import { render, screen } from '@testing-library/react';
import { MemoryRouter } from 'react-router';
import { beforeEach, describe, expect, it, vi } from 'vitest';

import { PermissionRoute } from '../PermissionRoute';

/**
 * AUD-076 (W3-5.5) — `<PermissionRoute>` is the route-level boundary that
 * stops an unauthorised user from rendering a protected page just by typing
 * its URL (the UX leak: the backend returns 403 only after the page mounted
 * and fired its data request). These tests assert the synchronous decision:
 *
 *   - permission held         → protected children render,
 *   - permission missing       → Forbidden403Page fallback renders,
 *   - identity still loading   → neither the children nor the fallback show.
 *
 * The `@/lib/identity` hooks are mocked so the component is exercised in
 * isolation without a QueryClient / `/api/auth/me` round-trip.
 */

const mocks = vi.hoisted(() => ({
  isLoading: false,
  can: new Set<string>(),
}));

vi.mock('@/lib/identity', () => ({
  useIdentity: () => ({ identity: null, isLoading: mocks.isLoading, isError: false }),
  useCanI: (code: string) => mocks.can.has(code),
  useCanIAny: (codes: readonly string[]) => codes.some((c) => mocks.can.has(c)),
  useCanIAll: (codes: readonly string[]) => codes.every((c) => mocks.can.has(c)),
}));

function renderRoute(ui: React.ReactNode) {
  return render(<MemoryRouter initialEntries={['/admin/break-glass']}>{ui}</MemoryRouter>);
}

const PROTECTED = <div data-testid="protected">secret panel</div>;

describe('PermissionRoute', () => {
  beforeEach(() => {
    mocks.isLoading = false;
    mocks.can = new Set();
  });

  it('renders the protected children when the single code is held', () => {
    mocks.can = new Set(['platform.break_glass_recovery']);

    renderRoute(
      <PermissionRoute code="platform.break_glass_recovery">{PROTECTED}</PermissionRoute>,
    );

    expect(screen.getByTestId('protected')).toBeInTheDocument();
    expect(
      screen.queryByRole('heading', { name: /brak dostępu|forbidden/i }),
    ).not.toBeInTheDocument();
  });

  it('renders the Forbidden403Page fallback when the code is missing', () => {
    mocks.can = new Set(['products.view']); // unrelated grant

    renderRoute(
      <PermissionRoute code="platform.break_glass_recovery">{PROTECTED}</PermissionRoute>,
    );

    // Protected content must never mount for an unauthorised user.
    expect(screen.queryByTestId('protected')).not.toBeInTheDocument();
    // The polished 403 page (heading region) renders instead.
    expect(screen.getByRole('heading', { level: 1 })).toBeInTheDocument();
  });

  it('grants access via anyOf when at least one code matches', () => {
    mocks.can = new Set(['settings.roles.manage']);

    renderRoute(
      <PermissionRoute anyOf={['settings.users.manage', 'settings.roles.manage']}>
        {PROTECTED}
      </PermissionRoute>,
    );

    expect(screen.getByTestId('protected')).toBeInTheDocument();
  });

  it('denies access via anyOf when no code matches', () => {
    mocks.can = new Set(['products.view']);

    renderRoute(
      <PermissionRoute anyOf={['settings.users.manage', 'settings.roles.manage']}>
        {PROTECTED}
      </PermissionRoute>,
    );

    expect(screen.queryByTestId('protected')).not.toBeInTheDocument();
    expect(screen.getByRole('heading', { level: 1 })).toBeInTheDocument();
  });

  it('shows neither children nor fallback while identity is still loading', () => {
    mocks.isLoading = true;
    mocks.can = new Set(['platform.break_glass_recovery']);

    renderRoute(
      <PermissionRoute code="platform.break_glass_recovery">{PROTECTED}</PermissionRoute>,
    );

    // Loading shim — protected content is held back to avoid the
    // click-through flash, and the 403 page is not shown yet either.
    expect(screen.queryByTestId('protected')).not.toBeInTheDocument();
    expect(screen.queryByRole('heading', { level: 1 })).not.toBeInTheDocument();
  });
});
