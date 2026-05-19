import { ShieldOff } from 'lucide-react';
import type { ReactNode } from 'react';
import { useTranslation } from 'react-i18next';

import { useCanI, useCanIAll, useCanIAny, useIdentity } from '@/lib/identity';

/**
 * RBAC-P4-003 (#680) — route-level permission boundary.
 *
 * Pairs with the existing `AuthedRoute` (auth-only) to add a permission
 * check on top:
 *
 *   <Route element={<PermissionRoute code="settings.users.manage" />}>
 *     <Route path="settings/users" element={<UsersListPage />} />
 *   </Route>
 *
 * Renders the {@link Forbidden403Page} fallback when the check fails so
 * navigation does not look broken; the URL stays put and the user can
 * choose to navigate away.
 *
 * Implementation note — uses `Outlet` indirectly through `children`;
 * callers wire it as a layout route. The component is a leaf wrapper,
 * not a router primitive, so callers compose it with `react-router`'s
 * `<Route element={...}>` pattern that renders the matched child.
 */
interface BaseProps {
  fallback?: ReactNode;
  children: ReactNode;
}

interface SingleProps extends BaseProps {
  code: string;
  anyOf?: never;
  allOf?: never;
}

interface AnyProps extends BaseProps {
  code?: never;
  anyOf: readonly string[];
  allOf?: never;
}

interface AllProps extends BaseProps {
  code?: never;
  anyOf?: never;
  allOf: readonly string[];
}

export type PermissionRouteProps = SingleProps | AnyProps | AllProps;

export function PermissionRoute(props: PermissionRouteProps) {
  const { children, fallback = <Forbidden403Page /> } = props;
  const { isLoading } = useIdentity();
  const singleAllowed = useCanI(props.code ?? '');
  const anyAllowed = useCanIAny(props.anyOf ?? []);
  const allAllowed = useCanIAll(props.allOf ?? []);

  if (isLoading) {
    return <RouteLoadingShim />;
  }

  let allowed: boolean;
  if (props.code !== undefined) {
    allowed = singleAllowed;
  } else if (props.anyOf !== undefined) {
    allowed = anyAllowed;
  } else if (props.allOf !== undefined) {
    allowed = allAllowed;
  } else {
    allowed = false;
  }

  return allowed ? children : fallback;
}

function RouteLoadingShim() {
  const { t } = useTranslation();

  return <p className="p-6 text-sm text-muted-foreground">{t('app.loading')}</p>;
}

/**
 * 403 page rendered when a route's permission check fails. Kept inside
 * the same module so it can be the default fallback without forcing
 * callers to import a separate page.
 */
export function Forbidden403Page() {
  const { t } = useTranslation();

  return (
    <div className="flex min-h-[60vh] flex-col items-center justify-center gap-4 p-8 text-center">
      <ShieldOff className="size-16 text-muted-foreground" aria-hidden="true" />
      <h1 className="text-2xl font-semibold">{t('rbac.forbidden.title', 'Access denied')}</h1>
      <p className="max-w-md text-sm text-muted-foreground">
        {t(
          'rbac.forbidden.body',
          'You do not have permission to view this page. If you believe this is a mistake, contact your tenant administrator.',
        )}
      </p>
    </div>
  );
}
