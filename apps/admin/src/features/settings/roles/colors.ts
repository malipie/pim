/**
 * RBAC role color map — keyed by `Role.code`. Source of truth:
 * `Zrodla/Front_Claude_Design/PIM-nowoczesny/settings/data.jsx` §RBAC_ROLES.
 *
 * Each role gets a paired `dot` (1.5×1.5 indicator) + `chip` (background +
 * text + ring) so list rows, sidebar chips, and editor cards all render
 * the same colour identity. Custom roles fall through to `pink` per design.
 */
export interface RoleColor {
  /** Tailwind utility for the inline `<span>` dot (e.g. `bg-emerald-500`). */
  dot: string;
  /** Tailwind compound for chip styling (bg + text + ring). */
  chip: string;
}

export const ROLE_COLORS: Record<string, RoleColor> = {
  super_admin: {
    dot: 'bg-rose-500',
    chip: 'bg-rose-50 text-rose-700 ring-rose-200',
  },
  tenant_owner: {
    dot: 'bg-zinc-900',
    chip: 'bg-zinc-900 text-white ring-zinc-700',
  },
  admin: {
    dot: 'bg-zinc-600',
    chip: 'bg-zinc-100 text-zinc-800 ring-zinc-300',
  },
  catalog_manager: {
    dot: 'bg-emerald-500',
    chip: 'bg-emerald-50 text-emerald-700 ring-emerald-200',
  },
  marketing: {
    dot: 'bg-violet-500',
    chip: 'bg-violet-50 text-violet-700 ring-violet-200',
  },
  content_editor: {
    dot: 'bg-violet-500',
    chip: 'bg-violet-50 text-violet-700 ring-violet-200',
  },
  modeler: {
    dot: 'bg-blue-500',
    chip: 'bg-blue-50 text-blue-700 ring-blue-200',
  },
  information_architect: {
    dot: 'bg-blue-500',
    chip: 'bg-blue-50 text-blue-700 ring-blue-200',
  },
  integration_manager: {
    dot: 'bg-amber-500',
    chip: 'bg-amber-50 text-amber-700 ring-amber-200',
  },
  channel_manager: {
    dot: 'bg-cyan-500',
    chip: 'bg-cyan-50 text-cyan-700 ring-cyan-200',
  },
  approver: {
    dot: 'bg-orange-500',
    chip: 'bg-orange-50 text-orange-700 ring-orange-200',
  },
  viewer: {
    dot: 'bg-zinc-400',
    chip: 'bg-zinc-100 text-zinc-700 ring-zinc-200',
  },
};

/** Fallback for custom roles (non-system) — pink per `data.jsx` example. */
export const CUSTOM_ROLE_COLOR: RoleColor = {
  dot: 'bg-pink-500',
  chip: 'bg-pink-50 text-pink-700 ring-pink-200',
};

export function resolveRoleColor(code: string | undefined | null): RoleColor {
  if (!code) return CUSTOM_ROLE_COLOR;
  return ROLE_COLORS[code] ?? CUSTOM_ROLE_COLOR;
}
