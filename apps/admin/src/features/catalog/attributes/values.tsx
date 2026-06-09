import {
  closestCenter,
  DndContext,
  type DragEndEvent,
  KeyboardSensor,
  PointerSensor,
  useSensor,
  useSensors,
} from '@dnd-kit/core';
import {
  arrayMove,
  SortableContext,
  sortableKeyboardCoordinates,
  useSortable,
  verticalListSortingStrategy,
} from '@dnd-kit/sortable';
import { CSS } from '@dnd-kit/utilities';
import { useOne } from '@refinedev/core';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import { ArrowDown, ArrowLeft, ArrowUp, GripVertical, Layers, Plus, Trash2 } from 'lucide-react';
import { useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Link, useParams } from 'react-router';

import { ATTRIBUTE_OPTION_SWATCHES, ColorPicker } from '@/components/modeling/color-picker';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { HttpError, jsonFetch } from '@/lib/http';
import { cn } from '@/lib/utils';

/**
 * VIEW-02 (#374) — pixel-perfect AttributeOption editor mapped from
 * `attribute-values.jsx` mockup. Backed by AttributeOptionsController
 * endpoints (#387) and the schema additions from #378
 * (color/isDefault/isDeprecated).
 *
 * MVP slice: drag-reorder is replaced by ↑↓ buttons hitting the
 * per-option PATCH (position swap). LocaleTabsField + dnd-kit + Audit
 * card land in VIEW-02b follow-up.
 */

interface AttributeDetail {
  id: string;
  code: string;
  label: Record<string, string>;
  type: string;
}

interface OptionRow {
  id: string;
  code: string;
  label: Record<string, string>;
  position: number;
  color: string | null;
  default: boolean;
  deprecated: boolean;
}

function pickLabel(label: Record<string, string>, locale: string): string {
  return label[locale] ?? label.pl ?? label.en ?? Object.values(label)[0] ?? '';
}

/**
 * #1353 — derive an immutable snake_case option `code` from the
 * human-readable name the operator types. Strips diacritics (incl.
 * Polish ł/ż/ó…), lowercases, collapses non-alphanumerics to `_`, and
 * ensures the code starts with a letter (backend regex `[a-z][a-z0-9_]*`).
 */
function slugifyValueCode(name: string): string {
  const slug = name
    .replace(/ł/g, 'l')
    .replace(/Ł/g, 'l')
    .normalize('NFD')
    .replace(/[̀-ͯ]/g, '')
    .toLowerCase()
    .replace(/[^a-z0-9]+/g, '_')
    .replace(/^_+|_+$/g, '');
  if (slug === '') return '';
  return /^[a-z]/.test(slug) ? slug : `v_${slug}`;
}

/** One editable locale column for the option-label editor + preview (#1263). */
interface LocaleChip {
  code: string;
  flag?: string;
}

// #1263 — known flag glyphs; unknown tenant locales render without a flag
// rather than being dropped (the old hardcoded ['pl','en','de'] list).
const LOCALE_FLAGS: Record<string, string> = {
  pl: '🇵🇱',
  en: '🇬🇧',
  de: '🇩🇪',
  cs: '🇨🇿',
  fr: '🇫🇷',
  es: '🇪🇸',
  it: '🇮🇹',
  uk: '🇺🇦',
};

