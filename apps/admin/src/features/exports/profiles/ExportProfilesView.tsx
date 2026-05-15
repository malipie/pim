import { useApiUrl, useCustom, useCustomMutation } from '@refinedev/core';
import { useTranslation } from 'react-i18next';

interface ExportProfileRow {
  id: string;
  name: string;
  description: string | null;
  config: Record<string, unknown>;
  last_run_at: string | null;
  run_count: number;
  created_at: string;
  updated_at: string;
}

interface ProfilesResponse {
  items: ExportProfileRow[];
  total: number;
}

/**
 * EXP-14 (#593) — Saved profiles grid.
 *
 * `GET /api/exports/profiles` (per-user scope, PRD §3.3 punkt 2)
 * with per-row actions:
 *   - Run now → POST `/{id}/run` → toast + redirect to sessions tab
 *     so the user sees the freshly-dispatched session.
 *   - Delete → DELETE the profile (existing sessions keep their
 *     profile_id set to null per the DB cascade behavior).
 *
 * Edit (open the modal pre-filled) is a follow-up tied to EXP-11
 * modal landing — the modal is the only surface that knows how to
 * render the column picker, so duplicating that here would be
 * premature.
 */
export function ExportProfilesView(): React.ReactElement {
  const { t } = useTranslation();
  const apiUrl = useApiUrl();

  const { result, query } = useCustom<ProfilesResponse>({
    url: `${apiUrl}/exports/profiles`,
    method: 'get',
    queryOptions: { staleTime: 5000 },
  });

  const { mutate: runNow } = useCustomMutation();
  const { mutate: del } = useCustomMutation();

  const profiles = result?.data?.items ?? [];

  const onRun = (id: string, name: string) => {
    runNow(
      {
        url: `${apiUrl}/exports/profiles/${id}/run`,
        method: 'post',
        values: {},
        successNotification: () => ({
          message: t('exports.profiles.run_success', {
            name,
            defaultValue: `Profile "${name}" — eksport rozpoczęty`,
          }),
          type: 'success',
        }),
      },
      {
        onSuccess: () => {
          window.location.href = '/integrations/exports/sessions';
        },
      },
    );
  };

  const onDelete = (id: string, name: string) => {
    if (
      !window.confirm(
        t('exports.profiles.confirm_delete', {
          name,
          defaultValue: `Usunąć profil "${name}"? Historia eksportów uruchomionych z tego profilu pozostaje, ale tracą powiązanie.`,
        }),
      )
    ) {
      return;
    }
    del(
      {
        url: `${apiUrl}/exports/profiles/${id}`,
        method: 'delete',
        values: {},
      },
      {
        onSuccess: () => {
          void query.refetch();
        },
      },
    );
  };

  if (profiles.length === 0) {
    return (
      <div className="rounded-md border border-dashed bg-muted/30 p-8 text-center">
        <h2 className="text-lg font-medium">
          {t('exports.profiles.empty_title', { defaultValue: 'Brak zapisanych profili eksportu' })}
        </h2>
        <p className="mt-2 text-sm text-muted-foreground">
          {t('exports.profiles.empty_subtitle', {
            defaultValue:
              'Zapisujesz profil w modalu Eksport — zaznacz "Zapisz jako profil" przed submit.',
          })}
        </p>
      </div>
    );
  }

  return (
    <div className="overflow-x-auto rounded-md border">
      <table className="w-full text-sm">
        <thead className="bg-muted/50 text-xs uppercase text-muted-foreground">
          <tr>
            <th className="px-3 py-2 text-left">
              {t('exports.profiles.col_name', { defaultValue: 'Nazwa' })}
            </th>
            <th className="px-3 py-2 text-left">
              {t('exports.profiles.col_created', { defaultValue: 'Utworzono' })}
            </th>
            <th className="px-3 py-2 text-left">
              {t('exports.profiles.col_last_run', { defaultValue: 'Ostatnie uruchomienie' })}
            </th>
            <th className="px-3 py-2 text-right">
              {t('exports.profiles.col_run_count', { defaultValue: 'Liczba uruchomień' })}
            </th>
            <th className="px-3 py-2 text-right">
              {t('exports.profiles.col_actions', { defaultValue: 'Akcje' })}
            </th>
          </tr>
        </thead>
        <tbody className="divide-y">
          {profiles.map((profile) => (
            <tr key={profile.id} className="hover:bg-muted/30">
              <td className="px-3 py-2">
                <div className="font-medium">{profile.name}</div>
                {profile.description !== null && profile.description !== '' && (
                  <div className="mt-0.5 text-xs text-muted-foreground">{profile.description}</div>
                )}
              </td>
              <td className="px-3 py-2 font-mono text-xs">
                {new Date(profile.created_at).toLocaleDateString()}
              </td>
              <td className="px-3 py-2 font-mono text-xs">
                {profile.last_run_at !== null
                  ? new Date(profile.last_run_at).toLocaleString()
                  : '—'}
              </td>
              <td className="px-3 py-2 text-right font-mono text-xs">{profile.run_count}</td>
              <td className="px-3 py-2 text-right">
                <div className="inline-flex gap-1">
                  <button
                    type="button"
                    onClick={() => onRun(profile.id, profile.name)}
                    className="rounded border border-input bg-background px-2 py-1 text-xs hover:bg-muted"
                  >
                    {t('exports.profiles.action_run', { defaultValue: 'Uruchom teraz' })}
                  </button>
                  <button
                    type="button"
                    onClick={() => onDelete(profile.id, profile.name)}
                    className="rounded border border-rose-200 bg-rose-50 px-2 py-1 text-xs text-rose-900 hover:bg-rose-100"
                  >
                    {t('exports.profiles.action_delete', { defaultValue: 'Usuń' })}
                  </button>
                </div>
              </td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );
}

export default ExportProfilesView;
