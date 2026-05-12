import { useList } from '@refinedev/core';
import * as React from 'react';
import { useTranslation } from 'react-i18next';

import { Button } from '@/components/ui/button';
import { Combobox } from '@/components/ui/combobox';
import {
  Dialog,
  DialogContent,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

import type { ImportScheduleRow, NotifyChannel, SchedulePriority } from './types';

const PRIORITIES: ReadonlyArray<SchedulePriority> = ['high', 'normal', 'low'];
const NOTIFY_OPTIONS: ReadonlyArray<NotifyChannel> = ['slack', 'email', 'webhook'];

interface ScheduleFormDialogProps {
  open: boolean;
  mode: 'create' | 'edit';
  schedule?: ImportScheduleRow;
  onClose: () => void;
  onSubmit: (input: ScheduleFormInput) => Promise<void> | void;
}

export interface ScheduleFormInput {
  name: string;
  code: string;
  cron: string;
  priority: SchedulePriority;
  enabled: boolean;
  sourceId: string | null;
  profileId: string | null;
  notifyChannels: ReadonlyArray<NotifyChannel>;
  notifyConfig: Record<string, unknown>;
}

interface NamedRef {
  id: string;
  name: string;
}

function slugify(value: string): string {
  return value
    .toLowerCase()
    .normalize('NFKD')
    .replace(/[̀-ͯ]/g, '')
    .replace(/[^a-z0-9]+/g, '-')
    .replace(/-+/g, '-')
    .replace(/^-|-$/g, '');
}

export function ScheduleFormDialog({
  open,
  mode,
  schedule,
  onClose,
  onSubmit,
}: ScheduleFormDialogProps) {
  const { t } = useTranslation();
  const isEdit = mode === 'edit';
  const [name, setName] = React.useState(schedule?.name ?? '');
  const [code, setCode] = React.useState(schedule?.code ?? '');
  const [cron, setCron] = React.useState(schedule?.cron ?? '0 6 * * *');
  const [priority, setPriority] = React.useState<SchedulePriority>(schedule?.priority ?? 'normal');
  const [enabled, setEnabled] = React.useState<boolean>(schedule?.enabled ?? true);
  const [sourceId, setSourceId] = React.useState<string>(schedule?.source?.id ?? '');
  const [profileId, setProfileId] = React.useState<string>(schedule?.profile?.id ?? '');
  const [notifyChannels, setNotifyChannels] = React.useState<ReadonlyArray<NotifyChannel>>(
    schedule?.notifyChannels ?? [],
  );
  const [submitting, setSubmitting] = React.useState(false);
  const [error, setError] = React.useState<string | null>(null);
  const [codeTouched, setCodeTouched] = React.useState(isEdit);

  const { result: sources } = useList<NamedRef>({
    resource: 'import-sources',
    pagination: { pageSize: 100 },
    queryOptions: { enabled: open },
  });
  const { result: profiles } = useList<NamedRef>({
    resource: 'import-profiles',
    pagination: { pageSize: 100 },
    queryOptions: { enabled: open },
  });

  React.useEffect(() => {
    if (!open) {
      return;
    }
    setName(schedule?.name ?? '');
    setCode(schedule?.code ?? '');
    setCron(schedule?.cron ?? '0 6 * * *');
    setPriority(schedule?.priority ?? 'normal');
    setEnabled(schedule?.enabled ?? true);
    setSourceId(schedule?.source?.id ?? '');
    setProfileId(schedule?.profile?.id ?? '');
    setNotifyChannels(schedule?.notifyChannels ?? []);
    setError(null);
    setCodeTouched(isEdit);
  }, [open, schedule, isEdit]);

  React.useEffect(() => {
    if (!codeTouched) {
      setCode(slugify(name));
    }
  }, [name, codeTouched]);

  function toggleChannel(channel: NotifyChannel) {
    setNotifyChannels((prev) =>
      prev.includes(channel) ? prev.filter((c) => c !== channel) : [...prev, channel],
    );
  }

  async function handleSubmit(event: React.FormEvent) {
    event.preventDefault();
    if (name.trim().length === 0) {
      setError(t('imports.schedule.dialog.errors.name_required'));
      return;
    }
    if (cron.trim().split(/\s+/).length !== 5) {
      setError(t('imports.schedule.dialog.errors.cron_invalid'));
      return;
    }
    setSubmitting(true);
    setError(null);
    try {
      await onSubmit({
        name: name.trim(),
        code: code.trim() || slugify(name),
        cron: cron.trim(),
        priority,
        enabled,
        sourceId: sourceId || null,
        profileId: profileId || null,
        notifyChannels,
        notifyConfig: {},
      });
      onClose();
    } catch (e) {
      setError(e instanceof Error ? e.message : String(e));
    } finally {
      setSubmitting(false);
    }
  }

  const sourcesList = sources.data ?? [];
  const profilesList = profiles.data ?? [];

  return (
    <Dialog open={open} onOpenChange={(next) => (!next ? onClose() : undefined)}>
      <DialogContent>
        <DialogHeader>
          <DialogTitle>
            {isEdit
              ? t('imports.schedule.dialog.title_edit')
              : t('imports.schedule.dialog.title_create')}
          </DialogTitle>
        </DialogHeader>
        <form onSubmit={handleSubmit} className="grid gap-4">
          <div className="grid grid-cols-2 gap-3">
            <div className="grid gap-2">
              <Label htmlFor="schedule-name">{t('imports.schedule.dialog.name')}</Label>
              <Input
                id="schedule-name"
                value={name}
                onChange={(e) => setName(e.target.value)}
                required
                maxLength={255}
              />
            </div>
            <div className="grid gap-2">
              <Label htmlFor="schedule-code">{t('imports.schedule.dialog.code')}</Label>
              <Input
                id="schedule-code"
                value={code}
                onChange={(e) => {
                  setCode(e.target.value);
                  setCodeTouched(true);
                }}
                pattern="[a-z0-9-]+"
                maxLength={64}
              />
            </div>
          </div>
          <div className="grid gap-2">
            <Label htmlFor="schedule-cron">{t('imports.schedule.dialog.cron')}</Label>
            <Input
              id="schedule-cron"
              value={cron}
              onChange={(e) => setCron(e.target.value)}
              placeholder="0 6 * * *"
              className="font-mono"
            />
            <span className="text-[11px] text-muted-foreground">
              {t('imports.schedule.dialog.cron_hint')}
            </span>
          </div>
          <div className="grid grid-cols-2 gap-3">
            <div className="grid gap-2">
              <Label htmlFor="schedule-priority">{t('imports.schedule.dialog.priority')}</Label>
              <Combobox
                options={PRIORITIES.map((p) => ({
                  value: p,
                  label: t(`imports.schedule.priority.${p}`),
                }))}
                value={priority}
                onChange={(v) => setPriority((v ?? 'normal') as SchedulePriority)}
              />
            </div>
            <label className="flex items-center gap-2 text-[13px] mt-7">
              <input
                type="checkbox"
                checked={enabled}
                onChange={(e) => setEnabled(e.target.checked)}
              />
              {t('imports.schedule.dialog.enabled')}
            </label>
          </div>
          <div className="grid grid-cols-2 gap-3">
            <div className="grid gap-2">
              <Label htmlFor="schedule-source">{t('imports.schedule.dialog.source')}</Label>
              <Combobox
                options={sourcesList.map((s) => ({ value: s.id, label: s.name }))}
                value={sourceId}
                onChange={(v) => setSourceId(v ?? '')}
                placeholder={t('imports.schedule.dialog.source_placeholder')}
                allowClear
              />
            </div>
            <div className="grid gap-2">
              <Label htmlFor="schedule-profile">{t('imports.schedule.dialog.profile')}</Label>
              <Combobox
                options={profilesList.map((p) => ({ value: p.id, label: p.name }))}
                value={profileId}
                onChange={(v) => setProfileId(v ?? '')}
                placeholder={t('imports.schedule.dialog.profile_placeholder')}
                allowClear
              />
            </div>
          </div>
          <fieldset className="grid gap-2 border-0">
            <legend className="text-[13px] font-medium">
              {t('imports.schedule.dialog.notify_channels')}
            </legend>
            <div className="flex items-center gap-3 flex-wrap">
              {NOTIFY_OPTIONS.map((ch) => (
                <label key={ch} className="flex items-center gap-2 text-[13px]">
                  <input
                    type="checkbox"
                    checked={notifyChannels.includes(ch)}
                    onChange={() => toggleChannel(ch)}
                  />
                  {ch}
                </label>
              ))}
            </div>
            <span className="text-[11px] text-muted-foreground">
              {t('imports.schedule.dialog.notify_hint')}
            </span>
          </fieldset>
          {error ? (
            <div role="alert" className="rounded-md bg-rose-50 px-3 py-2 text-[12px] text-rose-700">
              {error}
            </div>
          ) : null}
          <DialogFooter>
            <Button type="button" variant="outline" onClick={onClose}>
              {t('app.cancel')}
            </Button>
            <Button type="submit" disabled={submitting}>
              {submitting
                ? t('app.loading')
                : isEdit
                  ? t('app.save')
                  : t('imports.schedule.dialog.create')}
            </Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  );
}
