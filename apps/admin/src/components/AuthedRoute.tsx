import { useIsAuthenticated } from '@refinedev/core';
import { useTranslation } from 'react-i18next';
import { Navigate, useLocation } from 'react-router';

import { useIdentity } from '@/lib/identity';

interface Props {
  children: React.ReactNode;
}

/**
 * `/first-login-password` is the only route an authenticated user with
 * `password_change_required: true` is allowed to visit — every other
 * deep link redirects here so the admin-set password cannot survive past
 * the first login. The page itself sits outside `<AuthedRoute>` so it
 * doesn't recurse on itself.
 */
const FIRST_LOGIN_PATH = '/first-login-password';

export function AuthedRoute({ children }: Props) {
  const { t } = useTranslation();
  const { data, isLoading } = useIsAuthenticated();
  const { identity, isLoading: identityLoading } = useIdentity();
  const location = useLocation();

  if (isLoading) {
    return <p className="p-6 text-sm text-muted-foreground">{t('app.loading')}</p>;
  }
  if (!data?.authenticated) {
    return <Navigate to="/login" replace />;
  }
  // Manual user creation (#867) — force-password-change gate. Hold off on
  // the redirect until `useIdentity()` has resolved so we don't bounce
  // through `/first-login-password` on every refresh while the bootstrap
  // GET /api/auth/me is still in flight.
  if (
    !identityLoading &&
    identity?.passwordChangeRequired &&
    location.pathname !== FIRST_LOGIN_PATH
  ) {
    return <Navigate to={FIRST_LOGIN_PATH} replace />;
  }
  return <>{children}</>;
}
