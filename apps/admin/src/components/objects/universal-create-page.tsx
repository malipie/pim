/*
 * UP-08 (#1029) — universal full-page create wizard for any ObjectType.
 *
 * Operator decision: "Dodawanie - ma być pełen widok jak przy produkcie
 *   - nie żaden modal." This is a dedicated route (not a Dialog) that
 *   gives custom kinds the same full-page create experience that
 *   `/products/new` offers for products.
 *
 * Scope decisions:
 *   - POSTs to `/api/objects` (poly-kind endpoint; existing since
 *     ULV-02). On success, navigates to `/objects/:slug/:id` — the
 *     UP-07 detail page picks up from there for full inline editing.
 *   - Form is minimal in MVP: code + initial attribute values for any
 *     attribute declared by the ObjectType (via `effective-attribute-groups
 *     /preview` against the OT with an empty categoryIds payload). Full
 *     parity with /products/new (category-driven attribute overlay
 *     during create, validation rules per field) lands in follow-up
 *     once UP-07 categories editing is universal too.
 */
import { useQuery } from '@tanstack/react-query';
import { ArrowLeft, Save } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Link, useNavigate } from 'react-router';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { toast } from '@/components/ui/toast';
import { AttrGroupCard } from '@/features/catalog/products/components/attr-group-card';
import { AttrRow } from '@/features/catalog/products/components/attr-row';
import { CategorySelectorCard } from '@/features/catalog/products/components/category-selector-card';
import type {
  GroupMeta,
  LocaleOption,
  ProductLocale,
} from '@/features/catalog/products/components/types';
import { jsonFetch } from '@/lib/http';
import { cn } from '@/lib/utils';

export interface UniversalCreatePageProps {
  objectTypeId: string;
  objectTypeCode: string;
  objectTypeLabel: string;
  /** Where the cancel button navigates (typically `/objects/:slug`). */
  backHref: string;
  /** Builder for the detail route after successful create. */
  detailPathFor: (id: string) => string;
  /**
   * #1104 — when `true`, surfaces a Multimedia tab next to the
   * attribute tabs. Uploads need the post-create object id, so the
   * panel renders a disclaimer pointing the operator at the detail
   * page; the tab itself stays visible so the capability advertised
   * in modeling matches what the operator sees here.
   */
  hasMultimedia?: boolean;
  /**
   * #1359 — when the ObjectType is categorizable, the create form shows a
   * category picker and requires at least one category before save (same
   * rule products enforce). Non-categorizable types skip it entirely.
   */
  isCategorizable?: boolean;
}

const GROUP_ICONS: Record<string, string> = {
  identification: '🔑',
  identyfikacja: '🔑',
  marketing: '✨',
  technical: '⚙',
  technicals: '⚙',
  specyfikacje: '⚙',
  logistics: '📦',
  logistyka: '📦',
  pricing: '💰',
  cennik: '💰',
};

const ATTRIBUTES_TAB = 'attributes';
const MULTIMEDIA_TAB = 'multimedia';

