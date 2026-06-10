import { useQuery } from '@tanstack/react-query';
import { ArrowLeft, Clock, Link2, MoreHorizontal, Save, Sparkles, Trash2 } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Link, useNavigate, useSearchParams } from 'react-router';

import { DetailLoadingState } from '@/components/catalog/detail-loading-state';
import { DetailNotFoundState } from '@/components/catalog/detail-not-found-state';
import type { Provenance } from '@/components/provenance-badge';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogTitle } from '@/components/ui/dialog';
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { MockBadge } from '@/components/ui/mock-badge';
import { toast } from '@/components/ui/toast';
import { useListSchema } from '@/hooks/use-list-schema';
import { unwrapAttributesIndexed } from '@/lib/attributes-indexed';
import { httpErrorDetail, jsonFetch } from '@/lib/http';
import { isLegacyOptionalSystemGroupCode } from '@/lib/legacy-attribute-groups';
import { cn } from '@/lib/utils';
import { useDefaultObjectType } from '../use-default-object-type';
import { AgentSuggestionsCard } from './agent-suggestions-card';
import { AttrGroupCard } from './attr-group-card';
import { AttrRow } from './attr-row';
import { CategoriesTab } from './categories-tab';
import { CategorySelectorCard } from './category-selector-card';
import { CompletenessRing } from './completeness-ring';
import { DuplicateButton } from './duplicate-button';
import { EffectiveModelCard } from './effective-model-card';

import { LocaleChannelToolbar } from './locale-channel-toolbar';
import { PreviewButton } from './preview-button';
import { ProductMultimediaTab } from './product-multimedia-tab';
import { RelationsTab } from './relations-tab';
import { scopedCompleteness, scopeQuery } from './scope';
import { SyncStatusCard } from './sync-status-card';
import type {
  AttributeMeta,
  CatalogObjectDto,
  ChannelOption,
  GroupMeta,
  LocaleOption,
  ProductChannel,
  ProductDetailMode,
  ProductLocale,
  ScopeStatus,
} from './types';
import { VariantsListCard } from './variants-list-card';
import { VariantsTabHost } from './variants-tab-host';

/**
 * MODR-03 (#925) — special-purpose tabs that are NOT driven by
 * `effectiveGroups`. `attributes` hosts every `display_mode='stacked'`
 * group as inline sections; the rest are bespoke views (categories
 * picker, audit log, variants tree). Tab-mode groups become tabs
 * dynamically — see `useDynamicTabs`.
 */
const SPECIAL_TABS = ['attributes', 'multimedia', 'categories', 'history', 'variants'] as const;
type SpecialTabKey = (typeof SPECIAL_TABS)[number];
type TabKey = SpecialTabKey | string;

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
  audit: '🛡',
  audyt: '🛡',
};

export interface ProductDetailPageProps {
  mode: ProductDetailMode;
  productId?: string;
}

/**
 * VIEW-07 (#420) — single-component product detail / create page.
 *
 * Renders the full screen documented in
 * `Project Plan/UI/Wdrozenie_grafiki/ticket-VIEW-07-...`. In edit mode
 * loads the product + its effective attribute groups; in create mode
 * starts with an empty draft and POSTs on save.
 *
 * Inline-edit toggle: a single "Edytuj" / "Zapisz zmiany" button
 * controls the whole body. While editing, every non-locked attribute
 * row renders an input. Save dispatches one PATCH with the diff.
 */