export function AttributeValuesPage() {
  const { t, i18n } = useTranslation();
  const params = useParams<{ id: string }>();
  const id = params.id ?? '';
  const { result: attribute } = useOne<AttributeDetail>({
    resource: 'attributes',
    id,
    queryOptions: { enabled: id.length > 0 },
  });

  if (!attribute) {
    return <p className="text-sm text-muted-foreground">{t('app.loading')}</p>;
  }

  const supportsOptions = ['select', 'multiselect'].includes(attribute.type);
  if (!supportsOptions) {
    return (
      <div className="space-y-6 px-4 py-6 sm:px-6 lg:px-10">
        <BackLink id={id} />
        <Card>
          <CardContent className="space-y-2 py-10 text-center">
            <Layers className="mx-auto size-8 text-muted-foreground" />
            <h1 className="text-lg font-semibold">
              {t('attribute_values.unsupported_type_title', {
                defaultValue: 'Atrybut nie ma listy wartości',
              })}
            </h1>
            <p className="text-sm text-muted-foreground">
              {t('attribute_values.unsupported_type_body', {
                defaultValue: 'Edytor wartości jest dostępny tylko dla typów select / multiselect.',
              })}
            </p>
          </CardContent>
        </Card>
      </div>
    );
  }

  return <ValuesEditor attribute={attribute} locale={i18n.language} />;
}

