import { useList, useUpdate } from '@refinedev/core';
import { ChevronDown, Loader2 } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';

import { Input } from '@/components/ui/input';
import { useToast } from '@/components/ui/toast';
import { resolveLabel } from '@/features/catalog/attributes/list';

interface MappingRow {
  id: string;
  targetField: string;
  channel?: { id: string };
  objectType?: {
    id: string;
    code: string;
    label?: Record<string, string> | null;
  };
  attribute?: {
    id: string;
    code: string;
    label?: Record<string, string> | null;
    type?: string;
  };
}

interface ChannelMappingEditorProps {
  channelId: string;
}

export function ChannelMappingEditor({ channelId }: ChannelMappingEditorProps) {
  const { t, i18n } = useTranslation();

  const { result, query } = useList<MappingRow>({
    resource: 'channel_object_type_mappings',
    filters: [{ field: 'channel', operator: 'eq', value: channelId }],
    pagination: { mode: 'off' },
  });

  if (query.isLoading) {
    return (
      <p className="text-sm text-muted-foreground">
        <Loader2 className="mr-2 inline size-3 animate-spin" />
        {t('channels.mapping.loading')}
      </p>
    );
  }

  const rows = result.data;
  const grouped = groupByObjectType(rows);
  const groupKeys = Object.keys(grouped);

  if (groupKeys.length === 0) {
    return (
      <p className="text-sm text-muted-foreground">{t('channels.mapping.empty_object_type')}</p>
    );
  }

  return (
    <div className="space-y-4">
      <div className="space-y-1">
        <h3 className="text-base font-semibold">{t('channels.mapping.title')}</h3>
        <p className="text-sm text-muted-foreground">{t('channels.mapping.subtitle')}</p>
      </div>

      <div className="space-y-3">
        {groupKeys.map((otCode) => {
          const groupRows = grouped[otCode];
          const sample = groupRows[0]?.objectType;
          const objectTypeLabel = sample
            ? (resolveLabel(sample.label ?? null, i18n.language) ?? otCode)
            : otCode;

          return (
            <details key={otCode} open className="group rounded-xl border bg-card">
              <summary className="flex cursor-pointer list-none items-center justify-between gap-2 px-4 py-3 text-sm font-medium">
                <div className="flex items-center gap-2">
                  <span>{objectTypeLabel}</span>
                  <span className="rounded bg-muted px-2 py-0.5 font-mono text-[11px] text-muted-foreground">
                    {otCode}
                  </span>
                  <span className="text-xs text-muted-foreground">({groupRows.length})</span>
                </div>
                <ChevronDown className="size-4 transition-transform group-open:rotate-180" />
              </summary>
              <div className="border-t px-4 py-3">
                <div className="space-y-2">
                  {groupRows.map((row) => (
                    <MappingRowEditor key={row.id} row={row} />
                  ))}
                </div>
              </div>
            </details>
          );
        })}
      </div>
    </div>
  );
}

interface MappingRowEditorProps {
  row: MappingRow;
}

function MappingRowEditor({ row }: MappingRowEditorProps) {
  const { t, i18n } = useTranslation();
  const toast = useToast();
  const { mutate: doUpdate } = useUpdate();
  const [value, setValue] = useState(row.targetField ?? '');
  const [status, setStatus] = useState<'idle' | 'saving' | 'saved' | 'error'>('idle');

  const attribute = row.attribute;

  const handleBlur = () => {
    if (value === row.targetField) {
      return;
    }
    setStatus('saving');
    doUpdate(
      {
        resource: 'channel_object_type_mappings',
        id: row.id,
        values: { targetField: value },
      },
      {
        onSuccess: () => {
          setStatus('saved');
        },
        onError: () => {
          setStatus('error');
          toast.error(t('channels.mapping.save_error'));
        },
      },
    );
  };

  return (
    <div className="grid grid-cols-12 items-center gap-3 rounded-md border bg-background px-3 py-2">
      <div className="col-span-3">
        <p className="font-mono text-xs">{attribute?.code ?? '—'}</p>
        <p className="text-xs text-muted-foreground">
          {resolveLabel(attribute?.label ?? null, i18n.language) ?? ''}
        </p>
      </div>
      <div className="col-span-2">
        <span className="rounded bg-muted px-1.5 py-0.5 font-mono text-[10px] uppercase tracking-wide text-muted-foreground">
          {attribute?.type ?? 'unknown'}
        </span>
      </div>
      <div className="col-span-6">
        <Input
          value={value}
          onChange={(e) => {
            setValue(e.target.value);
            setStatus('idle');
          }}
          onBlur={handleBlur}
          placeholder={t('channels.mapping.target_field_placeholder')}
          aria-label={t('channels.mapping.target_field_label')}
          className="font-mono text-xs"
        />
      </div>
      <div className="col-span-1 text-right text-[11px] text-muted-foreground" aria-live="polite">
        {status === 'saving' ? <Loader2 className="ml-auto size-3 animate-spin" /> : null}
        {status === 'saved' ? (
          <span className="text-emerald-600">{t('channels.mapping.save_success')}</span>
        ) : null}
        {status === 'error' ? <span className="text-destructive">!</span> : null}
      </div>
    </div>
  );
}

function groupByObjectType(rows: MappingRow[]): Record<string, MappingRow[]> {
  const grouped: Record<string, MappingRow[]> = {};
  for (const row of rows) {
    const otCode = row.objectType?.code ?? 'unknown';
    if (!grouped[otCode]) {
      grouped[otCode] = [];
    }
    grouped[otCode].push(row);
  }

  // Stable sort within group by attribute code
  for (const key of Object.keys(grouped)) {
    grouped[key].sort((a, b) => (a.attribute?.code ?? '').localeCompare(b.attribute?.code ?? ''));
  }
  return grouped;
}