export function ProductDetailPage({ mode, productId }: ProductDetailPageProps) {
  const { t, i18n } = useTranslation();
  const navigate = useNavigate();
  const [searchParams] = useSearchParams();
  const lang = i18n.language === 'pl' ? 'pl' : 'en';
  const isEditMode = mode === 'edit';
  const id = productId ?? '';

  // PCAT-06b (#480): when entering create mode from a category panel
  // ("+ Create test object"), the URL carries `?categories=<id>&primary=<id>`.
  // After the product is POSTed we run a single PUT against
  // /api/products/{newId}/categories so the fresh row lands with the
  // assignment already in place.
  const prePopulatedCategoryIds = useMemo<string[]>(() => {
    if (mode !== 'create') return [];
    const raw = searchParams.get('categories');
    if (raw === null || raw === '') return [];
    return raw
      .split(',')
      .map((s) => s.trim())
      .filter((s) => s !== '');
  }, [mode, searchParams]);
  const prePopulatedPrimaryId = useMemo<string | null>(() => {
    if (mode !== 'create') return null;
    return searchParams.get('primary');
  }, [mode, searchParams]);

  const [locale, setLocale] = useState<ProductLocale>('pl');
  const [channel, setChannel] = useState<ProductChannel | null>(null);

  // #1150 / #1155 — load the product in the active locale + channel so
  // localizable / channel-scoped values reflect the picker. Replaces
  // Refine's useOne (its getOne drops the query string); locale + channel
  // are part of the query key, so switching either refetches the
  // scope-resolved reading.
  const productQuery = useQuery<CatalogObjectDto>({
    queryKey: ['products', id, locale, channel],
    queryFn: () =>
      jsonFetch<CatalogObjectDto>(`/api/products/${id}${scopeQuery(locale, channel)}`, {
        accept: 'application/ld+json',
      }),
    enabled: isEditMode && id !== '',
  });

  const { objectTypeId } = useDefaultObjectType('product');
  // UX bug fix #2 — UX-02 removed the legacy 'media' AttributeGroup
  // (operator decision: Multimedia is a capability, not an attribute
  // group). Without reading `has_multimedia` from list-schema the
  // legacy Multimedia tab never reappears on /products/{id}. Mirror
  // the UniversalDetailPage gating logic so the tab follows the flag.
  const schemaQuery = useListSchema(objectTypeId ?? undefined);
  const hasMultimediaCapability = schemaQuery.data?.objectType.has_multimedia ?? false;

  const [activeTab, setActiveTab] = useState<TabKey>('attributes');
  // #1351 — the detail page opens directly in edit mode; there is no
  // read-only state anymore. "Zapisz zmiany" is always visible and a
  // "Zapisz i wróć do listy" action saves + returns to the list.
  const isEditing = true;
  const [dirtyFields, setDirtyFields] = useState<Record<string, unknown>>({});
  const [expandedGroups, setExpandedGroups] = useState<Set<string>>(new Set());
  const [isSaving, setIsSaving] = useState<boolean>(false);
  const [showDeleteConfirm, setShowDeleteConfirm] = useState<boolean>(false);
  const [isDeleting, setIsDeleting] = useState<boolean>(false);

  // #891 create-mode category selection. POST `/api/products` carries
  // this so the product + assignments land atomically — the previous
  // PCAT-06b follow-up PUT is removed.
  const [createCategoryIds, setCreateCategoryIds] = useState<string[]>(
    () => prePopulatedCategoryIds,
  );
  const [createPrimaryId, setCreatePrimaryId] = useState<string | null>(
    () => prePopulatedPrimaryId,
  );

  // Resolve the category codes for chip rendering in create mode. Same
  // query key as `CategoryPickerDialog` so opening the picker hits the
  // shared cache — and so the sidebar updates the moment the picker
  // commits a new selection.
  const categoriesListQuery = useQuery({
    queryKey: ['categories', 'picker'],
    queryFn: () =>
      jsonFetch<{
        'hydra:member'?: Array<{ id: string; code: string }>;
        member?: Array<{ id: string; code: string }>;
      }>('/api/categories?itemsPerPage=200'),
    enabled: mode === 'create' && createCategoryIds.length > 0,
    staleTime: 60_000,
  });

  const createCategoriesSummaries = useMemo(() => {
    if (mode !== 'create') return [] as { categoryId: string; code: string; isPrimary: boolean }[];
    const rows =
      categoriesListQuery.data?.['hydra:member'] ?? categoriesListQuery.data?.member ?? [];
    const codeById = new Map<string, string>();
    for (const row of rows) codeById.set(row.id, row.code);
    return createCategoryIds.map((cid) => ({
      categoryId: cid,
      code: codeById.get(cid) ?? cid.slice(0, 8),
      isPrimary: cid === createPrimaryId,
    }));
  }, [mode, createCategoryIds, createPrimaryId, categoriesListQuery.data]);

  const product = isEditMode ? (productQuery.data ?? null) : null;
  const attrs = useMemo(
    () =>
      unwrapAttributesIndexed(product?.attributesIndexed as Record<string, unknown> | undefined),
    [product?.attributesIndexed],
  );

  // #891 — fetch effective groups via useQuery so CategoriesTab cache
  // invalidation actually triggers refetch (the previous useEffect was
  // immune to invalidation). In create mode the query keys flip between
  // the preview endpoint (when categories are selected) and the bare
  // ObjectType endpoint (when none are selected yet).
  const groupsQuery = useQuery<{ groups: GroupMeta[]; locales?: LocaleOption[] }>({
    queryKey:
      isEditMode && id !== ''
        ? ['products', id, 'effective-attribute-groups']
        : [
            'object-types',
            objectTypeId,
            'effective-attribute-groups',
            createCategoryIds.length > 0 ? [...createCategoryIds].sort() : 'base',
          ],
    queryFn: async () => {
      if (isEditMode && id !== '') {
        return jsonFetch<{ groups: GroupMeta[]; locales?: LocaleOption[] }>(
          `/api/products/${id}/effective-attribute-groups`,
        );
      }
      if (objectTypeId === null) {
        return { groups: [] };
      }
      if (createCategoryIds.length > 0) {
        return jsonFetch<{ groups: GroupMeta[]; locales?: LocaleOption[] }>(
          `/api/object_types/${objectTypeId}/effective-attribute-groups/preview`,
          {
            method: 'POST',
            contentType: 'application/json',
            accept: 'application/json',
            body: { categoryIds: createCategoryIds },
          },
        );
      }
      return jsonFetch<{ groups: GroupMeta[]; locales?: LocaleOption[] }>(
        `/api/object_types/${objectTypeId}/effective-attribute-groups`,
      );
    },
    enabled: isEditMode ? id !== '' : objectTypeId !== null,
    staleTime: 5_000,
    placeholderData: (prev) => prev,
  });

  const groups = useMemo(() => groupsQuery.data?.groups ?? [], [groupsQuery.data]);

  // #1149 — the locale picker is fed from the tenant's real enabled locales
  // (shipped by effective-attribute-groups). Default the selection to the
  // tenant default once, then respect manual switches.
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

  // #1155 — channel picker fed from /api/channels (tenant's real channels).
  const channelsQuery = useQuery<ChannelOption[]>({
    queryKey: ['channels', 'picker'],
    queryFn: async () => {
      const response = await jsonFetch<{ member?: ChannelOption[] } | ChannelOption[]>(
        '/api/channels',
        { accept: 'application/ld+json' },
      );
      return Array.isArray(response) ? response : (response.member ?? []);
    },
    enabled: isEditMode,
    staleTime: 60_000,
  });
  const channels = channelsQuery.data ?? [];

  // #1222 — scope-status: per-attribute inherited indicator.
  // Only fetched in edit mode (detail page), not in create mode.
  // Enabled only when a non-primary locale is active (primary locale
  // values are global — nothing can be "inherited from another locale").
  const scopeStatusQuery = useQuery<ScopeStatus>({
    queryKey: ['products', id, 'scope-status', locale, channel],
    queryFn: () =>
      jsonFetch<ScopeStatus>(`/api/products/${id}/scope-status${scopeQuery(locale, channel)}`),
    enabled: isEditMode && id !== '' && locale !== null,
    staleTime: 30_000,
  });
  const scopeStatus = scopeStatusQuery.data ?? {};

  // #1150 / #1155 — switching locale or channel discards unsaved edits so an
  // edit is never written to the wrong scope; the operator saves first.
  // biome-ignore lint/correctness/useExhaustiveDependencies: intentional — reset on scope change
  useEffect(() => {
    setDirtyFields({});
  }, [locale, channel]);

  // MODR-03 (#925) — tab list derived dynamically from `groups`:
  // every group with `display_mode='tab'` becomes its own tab; groups
  // with `display_mode='stacked'` (and the synthetic "default" bucket)
  // collect into the single `attributes` tab. Hooks must live above
  // the loading early-return below to obey rules-of-hooks.
  const tabModeGroups = useMemo(
    () => groups.filter((g) => (g.display_mode ?? 'tab') === 'tab'),
    [groups],
  );
  const stackedGroups = useMemo(
    () => groups.filter((g) => (g.display_mode ?? 'tab') === 'stacked'),
    [groups],
  );

  // MODR-06 (#928) — lightweight probe for incoming links so the
  // "Powiązania" tab can surface even when the object has no forward
  // relation attributes (e.g. a Category referenced from products).
  const reverseRelationsQuery = useQuery<{ hasReverse: boolean; count: number }>({
    queryKey: ['objects', id, 'relations', 'reverse', 'count'],
    queryFn: () =>
      jsonFetch<{ hasReverse: boolean; count: number }>(
        `/api/objects/${id}/relations/reverse/count`,
      ),
    enabled: isEditMode && id !== '',
    staleTime: 30_000,
  });
  const hasReverseRelations = reverseRelationsQuery.data?.hasReverse ?? false;
  // Issue #1092 — forward relation attributes are normal attributes
  // now: they render inline through their natural group placement
  // (stacked-mode group → inline in the `attributes` tab; tab-mode
  // group → its own dedicated tab via `tabModeGroups`). The synthetic
  // `relations` tab only survives for the reverse-only case (object is
  // pointed at from elsewhere but has no forward relation attribute of
  // its own) — that path still needs the bespoke `RelationsTab` because
  // `effective-attribute-groups` has no concept of "incoming links".
  const shouldSurfaceRelationsTab = hasReverseRelations;

  const visibleTabs: readonly TabKey[] = useMemo(() => {
    if (mode === 'create') return ['attributes'];
    const fromGroups = tabModeGroups.map((g) => g.code);
    // MODR-06 (#928) — inject a synthetic `relations` tab when the
    // object has reverse links but no forward `relations` AttributeGroup.
    const ensureRelations =
      shouldSurfaceRelationsTab && !fromGroups.includes('relations') ? ['relations'] : [];
    const multimedia: TabKey[] = hasMultimediaCapability ? ['multimedia' as const] : [];
    // #1348 — the "Atrybuty" tab is the bucket for stacked/ungrouped
    // attributes (synthetic default group + display_mode='stacked'
    // groups). When the ObjectType has no such attributes the tab is
    // empty (only the ad-hoc adder), so drop it entirely — tab-mode
    // groups keep their own dedicated tabs.
    const attributesTab: TabKey[] = stackedGroups.length > 0 ? ['attributes' as const] : [];
    return [
      ...attributesTab,
      ...fromGroups,
      ...ensureRelations,
      ...multimedia,
      'categories' as const,
      'history' as const,
      'variants' as const,
    ];
  }, [mode, tabModeGroups, stackedGroups, shouldSurfaceRelationsTab, hasMultimediaCapability]);

  // Keep activeTab valid as group set changes (e.g. groups data arrives
  // after first render or a tab→stacked switch in the wizard). Falls back
  // to the first visible tab — `attributes` may now be absent (#1348).
  useEffect(() => {
    const fallback = visibleTabs[0];
    if (fallback !== undefined && !visibleTabs.includes(activeTab)) {
      setActiveTab(fallback);
    }
  }, [activeTab, visibleTabs]);

  // Default expand all groups once they first arrive (mockup shows every
  // section open). Refetches after the first success keep the operator's
  // chosen expand/collapse state intact.
  const [didExpandInitial, setDidExpandInitial] = useState(false);
  useEffect(() => {
    if (didExpandInitial) return;
    if (groups.length === 0) return;
    setExpandedGroups(new Set(groups.map((g) => g.id)));
    setDidExpandInitial(true);
  }, [didExpandInitial, groups]);

  const toggleGroup = (groupId: string): void => {
    setExpandedGroups((prev) => {
      const next = new Set(prev);
      if (next.has(groupId)) next.delete(groupId);
      else next.add(groupId);
      return next;
    });
  };

  const setFieldValue = (code: string, value: unknown): void => {
    setDirtyFields((prev) => ({ ...prev, [code]: value }));
  };

  const fieldValue = (code: string): unknown => {
    if (Object.hasOwn(dirtyFields, code)) {
      return dirtyFields[code];
    }
    return attrs[code];
  };

  const handleSave = async (returnToList = false): Promise<void> => {
    if (isSaving) return;
    setIsSaving(true);
    try {
      if (mode === 'create') {
        const skuRaw = dirtyFields.sku ?? dirtyFields.code ?? '';
        const sku = typeof skuRaw === 'string' ? skuRaw.trim() : '';
        if (sku === '') {
          toast.error(
            t('products.detail.validation.sku_required', { defaultValue: 'SKU jest wymagane' }),
          );
          setIsSaving(false);
          return;
        }
        if (objectTypeId === null) {
          toast.error(
            t('products.detail.validation.object_type_missing', {
              defaultValue: 'Brak built-in ObjectType — uruchom seeder katalogu',
            }),
          );
          setIsSaving(false);
          return;
        }
        // #891 — kategoria wymagana dla nowych produktów.
        if (createCategoryIds.length === 0) {
          toast.error(
            t('products.detail.validation.categories_required', {
              defaultValue: 'Przypisz przynajmniej jedną kategorię',
            }),
          );
          setIsSaving(false);
          return;
        }
        const primary =
          createPrimaryId !== null && createCategoryIds.includes(createPrimaryId)
            ? createPrimaryId
            : createCategoryIds[0];
        const attributes = stripAttributes(dirtyFields);
        const body: Record<string, unknown> = {
          code: sku,
          objectTypeId,
          categoryIds: createCategoryIds,
          primaryCategoryId: primary,
        };
        if (Object.keys(attributes).length > 0) body.attributes = attributes;
        const created = await jsonFetch<{ id: string }>('/api/products', {
          method: 'POST',
          contentType: 'application/ld+json',
          body,
        });
        toast.success(
          t('products.detail.create.success', {
            defaultValue: 'Utworzono produkt {{code}}',
            code: sku,
          }),
        );
        navigate(`/products/${created.id}`);
      } else {
        if (Object.keys(dirtyFields).length === 0) {
          // Nothing to persist — "Zapisz i wróć do listy" still returns.
          if (returnToList) navigate('/products');
          setIsSaving(false);
          return;
        }
        const attributes = stripAttributes(dirtyFields);
        // #1150 / #1155 — write in the active locale + channel: localizable
        // / scopable attributes land on that scope's row, others stay
        // global (BE decides per flag).
        await jsonFetch(`/api/products/${id}${scopeQuery(locale, channel)}`, {
          method: 'PATCH',
          contentType: 'application/merge-patch+json',
          body: { attributes },
        });
        await productQuery.refetch();
        setDirtyFields({});
        toast.success(t('products.detail.save.success', { defaultValue: 'Zapisano zmiany' }));
        // #1351 — "Zapisz zmiany" keeps the row in edit mode; only
        // "Zapisz i wróć do listy" navigates back to the list.
        if (returnToList) navigate('/products');
      }
    } catch (error) {
      // #1179 — surface the server's Problem Details `detail` (e.g. duplicate
      // identifier 409) instead of the generic copy, so the operator knows
      // what to fix.
      toast.error(
        httpErrorDetail(error) ??
          t('products.detail.save.failed', { defaultValue: 'Nie udało się zapisać' }),
      );
    } finally {
      setIsSaving(false);
    }
  };

  const cancelEdit = (): void => {
    // #1351 — no read-only mode anymore; "Anuluj" just discards unsaved
    // edits and restores the persisted values.
    setDirtyFields({});
    void productQuery.refetch();
  };

  const handleDelete = async (): Promise<void> => {
    if (mode !== 'edit' || id === '' || isDeleting) return;
    setIsDeleting(true);
    try {
      await jsonFetch(`/api/products/${id}`, { method: 'DELETE' });
      toast.success(
        t('products.detail.delete.success', {
          defaultValue: 'Usunięto produkt {{code}}',
          code: product?.code ?? id,
        }),
      );
      navigate('/products');
    } catch {
      toast.error(
        t('products.detail.delete.failed', { defaultValue: 'Nie udało się usunąć produktu' }),
      );
      setIsDeleting(false);
      setShowDeleteConfirm(false);
    }
  };

  // Issue #1043 — split the original mixed guard into loading + not-found.
  // The previous condition treated `product === undefined` (post-404) as
  // still-loading and pinned the page on an infinite "Ładowanie…". Refine's
  // `useOne` returns `isLoading=false`, `isError=true`, `data=undefined`
  // after a 404, so we now check `isError` explicitly.
  if (isEditMode && productQuery.isLoading) {
    return <DetailLoadingState />;
  }
  if (isEditMode && (productQuery.isError || product === null || product === undefined)) {
    return (
      <DetailNotFoundState
        id={id}
        backHref="/products"
        title={t('products.detail.errors.not_found_title', {
          defaultValue: 'Produkt nie znaleziony',
        })}
        description={t('products.detail.errors.not_found_description', {
          defaultValue: 'Produkt o ID "{{id}}" nie istnieje lub został usunięty.',
          id,
        })}
        backLabel={t('products.detail.errors.back_to_list', {
          defaultValue: 'Wróć do listy produktów',
        })}
      />
    );
  }

  const skuValue =
    mode === 'create'
      ? typeof dirtyFields.sku === 'string'
        ? dirtyFields.sku
        : ''
      : (product?.code ?? '');
  const nameValue =
    mode === 'create'
      ? typeof dirtyFields.name === 'string'
        ? dirtyFields.name
        : ''
      : typeof attrs.name === 'string'
        ? attrs.name
        : (product?.code ?? '');
  const brandValue =
    mode === 'create'
      ? typeof dirtyFields.brand === 'string'
        ? dirtyFields.brand
        : ''
      : typeof attrs.brand === 'string'
        ? attrs.brand
        : '';
  const objectTypeName = product?.objectType?.name?.[lang] ?? null;
  const completenessPct = product?.completenessPct ?? 0;
  // #1152 — the ring reflects the active locale/channel scope (Akeneo
  // "readiness per target"); falls back to the global pct.
  const { pct: scopedCompletenessPct, scope: completenessScope } = scopedCompleteness(
    product?.completeness,
    locale,
    channel,
    completenessPct,
  );
  const breadcrumbCategory =
    typeof attrs.category === 'string' && attrs.category !== ''
      ? attrs.category
      : (objectTypeName ?? '—');

  return (
    <div className="bg-zinc-50 -mx-6 -mt-6 min-h-[calc(100vh-3rem)]">
      {/* Header */}
      <header className="sticky top-0 z-20 glass-strong border-b border-zinc-100">
        <div className="px-7 pb-3 pt-5">
          <div className="flex items-center gap-3">
            <Button
              asChild
              variant="ghost"
              size="icon"
              className="size-9 rounded-xl bg-white soft-shadow"
            >
              <Link
                to="/products"
                aria-label={t('products.back', { defaultValue: 'Powrót do listy' })}
              >
                <ArrowLeft className="size-4" />
              </Link>
            </Button>
            <div className="text-[12px] text-zinc-500">
              <span>{t('products.title', { defaultValue: 'Produkty' })}</span>
              <span className="mx-1.5 text-zinc-300">/</span>
              <span>{breadcrumbCategory}</span>
              {skuValue !== '' ? (
                <>
                  <span className="mx-1.5 text-zinc-300">/</span>
                  <span className="font-medium text-zinc-900">{skuValue}</span>
                </>
              ) : null}
            </div>
            <div className="ml-auto flex items-center gap-2">
              <PreviewButton disabled={mode === 'create'} />
              {mode === 'edit' && id !== '' ? <DuplicateButton productId={id} /> : null}
              {mode === 'edit' && id !== '' ? (
                <DropdownMenu>
                  <DropdownMenuTrigger asChild>
                    <Button
                      type="button"
                      variant="ghost"
                      size="icon"
                      className="size-9 rounded-xl bg-white soft-shadow"
                      aria-label={t('products.detail.actions.more', { defaultValue: 'Więcej' })}
                    >
                      <MoreHorizontal className="size-4" />
                    </Button>
                  </DropdownMenuTrigger>
                  <DropdownMenuContent align="end" className="w-56">
                    <DropdownMenuItem
                      onSelect={() => setShowDeleteConfirm(true)}
                      className="text-rose-600 focus:bg-rose-50 focus:text-rose-700"
                    >
                      <Trash2 className="mr-2 size-4" />
                      {t('products.detail.actions.delete', { defaultValue: 'Usuń produkt' })}
                    </DropdownMenuItem>
                  </DropdownMenuContent>
                </DropdownMenu>
              ) : (
                <Button
                  type="button"
                  variant="ghost"
                  size="icon"
                  className="size-9 rounded-xl bg-white soft-shadow"
                  aria-label={t('products.detail.actions.more', { defaultValue: 'Więcej' })}
                  disabled
                >
                  <MoreHorizontal className="size-4" />
                </Button>
              )}
              <span className="mx-1 h-6 w-px bg-zinc-200" />
              {mode === 'edit' ? (
                <Button
                  type="button"
                  variant="ghost"
                  size="sm"
                  onClick={cancelEdit}
                  disabled={isSaving}
                  className="h-9 rounded-xl px-3 text-[12.5px] text-zinc-600"
                >
                  {t('products.detail.actions.cancel', { defaultValue: 'Anuluj' })}
                </Button>
              ) : null}
              <Button
                type="button"
                onClick={() => void handleSave()}
                disabled={isSaving || (mode === 'create' && objectTypeId === null)}
                className="h-9 rounded-xl bg-zinc-900 px-4 text-[12.5px] font-medium text-white hover:bg-zinc-800"
              >
                <Save className="size-4" />
                {mode === 'create'
                  ? t('products.detail.actions.create', { defaultValue: 'Utwórz produkt' })
                  : t('products.detail.actions.save', { defaultValue: 'Zapisz zmiany' })}
              </Button>
              {/* #1351 — save and return to the list (edit mode only). */}
              {mode === 'edit' ? (
                <Button
                  type="button"
                  variant="outline"
                  onClick={() => void handleSave(true)}
                  disabled={isSaving}
                  className="h-9 rounded-xl px-4 text-[12.5px] font-medium"
                >
                  <Save className="size-4" />
                  {t('products.detail.actions.save_and_return', {
                    defaultValue: 'Zapisz i wróć do listy',
                  })}
                </Button>
              ) : null}
            </div>
          </div>

          <div className="mt-4 flex items-start gap-5">
            <div
              className="grid size-[72px] shrink-0 place-items-center rounded-2xl bg-white text-[34px] soft-shadow"
              aria-hidden
            >
              ▣
            </div>
            <div className="min-w-0 flex-1">
              {mode === 'create' ? (
                <div className="space-y-2">
                  <div className="flex items-center gap-2.5 text-[12px] text-zinc-500">
                    <Input
                      autoFocus
                      placeholder={t('products.detail.create.placeholder.sku', {
                        defaultValue: 'SKU',
                      })}
                      value={skuValue}
                      onChange={(event) => setFieldValue('sku', event.target.value)}
                      className="h-7 w-32 rounded-lg border-zinc-200 bg-white px-2 font-mono text-[12px]"
                    />
                  </div>
                  <Input
                    placeholder={t('products.detail.create.placeholder.name', {
                      defaultValue: 'Nazwa produktu',
                    })}
                    value={nameValue}
                    onChange={(event) => setFieldValue('name', event.target.value)}
                    className="font-display h-10 rounded-lg border-zinc-200 bg-white text-[20px] font-semibold tracking-tight"
                  />
                  {/* #1357 — "Marka" removed from the new-entry form. */}
                </div>
              ) : (
                <>
                  <div className="flex items-center gap-2.5 text-[12px] text-zinc-500">
                    <span className="font-mono">{product?.code}</span>
                    {brandValue !== '' ? (
                      <>
                        <span className="text-zinc-300">·</span>
                        <span>{brandValue}</span>
                      </>
                    ) : null}
                    <span className="text-zinc-300">·</span>
                    <span className="inline-flex items-center gap-1.5">
                      <span
                        className={cn(
                          'size-1.5 rounded-full',
                          product?.enabled ? 'bg-emerald-500' : 'bg-zinc-300',
                        )}
                        aria-hidden
                      />
                      {product?.enabled
                        ? t('products.detail.status.active', { defaultValue: 'Aktywny' })
                        : t('products.detail.status.inactive', { defaultValue: 'Nieaktywny' })}
                    </span>
                  </div>
                  {isEditing ? (
                    <Input
                      aria-label={t('products.detail.create.placeholder.name', {
                        defaultValue: 'Nazwa produktu',
                      })}
                      placeholder={t('products.detail.create.placeholder.name', {
                        defaultValue: 'Nazwa produktu',
                      })}
                      value={nameValue}
                      onChange={(event) => setFieldValue('name', event.target.value)}
                      className="font-display mt-1 h-11 rounded-lg border-zinc-200 bg-white text-[26px] font-semibold tracking-tight"
                    />
                  ) : (
                    <h1 className="font-display mt-1 text-[26px] font-semibold leading-tight tracking-tight">
                      {nameValue}
                    </h1>
                  )}
                  {objectTypeName !== null ? (
                    <div className="mt-2.5 flex flex-wrap items-center gap-2">
                      <span className="rounded-full bg-white px-2 py-1 text-[11px] font-medium text-zinc-700 soft-shadow">
                        {objectTypeName}
                      </span>
                    </div>
                  ) : null}
                </>
              )}
            </div>
            {mode === 'edit' ? (
              <div className="flex flex-col items-center gap-1">
                <CompletenessRing pct={scopedCompletenessPct} size={72} stroke={6} />
                {completenessScope !== null ? (
                  <span
                    className="num rounded bg-zinc-100 px-1.5 py-0.5 text-[10px] font-medium uppercase text-zinc-500"
                    title={t('products.completeness.scope_tooltip', {
                      scope: completenessScope.toUpperCase(),
                      defaultValue: 'Kompletność dla zakresu {{scope}}',
                    })}
                  >
                    {completenessScope}
                  </span>
                ) : null}
              </div>
            ) : null}
          </div>
        </div>

        {/* Tabs + locale/channel toolbar */}
        <div className="flex items-center gap-1 border-t border-zinc-100 px-7">
          <div
            className="flex flex-1 items-center gap-1"
            role="tablist"
            aria-label={t('products.detail.tabs.aria', { defaultValue: 'Zakładki produktu' })}
          >
            {visibleTabs.map((tab) => {
              const isActive = activeTab === tab;
              const badge = tabBadge(tab, groups, stackedGroups, product);
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
                  {tabLabel(tab, groups, lang, t)}
                  {badge !== null ? (
                    <span
                      className={cn(
                        'num rounded px-1.5 py-0.5 text-[10.5px]',
                        isActive ? 'bg-zinc-900 text-white' : 'bg-zinc-100 text-zinc-500',
                      )}
                    >
                      {badge}
                    </span>
                  ) : null}
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
          {mode === 'edit' ? (
            <LocaleChannelToolbar
              locale={locale}
              channel={channel}
              onLocaleChange={setLocale}
              onChannelChange={setChannel}
              locales={locales}
              channels={channels}
            />
          ) : null}
        </div>
      </header>

      {/* Body */}
      <div className="grid grid-cols-1 gap-5 px-7 py-6 lg:grid-cols-[minmax(0,1fr)_320px]">
        <div className="min-w-0 space-y-3">
          {(() => {
            // MODR-03 (#925) — content area dispatch based on active tab.
            // 'attributes' hosts every stacked group as inline AttrGroupCard
            // sections; a tab-mode group code renders that single group;
            // 'categories'/'history'/'variants' delegate to bespoke components.
            const renderStackedGroup = (group: GroupMeta) => (
              <AttrGroupCard
                key={group.id}
                id={group.id}
                title={group.label[lang] ?? group.code}
                icon={GROUP_ICONS[group.code]}
                filledCount={countFilled(group, fieldValue)}
                totalCount={group.attributes.length}
                expanded={expandedGroups.has(group.id)}
                onToggle={() => toggleGroup(group.id)}
                isSystem={isLegacyOptionalSystemGroupCode(group.code)}
              >
                {group.attributes.map((attr) => (
                  <AttrRow
                    key={attr.id}
                    attribute={attr}
                    value={fieldValue(attr.code)}
                    provenance={resolveProvenance(attr, product)}
                    locale={locale}
                    channel={channel}
                    isEditing={isEditing}
                    isLocked={attr.is_system}
                    onChange={(next) => setFieldValue(attr.code, next)}
                    relationContextProductId={isEditMode ? id : undefined}
                    isInherited={
                      scopeStatus[attr.code]?.has_override === false &&
                      scopeStatus[attr.code]?.inherited_from != null
                    }
                    inheritedFrom={scopeStatus[attr.code]?.inherited_from ?? null}
                  />
                ))}
              </AttrGroupCard>
            );

            if (activeTab === 'attributes') {
              // #1357 — the non-functional "Dodaj grupę atrybutów ad-hoc"
              // stub was removed from this view per operator request.
              return <>{stackedGroups.map(renderStackedGroup)}</>;
            }

            // Tab-mode AttributeGroup → render only that group, with
            // a bespoke component for relations that retains its legacy
            // data flow (relation links endpoint). Multimedia is no
            // longer dispatched here — UX-02 removes it from the
            // AttributeGroup model entirely; the conditional Multimedia
            // tab lives as a hardcoded special tab driven by
            // `ObjectType.hasMultimedia` (UX-06+).
            const tabGroup = tabModeGroups.find((g) => g.code === activeTab);
            if (tabGroup && mode === 'edit') {
              if (tabGroup.code === 'relations') {
                return <RelationsTab productId={id} />;
              }
              return renderStackedGroup(tabGroup);
            }

            // MODR-06 (#928) — synthetic `relations` tab for the
            // reverse-only case (object has no forward AttributeGroup
            // but is pointed at from elsewhere). RelationsTab already
            // gracefully renders just the reverse panel when forward
            // groups are empty.
            if (activeTab === 'relations' && mode === 'edit') {
              return <RelationsTab productId={id} />;
            }

            if (mode === 'edit' && isSpecialTab(activeTab)) {
              return (
                <OtherTabs
                  activeTab={activeTab}
                  productId={id}
                  objectTypeId={objectTypeId}
                  locale={locale}
                  channel={channel}
                />
              );
            }

            return null;
          })()}

          {mode === 'create' && createCategoryIds.length === 0 ? (
            <p className="px-1 text-[12px] text-muted-foreground">
              {t('products.detail.create.hint', {
                defaultValue:
                  'Wybierz kategorię w panelu po prawej aby zobaczyć dziedziczone grupy atrybutów.',
              })}
            </p>
          ) : null}
        </div>

        <aside
          className="space-y-3"
          aria-label={t('products.detail.sidebar.aria', {
            defaultValue: 'Panel boczny produktu',
          })}
        >
          <CategorySelectorCard
            {...(mode === 'edit' && id !== ''
              ? { mode: 'edit', productId: id, objectTypeId }
              : {
                  mode: 'create',
                  selectedCategoryIds: createCategoryIds,
                  primaryCategoryId: createPrimaryId,
                  selectedCategories: createCategoriesSummaries,
                  onChange: (ids, primary) => {
                    setCreateCategoryIds(ids);
                    setCreatePrimaryId(primary);
                  },
                  objectTypeId,
                })}
          />
          {mode === 'edit' && id !== '' ? (
            <>
              <SyncStatusCard productId={id} />
              <VariantsListCard
                masterProductId={id}
                onSelectVariant={(variantId) => navigate(`/products/${variantId}`)}
                onCreateVariant={() => setActiveTab('variants')}
              />
              <EffectiveModelCard groups={groups} objectTypeName={objectTypeName ?? 'Product'} />
              <AgentSuggestionsCard />
            </>
          ) : null}
        </aside>
      </div>

      <Dialog
        open={showDeleteConfirm}
        onOpenChange={(next) => {
          if (!next && !isDeleting) setShowDeleteConfirm(false);
        }}
      >
        <DialogContent>
          <div className="space-y-2">
            <DialogTitle>
              {t('products.detail.delete.confirm_title', { defaultValue: 'Usunąć produkt?' })}
            </DialogTitle>
            <DialogDescription>
              {t('products.detail.delete.confirm_body', {
                defaultValue:
                  'Czy na pewno chcesz usunąć produkt {{code}}? Tej operacji nie da się cofnąć.',
                code: product?.code ?? '',
              })}
            </DialogDescription>
          </div>
          <div className="mt-4 flex justify-end gap-2">
            <Button
              variant="ghost"
              onClick={() => setShowDeleteConfirm(false)}
              disabled={isDeleting}
            >
              {t('app.cancel', { defaultValue: 'Anuluj' })}
            </Button>
            <Button variant="destructive" onClick={() => void handleDelete()} disabled={isDeleting}>
              {isDeleting
                ? t('products.detail.delete.deleting', { defaultValue: 'Usuwanie…' })
                : t('products.detail.delete.confirm_submit', { defaultValue: 'Usuń produkt' })}
            </Button>
          </div>
        </DialogContent>
      </Dialog>
    </div>
  );
}

const SPECIAL_TAB_DEFAULT_LABELS: Record<SpecialTabKey, string> = {
  attributes: 'Atrybuty',
  multimedia: 'Multimedia',
  categories: 'Kategorie',
  history: 'Historia',
  variants: 'Warianty',
};

function tabLabel(
  tab: TabKey,
  groups: GroupMeta[],
  lang: 'pl' | 'en',
  t: (key: string, options?: { defaultValue?: string }) => string,
): string {
  if (isSpecialTab(tab)) {
    return t(`products.detail.tabs.${tab}`, {
      defaultValue: SPECIAL_TAB_DEFAULT_LABELS[tab],
    });
  }
  const group = groups.find((g) => g.code === tab);
  if (!group) return tab;
  const i18nKey = `products.detail.tabs.group_${group.code}`;
  const fallback = group.label[lang] ?? group.code;
  return t(i18nKey, { defaultValue: fallback });
}

function isSpecialTab(tab: TabKey): tab is SpecialTabKey {
  return (SPECIAL_TABS as readonly string[]).includes(tab);
}

function tabBadge(
  tab: TabKey,
  groups: GroupMeta[],
  stackedGroups: GroupMeta[],
  product: CatalogObjectDto | null | undefined,
): number | null {
  if (tab === 'attributes') {
    return stackedGroups.length === 0 ? null : stackedGroups.length;
  }
  if (tab === 'categories') return null;
  if (tab === 'history') return null;
  if (tab === 'variants') {
    const count = (product?.attributesIndexed as { variantsCount?: number } | undefined)
      ?.variantsCount;
    return typeof count === 'number' ? count : null;
  }
  // Tab-mode AttributeGroup → badge shows the attribute count when > 0.
  const group = groups.find((g) => g.code === tab);
  if (!group) return null;
  return group.attributes.length === 0 ? null : group.attributes.length;
}

function stripAttributes(dirty: Record<string, unknown>): Record<string, unknown> {
  const out: Record<string, unknown> = {};
  for (const [k, v] of Object.entries(dirty)) {
    if (k === 'sku' || k === 'code') continue;
    out[k] = v;
  }
  return out;
}

function countFilled(group: GroupMeta, fieldValue: (code: string) => unknown): number {
  let filled = 0;
  for (const attr of group.attributes) {
    const value = fieldValue(attr.code);
    if (value === undefined || value === null) continue;
    if (typeof value === 'string' && value.trim() === '') continue;
    filled += 1;
  }
  return filled;
}

function resolveProvenance(
  attr: AttributeMeta,
  product: CatalogObjectDto | null | undefined,
): Provenance {
  if (attr.is_system) return 'integration';
  const indexed = product?.attributesIndexed as
    | Record<string, { provenance?: Provenance }>
    | undefined;
  const meta = indexed?.[attr.code];
  if (meta && typeof meta === 'object' && typeof meta.provenance === 'string') {
    return meta.provenance;
  }
  return 'manual';
}

function OtherTabs({
  activeTab,
  productId,
  objectTypeId,
  locale,
  channel,
}: {
  activeTab: SpecialTabKey;
  productId: string;
  objectTypeId: string | null;
  locale: ProductLocale;
  channel: ProductChannel | null;
}) {
  // UX bug fix #2 — Multimedia is back as a special tab gated by
  // `ObjectType.hasMultimedia` (UX-02 removed it from the AttributeGroup
  // dispatcher; mirroring the UniversalDetailPage gating brings the
  // legacy product card back in sync with the capability flag).
  if (activeTab === 'multimedia') return <ProductMultimediaTab productId={productId} />;
  if (activeTab === 'categories')
    return <CategoriesTab productId={productId} objectTypeId={objectTypeId} />;
  if (activeTab === 'history') return <HistoryStub />;
  if (activeTab === 'variants')
    return <VariantsTabHost productId={productId} locale={locale} channel={channel} />;
  return null;
}

function HistoryStub() {
  const events = [
    {
      who: 'Marcin Lipiec',
      when: '5 min temu',
      what: 'Zmieniono description.pl',
      tone: 'bg-accent-violet/10 text-accent-violet',
    },
    {
      who: 'agent.sonnet',
      when: '2 godz. temu',
      what: 'Wzbogacono kod HS (taryfa UE 2026)',
      tone: 'bg-accent-emerald/10 text-accent-emerald',
    },
    {
      who: 'Anna Wiśniewska',
      when: 'wczoraj',
      what: 'Dodano 3 zdjęcia produktu',
      tone: 'bg-accent-blue/10 text-accent-blue',
    },
  ];

  return (
    <MockBadge variant="overlay" tooltip="MOCK · Pełna timeline wymaga endpointu audit-log">
      <div className="rounded-2xl border border-line bg-surface p-5 soft-shadow">
        <header className="mb-4 flex items-center gap-2">
          <Sparkles className="size-4 text-muted-foreground" />
          <h3 className="text-[14px] font-semibold text-ink">Historia zmian</h3>
        </header>
        <ol className="relative space-y-4 border-l border-line pl-6">
          {events.map((event) => (
            <li key={`${event.who}-${event.when}`} className="relative">
              <span
                className={`absolute -left-[31px] flex size-5 items-center justify-center rounded-full ring-2 ring-background ${event.tone}`}
              >
                <Clock className="size-2.5" />
              </span>
              <div className="flex items-center gap-2">
                <span className="text-[13px] font-medium text-ink">{event.who}</span>
                <span className="text-[11px] text-muted-foreground">{event.when}</span>
              </div>
              <p className="mt-0.5 text-[12.5px] text-ink-2">{event.what}</p>
            </li>
          ))}
        </ol>
        <div className="mt-3 flex items-center gap-2 text-[11px] text-muted-foreground">
          <Link2 className="size-3" />
          Sidebar pokazuje 5 ostatnich; pełna paginowana historia czeka na endpoint.
        </div>
      </div>
    </MockBadge>
  );
}