function ValuesEditor({ attribute, locale }: { attribute: AttributeDetail; locale: string }) {
  const { t } = useTranslation();
  const queryClient = useQueryClient();
  const queryKey: readonly string[] = ['attribute_options', attribute.code];

  // #1263 — the editable locale columns come from the tenant's enabled
  // locales (Settings → Locales), not a hardcoded pl/en/de list. Falls back
  // to pl/en before the workspace resolves / when the call fails.
  const { data: workspace } = useQuery<{ enabledLocales?: string[] }>({
    queryKey: ['workspace', 'current', 'locales'],
    queryFn: () => jsonFetch<{ enabledLocales?: string[] }>('/api/workspaces/current'),
    staleTime: 60_000,
  });
  const localeChips: LocaleChip[] = (workspace?.enabledLocales ?? ['pl', 'en']).map((code) => ({
    code,
    flag: LOCALE_FLAGS[code],
  }));

  const { data: options = [] } = useQuery<OptionRow[]>({
    queryKey,
    queryFn: async () => {
      const payload = await jsonFetch<{ member: OptionRow[] }>(
        `/api/attributes/${attribute.code}/options`,
        { method: 'GET' },
      );
      return payload.member ?? [];
    },
  });

  const [activeId, setActiveId] = useState<string | null>(null);
  useEffect(() => {
    const first = options[0];
    if (activeId === null && first !== undefined) {
      setActiveId(first.id);
    }
  }, [activeId, options]);

  const active = options.find((o) => o.id === activeId) ?? null;
  const refresh = () => queryClient.invalidateQueries({ queryKey });

  // Inline-create: pop a draft row into local state with a focusable
  // empty Code input. Submit on Enter or click Save → POST → refetch.
  // No native prompt — operator stays inside the editor.
  const [draftName, setDraftName] = useState<string>('');
  const [draftError, setDraftError] = useState<string | null>(null);
  const [creating, setCreating] = useState(false);

  const addValue = () => {
    setDraftName('');
    setDraftError(null);
    setCreating(true);
  };

  const cancelDraft = () => {
    setCreating(false);
    setDraftName('');
    setDraftError(null);
  };

  const submitDraft = async () => {
    // #1353 — the operator types a human-readable name; the immutable
    // `code` is auto-derived (slugified) so the field can be labelled
    // "Nazwa" instead of the confusing "code (snake_case)".
    const name = draftName.trim();
    if (name.length === 0) {
      setDraftError(
        t('attribute_values.draft_name_required', { defaultValue: 'Nazwa nie może być pusta' }),
      );
      return;
    }
    const code = slugifyValueCode(name);
    if (code.length === 0) {
      setDraftError(
        t('attribute_values.draft_name_invalid', {
          defaultValue: 'Nazwa musi zawierać przynajmniej jedną literę',
        }),
      );
      return;
    }
    try {
      const created = await jsonFetch<OptionRow>(`/api/attributes/${attribute.code}/options`, {
        method: 'POST',
        contentType: 'application/json',
        body: { code, label: { pl: name, en: name } },
      });
      setActiveId(created.id);
      cancelDraft();
      refresh();
    } catch (err) {
      if (err instanceof HttpError) {
        const detail =
          err.body && typeof err.body === 'object' && 'detail' in err.body
            ? String((err.body as Record<string, unknown>).detail)
            : null;
        setDraftError(detail ?? `HTTP ${err.status}`);
      } else {
        setDraftError(
          t('attribute_values.draft_save_error', { defaultValue: 'Nie udało się zapisać' }),
        );
      }
    }
  };

  // dnd-kit setup: pointer + keyboard sensors give us mouse and a11y
  // out of the box. Drag distance threshold prevents row clicks (which
  // also call setActiveId) from triggering a no-op drag.
  const sensors = useSensors(
    useSensor(PointerSensor, { activationConstraint: { distance: 5 } }),
    useSensor(KeyboardSensor, { coordinateGetter: sortableKeyboardCoordinates }),
  );

  const handleDragEnd = async (event: DragEndEvent) => {
    const { active: dragged, over } = event;
    if (over === null || dragged.id === over.id) return;
    const oldIndex = options.findIndex((o) => o.id === dragged.id);
    const newIndex = options.findIndex((o) => o.id === over.id);
    if (oldIndex < 0 || newIndex < 0) return;

    const reordered = arrayMove(options, oldIndex, newIndex);
    // Optimistic local state via cache write — refetch on settle
    // matches server-assigned positions back into the cache.
    queryClient.setQueryData<OptionRow[]>(queryKey, reordered);

    // Persist by issuing per-row PATCH calls in the new order. Each row
    // gets its index as the new position so the array order survives a
    // hard refresh. We send the calls in parallel (the AttributeOption
    // partial-unique only constrains `is_default`, not `position`).
    try {
      await Promise.all(
        reordered.map((row, index) =>
          jsonFetch(`/api/attributes/${attribute.code}/options/${row.code}`, {
            method: 'PATCH',
            contentType: 'application/merge-patch+json',
            body: { position: index },
          }),
        ),
      );
    } finally {
      refresh();
    }
  };

  return (
    <div className="space-y-6 px-4 py-6 sm:px-6 lg:px-10">
      <BackLink id={attribute.id} />
      <div className="flex items-start gap-3">
        <div className="grid h-12 w-12 shrink-0 place-items-center rounded-2xl bg-violet-50 text-violet-700">
          <Layers className="size-5" />
        </div>
        <div className="min-w-0 flex-1">
          <div className="text-[12.5px] font-medium text-muted-foreground">
            {t('attribute_values.page_caption', {
              defaultValue: 'Allowed values · {{type}}',
              type: attribute.type,
            })}
          </div>
          <h1 className="font-display text-[26px] font-semibold tracking-tight">
            <span className="font-mono">{attribute.code}</span>
            <span className="mx-2 text-muted-foreground">·</span>
            <span>{pickLabel(attribute.label, locale)}</span>
          </h1>
          <div className="text-[12.5px] text-muted-foreground">
            {options.length} {t('attribute_values.values_word', { defaultValue: 'wartości' })}
          </div>
        </div>
      </div>

      <div className="grid gap-6 lg:grid-cols-[360px_1fr]">
        <Card className="self-start">
          <CardContent className="p-3">
            <DndContext
              sensors={sensors}
              collisionDetection={closestCenter}
              onDragEnd={(event) => {
                void handleDragEnd(event);
              }}
            >
              <SortableContext
                items={options.map((o) => o.id)}
                strategy={verticalListSortingStrategy}
              >
                <div className="space-y-1">
                  {options.map((option) => (
                    <ValueRowItem
                      key={option.id}
                      option={option}
                      isActive={option.id === activeId}
                      locale={locale}
                      onSelect={() => setActiveId(option.id)}
                    />
                  ))}
                  {options.length === 0 ? (
                    <p className="px-2 py-6 text-center text-sm text-muted-foreground">
                      {t('attribute_values.empty', {
                        defaultValue: 'Brak zdefiniowanych wartości.',
                      })}
                    </p>
                  ) : null}
                </div>
              </SortableContext>
            </DndContext>
            {creating ? (
              <div className="mt-2 space-y-2 rounded-xl border border-violet-200 bg-violet-50/40 p-2">
                <Input
                  autoFocus
                  value={draftName}
                  onChange={(e) => {
                    setDraftName(e.target.value);
                    setDraftError(null);
                  }}
                  onKeyDown={(e) => {
                    if (e.key === 'Enter') {
                      e.preventDefault();
                      void submitDraft();
                    }
                    if (e.key === 'Escape') cancelDraft();
                  }}
                  placeholder={t('attribute_values.draft_name_placeholder', {
                    defaultValue: 'Nazwa',
                  })}
                  className="h-9"
                />
                {draftError !== null ? (
                  <p className="px-1 text-[12px] text-destructive">{draftError}</p>
                ) : null}
                <div className="flex items-center justify-end gap-2">
                  <Button
                    type="button"
                    variant="ghost"
                    size="sm"
                    className="h-8"
                    onClick={cancelDraft}
                  >
                    {t('app.cancel')}
                  </Button>
                  <Button
                    type="button"
                    size="sm"
                    className="h-8"
                    onClick={() => {
                      void submitDraft();
                    }}
                  >
                    {t('app.save', { defaultValue: 'Zapisz' })}
                  </Button>
                </div>
              </div>
            ) : (
              <Button
                type="button"
                variant="outline"
                className="mt-2 w-full border-dashed"
                onClick={addValue}
              >
                <Plus className="size-4" />
                {t('attribute_values.add_action', { defaultValue: 'Dodaj wartość' })}
              </Button>
            )}
          </CardContent>
        </Card>

        {active ? (
          <div className="space-y-6">
            <DefinitionCard
              key={active.id}
              attributeCode={attribute.code}
              option={active}
              options={options}
              locales={localeChips}
              refresh={refresh}
              onDeleted={() => setActiveId(null)}
            />
            <PreviewCard
              option={active}
              attributeName={pickLabel(attribute.label, locale)}
              locales={localeChips}
            />
            <AuditCard attributeCode={attribute.code} option={active} />
          </div>
        ) : (
          <Card>
            <CardContent className="space-y-1 py-10 text-center">
              <h2 className="text-base font-semibold">
                {t('attribute_values.no_value_selected_title', {
                  defaultValue: 'Brak wybranej wartości',
                })}
              </h2>
              <p className="text-sm text-muted-foreground">
                {t('attribute_values.no_value_selected_desc', {
                  defaultValue: 'Wybierz wartość z listy lub dodaj nową, aby edytować szczegóły.',
                })}
              </p>
            </CardContent>
          </Card>
        )}
      </div>
    </div>
  );
}

