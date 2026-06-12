import { useTranslation } from 'react-i18next';

import { RoleChip } from './RoleChip';
import { ScopePill } from './ScopePill';

export interface EffectivePermissionsPanelProps {
  /** Total atomic permissions across all modules (for the "X / Y" denominator). */
  totalPermissions: number;
  /** Sum of permissions granted by the currently-selected roles (after union). */
  effectivePermissions: number;
  /** Currently selected roles (post-edit, before save). */
  selectedRoles: Array<{ id: string; code: string; name: string }>;
  /** Per-user locale scope (`["*"]` or empty = unrestricted). */
  localeScope: string[];
  /** Per-user channel scope (`["*"]` or empty = unrestricted). */
  channelScope: string[];
  /** Sidebar key-value rows (status, created, last login, SSO) — optional. */
  meta?: ReadonlyArray<{ label: string; value: React.ReactNode }>;
}

/**
 * Right-rail summary card for the User Detail page per
 * `Zrodla/.../settings/users.jsx` §UserEditorPage right-column section.
 *
 * Renders a big effective-permissions count + progress bar, then the
 * currently-assigned roles + scope chips, then a list of metadata rows
 * (status / created / last login / SSO).
 */
export function EffectivePermissionsPanel({
  totalPermissions,
  effectivePermissions,
  selectedRoles,
  localeScope,
  channelScope,
  meta,
}: EffectivePermissionsPanelProps) {
  const { t } = useTranslation();
  const pct =
    totalPermissions > 0 ? Math.round((effectivePermissions / totalPermissions) * 100) : 0;

  return (
    <div className="sticky top-[88px] rounded-3xl bg-white p-5 shadow-[0_1px_2px_rgba(0,0,0,0.04),0_4px_12px_rgba(0,0,0,0.04)]">
      <div className="mb-3 text-[10.5px] font-medium uppercase tracking-wider text-zinc-500">
        {t('settings.users.detail.effective_permissions', {
          defaultValue: 'Efektywne uprawnienia',
        })}
      </div>

      <div className="font-mono text-[34px] font-semibold leading-none tracking-tight text-zinc-900">
        {effectivePermissions}
      </div>
      <div className="mt-1 text-[11.5px] text-zinc-500">
        {t('settings.users.detail.coverage_summary', {
          total: totalPermissions,
          pct,
          defaultValue: 'z {{total}} atomic permissions ({{pct}}% pokrycia)',
        })}
      </div>
      <div className="mt-3 h-2 overflow-hidden rounded-full bg-zinc-100">
        <div
          className="h-full bg-zinc-900 transition-all"
          style={{ width: `${Math.min(100, Math.max(0, pct))}%` }}
        />
      </div>

      <div className="mt-5 space-y-3 border-t border-zinc-100 pt-4">
        <div>
          <div className="mb-1.5 text-[10.5px] font-medium uppercase tracking-wider text-zinc-500">
            {t('settings.users.detail.assigned_roles', { defaultValue: 'Przypisane role' })}
          </div>
          {selectedRoles.length === 0 ? (
            <div className="text-[12px] text-rose-600">
              {t('settings.users.detail.no_roles_warning', {
                defaultValue: 'Brak ról — user nie będzie miał żadnych uprawnień.',
              })}
            </div>
          ) : (
            <div className="flex flex-wrap gap-1">
              {selectedRoles.map((role) => (
                <RoleChip key={role.id} code={role.code} name={role.name} size="sm" />
              ))}
            </div>
          )}
          {selectedRoles.length >= 2 ? (
            <div className="mt-1 font-mono text-[10.5px] text-zinc-500">
              {t('settings.users.role_union', {
                count: selectedRoles.length,
                defaultValue: 'union · {{count}} ról',
              })}
            </div>
          ) : null}
        </div>

        <div>
          <div className="mb-1.5 text-[10.5px] font-medium uppercase tracking-wider text-zinc-500">
            {t('settings.users.detail.scope_label', { defaultValue: 'Scope' })}
          </div>
          <div className="space-y-1">
            <ScopePill values={localeScope} kind="locale" />
            <ScopePill values={channelScope} kind="channel" />
          </div>
        </div>
      </div>

      {meta && meta.length > 0 ? (
        <div className="mt-5 space-y-1.5 border-t border-zinc-100 pt-4 text-[11.5px]">
          {meta.map((row) => (
            <div key={row.label} className="flex items-center justify-between">
              <span className="text-zinc-500">{row.label}</span>
              <span className="font-medium text-zinc-900">{row.value}</span>
            </div>
          ))}
        </div>
      ) : null}
    </div>
  );
}