export function UniversalCreatePage({
  objectTypeId,
  objectTypeCode,
  objectTypeLabel,
  backHref,
  detailPathFor,
  hasMultimedia = false,
  isCategorizable = false,
}: UniversalCreatePageProps) {
  const { t, i18n } = useTranslation();
  const navigate = useNavigate();
  const lang = i18n.language === 'pl' ? 'pl' : 'en';
  const [code, setCode] = useState('');
  // #1359 — create-mode category selection (stashed in local state, sent
  // with the POST /api/objects body), mirroring /products/new.
  const [categoryIds, setCategoryIds] = useState<string[]>([]);
  const [primaryCategoryId, setPrimaryCategoryId] = useState<string | null>(null);
  const [dirtyFields, setDirtyFields] = useState<Record<string, unknown>>({});
  const [expandedGroups, setExpandedGroups] = useState<Set<string>>(new Set());
  const [isSaving, setIsSaving] = useState(false);
  const [locale, setLocale] = useState<ProductLocale>('pl');
  const [activeTab, setActiveTab] = useState<string>(ATTRIBUTES_TAB);

  // UP-08 — fetch the ObjectType's effective groups without an object
  // context (no categories selected). Uses the `/preview` POST endpoint
  // with an empty categoryIds payload so the response shape mirrors
  // the detail page; category-driven overlay during create is a
  // follow-up.
  //
  // #1098 — `refetchOnMount: 'always'` keeps the tab list aligned with
  // modeling changes the operator just made. Without it the 5-minute
  // staleTime + lack of cross-page invalidation hides newly attached
  // groups (e.g. operator attaches a second AttributeGroup in
  // modeling, navigates back here, and only sees the cached payload).
  const groupsQuery = useQuery<{ groups: GroupMeta[]; locales?: LocaleOption[] }>({
    queryKey: ['object-type', objectTypeId, 'effective-attribute-groups', 'preview', 'empty'],
    enabled: objectTypeId !== '',
    staleTime: 30_000,
    refetchOnMount: 'always',
    queryFn: () =>
      jsonFetch<{ groups: GroupMeta[]; locales?: LocaleOption[] }>(
        `/api/object_types/${objectTypeId}/effective-attribute-groups/preview`,
        {
          method: 'POST',
          contentType: 'application/json',
          accept: 'application/json',
          body: { categoryIds: [] },
        },
      ),
  });
  // Hide empty groups (0 attributes) — mirror universal-detail-page so the
  // create form never shows an empty "0/0" tab for a group whose attributes
  // were all removed in modeling. Modeling views keep empty groups.
  const groups = useMemo(
    () => (groupsQuery.data?.groups ?? []).filter((g) => g.attributes.length > 0),
    [groupsQuery.data],
  );

  // #1227 — initialize locale from tenant default once (mirrors product-detail-page #1149).
  const locales = useMemo<LocaleOption[]>(
    () => groupsQuery.data?.locales ?? [],
    [groupsQuery.data],
  );
  const [didInitLocale, setDidInitLocale] = useState(false);
  useEffect(() => {
    if (didInitLocale || locales.length === 0) return;
    const def = locales.find((l) => l.is_default) ?? locales[0];
    if (def === undefined) return;
    setLocale(def.code);
    setDidInitLocale(true);
  }, [locales, didInitLocale]);

  // #1098 — mirror MODR-04 split from product-detail-page: tab-mode
  // groups become their own tab, stacked-mode groups live inline
  // inside the default "Atrybuty" tab. Operator gets the same mental
  // model in create as in detail.
  const tabModeGroups = useMemo(
    () => groups.filter((g) => (g.display_mode ?? 'tab') === 'tab'),
    [groups],
  );
  const stackedGroups = useMemo(
    () => groups.filter((g) => (g.display_mode ?? 'tab') === 'stacked'),
    [groups],
  );

  const visibleTabs: readonly string[] = useMemo(() => {
    if (groups.length === 0 && !hasMultimedia) return [];
    const tabs: string[] = [];
    // Hide the synthetic Atrybuty tab when no stacked groups exist —
    // otherwise the operator stares at an empty default tab before
    // they realise the data lives behind a different chip.
    if (stackedGroups.length > 0 || (tabModeGroups.length === 0 && groups.length > 0)) {
      tabs.push(ATTRIBUTES_TAB);
    }
    for (const group of tabModeGroups) tabs.push(group.code);
    // #1104 — surface Multimedia capability flagged in modeling.
    // The panel renders a disclaimer that uploads live on the detail
    // page (they need the post-create object id).
    if (hasMultimedia) tabs.push(MULTIMEDIA_TAB);
    return tabs;
  }, [groups, stackedGroups, tabModeGroups, hasMultimedia]);

  // Keep activeTab valid when the visible tab set changes (e.g. groups
  // arrive after first render, operator attaches a new group via
  // modeling and the refetch lands).
  useEffect(() => {
    if (visibleTabs.length === 0) return;
    if (!visibleTabs.includes(activeTab)) {
      setActiveTab(visibleTabs[0] ?? ATTRIBUTES_TAB);
    }
  }, [activeTab, visibleTabs]);

  const toggleGroup = (groupId: string): void => {
    setExpandedGroups((prev) => {
      const next = new Set(prev);
      if (next.has(groupId)) next.delete(groupId);
      else next.add(groupId);
      return next;
    });
  };

  const setFieldValue = (codeKey: string, value: unknown): void => {
    setDirtyFields((prev) => ({ ...prev, [codeKey]: value }));
  };

  // #1359 — resolve category codes for chip rendering (same shared cache
  // key as the products create flow + the picker dialog).
  const categoriesListQuery = useQuery({
    queryKey: ['categories', 'picker'],
    queryFn: () =>
      jsonFetch<{
        'hydra:member'?: Array<{ id: string; code: string }>;
        member?: Array<{ id: string; code: string }>;
      }>('/api/categories?itemsPerPage=200'),
    enabled: isCategorizable && categoryIds.length > 0,
    staleTime: 60_000,
  });
  const categorySummaries = useMemo(() => {
    const rows =
      categoriesListQuery.data?.['hydra:member'] ?? categoriesListQuery.data?.member ?? [];
    const codeById = new Map<string, string>();
    for (const row of rows) codeById.set(row.id, row.code);
    return categoryIds.map((cid) => ({
      categoryId: cid,
      code: codeById.get(cid) ?? cid.slice(0, 8),
      isPrimary: cid === primaryCategoryId,
    }));
  }, [categoryIds, primaryCategoryId, categoriesListQuery.data]);

  const handleCreate = async (): Promise<void> => {
    if (isSaving) return;
    const trimmedCode = code.trim();
    if (trimmedCode === '') {
      toast.error(
        t('object_create.validation.name_required', { defaultValue: 'Nazwa jest wymagana' }),
      );
      return;
    }
    // #1359 — categorizable types must carry at least one category, the
    // same rule /products/new enforces.
    if (isCategorizable && categoryIds.length === 0) {
      toast.error(
        t('object_create.validation.categories_required', {
          defaultValue: 'Przypisz przynajmniej jedną kategorię',
        }),
      );
      return;
    }
    setIsSaving(true);
    try {
      // #1102 — relation values live in `object_relations`, not
      // `object_values`. The POST /api/objects payload only handles
      // the latter, so split the dirty dict in two: ordinary attrs
      // ride along with the POST, relation attrs get one PUT each
      // after the main row exists.
      const relationCodes = collectRelationCodes(groups);
      const { normal, relations } = splitDirtyAttributes(dirtyFields, relationCodes);
      const body: Record<string, unknown> = {
        objectTypeId,
        code: trimmedCode,
      };
      if (Object.keys(normal).length > 0) body.attributes = normal;
      // #1359 — atomic category assignment for categorizable types.
      if (isCategorizable && categoryIds.length > 0) {
        body.categoryIds = categoryIds;
        body.primaryCategoryId =
          primaryCategoryId !== null && categoryIds.includes(primaryCategoryId)
            ? primaryCategoryId
            : categoryIds[0];
      }
      const created = await jsonFetch<{ id: string }>('/api/objects', {
        method: 'POST',
        contentType: 'application/ld+json',
        body,
      });

      const relationFailures: string[] = [];
      for (const [attrCode, targets] of Object.entries(relations)) {
        if (targets.length === 0) continue;
        try {
          await jsonFetch(`/api/objects/${created.id}/relations/${attrCode}`, {
            method: 'PUT',
            contentType: 'application/json',
            body: { targets: targets.map((id) => ({ id })) },
          });
        } catch {
          relationFailures.push(attrCode);
        }
      }

      if (relationFailures.length > 0) {
        toast.error(
          t('object_create.relations_partial_error', {
            defaultValue: 'Obiekt utworzony, ale relacje nie zapisane: {{codes}}',
            codes: relationFailures.join(', '),
          }),
        );
      } else {
        toast.success(
          t('object_create.success', {
            defaultValue: 'Utworzono {{code}}',
            code: trimmedCode,
          }),
        );
      }
      navigate(detailPathFor(created.id));
    } catch {
      toast.error(t('object_create.failed', { defaultValue: 'Nie udało się utworzyć obiektu' }));
    } finally {
      setIsSaving(false);
    }
  };

  const renderGroup = (group: GroupMeta) => (
    <AttrGroupCard
      key={group.id}
      id={group.id}
      title={group.label[lang] ?? group.code}
      icon={GROUP_ICONS[group.code]}
      filledCount={countFilled(group, dirtyFields)}
      totalCount={group.attributes.length}
      expanded={expandedGroups.has(group.id) || expandedGroups.size === 0}
      onToggle={() => toggleGroup(group.id)}
    >
      {group.attributes.map((attr) => (
        <AttrRow
          key={attr.id}
          attribute={attr}
          value={dirtyFields[attr.code]}
          provenance="manual"
          locale={locale}
          channel={null}
          isEditing={true}
          isLocked={attr.is_system}
          onChange={(next) => setFieldValue(attr.code, next)}
          createMode={true}
        />
      ))}
    </AttrGroupCard>
  );

  const tabLabel = (tab: string): string => {
    if (tab === ATTRIBUTES_TAB) {
      return t('object_create.tabs.attributes', { defaultValue: 'Atrybuty' });
    }
    if (tab === MULTIMEDIA_TAB) {
      return t('object_create.tabs.multimedia', { defaultValue: 'Multimedia' });
    }
    const group = tabModeGroups.find((g) => g.code === tab);
    if (group === undefined) return tab;
    return group.label[lang] ?? group.code;
  };

  return (
    <div className="-mx-6 -mt-6 min-h-[calc(100vh-3rem)] bg-zinc-50">
      <header className="glass-strong sticky top-0 z-20 border-b border-zinc-100">
        <div className="px-7 pb-3 pt-5">
          <div className="flex items-center gap-3">
            <Button
              asChild
              variant="ghost"
              size="icon"
              className="soft-shadow size-9 rounded-xl bg-white"
            >
              <Link to={backHref} aria-label={t('object_create.back', { defaultValue: 'Powrót' })}>
                <ArrowLeft className="size-4" />
              </Link>
            </Button>
            <div className="text-[12px] text-zinc-500">
              <span>{objectTypeLabel}</span>
              <span className="mx-1.5 text-zinc-300">/</span>
              <span className="font-medium text-zinc-900">
                {t('object_create.new', { defaultValue: 'Nowy' })}
              </span>
            </div>
            <div className="ml-auto flex items-center gap-2">
              <Button
                type="button"
                variant="ghost"
                size="sm"
                onClick={() => navigate(backHref)}
                disabled={isSaving}
                className="h-9 rounded-xl px-3 text-[12.5px] text-zinc-600"
              >
                {t('object_create.actions.cancel', { defaultValue: 'Anuluj' })}
              </Button>
              <Button
                type="button"
                onClick={() => void handleCreate()}
                disabled={isSaving || code.trim() === ''}
                className="h-9 rounded-xl bg-zinc-900 px-4 text-[12.5px] font-medium text-white hover:bg-zinc-800"
              >
                <Save className="size-4" />
                {t('object_create.actions.create', { defaultValue: 'Utwórz' })}
              </Button>
            </div>
          </div>

          <div className="mt-4 flex items-start gap-5">
            <div
              className="soft-shadow grid size-[72px] shrink-0 place-items-center rounded-2xl bg-white text-[34px]"
              aria-hidden
            >
              ▣
            </div>
            <div className="min-w-0 flex-1 space-y-2">
              {/* #1361 — this single identifier field is the object's
                  human-readable name for custom object types (e.g. a
                  service "Wniesienie"); label it "Nazwa" and drop the
                  code/uniqueness hint that read like an SKU. */}
              <Input
                autoFocus
                placeholder={t('object_create.placeholder.name', {
                  defaultValue: 'Nazwa',
                })}
                value={code}
                onChange={(event) => setCode(event.target.value)}
                className="font-display h-10 rounded-lg border-zinc-200 bg-white text-[20px] font-semibold tracking-tight"
              />
            </div>
          </div>
        </div>

        {visibleTabs.length > 1 ? (
          <div className="flex items-center gap-1 border-t border-zinc-100 px-7">
            <div
              className="flex flex-1 items-center gap-1"
              role="tablist"
              aria-label={t('object_create.tabs.aria', { defaultValue: 'Zakładki typu obiektu' })}
            >
              {visibleTabs.map((tab) => {
                const isActive = activeTab === tab;
                return (
                  <button
                    key={tab}
                    type="button"
                    role="tab"
                    aria-selected={isActive}
                    onClick={() => setActiveTab(tab)}
                    className={cn(
                      'relative inline-flex h-[44px] items-center gap-2 px-3.5 text-[13px] font-medium tracking-tight',
                      isActive ? 'text-zinc-900' : 'text-zinc-500 hover:text-zinc-800',
                    )}
                  >
                    {tabLabel(tab)}
                    {isActive ? (
                      <span
                        className="absolute -bottom-px left-0 right-0 h-[2px] rounded-t bg-zinc-900"
                        aria-hidden
                      />
                    ) : null}
                  </button>
                );
              })}
            </div>
          </div>
        ) : null}
      </header>

      <div className="grid grid-cols-1 gap-5 px-7 py-6 lg:grid-cols-[minmax(0,1fr)_320px]">
        <div className="min-w-0 space-y-3">
          {groupsQuery.isLoading ? (
            <p className="text-sm text-muted-foreground">{t('app.loading')}</p>
          ) : activeTab === MULTIMEDIA_TAB ? (
            <div className="border-line bg-surface rounded-2xl border border-dashed p-6 text-center">
              <p className="text-ink text-[13px] font-medium">
                {t('object_create.multimedia.unavailable', {
                  defaultValue: 'Multimedia dostępne po pierwszym zapisie',
                })}
              </p>
              <p className="mt-1 text-[11.5px] text-muted-foreground">
                {t('object_create.multimedia.hint', {
                  defaultValue:
                    'Najpierw utwórz obiekt (Kod + Utwórz). Zdjęcia i pliki dodasz w karcie obiektu po zapisie.',
                })}
              </p>
            </div>
          ) : groups.length === 0 ? (
            <div className="border-line bg-surface rounded-2xl border border-dashed p-6 text-center">
              <p className="text-ink text-[13px] font-medium">
                {t('object_create.no_attributes', {
                  defaultValue: 'Ten typ obiektu nie ma jeszcze atrybutów.',
                })}
              </p>
              <p className="mt-1 text-[11.5px] text-muted-foreground">
                {t('object_create.no_attributes_hint', {
                  defaultValue:
                    'Dodaj atrybuty w modelowaniu (Modelowanie → Typy obiektów → {{label}}).',
                  label: objectTypeLabel,
                })}
              </p>
            </div>
          ) : activeTab === ATTRIBUTES_TAB ? (
            stackedGroups.map(renderGroup)
          ) : (
            (() => {
              const tabGroup = tabModeGroups.find((g) => g.code === activeTab);
              if (tabGroup === undefined) return null;
              return renderGroup(tabGroup);
            })()
          )}
        </div>

        <aside
          className="space-y-3"
          aria-label={t('object_create.sidebar.aria', { defaultValue: 'Panel boczny' })}
        >
          <div className="rounded-2xl border border-zinc-200 bg-white p-4">
            <h3 className="text-[13px] font-semibold text-zinc-900">
              {t('object_create.sidebar.type', { defaultValue: 'Typ obiektu' })}
            </h3>
            <p className="mt-1 text-[12px] text-zinc-600">{objectTypeLabel}</p>
            <p className="mt-3 text-[11px] text-zinc-400">
              <span className="font-mono">{objectTypeCode}</span>
            </p>
          </div>
          {/* #1359 — category picker for categorizable types (required). */}
          {isCategorizable ? (
            <CategorySelectorCard
              mode="create"
              objectTypeId={objectTypeId}
              selectedCategoryIds={categoryIds}
              primaryCategoryId={primaryCategoryId}
              selectedCategories={categorySummaries}
              onChange={(ids, primary) => {
                setCategoryIds(ids);
                setPrimaryCategoryId(primary);
              }}
            />
          ) : null}
        </aside>
      </div>
    </div>
  );
}

