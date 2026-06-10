import { useGetIdentity } from '@refinedev/core';
import { useQuery } from '@tanstack/react-query';
import { Boxes, FolderTree, Layers, Package, Pencil, Play, Plus, Tags, Trash2 } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { Link, useNavigate } from 'react-router';
import { toast } from '@/components/ui/toast';
import { EmptyState } from '@/components/ui-v2/empty-state';
import { FormatPill } from '@/components/ui-v2/format-pill';
import { type FilterDsl, isFilterGroup } from '@/lib/filters/filter-dsl';
import { jsonFetch } from '@/lib/http';

import { useInvalidateExportSessions } from '../hooks/useExportSessions';
import { entityTypeLabelKey, formatStartedAt } from '../sessions/session-format';

interface ExportProfileRow {
  id: string;
  name: string;
  description: string | null;
  entity_type: string;
  object_type_id: string | null;
  config: {
    format?: string;
    selected_columns?: string[];
    filter?: FilterDsl | null;
  };
  last_run_at: string | null;
  run_count: number;
}

interface ProfilesResponse {
  items: ExportProfileRow[];
  total: number;
}

interface Identity {
  name?: string;
  email?: string;
}

const ENTITY_ICONS: Record<string, typeof Package> = {
  product: Package,
  custom_module: Boxes,
  module_schema: Layers,
  attributes_groups: Tags,
  categories: FolderTree,
};

function filterChipsOf(profile: ExportProfileRow): string[] {
  const dsl = profile.config.filter ?? null;
  if (dsl === null) return [];
  if (!isFilterGroup(dsl)) return [dsl.attr];
  return dsl.conditions.flatMap((entry) => (isFilterGroup(entry) ? [] : [entry.attr]));
}

/**
 * EXR-13 (#1389) — Export Profiles tab in the v2 design: table with
 * entity / format / columns / filters summary and per-row actions.
 * "Uruchom teraz" uses the dedicated profile-run endpoint (async
 * dispatch + run_count telemetry; the dev sync transport executes
 * small jobs inline anyway). Edit opens the wizard prefilled via
 * ?profile={id} (EXR-12 store init).
 */
