import type { RoleListItem, RoleScope } from './types';

/**
 * UI re-align (#865) — resolve a role's platform/tenant scope from the
 * backend payload, falling back to the role `code` when the backend
 * hasn't yet exposed `scope`. `super_admin` is the only platform-scoped
 * role in the MVP catalogue; everything else (system or custom) is
 * tenant-scoped. The fallback keeps the §5.3 filter pills meaningful
 * before the backend extension lands.
 */
export function resolveRoleScope(role: Pick<RoleListItem, 'code' | 'scope'>): RoleScope {
  if (role.scope) return role.scope;
  return role.code === 'super_admin' ? 'platform' : 'tenant';
}