function PreviewCard({
  option,
  attributeName,
  locales,
}: {
  option: OptionRow;
  attributeName: string;
  locales: LocaleChip[];
}) {
  const { t } = useTranslation();
  return (
    <Card className="p-6">
      <div className="mb-3 text-[11px] font-medium uppercase tracking-wider text-muted-foreground">
        {t('attribute_values.preview_title', { defaultValue: 'Podgląd' })}
      </div>
      <div className="grid gap-3 sm:grid-cols-3">
        {locales.map(({ code, flag }) => {
          const localeLabel = option.label[code];
          return (
            <div key={code} className="rounded-xl border border-zinc-200 bg-white p-4">
              <div className="mb-2 flex items-center gap-1.5 text-[11px] font-medium uppercase tracking-wider text-muted-foreground">
                <span aria-hidden>{flag}</span>
                <span>{code}</span>
              </div>
              <div className="text-[11.5px] text-muted-foreground">{attributeName}</div>
              <div className="mt-2 flex h-10 items-center gap-2 rounded-xl border border-zinc-200 bg-zinc-50 px-3">
                {option.color !== null ? (
                  <span
                    className="size-2.5 rounded-full"
                    style={{ background: option.color }}
                    aria-hidden
                  />
                ) : null}
                {localeLabel ? (
                  <span className="text-[13px] text-foreground">{localeLabel}</span>
                ) : (
                  <span className="text-[12.5px] italic text-muted-foreground">
                    {t('attribute_values.preview_no_translation', {
                      defaultValue: '(brak tłumaczenia)',
                    })}
                  </span>
                )}
                <span className="ml-auto text-muted-foreground">▾</span>
              </div>
            </div>
          );
        })}
      </div>
    </Card>
  );
}

