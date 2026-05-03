import { useQuery } from '@tanstack/react-query';
import { ArrowLeft, Check, Lock, Search } from 'lucide-react';
import { useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Link, useNavigate } from 'react-router';

import { BuiltInLockBadge } from '@/components/modeling/built-in-lock-badge';
import { ColorPicker, DEFAULT_WIZARD_COLORS } from '@/components/modeling/color-picker';
import { DEFAULT_WIZARD_ICONS, IconPicker } from '@/components/modeling/icon-picker';
import { LocaleTabsField } from '@/components/modeling/locale-tabs-field';
import { ObjectTypeIcon } from '@/components/modeling/object-type-icon';
import { SettingToggleRow } from '@/components/modeling/setting-toggle-row';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { HttpError, jsonFetch } from '@/lib/http';
import { useCurrentWorkspace, useInvalidateCurrentWorkspace } from '@/lib/use-current-workspace';
import { cn } from '@/lib/utils';

interface CreatedObjectType {
  id: string;
  code: string;
  kind: string;
  label: Record<string, string>;
  builtIn: boolean;
}

interface AttributeGroupRow {
  id: string;
  code: string;
  label?: Record<string, string> | string | null;
  icon?: string | null;
  color?: string | null;
  is_system_group?: boolean;
  isSystemGroup?: boolean;
}

function resolveGroupLabel(
  label: Record<string, string> | string | null | undefined,
  language: string,
): string {
  if (typeof label === 'string') return label;
  if (label && typeof label === 'object') {
    return label[language] ?? label.pl ?? label.en ?? Object.values(label)[0] ?? '';
  }
  return '';
}

const STEP_KEYS = ['identification', 'attributes', 'settings', 'summary'] as const;

/**
 * VIEW-01 (#372) — full-screen 4-step wizard for `/modeling/object-types/new`.
 * Renders inline within the modeling shell (NOT a dialog/sheet) so the
 * sidebar workspace + topbar stay visible. Layout matches
 * `NewObjectTypeView` (object-types.jsx 304–478) — content column +
 * 320px sidebar with live preview and tips.
 */
