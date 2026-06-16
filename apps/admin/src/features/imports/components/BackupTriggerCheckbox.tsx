import { useApiUrl, useCustom } from '@refinedev/core';
import * as React from 'react';
import { useTranslation } from 'react-i18next';

import { Progress } from '@/components/ui/progress';
import { HttpError, jsonFetch } from '@/lib/http';
import { cn } from '@/lib/utils';

interface BackupTriggerCheckboxProps {
  checked: boolean;
  onChange: (checked: boolean) => void;
  /**
   * Pushed up to the parent so Step 4 can disable the "Uruchom import"
   * CTA while the backup is still running.
   */
  onStatusChange?: (status: 'idle' | 'pending' | 'running' | 'completed' | 'failed') => void;
  /**
   * IMP2-2.10 (#1486) — the created backup's id, surfaced so Step 4 can
   * forward `backup_id` to `POST /api/import-sessions`. `null` when the box
   * is unchecked or the trigger failed.
   */
  onBackupCreated?: (backupId: string | null) => void;
}

interface BackupStatusResponse {
  id: string;
  status: 'pending' | 'running' | 'completed' | 'failed';
  error_message: string | null;
}

/**
 * Spec §5.5 — wizard Step 4 backup checkbox.
 *
 * On check the checkbox `POST`s `/api/backups`, then polls the status
 * endpoint every 5 s until the row reaches a terminal state. The
 * IMP-06 in-memory rate limiter caps abuse at 1/h/tenant.
 */
export function BackupTriggerCheckbox({
  checked,
  onChange,
  onStatusChange,
  onBackupCreated,
}: BackupTriggerCheckboxProps): React.ReactElement {
  const { t } = useTranslation();
  const apiUrl = useApiUrl();
  const [backupId, setBackupId] = React.useState<string | null>(null);
  const [error, setError] = React.useState<string | null>(null);

  const { result } = useCustom<BackupStatusResponse>({
    url: `${apiUrl}/backups/${backupId ?? ''}`,
    method: 'get',
    queryOptions: {
      enabled: backupId !== null,
      refetchInterval: 5000,
    },
  });
  const status = result.data?.status ?? 'idle';

  React.useEffect(() => {
    onStatusChange?.(status);
  }, [status, onStatusChange]);

  // IMP2-2.10 (#1486) — surface the backup id to the parent. The CTA is gated
  // on `completed`, so the id is only acted on once the snapshot is ready.
  React.useEffect(() => {
    onBackupCreated?.(backupId);
  }, [backupId, onBackupCreated]);

  const handleToggle = (next: boolean): void => {
    onChange(next);
    if (!next) {
      setBackupId(null);
      setError(null);
      return;
    }
    jsonFetch<{ id: string }>('/api/backups', {
      method: 'POST',
      body: { triggered_by_action: 'pre_import' },
      contentType: 'application/json',
    })
      .then((data) => setBackupId(data.id))
      .catch((err: unknown) => {
        if (err instanceof HttpError) {
          setError(`HTTP ${err.status}`);
        } else {
          setError(err instanceof Error ? err.message : 'unknown');
        }
        onChange(false);
      });
  };

  return (
    <div className="space-y-2 rounded-md border bg-muted/20 p-4">
      <label className="flex items-start gap-2 text-sm">
        <input
          type="checkbox"
          checked={checked}
          onChange={(event) => handleToggle(event.target.checked)}
          className="mt-1"
        />
        <span className="space-y-1">
          <span className="font-medium">
            {t('imports.confirm.backup.label', {
              defaultValue: 'Utwórz pełen backup bazy danych (pgBackRest)',
            })}
          </span>
          <span className="block text-xs text-muted-foreground">
            💡{' '}
            {t('imports.confirm.backup.hint_recommended', {
              defaultValue: 'Zalecane dla importów >1000 produktów',
            })}
          </span>
          <span className="block text-xs text-muted-foreground">
            ⏱{' '}
            {t('imports.confirm.backup.hint_duration', {
              defaultValue: 'Backup zajmie 5–30 minut',
            })}
          </span>
          <span className="block text-xs text-muted-foreground">
            {t('imports.confirm.backup.hint_rollback', {
              defaultValue: 'Bez backupu: dostępny soft rollback przez 24h',
            })}
          </span>
        </span>
      </label>

      {checked && backupId !== null && (
        <div className="space-y-1">
          <Progress
            value={status === 'completed' ? 100 : status === 'running' ? 50 : 10}
            ariaLabel="Backup progress"
          />
          <p
            className={cn(
              'text-xs',
              status === 'completed' && 'text-green-700',
              status === 'failed' && 'text-destructive',
              (status === 'pending' || status === 'running') && 'text-muted-foreground',
            )}
          >
            {status === 'pending' && '🔄 Oczekuje…'}
            {status === 'running' && '🔄 Backup w toku…'}
            {status === 'completed' && '✅ Backup gotowy'}
            {status === 'failed' && `❌ ${result.data?.error_message ?? 'Błąd'}`}
          </p>
        </div>
      )}

      {error !== null && (
        <p role="alert" className="text-xs text-destructive">
          {error}
        </p>
      )}
    </div>
  );
}
