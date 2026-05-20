import type { ComponentProps, ReactNode } from 'react';

import { Button } from '@/components/ui/button';
import { useCanI, useCanIAny } from '@/lib/identity';

/**
 * RBAC-P6-005 (#717) — declarative wrapper that hides action-bearing
 * UI when the caller lacks the backing permission.
 *
 * The pattern is the click-time companion to {@link PermissionGate}.
 * `<PermissionGate>` is best for whole sections (a panel, a settings
 * tab, a row of unrelated cards). `<GatedAction>` is meant for the
 * inline "+ New" / "Bulk edit" / "Delete selected" / "Publish" / "Import"
 * buttons that punctuate list views and detail pages — where the
 * surrounding chrome should stay visible but the specific verb the user
 * cannot perform should disappear (or render disabled with a tooltip
 * explaining why).
 *
 * Choose one of two modes:
 *
 *   - `mode="hide"` (default) — when the permission is missing, render
 *     nothing. Best for primary CTAs (`+ New product`) where there is no
 *     value showing an inert button.
 *   - `mode="disabled"` — render the children with `aria-disabled` + a
 *     visual washed-out state, and intercept clicks. Best when the
 *     surrounding layout depends on the button's footprint (toolbars
 *     with fixed gaps, action menus that would jump around on toggle).
 *
 * Usage:
 *
 *   <GatedAction permission="products.add">
 *     <Button onClick={openNewProductDialog}>+ Nowy produkt</Button>
 *   </GatedAction>
 *
 *   <GatedAction anyOf={['products.delete', 'products.bulk_operations']} mode="disabled">
 *     <Button variant="destructive">Usuń</Button>
 *   </GatedAction>
 *
 * The component composes `useCanI` / `useCanIAny`; tests can render
 * with the identity provider seeded to whichever permission set they
 * need. While identity is bootstrapping (`useIdentity().isLoading`),
 * the wrapped action is treated as denied so the brief flash of a
 * forbidden button cannot prompt a 403 from the backend.
 */
interface GatedActionBaseProps {
  mode?: 'hide' | 'disabled';
  children: ReactNode;
}

interface GatedActionSingleProps extends GatedActionBaseProps {
  permission: string;
  anyOf?: never;
}

interface GatedActionAnyProps extends GatedActionBaseProps {
  permission?: never;
  anyOf: readonly string[];
}

export type GatedActionProps = GatedActionSingleProps | GatedActionAnyProps;

export function GatedAction(props: GatedActionProps) {
  const { mode = 'hide', children } = props;

  const singleAllowed = useCanI(props.permission ?? '');
  const anyAllowed = useCanIAny(props.anyOf ?? []);
  const allowed = props.permission !== undefined ? singleAllowed : anyAllowed;

  if (allowed) {
    return children;
  }

  if (mode === 'hide') {
    return null;
  }

  return (
    <span
      aria-disabled="true"
      data-permission-denied="true"
      className="cursor-not-allowed opacity-50"
    >
      <span className="pointer-events-none">{children}</span>
    </span>
  );
}

/**
 * Convenience: a permission-aware `<Button>` for the common case where
 * the action verb is hidden when the permission is missing. Forwards
 * every Button prop, so feature pages drop in the same way they would
 * with `<Button>` and just add `permission="…"`.
 */
type ButtonOwnProps = ComponentProps<typeof Button>;

interface GatedButtonSingleProps extends ButtonOwnProps {
  permission: string;
  anyOf?: never;
  mode?: 'hide' | 'disabled';
}

interface GatedButtonAnyProps extends ButtonOwnProps {
  permission?: never;
  anyOf: readonly string[];
  mode?: 'hide' | 'disabled';
}

export type GatedButtonProps = GatedButtonSingleProps | GatedButtonAnyProps;

export function GatedButton({ permission, anyOf, mode, ...buttonProps }: GatedButtonProps) {
  if (permission !== undefined) {
    return (
      <GatedAction permission={permission} mode={mode}>
        <Button {...buttonProps} />
      </GatedAction>
    );
  }
  return (
    <GatedAction anyOf={anyOf as readonly string[]} mode={mode}>
      <Button {...buttonProps} />
    </GatedAction>
  );
}