function AuditCard({ attributeCode, option }: { attributeCode: string; option: OptionRow }) {
  const { t } = useTranslation();
  const { data } = useQuery<{ instances: number }>({
    queryKey: ['attribute_options_usage', attributeCode, option.code],
    queryFn: async () =>
      jsonFetch<{ instances: number }>(
        `/api/attributes/${attributeCode}/options/${option.code}/usage`,
      ),
    staleTime: 60_000,
  });
  const instances = data?.instances ?? 0;

  return (
    <Card className="p-6">
      <div className="mb-3 text-[11px] font-medium uppercase tracking-wider text-muted-foreground">
        {t('attribute_values.audit_title', { defaultValue: 'Wpływ i audyt' })}
      </div>
      <div className="grid gap-4 sm:grid-cols-3">
        <Stat
          value={instances.toLocaleString('pl-PL')}
          label={t('attribute_values.audit_instances_label', {
            defaultValue: 'instancji ma tę wartość',
          })}
        />
        <Stat
          value={String(option.position + 1)}
          label={t('attribute_values.audit_position_label', {
            defaultValue: 'pozycja w sortowaniu',
          })}
        />
        <Stat
          value="attribute.value.update"
          label={t('attribute_values.audit_event_label', { defaultValue: 'zdarzenie audit log' })}
          mono
        />
      </div>
      {instances > 0 ? (
        <div className="mt-4 flex items-start gap-2.5 rounded-xl border border-amber-200 bg-amber-50 px-4 py-2.5">
          <span className="text-amber-700">⚠</span>
          <div className="text-[12px] text-amber-900">
            <strong>{t('attribute_values.audit_warning_title', { defaultValue: 'Uwaga:' })}</strong>{' '}
            {t('attribute_values.audit_warning_body', {
              defaultValue:
                'ta wartość jest używana przez {{count}} obiektów. Usunięcie wymagać będzie migracji — system zaproponuje mapowanie na inną wartość.',
              count: instances,
            })}
          </div>
        </div>
      ) : null}
    </Card>
  );
}

function Stat({ value, label, mono }: { value: string; label: string; mono?: boolean }) {
  return (
    <div className="rounded-xl border border-zinc-200 bg-white p-3">
      <div
        className={cn(
          'text-[18px] font-semibold tabular-nums',
          mono ? 'font-mono text-[13px]' : '',
        )}
      >
        {value}
      </div>
      <div className="mt-0.5 text-[11.5px] text-muted-foreground">{label}</div>
    </div>
  );
}

function BackLink({ id }: { id: string }) {
  const { t } = useTranslation();
  return (
    <Button asChild variant="ghost" size="sm" className="-ml-3">
      <Link to={`/modeling/attributes/${id}`}>
        <ArrowLeft className="size-4" />
        {t('attribute_values.back', { defaultValue: 'Wróć do atrybutu' })}
      </Link>
    </Button>
  );
}