function collectRelationCodes(groups: GroupMeta[]): Set<string> {
  const codes = new Set<string>();
  for (const group of groups) {
    for (const attr of group.attributes) {
      if (attr.type === 'relation') codes.add(attr.code);
    }
  }
  return codes;
}

function splitDirtyAttributes(
  dirty: Record<string, unknown>,
  relationCodes: Set<string>,
): { normal: Record<string, unknown>; relations: Record<string, string[]> } {
  const normal: Record<string, unknown> = {};
  const relations: Record<string, string[]> = {};
  for (const [k, v] of Object.entries(dirty)) {
    if (k === 'sku' || k === 'code') continue;
    if (relationCodes.has(k)) {
      relations[k] = toIdList(v);
      continue;
    }
    normal[k] = v;
  }
  return { normal, relations };
}

function toIdList(value: unknown): string[] {
  if (typeof value === 'string' && value !== '') return [value];
  if (Array.isArray(value)) {
    return value.filter((v): v is string => typeof v === 'string' && v !== '');
  }
  return [];
}

function countFilled(group: GroupMeta, dirty: Record<string, unknown>): number {
  let filled = 0;
  for (const attr of group.attributes) {
    const value = dirty[attr.code];
    if (value === undefined || value === null) continue;
    if (typeof value === 'string' && value.trim() === '') continue;
    filled += 1;
  }
  return filled;
}
