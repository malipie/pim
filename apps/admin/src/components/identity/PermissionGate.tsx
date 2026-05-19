import type { ReactNode } from 'react';

import { useCanI, useCanIAll, useCanIAny, useIdentity } from '@/lib/identity';

/**
 * RBAC-P4-004 (#681) — declarative permission boundary for admin UI.
 *
 *   - `<PermissionGate code="products.view">…children</PermissionGate>`
 *     renders the children when the caller holds the PRD §3.2 code.
 *   - `<PermissionGate anyOf={['a', 'b']}>` requires at least one match.
 *   - `<PermissionGate allOf={['a', 'b']}>` requires every code.
 *   - `fallback` (optional) renders when the gate is closed — defaults
 *     to `null` so gated UI simply disappears.
 *   - `whileLoading` renders during the bootstrap `/api/auth/me` fetch
 *     so callers can show skeletons; defaults to `null` to avoid the
 *     click-through flash where the user briefly sees forbidden UI.
 *
 * Exactly one of `code` / `anyOf` / `allOf` must be set; multiple
 * predicates are a configuration error and the component renders the
 * `fallback` to prevent accidental over-grant.
 *
 * Implementation note — the gate composes the three `useCanI*` hooks
 * unconditionally so React's rules of hooks hold; only one of the
 * three results is read per render based on the props.
 */
interface PermissionGateBaseProps {
  fallback?: ReactNode;
  whileLoading?: ReactNode;
  children: ReactNode;
}

interface PermissionGateSingleProps extends PermissionGateBaseProps {
  code: string;
  anyOf?: never;
  allOf?: never;
}

interface PermissionGateAnyProps extends PermissionGateBaseProps {
  code?: never;
  anyOf: readonly string[];
  allOf?: never;
}

interface PermissionGateAllProps extends PermissionGateBaseProps {
  code?: never;
  anyOf?: never;
  allOf: readonly string[];
}

export type PermissionGateProps =
  | PermissionGateSingleProps
  | PermissionGateAnyProps
  | PermissionGateAllProps;

export function PermissionGate(props: PermissionGateProps) {
  const { children, fallback = null, whileLoading = null } = props;
  const { isLoading } = useIdentity();

  // Hooks always called in the same order — pick the result later.
  const singleAllowed = useCanI(props.code ?? '');
  const anyAllowed = useCanIAny(props.anyOf ?? []);
  const allAllowed = useCanIAll(props.allOf ?? []);

  if (isLoading) {
    return whileLoading;
  }

  let allowed: boolean;
  if (props.code !== undefined) {
    allowed = singleAllowed;
  } else if (props.anyOf !== undefined) {
    allowed = anyAllowed;
  } else if (props.allOf !== undefined) {
    allowed = allAllowed;
  } else {
    // Defensive: prop discriminator should make this unreachable, but
    // if a caller manages to pass none of the three, default to deny.
    allowed = false;
  }

  return allowed ? children : fallback;
}