function ValueRowItem({
  option,
  isActive,
  locale,
  onSelect,
}: {
  option: OptionRow;
  isActive: boolean;
  locale: string;
  onSelect: () => void;
}) {
  const { t } = useTranslation();
  const label = pickLabel(option.label, locale) || option.code;
  const { attributes, listeners, setNodeRef, transform, transition, isDragging } = useSortable({
    id: option.id,
  });
  const style: React.CSSProperties = {
    transform: CSS.Transform.toString(transform),
    transition,
    opacity: isDragging ? 0.6 : 1,
  };

  return (
    <div
      ref={setNodeRef}
      style={style}
      className={cn(
        'flex w-full items-center gap-2 rounded-xl text-left transition',
        isActive
          ? 'bg-zinc-900 text-white'
          : 'border border-zinc-100 bg-white hover:border-zinc-200 hover:bg-zinc-50/60',
      )}
    >
      <button
        type="button"
        {...attributes}
        {...listeners}
        aria-label={t('attribute_values.drag_handle_aria', {
          defaultValue: 'Przeciągnij aby zmienić kolejność',
        })}
        className={cn(
          'flex h-full cursor-grab items-center px-2 py-2.5 text-zinc-300 hover:text-zinc-500 active:cursor-grabbing',
          isActive ? 'text-white/40 hover:text-white/70' : '',
        )}
      >
        <GripVertical className="size-4" />
      </button>
      <button
        type="button"
        onClick={onSelect}
        className="flex flex-1 items-center gap-3 px-1 py-2.5 text-left"
      >
        <span
          className={cn(
            'size-3 shrink-0 rounded-full',
            option.color === null ? 'border border-zinc-200' : '',
          )}
          style={option.color !== null ? { background: option.color } : undefined}
          aria-hidden
        />
        <div className="min-w-0 flex-1">
          <div className="flex items-center gap-2">
            <span
              className={cn(
                'truncate font-mono text-[12.5px] font-medium',
                isActive ? 'text-white/90' : 'text-zinc-900',
              )}
            >
              {option.code}
            </span>
            {option.default ? (
              <span
                className={cn(
                  'rounded px-1 text-[10px] font-semibold uppercase tracking-wider',
                  isActive ? 'bg-white/20 text-white' : 'bg-emerald-100 text-emerald-700',
                )}
              >
                {t('attribute_values.default_badge', { defaultValue: 'default' })}
              </span>
            ) : null}
            {option.deprecated ? (
              <span
                className={cn(
                  'rounded px-1 text-[10px] font-semibold uppercase tracking-wider',
                  isActive ? 'bg-white/20 text-white' : 'bg-zinc-200 text-zinc-600',
                )}
              >
                {t('attribute_values.deprecated_badge', { defaultValue: 'wycofana' })}
              </span>
            ) : null}
          </div>
          <div
            className={cn(
              'truncate text-[11.5px]',
              isActive ? 'text-white/70' : 'text-muted-foreground',
            )}
          >
            {label}
          </div>
        </div>
      </button>
    </div>
  );
}

