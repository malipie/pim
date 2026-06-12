import { useTranslation } from 'react-i18next';

import { cn } from '@/lib/utils';

export type ScopeKind = 'locale' | 'channel';

export interface ScopePillProps {
  values: readonly string[] | null | undefined;
  kind: ScopeKind;
}

/**
 * Renders the user's locale/channel scope per `Zrodla/.../settings/users.jsx`
 * §ScopePill. Empty / `["*"]` arrays display as muted "wszystkie"; otherwise
 * values are upper-cased and joined with `·`.
 *
 * Locale scope chips use violet, channel scope chips use cyan — matches the
 * design data palette (`bg-orange-50` / `bg-cyan-50`).
 */
export function ScopePill({ values, kind }: ScopePillProps) {
  const { t } = useTranslation();

  const list = values ?? [];
  const isUnrestricted = list.length === 0 || (list.length === 1 && list[0] === '*');

  if (isUnrestricted) {
    return (
      <span className="text-[11px] text-zinc-500">
        {t('settings.users.scope_all', { defaultValue: 'wszystkie' })}
      </span>
    );
  }

  const labels = list.map((v) => v.toUpperCase()).join(' · ');
  const cls =
    kind === 'locale'
      ? 'bg-orange-50 text-orange-700 ring-orange-200'
      : 'bg-cyan-50 text-cyan-700 ring-cyan-200';

  return (
    <span
      className={cn(
        'inline-flex items-center gap-1 rounded px-1.5 py-0.5 font-mono text-[10.5px] font-medium ring-1',
        cls,
      )}
    >
      <span aria-hidden>{kind === 'locale' ? '🌐' : '📡'}</span>
      {labels}
    </span>
  );
}