export function ExportProfilesView(): React.ReactElement {
  const { t } = useTranslation();
  const navigate = useNavigate();
  const { data: identity } = useGetIdentity<Identity>();
  const invalidateSessions = useInvalidateExportSessions();

  const profilesQuery = useQuery<ProfilesResponse>({
    queryKey: ['exports', 'profiles', 'list'],
    queryFn: () =>
      jsonFetch<ProfilesResponse>('/api/exports/profiles', { accept: 'application/json' }),
    staleTime: 5_000,
  });
  const profiles = profilesQuery.data?.items ?? [];

  const onRun = async (profile: ExportProfileRow) => {
    try {
      const session = await jsonFetch<{ id: string }>(`/api/exports/profiles/${profile.id}/run`, {
        method: 'POST',
        accept: 'application/json',
      });
      invalidateSessions();
      toast.success(t('exports.profiles.run_success', { name: profile.name }));
      void navigate('/integrations/exports/sessions', {
        state: { highlightSession: session.id },
      });
    } catch {
      toast.error(t('exports.profiles.run_failed'));
    }
  };

  const onDelete = async (profile: ExportProfileRow) => {
    if (!window.confirm(t('exports.profiles.confirm_delete', { name: profile.name }))) {
      return;
    }
    try {
      await jsonFetch(`/api/exports/profiles/${profile.id}`, {
        method: 'DELETE',
        accept: 'application/json',
      });
      void profilesQuery.refetch();
      toast.success(t('exports.profiles.deleted', { name: profile.name }));
    } catch {
      toast.error(t('exports.profiles.delete_failed'));
    }
  };

  if (profilesQuery.isLoading) {
    return <div className="h-40 animate-pulse rounded-2xl bg-zinc-100" aria-busy="true" />;
  }

  if (profiles.length === 0) {
    return (
      <div className="rounded-2xl border border-dashed border-zinc-200 bg-surface">
        <EmptyState
          title={t('exports.profiles.empty_title')}
          description={t('exports.profiles.empty_subtitle')}
          action={
            <Link
              to="/integrations/exports/new"
              className="focus-ring inline-flex h-9 items-center gap-1.5 rounded-xl bg-cta px-3.5 text-[13px] font-semibold text-cta-foreground transition hover:bg-accent-hover"
            >
              <Plus className="size-4" aria-hidden />
              {t('exports.new_cta')}
            </Link>
          }
        />
      </div>
    );
  }

  return (
    <div className="overflow-hidden rounded-2xl border border-zinc-200 bg-surface shadow-card">
      <div className="overflow-x-auto">
        <table className="w-full text-[13px]">
          <thead>
            <tr className="text-left">
              {(
                [
                  'col_name',
                  'col_entity',
                  'col_format',
                  'col_columns',
                  'col_filters',
                  'col_owner',
                  'col_last_run',
                  'col_runs',
                ] as const
              ).map((key) => (
                <th
                  key={key}
                  className="px-4 py-3 text-[11px] font-medium tracking-wider text-zinc-500 uppercase"
                >
                  {t(`exports.profiles.${key}`)}
                </th>
              ))}
              <th aria-hidden className="w-28" />
            </tr>
          </thead>
          <tbody className="divide-y divide-zinc-100">
            {profiles.map((profile) => {
              const EntityIcon = ENTITY_ICONS[profile.entity_type] ?? Package;
              const chips = filterChipsOf(profile);
              return (
                <tr key={profile.id} className="transition-colors hover:bg-zinc-50">
                  <td className="px-4 py-3">
                    <div className="font-medium text-ink">{profile.name}</div>
                    {profile.description !== null && profile.description !== '' && (
                      <div className="truncate text-[11.5px] text-zinc-500">
                        {profile.description}
                      </div>
                    )}
                  </td>
                  <td className="px-4 py-3">
                    <span className="flex items-center gap-2 text-zinc-600">
                      <span className="grid h-6 w-6 shrink-0 place-items-center rounded-md bg-zinc-100 text-zinc-500">
                        <EntityIcon className="size-3.5" aria-hidden />
                      </span>
                      {t(entityTypeLabelKey(profile.entity_type))}
                    </span>
                  </td>
                  <td className="px-4 py-3">
                    <FormatPill format={profile.config.format ?? 'xlsx'} />
                  </td>
                  <td className="num px-4 py-3 font-mono text-zinc-600">
                    {profile.config.selected_columns?.length ?? 0}
                  </td>
                  <td className="px-4 py-3">
                    {chips.length === 0 ? (
                      <span className="text-zinc-500">—</span>
                    ) : (
                      <span className="flex flex-wrap gap-1">
                        {chips.slice(0, 2).map((chip) => (
                          <span
                            key={chip}
                            className="rounded bg-zinc-100 px-1.5 py-0.5 font-mono text-[10.5px] text-zinc-600"
                          >
                            {chip}
                          </span>
                        ))}
                        {chips.length > 2 && (
                          <span className="text-[11px] text-zinc-500">+{chips.length - 2}</span>
                        )}
                      </span>
                    )}
                  </td>
                  <td className="px-4 py-3 text-zinc-600">
                    {identity?.name ?? identity?.email ?? '—'}
                  </td>
                  <td className="num px-4 py-3 font-mono text-[12px] text-zinc-600">
                    {profile.last_run_at === null ? '—' : formatStartedAt(profile.last_run_at)}
                  </td>
                  <td className="num px-4 py-3 font-mono text-zinc-600">{profile.run_count}</td>
                  <td className="px-2 py-3">
                    <div className="flex items-center justify-end gap-0.5">
                      <button
                        type="button"
                        onClick={() => void onRun(profile)}
                        aria-label={t('exports.profiles.action_run', { name: profile.name })}
                        title={t('exports.profiles.action_run', { name: profile.name })}
                        className="focus-ring grid h-7 w-7 place-items-center rounded-md text-zinc-500 hover:bg-zinc-100 hover:text-ink"
                      >
                        <Play className="size-3.5" aria-hidden />
                      </button>
                      <Link
                        to={`/integrations/exports/new?profile=${profile.id}`}
                        aria-label={t('exports.profiles.action_edit', { name: profile.name })}
                        title={t('exports.profiles.action_edit', { name: profile.name })}
                        className="focus-ring grid h-7 w-7 place-items-center rounded-md text-zinc-500 hover:bg-zinc-100 hover:text-ink"
                      >
                        <Pencil className="size-3.5" aria-hidden />
                      </Link>
                      <button
                        type="button"
                        onClick={() => void onDelete(profile)}
                        aria-label={t('exports.profiles.action_delete', { name: profile.name })}
                        title={t('exports.profiles.action_delete', { name: profile.name })}
                        className="focus-ring grid h-7 w-7 place-items-center rounded-md text-zinc-500 hover:bg-brick-50 hover:text-brick-600"
                      >
                        <Trash2 className="size-3.5" aria-hidden />
                      </button>
                    </div>
                  </td>
                </tr>
              );
            })}
          </tbody>
        </table>
      </div>
    </div>
  );
}

export default ExportProfilesView;