function DefinitionCard({
  attributeCode,
  option,
  options,
  locales,
  refresh,
  onDeleted,
}: {
  attributeCode: string;
  option: OptionRow;
  options: OptionRow[];
  locales: LocaleChip[];
  refresh: () => void;
  onDeleted: () => void;
}) {
  const { t } = useTranslation();
  const [labelMap, setLabelMap] = useState<Record<string, string>>({ ...option.label });
  // #1263 — default the active tab to the tenant's first enabled locale.
  const [activeLocale, setActiveLocale] = useState<string>(locales[0]?.code ?? 'pl');
  const [color, setColor] = useState<string | null>(option.color);
  const [isDefault, setIsDefault] = useState(option.default);
  const [isDeprecated, setIsDeprecated] = useState(option.deprecated);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const index = options.findIndex((o) => o.id === option.id);
  const canMoveUp = index > 0;
  const canMoveDown = index >= 0 && index < options.length - 1;

  const dirty =
    JSON.stringify(labelMap) !== JSON.stringify(option.label) ||
    color !== option.color ||
    isDefault !== option.default ||
    isDeprecated !== option.deprecated;

  const buildLabel = (): Record<string, string> => {
    const next: Record<string, string> = {};
    for (const [code, value] of Object.entries(labelMap)) {
      if (value.trim().length > 0) next[code] = value;
    }
    return next;
  };

  const setLabelFor = (locale: string, value: string) => {
    setLabelMap((prev) => ({ ...prev, [locale]: value }));
  };

  const save = async () => {
    setSaving(true);
    setError(null);
    try {
      await jsonFetch(`/api/attributes/${attributeCode}/options/${option.code}`, {
        method: 'PATCH',
        contentType: 'application/merge-patch+json',
        body: { label: buildLabel(), color, default: isDefault, deprecated: isDeprecated },
      });
      refresh();
    } catch (err) {
      if (err instanceof HttpError) {
        setError(`HTTP ${err.status}`);
      } else {
        setError(t('attribute_values.save_error', { defaultValue: 'Nie udało się zapisać' }));
      }
    } finally {
      setSaving(false);
    }
  };

  const swap = async (otherIndex: number) => {
    const other = options[otherIndex];
    if (!other) return;
    await jsonFetch(`/api/attributes/${attributeCode}/options/${option.code}`, {
      method: 'PATCH',
      contentType: 'application/merge-patch+json',
      body: { position: other.position },
    });
    await jsonFetch(`/api/attributes/${attributeCode}/options/${other.code}`, {
      method: 'PATCH',
      contentType: 'application/merge-patch+json',
      body: { position: option.position },
    });
    refresh();
  };

  const remove = async () => {
    if (
      !window.confirm(t('attribute_values.delete_confirm', { defaultValue: 'Usunąć tę wartość?' }))
    ) {
      return;
    }
    try {
      await jsonFetch(`/api/attributes/${attributeCode}/options/${option.code}`, {
        method: 'DELETE',
      });
      onDeleted();
      refresh();
    } catch (err) {
      if (err instanceof HttpError) setError(`HTTP ${err.status}`);
    }
  };

  return (
    <Card>
      <CardContent className="space-y-6 pt-6">
        <div className="flex items-start justify-between gap-3">
          <div className="text-[11px] font-medium uppercase tracking-wider text-muted-foreground">
            {t('attribute_values.definition_title', { defaultValue: 'Definicja wartości' })}
          </div>
          <div className="flex items-center gap-1">
            <Button
              type="button"
              variant="ghost"
              size="icon"
              onClick={() => {
                void swap(index - 1);
              }}
              disabled={!canMoveUp}
              aria-label={t('attribute_values.move_up_tooltip', { defaultValue: 'Wyżej' })}
            >
              <ArrowUp className="size-4" />
            </Button>
            <Button
              type="button"
              variant="ghost"
              size="icon"
              onClick={() => {
                void swap(index + 1);
              }}
              disabled={!canMoveDown}
              aria-label={t('attribute_values.move_down_tooltip', { defaultValue: 'Niżej' })}
            >
              <ArrowDown className="size-4" />
            </Button>
            <Button
              type="button"
              variant="ghost"
              size="icon"
              onClick={() => {
                void remove();
              }}
              aria-label={t('attribute_values.delete_tooltip', { defaultValue: 'Usuń' })}
            >
              <Trash2 className="size-4 text-rose-500" />
            </Button>
          </div>
        </div>

        <div className="grid gap-4 sm:grid-cols-2">
          <div className="space-y-1.5">
            <Label htmlFor="value-code">
              {t('attribute_values.code_label', { defaultValue: 'Code' })}
            </Label>
            <Input id="value-code" value={option.code} readOnly className="font-mono" />
          </div>
          <div className="space-y-1.5">
            <Label>
              {t('attribute_values.color_label', { defaultValue: 'Kolor (opcjonalny)' })}
            </Label>
            <div className="flex items-center gap-3">
              <ColorPicker
                selected={color ?? ''}
                onSelect={(hex) => setColor(hex)}
                options={ATTRIBUTE_OPTION_SWATCHES}
              />
              {color !== null ? (
                <Button type="button" variant="ghost" size="sm" onClick={() => setColor(null)}>
                  {t('app.clear', { defaultValue: 'Wyczyść' })}
                </Button>
              ) : null}
            </div>
          </div>
        </div>

        <div className="space-y-2">
          <Label>
            {t('attribute_values.labels_title', { defaultValue: 'Etykiety wyświetlane' })}
          </Label>
          <div className="flex items-center gap-1 border-b border-zinc-100">
            {locales.map(({ code, flag }) => {
              const filled = (labelMap[code] ?? '').trim().length > 0;
              return (
                <button
                  key={code}
                  type="button"
                  onClick={() => setActiveLocale(code)}
                  className={cn(
                    '-mb-px flex items-center gap-1.5 border-b-2 px-3 py-2 text-[12.5px] font-medium uppercase tracking-wider transition',
                    activeLocale === code
                      ? 'border-zinc-900 text-foreground'
                      : 'border-transparent text-muted-foreground hover:text-foreground',
                  )}
                >
                  <span aria-hidden>{flag}</span>
                  <span>{code}</span>
                  {!filled ? (
                    <span className="size-1.5 rounded-full bg-amber-400" aria-hidden />
                  ) : null}
                </button>
              );
            })}
          </div>
          <Input
            value={labelMap[activeLocale] ?? ''}
            onChange={(e) => setLabelFor(activeLocale, e.target.value)}
            placeholder={t('attribute_values.labels_placeholder', {
              defaultValue: 'Etykieta wartości',
            })}
          />
        </div>

        <div className="grid gap-3 sm:grid-cols-2">
          <label
            className={cn(
              'flex cursor-pointer items-start gap-3 rounded-xl border px-3 py-2.5 transition',
              isDefault
                ? 'border-emerald-300 bg-emerald-50/50'
                : 'border-zinc-200 hover:bg-zinc-50',
            )}
          >
            <input
              type="checkbox"
              checked={isDefault}
              onChange={(e) => setIsDefault(e.target.checked)}
              className="mt-1"
            />
            <span>
              <span className="block text-[13px] font-medium">
                {t('attribute_values.default_label', { defaultValue: 'Wartość domyślna' })}
              </span>
              <span className="block text-[11.5px] text-muted-foreground">
                {t('attribute_values.default_desc', {
                  defaultValue: 'Wybierana automatycznie dla nowych obiektów',
                })}
              </span>
            </span>
          </label>
          <label
            className={cn(
              'flex cursor-pointer items-start gap-3 rounded-xl border px-3 py-2.5 transition',
              isDeprecated ? 'border-zinc-300 bg-zinc-100' : 'border-zinc-200 hover:bg-zinc-50',
            )}
          >
            <input
              type="checkbox"
              checked={isDeprecated}
              onChange={(e) => setIsDeprecated(e.target.checked)}
              className="mt-1"
            />
            <span>
              <span className="block text-[13px] font-medium">
                {t('attribute_values.deprecated_label', { defaultValue: 'Wycofana' })}
              </span>
              <span className="block text-[11.5px] text-muted-foreground">
                {t('attribute_values.deprecated_desc', {
                  defaultValue: 'Ukryj w nowych formularzach, zachowaj w istniejących',
                })}
              </span>
            </span>
          </label>
        </div>

        {error !== null ? (
          <p className="rounded-md border border-destructive/50 bg-destructive/5 px-3 py-2 text-sm text-destructive">
            {error}
          </p>
        ) : null}

        <div className="flex items-center justify-end gap-2">
          <Button
            type="button"
            variant="ghost"
            disabled={!dirty || saving}
            onClick={() => {
              setLabelMap({ ...option.label });
              setColor(option.color);
              setIsDefault(option.default);
              setIsDeprecated(option.deprecated);
            }}
          >
            {t('app.cancel')}
          </Button>
          <Button
            type="button"
            disabled={!dirty || saving}
            onClick={() => {
              void save();
            }}
          >
            {t('attribute_values.save_action', { defaultValue: 'Zapisz zmiany' })}
          </Button>
        </div>
      </CardContent>
    </Card>
  );
}
