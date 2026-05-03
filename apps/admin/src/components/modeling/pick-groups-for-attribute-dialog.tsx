import { useQuery } from '@tanstack/react-query';
import { Check, FolderTree, Search, X } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';

import { BuiltInLockBadge } from '@/components/modeling/built-in-lock-badge';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent } from '@/components/ui/dialog';
import { resolveLabel } from '@/features/catalog/attributes/list';
import { jsonFetch } from '@/lib/http';
import { cn } from '@/lib/utils';

interface AttributeGroupRow {
  id: string;
  code: string;
  label?: Record<string, string> | string | null;
  description?: Record<string, string> | string | null;
  color?: string | null;
  icon?: string | null;
  systemGroup?: boolean;
}

interface Props {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  /** Initially-selected group codes (so the dialog can be reopened). */
  initialPicked: Set<string>;
  /** Returns selected codes when the user confirms. */
  onConfirm: (codes: Set<string>) => void;
  locale: string;
}

/**
 * Reverse direction of `<AddAttributesFromLibraryDialog>` — operator picks
 * existing groups while creating a new attribute. The actual attach happens
 * after the parent's `POST /api/attributes` returns, in the parent component.
 */
export function PickGroupsForAttributeDialog({
  open,
  onOpenChange,
  initialPicked,
  onConfirm,
  locale,
}: Props) {
  const { t } = useTranslation();
  const [q, setQ] = useState('');
  const [picked, setPicked] = useState<Set<string>>(new Set());

  useEffect(() => {
    if (open) {
      setQ('');
      setPicked(new Set(initialPicked));
    }
  }, [open, initialPicked]);

  const { data: groups = [] } = useQuery<AttributeGroupRow[]>({
    queryKey: ['attribute_groups', 'picker'],
    queryFn: async () => {
      const data = await jsonFetch<{ member?: AttributeGroupRow[] }>(
        '/api/attribute_groups?itemsPerPage=200',
      );
      return data.member ?? [];
    },
    enabled: open,
    staleTime: 30_000,
  });

  const filtered = useMemo(() => {
    const needle = q.trim().toLowerCase();
    return groups.filter((g) => {
      if (needle.length === 0) return true;
      const code = g.code.toLowerCase();
      const labelStr =
        typeof g.label === 'string'
          ? g.label.toLowerCase()
          : g.label !== null && typeof g.label === 'object' && g.label !== undefined
            ? Object.values(g.label).join(' ').toLowerCase()
            : '';
      return code.includes(needle) || labelStr.includes(needle);
    });
  }, [groups, q]);

  const toggle = (code: string) => {
    setPicked((prev) => {
      const next = new Set(prev);
      if (next.has(code)) next.delete(code);
      else next.add(code);
      return next;
    });
  };

  const confirm = () => {
    onConfirm(picked);
    onOpenChange(false);
  };

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="max-w-[680px] gap-0 p-0">
        <div className="flex items-start gap-3 border-b border-zinc-100 px-7 pb-4 pt-6">
          <div className="grid h-10 w-10 shrink-0 place-items-center rounded-2xl bg-zinc-900 text-white">
            <FolderTree className="size-4" />
          </div>
          <div className="min-w-0 flex-1">
            <div className="font-display text-[18px] font-semibold tracking-tight">
              {t('modeling.attributes.pick_groups.title', {
                defaultValue: 'Dołącz atrybut do grup',
              })}
            </div>
            <div className="mt-0.5 text-[12.5px] text-muted-foreground">
              {t('modeling.attributes.pick_groups.desc', {
                defaultValue: 'Wybierz grupy do których atrybut zostanie dołączony po utworzeniu.',
              })}
            </div>
          </div>
          <button
            type="button"
            onClick={() => onOpenChange(false)}
            className="grid size-9 shrink-0 place-items-center rounded-xl text-muted-foreground hover:bg-zinc-100"
            aria-label={t('app.close', { defaultValue: 'Zamknij' })}
          >
            <X className="size-4" />
          </button>
        </div>

        <div className="px-7 pb-3 pt-4">
          <div className="flex h-10 items-center gap-2 rounded-xl border border-zinc-200 bg-zinc-50 px-3">
            <Search className="size-4 text-muted-foreground" />
            <input
              value={q}
              onChange={(e) => setQ(e.target.value)}
              placeholder={t('modeling.attributes.pick_groups.search_placeholder', {
                defaultValue: 'Szukaj grup po code lub nazwie…',
              })}
              className="flex-1 bg-transparent text-[13px] outline-none placeholder:text-muted-foreground"
            />
          </div>
        </div>

        <div className="max-h-[420px] overflow-y-auto px-7 pb-2">
          {filtered.length === 0 ? (
            <div className="px-4 py-12 text-center text-[13px] text-muted-foreground">
              {t('modeling.attributes.pick_groups.empty', {
                defaultValue: 'Brak grup dla podanych kryteriów',
              })}
            </div>
          ) : (
            <div className="space-y-1">
              {filtered.map((g) => {
                const isPicked = picked.has(g.code);
                const labelStr = resolveLabel(g.label, locale);
                return (
                  <label
                    key={g.id}
                    className={cn(
                      'grid cursor-pointer grid-cols-[24px_44px_1fr] items-center gap-3 rounded-xl border px-3 py-2.5 transition',
                      isPicked
                        ? 'border-zinc-900 bg-zinc-900 text-white'
                        : 'border-zinc-100 bg-white hover:border-zinc-300 hover:bg-zinc-50',
                    )}
                  >
                    <input
                      type="checkbox"
                      checked={isPicked}
                      onChange={() => toggle(g.code)}
                      className="size-4 rounded"
                    />
                    <span
                      className="grid size-9 place-items-center rounded-xl text-[16px]"
                      style={{
                        background: `${g.color ?? '#71717a'}18`,
                        color: g.color ?? '#71717a',
                      }}
                    >
                      {g.icon ?? '📦'}
                    </span>
                    <div className="min-w-0">
                      <div className="flex items-center gap-2">
                        <span
                          className={cn(
                            'truncate text-[13.5px] font-semibold tracking-tight',
                            isPicked ? '' : '',
                          )}
                        >
                          {labelStr}
                        </span>
                        {g.systemGroup ? <BuiltInLockBadge /> : null}
                      </div>
                      <div
                        className={cn(
                          'truncate font-mono text-[11.5px]',
                          isPicked ? 'text-white/70' : 'text-muted-foreground',
                        )}
                      >
                        {g.code}
                      </div>
                    </div>
                  </label>
                );
              })}
            </div>
          )}
        </div>

        <div className="flex items-center justify-between border-t border-zinc-100 bg-zinc-50/60 px-7 py-4">
          <div className="text-[12.5px] text-muted-foreground">
            {t('modeling.attributes.pick_groups.picked_prefix', { defaultValue: 'Wybrano' })}{' '}
            <span className="font-semibold text-foreground tabular-nums">{picked.size}</span>{' '}
            {picked.size === 1 ? 'grupę' : 'grup'}
          </div>
          <div className="flex items-center gap-2">
            <Button
              type="button"
              variant="ghost"
              size="sm"
              className="h-9 rounded-xl"
              onClick={() => onOpenChange(false)}
            >
              {t('app.cancel', { defaultValue: 'Anuluj' })}
            </Button>
            <Button
              type="button"
              size="sm"
              onClick={confirm}
              className="h-9 rounded-xl bg-zinc-900 hover:bg-zinc-800"
            >
              <Check className="size-4" />
              {t('modeling.attributes.pick_groups.confirm_action', {
                defaultValue: 'Wybierz {{count}}',
                count: picked.size,
              })}
            </Button>
          </div>
        </div>
      </DialogContent>
    </Dialog>
  );
}