export function ObjectTypeWizard() {
  const { t, i18n } = useTranslation();
  const navigate = useNavigate();
  const workspace = useCurrentWorkspace();
  const invalidateWorkspace = useInvalidateCurrentWorkspace();

  const enabledLocales = workspace.data?.enabledLocales ?? ['pl', 'en'];
  const primaryLocale = workspace.data?.primaryLocale ?? 'pl';

  const [step, setStep] = useState<number>(1);
  const [label, setLabel] = useState<Record<string, string>>({});
  const [code, setCode] = useState<string>('');
  const [icon, setIcon] = useState<string>(DEFAULT_WIZARD_ICONS[0]);
  const [color, setColor] = useState<string>(DEFAULT_WIZARD_COLORS[0]);
  const [hierarchical, setHierarchical] = useState(false);
  const [hasVariants, setHasVariants] = useState(false);
  const [abstractFlag, setAbstractFlag] = useState(false);
  const [pickedGroupIds, setPickedGroupIds] = useState<Set<string>>(new Set());
  const [groupQuery, setGroupQuery] = useState('');
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const namePrimary = label[primaryLocale]?.trim() ?? '';

  const { data: attributeGroups = [], isLoading: groupsLoading } = useQuery<AttributeGroupRow[]>({
    queryKey: ['attribute_groups', 'picker'],
    queryFn: async () => {
      const data = await jsonFetch<{ member?: AttributeGroupRow[] }>(
        '/api/attribute_groups?itemsPerPage=200',
      );
      return data.member ?? [];
    },
    staleTime: 30_000,
  });

  const customGroups = useMemo(
    () => attributeGroups.filter((g) => !(g.is_system_group ?? g.isSystemGroup ?? false)),
    [attributeGroups],
  );

  const filteredCustomGroups = useMemo(() => {
    const needle = groupQuery.trim().toLowerCase();
    if (needle === '') return customGroups;
    return customGroups.filter(
      (g) =>
        g.code.toLowerCase().includes(needle) ||
        resolveGroupLabel(g.label, i18n.language).toLowerCase().includes(needle),
    );
  }, [customGroups, groupQuery, i18n.language]);

  const toggleGroup = (id: string) => {
    setPickedGroupIds((prev) => {
      const next = new Set(prev);
      if (next.has(id)) next.delete(id);
      else next.add(id);
      return next;
    });
  };

  const validateStep = (n: number): string | null => {
    if (n === 1) {
      if (namePrimary === '') {
        return t('object_type_wizard.validation_name_pl_required', {
          defaultValue: 'Nazwa PL jest wymagana.',
        });
      }
      if (code === '') {
        return t('object_type_wizard.validation_code_required', {
          defaultValue: 'Code jest wymagany.',
        });
      }
      if (!/^[a-z0-9_]+$/.test(code)) {
        return t('object_type_wizard.validation_code_format', {
          defaultValue: 'Code musi być w snake_case (małe litery, cyfry, _).',
        });
      }
    }
    return null;
  };

  const goNext = () => {
    setError(null);
    const issue = validateStep(step);
    if (issue) {
      setError(issue);
      return;
    }
    setStep((s) => Math.min(STEP_KEYS.length, s + 1));
  };

  const goPrev = () => {
    setError(null);
    setStep((s) => Math.max(1, s - 1));
  };

  const handleSubmit = async () => {
    const issue = validateStep(1);
    if (issue) {
      setError(issue);
      setStep(1);
      return;
    }
    setSubmitting(true);
    setError(null);

    try {
      const created = await jsonFetch<CreatedObjectType>('/api/object_types', {
        method: 'POST',
        body: {
          code,
          label: Object.fromEntries(
            Object.entries(label).filter(([, v]) => v && v.trim().length > 0),
          ),
          icon,
          color,
          hierarchical,
          hasVariants,
          abstract: abstractFlag,
        },
      });

      if (pickedGroupIds.size > 0) {
        // Sequential POSTs match the DeclareAttributeGroupDialog pattern:
        // server is idempotent on duplicate, so a partial-failure retry is
        // safe, and ordering keeps the audit log deterministic.
        const failed: string[] = [];
        for (const groupId of pickedGroupIds) {
          try {
            await jsonFetch(`/api/object_types/${created.id}/groups/${groupId}`, {
              method: 'POST',
            });
          } catch {
            failed.push(groupId);
          }
        }
        if (failed.length > 0) {
          setError(
            t('object_type_wizard.attach_groups_partial_error', {
              defaultValue:
                'Typ utworzono, ale {{count}} grup atrybutów nie zostało dołączonych. Dołącz je ręcznie na widoku detalu.',
              count: failed.length,
            }),
          );
        }
      }

      void invalidateWorkspace();
      navigate(`/modeling/object-types/${created.id}`, { replace: true });
    } catch (e) {
      if (e instanceof HttpError && e.status === 409) {
        setError(
          t('object_type_wizard.conflict_code_taken', {
            defaultValue: 'Code „{{code}}" jest już zajęty w tym tenancie.',
            code,
          }),
        );
        setStep(1);
      } else {
        setError(e instanceof Error ? e.message : 'unknown');
      }
    } finally {
      setSubmitting(false);
    }
  };

  return (
    <div>
      <Button
        asChild
        variant="ghost"
        size="sm"
        className="-ml-3 mb-4 gap-1.5 text-[12.5px] font-medium text-zinc-500 hover:text-zinc-900"
      >
        <Link to="/modeling/object-types">
          <ArrowLeft className="size-3.5" />
          {t('object_types.back_to_list', { defaultValue: 'Wstecz do listy Object Types' })}
        </Link>
      </Button>

      <header className="mb-6 flex items-start justify-between gap-6">
        <div className="flex-1">
          <div className="text-[13px] font-medium text-zinc-500">
            {t('object_type_wizard.title_new', { defaultValue: 'Nowy ObjectType' })}
          </div>
          <div className="display text-[28px] font-semibold tracking-tight">
            {namePrimary || t('object_type_wizard.default_name', { defaultValue: 'Bez nazwy' })}
          </div>
          <p className="mt-1 max-w-2xl text-[13px] text-zinc-500">
            {t('object_type_wizard.intro', {
              defaultValue:
                'Stwórz nowy rodzaj obiektu w swoim PIM-ie. Po utworzeniu będzie można dodawać instancje, podłączać atrybuty i mapować integracje.',
            })}
          </p>
        </div>
        <div className="flex shrink-0 items-center gap-2">
          <Button asChild variant="ghost">
            <Link to="/modeling/object-types">
              {t('object_type_wizard.cancel', { defaultValue: 'Anuluj' })}
            </Link>
          </Button>
          <Button
            type="button"
            disabled={submitting}
            onClick={() => void handleSubmit()}
            className="gap-1.5"
          >
            <Check className="size-3.5" />
            {submitting
              ? t('object_type_wizard.submitting', { defaultValue: 'Tworzenie…' })
              : t('object_type_wizard.submit', { defaultValue: 'Utwórz typ' })}
          </Button>
        </div>
      </header>

      <ol
        className="mb-6 flex flex-wrap items-center gap-2"
        role="progressbar"
        aria-valuenow={step}
        aria-valuemin={1}
        aria-valuemax={STEP_KEYS.length}
        aria-label={t('object_type_wizard.progress_aria', {
          defaultValue: 'Krok {{n}} z {{max}}',
          n: step,
          max: STEP_KEYS.length,
        })}
      >
        {STEP_KEYS.map((key, idx) => {
          const n = idx + 1;
          const isActive = step === n;
          const isDone = step > n;
          return (
            <li key={key} className="flex items-center gap-2">
              <button
                type="button"
                onClick={() => setStep(n)}
                className={cn(
                  'inline-flex h-8 items-center gap-2 rounded-xl px-3 text-[12.5px] font-medium transition focus:outline-none focus-visible:ring-2 focus-visible:ring-ring',
                  isActive
                    ? 'bg-zinc-900 text-white'
                    : isDone
                      ? 'bg-zinc-100 text-zinc-700'
                      : 'border border-zinc-200 bg-white text-zinc-500',
                )}
              >
                <span
                  className={cn(
                    'num grid size-5 place-items-center rounded-full text-[10.5px] font-semibold',
                    isActive
                      ? 'bg-white/15'
                      : isDone
                        ? 'bg-emerald-500 text-white'
                        : 'bg-zinc-100 text-zinc-500',
                  )}
                >
                  {isDone ? <Check className="size-2.5" /> : n}
                </span>
                {t(`object_type_wizard.step_${n}_label`, {
                  defaultValue:
                    n === 1
                      ? 'Identyfikacja'
                      : n === 2
                        ? 'Atrybuty'
                        : n === 3
                          ? 'Ustawienia'
                          : 'Podsumowanie',
                })}
              </button>
              {idx < STEP_KEYS.length - 1 ? <span className="block h-px w-6 bg-zinc-200" /> : null}
            </li>
          );
        })}
      </ol>

      <div className="grid gap-6 lg:grid-cols-[1fr_320px]">
        <Card>
          <CardContent className="space-y-5 p-6">
            {step === 1 ? (
              <>
                <div className="text-[11px] font-medium uppercase tracking-wider text-zinc-500">
                  {t('object_type_wizard.step_1_label', { defaultValue: 'Identyfikacja' })}
                </div>
                <div>
                  <div className="mb-2 text-[11.5px] font-medium text-zinc-500">
                    {t('object_type_wizard.field_name', { defaultValue: 'Nazwa' })}
                  </div>
                  <LocaleTabsField
                    values={label}
                    enabledLocales={enabledLocales}
                    primaryLocale={primaryLocale}
                    placeholder={t('object_type_wizard.name_placeholder', {
                      defaultValue: 'np. Subskrypcja',
                    })}
                    onChange={setLabel}
                    onLocaleAdded={() => void invalidateWorkspace()}
                  />
                </div>
                <div className="grid grid-cols-1 gap-x-8 gap-y-4 sm:grid-cols-2">
                  <div>
                    <div className="mb-1.5 text-[11.5px] font-medium text-zinc-500">
                      {t('object_type_wizard.field_code', { defaultValue: 'Code' })}
                    </div>
                    <Input
                      value={code}
                      onChange={(e) =>
                        setCode(e.target.value.toLowerCase().replace(/[^a-z0-9_]/g, ''))
                      }
                      placeholder="subscription"
                      className="font-mono"
                    />
                  </div>
                  <div>
                    <div className="mb-1.5 text-[11.5px] font-medium text-zinc-500">
                      {t('object_type_wizard.field_icon', { defaultValue: 'Ikona' })}
                    </div>
                    <IconPicker selected={icon} onSelect={setIcon} />
                  </div>
                  <div className="sm:col-span-2">
                    <div className="mb-1.5 text-[11.5px] font-medium text-zinc-500">
                      {t('object_type_wizard.field_color', { defaultValue: 'Kolor' })}
                    </div>
                    <ColorPicker selected={color} onSelect={setColor} />
                  </div>
                </div>
              </>
            ) : null}
            {step === 2 ? (
              <>
                <div className="text-[11px] font-medium uppercase tracking-wider text-zinc-500">
                  {t('object_type_wizard.step_2_builtin_section', {
                    defaultValue: 'Built-in attribute groups',
                  })}
                </div>
                <p className="-mt-2 text-[12.5px] text-zinc-500">
                  {t('object_type_wizard.step_2_intro', {
                    defaultValue:
                      'Te grupy zostaną dołączone automatycznie i nie można ich usunąć:',
                  })}
                </p>
                <div className="space-y-2">
                  {['Identyfikacja', 'Audyt', 'Lokalizacje'].map((g) => (
                    <div
                      key={g}
                      className="flex items-center gap-2 rounded-xl bg-zinc-50 px-3 py-2.5"
                    >
                      <BuiltInLockBadge />
                      <span className="text-[13px] font-medium">{g}</span>
                    </div>
                  ))}
                </div>
                <div className="flex items-center justify-between pt-3">
                  <div className="text-[11px] font-medium uppercase tracking-wider text-zinc-500">
                    {t('object_type_wizard.step_2_custom_label', {
                      defaultValue: 'Custom attribute groups',
                    })}
                  </div>
                  <Button asChild variant="ghost" size="sm" className="h-7 text-[12px]">
                    <Link to="/modeling/attribute-groups/new" target="_blank" rel="noopener">
                      +{' '}
                      {t('object_type_wizard.step_2_create_group', {
                        defaultValue: 'Nowa grupa',
                      })}
                    </Link>
                  </Button>
                </div>
                <p className="-mt-2 text-[12.5px] text-zinc-500">
                  {t('object_type_wizard.step_2_pick_intro', {
                    defaultValue:
                      'Zaznacz grupy które chcesz dołączyć do tego typu. Built-in dołączane są automatycznie.',
                  })}
                </p>
                <div className="flex h-9 items-center gap-2 rounded-xl border border-zinc-200 bg-white px-3">
                  <Search className="size-3.5 text-zinc-400" />
                  <input
                    type="text"
                    value={groupQuery}
                    onChange={(e) => setGroupQuery(e.target.value)}
                    placeholder={t('object_type_wizard.step_2_search_placeholder', {
                      defaultValue: 'Szukaj grup po code lub nazwie…',
                    })}
                    className="flex-1 bg-transparent text-[13px] outline-none placeholder:text-zinc-400"
                    aria-label={t('object_type_wizard.step_2_search_aria', {
                      defaultValue: 'Filtruj grupy atrybutów',
                    })}
                  />
                </div>
                {groupsLoading ? (
                  <p className="text-[12.5px] text-muted-foreground">
                    {t('app.loading', { defaultValue: 'Ładowanie…' })}
                  </p>
                ) : customGroups.length === 0 ? (
                  <div className="rounded-2xl border border-dashed border-zinc-200 px-4 py-6 text-center text-[12.5px] text-zinc-500">
                    {t('object_type_wizard.step_2_empty_custom', {
                      defaultValue:
                        'Brak custom grup. Utwórz pierwszą — np. Marketing, Pricing, Specyfika.',
                    })}
                  </div>
                ) : filteredCustomGroups.length === 0 ? (
                  <p className="px-1 text-[12.5px] text-muted-foreground">
                    {t('object_type_wizard.step_2_no_results', {
                      defaultValue: 'Brak wyników dla podanego filtra.',
                    })}
                  </p>
                ) : (
                  <div className="max-h-[320px] divide-y divide-zinc-50 overflow-y-auto rounded-xl border border-zinc-100">
                    {filteredCustomGroups.map((g) => {
                      const isPicked = pickedGroupIds.has(g.id);
                      const labelText = resolveGroupLabel(g.label, i18n.language) || g.code;
                      return (
                        <button
                          key={g.id}
                          type="button"
                          onClick={() => toggleGroup(g.id)}
                          aria-pressed={isPicked}
                          className={cn(
                            'flex w-full items-center gap-3 px-4 py-2.5 text-left transition',
                            isPicked ? 'bg-violet-50/60' : 'hover:bg-zinc-50',
                          )}
                        >
                          <span
                            className={cn(
                              'grid size-5 shrink-0 place-items-center rounded border text-[10.5px] font-semibold',
                              isPicked
                                ? 'border-violet-500 bg-violet-500 text-white'
                                : 'border-zinc-300 bg-white',
                            )}
                          >
                            {isPicked ? <Check className="size-3" /> : null}
                          </span>
                          <span
                            className="grid size-8 shrink-0 place-items-center rounded-md text-[14px]"
                            style={{
                              background: g.color ? `${g.color}1f` : '#f4f4f5',
                              color: g.color ?? '#71717a',
                            }}
                            aria-hidden
                          >
                            {g.icon ?? '📦'}
                          </span>
                          <div className="min-w-0 flex-1">
                            <div className="truncate text-[13px] font-medium tracking-tight">
                              {labelText}
                            </div>
                            <div className="truncate font-mono text-[11px] text-zinc-400">
                              {g.code}
                            </div>
                          </div>
                        </button>
                      );
                    })}
                  </div>
                )}
                {pickedGroupIds.size > 0 ? (
                  <p className="text-[12px] text-muted-foreground">
                    {t('object_type_wizard.step_2_picked_count', {
                      defaultValue: 'Wybrano: {{count}}',
                      count: pickedGroupIds.size,
                    })}
                  </p>
                ) : null}
              </>
            ) : null}
            {step === 3 ? (
              <>
                <div className="text-[11px] font-medium uppercase tracking-wider text-zinc-500">
                  {t('object_type_wizard.step_3_label', { defaultValue: 'Ustawienia' })}
                </div>
                <SettingToggleRow
                  label={t('object_types.setting_hierarchical_label', {
                    defaultValue: 'Is hierarchical',
                  })}
                  description={t('object_types.setting_hierarchical_desc', {
                    defaultValue: 'Obiekty mogą tworzyć drzewo (jak Category)',
                  })}
                  checked={hierarchical}
                  onChange={setHierarchical}
                />
                <SettingToggleRow
                  label={t('object_types.setting_variants_label', { defaultValue: 'Has variants' })}
                  description={t('object_types.setting_variants_desc', {
                    defaultValue: 'Obiekty mogą mieć warianty (jak Product → kolor × rozmiar)',
                  })}
                  checked={hasVariants}
                  onChange={setHasVariants}
                />
                <SettingToggleRow
                  label={t('object_types.setting_abstract_label', { defaultValue: 'Is abstract' })}
                  description={t('object_types.setting_abstract_desc', {
                    defaultValue: 'Nie można tworzyć instancji bezpośrednio (tylko przez sub-typy)',
                  })}
                  checked={abstractFlag}
                  onChange={setAbstractFlag}
                />
              </>
            ) : null}
            {step === 4 ? (
              <>
                <div className="text-[11px] font-medium uppercase tracking-wider text-zinc-500">
                  {t('object_type_wizard.step_4_label', { defaultValue: 'Podsumowanie' })}
                </div>
                <p className="text-[13px] text-zinc-600">
                  {t('object_type_wizard.step_4_intro', {
                    defaultValue:
                      'Sprawdź ustawienia i zatwierdź. Po utworzeniu typ pojawi się w sekcji Custom.',
                  })}
                </p>
                <div className="grid grid-cols-1 gap-3 rounded-2xl bg-zinc-50 p-4 text-[13px] sm:grid-cols-2">
                  <div>
                    <span className="text-zinc-500">
                      {t('object_type_wizard.field_name', { defaultValue: 'Nazwa' })}:
                    </span>{' '}
                    <span className="font-medium">{namePrimary || '—'}</span>
                  </div>
                  <div>
                    <span className="text-zinc-500">
                      {t('object_type_wizard.field_code', { defaultValue: 'Code' })}:
                    </span>{' '}
                    <span className="font-mono">{code || '—'}</span>
                  </div>
                  <div>
                    <span className="text-zinc-500">
                      {t('object_type_wizard.field_icon', { defaultValue: 'Ikona' })}:
                    </span>{' '}
                    {icon}
                  </div>
                  <div className="flex items-center gap-2">
                    <span className="text-zinc-500">
                      {t('object_type_wizard.field_color', { defaultValue: 'Kolor' })}:
                    </span>
                    <span aria-hidden className="size-4 rounded" style={{ background: color }} />
                  </div>
                  <div className="sm:col-span-2">
                    <span className="text-zinc-500">
                      {t('object_type_wizard.summary_groups_label', {
                        defaultValue: 'Custom attribute groups',
                      })}
                      :
                    </span>{' '}
                    {pickedGroupIds.size === 0 ? (
                      <span className="text-zinc-400">
                        {t('object_type_wizard.summary_groups_empty', {
                          defaultValue: '— (tylko built-in)',
                        })}
                      </span>
                    ) : (
                      <span className="font-medium">
                        {Array.from(pickedGroupIds)
                          .map((id) => {
                            const g = attributeGroups.find((x) => x.id === id);
                            return g ? resolveGroupLabel(g.label, i18n.language) || g.code : id;
                          })
                          .join(', ')}
                      </span>
                    )}
                  </div>
                </div>
              </>
            ) : null}

            {error ? (
              <p role="alert" className="text-sm text-rose-600">
                {error}
              </p>
            ) : null}

            <div className="flex items-center justify-between border-t border-zinc-100 pt-3">
              <Button variant="ghost" onClick={goPrev} disabled={step === 1}>
                {t('object_type_wizard.prev', { defaultValue: '← Poprzedni' })}
              </Button>
              {step < STEP_KEYS.length ? (
                <Button onClick={goNext}>
                  {t('object_type_wizard.next', { defaultValue: 'Dalej →' })}
                </Button>
              ) : (
                <Button
                  onClick={() => void handleSubmit()}
                  disabled={submitting}
                  className="gap-1.5"
                >
                  <Check className="size-3.5" />
                  {submitting
                    ? t('object_type_wizard.submitting', { defaultValue: 'Tworzenie…' })
                    : t('object_type_wizard.submit', { defaultValue: 'Utwórz typ' })}
                </Button>
              )}
            </div>
          </CardContent>
        </Card>

        <aside className="space-y-3">
          <Card>
            <CardContent className="space-y-3 p-5">
              <div className="text-[11px] font-medium uppercase tracking-wider text-zinc-500">
                {t('object_type_wizard.preview_label', { defaultValue: 'Podgląd' })}
              </div>
              <div className="flex items-center gap-3">
                <ObjectTypeIcon icon={icon} color={color} size="sm" />
                <div>
                  <div className="text-[14px] font-semibold tracking-tight">
                    {namePrimary ||
                      t('object_type_wizard.preview_default_name', {
                        defaultValue: 'Nowy typ',
                      })}
                  </div>
                  <div className="font-mono text-[11.5px] text-zinc-500">
                    {code ||
                      t('object_type_wizard.preview_default_code', {
                        defaultValue: 'code…',
                      })}
                  </div>
                </div>
              </div>
            </CardContent>
          </Card>
          <Card>
            <CardContent className="space-y-3 p-5">
              <div className="text-[11px] font-medium uppercase tracking-wider text-zinc-500">
                {t('object_type_wizard.tips_label', { defaultValue: 'Wskazówki' })}
              </div>
              <ul className="space-y-1.5 text-[12px] text-zinc-600">
                <li className="flex items-start gap-2">
                  <Lock className="mt-0.5 size-3 text-zinc-400" />
                  {t('object_type_wizard.tip_snake_case', {
                    defaultValue: 'Code powinien być w snake_case',
                  })}
                </li>
                <li>
                  •{' '}
                  {t('object_type_wizard.tip_name_visibility', {
                    defaultValue: 'Nazwa pojawia się w UI i navbarze',
                  })}
                </li>
                <li>
                  •{' '}
                  {t('object_type_wizard.tip_hierarchical', {
                    defaultValue: 'Hierarchical = drzewo (jak Category)',
                  })}
                </li>
                <li>
                  •{' '}
                  {t('object_type_wizard.tip_variants', {
                    defaultValue: 'Variants = osie wariantowości (kolor × rozmiar)',
                  })}
                </li>
              </ul>
            </CardContent>
          </Card>
        </aside>
      </div>
    </div>
  );
}
