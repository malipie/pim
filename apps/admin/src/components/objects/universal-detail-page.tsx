/*
 * UP-07 (#1023) — universal object detail page.
 *
 * Drives `/objects/:slug/:id` for any ObjectType (built-in or custom).
 * Reuses the kind-agnostic primitives from the legacy ProductDetailPage
 * (AttrGroupCard, AttrRow, CompletenessRing, LocaleChannelToolbar,
 * EffectiveModelCard) but loads + mutates via the poly-kind
 * `/api/objects/{id}` endpoints (UP-01 PATCH/DELETE, UP-07a effective
 * groups, UP-03 categories) instead of `/api/products/{id}/*`.
 *
 * Capability gates per ObjectType:
 *   - tabs / sidebar slots that ship in this MVP for every kind:
 *       attributes (always), tab-mode groups (from effective-groups
 *       response), categories (when `isCategorizable`).
 *   - product-only slots deferred to follow-up: variants tab, multimedia
 *     tab, SyncStatusCard, AgentSuggestionsCard, DuplicateButton,
 *     PreviewButton. These remain reachable through the legacy
 *     `/products/{id}` route (dual maintenance per UP-10) so power
 *     features stay available for the product kind even before the
 *     poly-kind refactor of CategoriesTab / VariantsTab / MultimediaTab.
 *
 * Świadome odejścia from full ProductDetailPage parity:
 *   - CategoriesTab integration (CategoryPickerDialog) is product-only
 *     today; universal categories tab ships a read-only chip list with
 *     "Edit in legacy view" CTA. Picker rewrite for objects endpoint =
 *     UP-07 follow-up ticket.
 *   - VariantsListCard + VariantsTabHost are product-only; universal
 *     equivalent (also covered by UP-04 BE) lands as follow-up.
 *   - Create mode is NOT in this component — UP-08 ships
 *     UniversalCreatePage as its own full-page wizard route.
 */
import { useQuery } from '@tanstack/react-query';
import { ArrowLeft, MoreHorizontal, Pencil, Save, Trash2 } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Link, useNavigate } from 'react-router';
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
import { toast } from '@/components/ui/toast';
import { AttrGroupCard } from '@/features/catalog/products/components/attr-group-card';
import { AttrRow } from '@/features/catalog/products/components/attr-row';
import { CompletenessRing } from '@/features/catalog/products/components/completeness-ring';
import { EffectiveModelCard } from '@/features/catalog/products/components/effective-model-card';
import { LocaleChannelToolbar } from '@/features/catalog/products/components/locale-channel-toolbar';
import { ProductMultimediaTab } from '@/features/catalog/products/components/product-multimedia-tab';
import type {
  AttributeMeta,
  CatalogObjectDto,
  GroupMeta,
  LocaleOption,
  ProductChannel,
  ProductLocale,
} from '@/features/catalog/products/components/types';
import { unwrapAttributesIndexed } from '@/lib/attributes-indexed';
import { jsonFetch } from '@/lib/http';
import { isLegacyOptionalSystemGroupCode } from '@/lib/legacy-attribute-groups';
import { cn } from '@/lib/utils';

const SPECIAL_TABS = ['attributes', 'categories', 'multimedia', 'variants'] as const;
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

export interface UniversalDetailPageProps {
  objectId: string;
  objectTypeCode: string;
  objectTypeLabel: string;
  /** Back link (typically `/objects/:slug`). */
  backHref: string;
  /** Capability flags from the list-schema response. */
  isCategorizable: boolean;
  hasMultimedia: boolean;
  hasVariants: boolean;
}

interface CategoryAssignment {
  categoryId: string;
  code: string;
  isPrimary: boolean;
  position: number;
}

interface CategoriesResponse {
  productId?: string;
  objectId?: string;
  primaryCategoryId: string | null;
  assignments: CategoryAssignment[];
}

export function UniversalDetailPage({
  objectId,
  objectTypeCode,
  objectTypeLabel,
  backHref,
  isCategorizable,
  hasMultimedia,
  hasVariants,
}: UniversalDetailPageProps) {
  const { t, i18n } = useTranslation();
  const navigate = useNavigate();
  const lang = i18n.language === 'pl' ? 'pl' : 'en';

  // UP-07 — poly-kind GET /api/objects/{id} (existing AP4 ApiResource).
  const objectQuery = useQuery({
    queryKey: ['object', objectId],
    enabled: objectId !== '',
    staleTime: 30_000,
    queryFn: async (): Promise<CatalogObjectDto> => {
      return jsonFetch<CatalogObjectDto>(`/api/objects/${objectId}`, {
        accept: 'application/ld+json',
      });
    },
  });

  // UP-07a — poly-kind /api/objects/{id}/effective-attribute-groups.
  const groupsQuery = useQuery<{ groups: GroupMeta[]; locales?: LocaleOption[] }>({
    queryKey: ['object', objectId, 'effective-attribute-groups'],
    enabled: objectId !== '',
    staleTime: 5_000,
    queryFn: () =>
      jsonFetch<{ groups: GroupMeta[]; locales?: LocaleOption[] }>(
        `/api/objects/${objectId}/effective-attribute-groups`,
      ),
    placeholderData: (prev) => prev,
  });

  // UP-03 — poly-kind /api/objects/{id}/categories.
  const categoriesQuery = useQuery<CategoriesResponse>({
    queryKey: ['object', objectId, 'categories'],
    enabled: objectId !== '' && isCategorizable,
    staleTime: 60_000,
    queryFn: () =>
      jsonFetch<CategoriesResponse>(`/api/objects/${objectId}/categories`, {
        accept: 'application/json',
      }),
  });

  const product = objectQuery.data ?? null;
  const attrs = useMemo(
    () =>
      unwrapAttributesIndexed(product?.attributesIndexed as Record<string, unknown> | undefined),
    [product?.attributesIndexed],
  );
  // Hide empty groups (0 attributes) from the object card: after an
  // operator deletes the last attribute of a group, the backend still
  // returns the now-empty group (it's a valid modeling construct), but
  // rendering it as an empty "0/0" tab/card is noise. Modeling views keep
  // empty groups; here we only render groups that contribute attributes.
  const groups = useMemo(
    () => (groupsQuery.data?.groups ?? []).filter((g) => g.attributes.length > 0),
    [groupsQuery.data],
  );

  // #1149 — dynamic locale picker from the tenant's enabled locales.
  const locales = useMemo<LocaleOption[]>(
    () => groupsQuery.data?.locales ?? [],
    [groupsQuery.data],
  );

  const tabModeGroups = useMemo(
    () => groups.filter((g) => (g.display_mode ?? 'tab') === 'tab'),
    [groups],
  );
  const stackedGroups = useMemo(
    () => groups.filter((g) => (g.display_mode ?? 'tab') === 'stacked'),
    [groups],
  );

  const visibleTabs: readonly TabKey[] = useMemo(() => {
    const fromGroups = tabModeGroups.map((g) => g.code);
    const tabs: TabKey[] = ['attributes', ...fromGroups];
    if (hasMultimedia) tabs.push('multimedia');
    if (isCategorizable) tabs.push('categories');
    if (hasVariants) tabs.push('variants');
    return tabs;
  }, [tabModeGroups, isCategorizable, hasMultimedia, hasVariants]);

  const [activeTab, setActiveTab] = useState<TabKey>('attributes');
  const [locale, setLocale] = useState<ProductLocale>('pl');
  const [channel, setChannel] = useState<ProductChannel | null>(null);
  const [isEditing, setIsEditing] = useState(false);
  const [dirtyFields, setDirtyFields] = useState<Record<string, unknown>>({});
  const [expandedGroups, setExpandedGroups] = useState<Set<string>>(new Set());
  const [isSaving, setIsSaving] = useState(false);
  const [showDeleteConfirm, setShowDeleteConfirm] = useState(false);
  const [isDeleting, setIsDeleting] = useState(false);

  useEffect(() => {
    if (!visibleTabs.includes(activeTab)) {
      setActiveTab('attributes');
    }
  }, [activeTab, visibleTabs]);

  // #1149 — default the picker to the tenant default locale once loaded.
  const [didInitLocale, setDidInitLocale] = useState(false);
  useEffect(() => {
    if (didInitLocale || locales.length === 0) return;
    const def = locales.find((l) => l.is_default) ?? locales[0];
    if (def === undefined) return;
    setLocale(def.code);
    setDidInitLocale(true);
  }, [locales, didInitLocale]);

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
    if (Object.hasOwn(dirtyFields, code)) return dirtyFields[code];
    return attrs[code];
  };

  const handleSave = async (): Promise<void> => {
    if (isSaving) return;
    if (Object.keys(dirtyFields).length === 0) {
      setIsEditing(false);
      return;
    }
    setIsSaving(true);
    try {
      const attributes = stripAttributes(dirtyFields);
      await jsonFetch(`/api/objects/${objectId}`, {
        method: 'PATCH',
        contentType: 'application/merge-patch+json',
        body: { attributes },
      });
      await objectQuery.refetch();
      setDirtyFields({});
      setIsEditing(false);
      toast.success(t('products.detail.save.success', { defaultValue: 'Zapisano zmiany' }));
    } catch {
      toast.error(t('products.detail.save.failed', { defaultValue: 'Nie udało się zapisać' }));
    } finally {
      setIsSaving(false);
    }
  };

  const cancelEdit = (): void => {
    setDirtyFields({});
    setIsEditing(false);
  };

  const handleDelete = async (): Promise<void> => {
    if (isDeleting) return;
    setIsDeleting(true);
    try {
      await jsonFetch(`/api/objects/${objectId}`, { method: 'DELETE' });
      toast.success(
        t('products.detail.delete.success', {
          defaultValue: 'Usunięto obiekt {{code}}',
          code: product?.code ?? objectId,
        }),
      );
      navigate(backHref);
    } catch {
      toast.error(t('products.detail.delete.failed', { defaultValue: 'Nie udało się usunąć' }));
      setIsDeleting(false);
      setShowDeleteConfirm(false);
    }
  };

  // Issue #1043 — order matters: check `isLoading` FIRST (covers initial
  // mount), then the not-found bucket. The previous order had `product
  // === null` in the loading guard, so a 404 stayed on `Ładowanie…`
  // forever because `objectQuery.data ?? null` is null after a failed
  // fetch and the error branch below was unreachable.
  if (objectQuery.isLoading) {
    return <DetailLoadingState />;
  }
  if (objectQuery.isError || product === null || product === undefined) {
    return (
      <DetailNotFoundState
        id={objectId}
        backHref={backHref}
        title={t('object_detail.errors.not_found_title', {
          defaultValue: 'Obiekt nie znaleziony',
        })}
        description={t('object_detail.errors.not_found_description', {
          defaultValue: 'Obiekt o ID "{{id}}" nie istnieje lub został usunięty.',
          id: objectId,
        })}
        backLabel={t('object_detail.errors.back_to_list', {
          defaultValue: 'Wróć do listy',
        })}
      />
    );
  }

  const skuValue = product.code ?? '';
  const nameValue = typeof attrs.name === 'string' ? attrs.name : skuValue;
  const completenessPct = product.completenessPct ?? 0;

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
              <Link to={backHref} aria-label={t('object_detail.back', { defaultValue: 'Powrót' })}>
                <ArrowLeft className="size-4" />
              </Link>
            </Button>
            <div className="text-[12px] text-zinc-500">
              <span>{objectTypeLabel}</span>
              {skuValue !== '' ? (
                <>
                  <span className="mx-1.5 text-zinc-300">/</span>
                  <span className="font-medium text-zinc-900">{skuValue}</span>
                </>
              ) : null}
            </div>
            <div className="ml-auto flex items-center gap-2">
              <DropdownMenu>
                <DropdownMenuTrigger asChild>
                  <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    className="soft-shadow size-9 rounded-xl bg-white"
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
                    {t('products.detail.actions.delete', { defaultValue: 'Usuń obiekt' })}
                  </DropdownMenuItem>
                </DropdownMenuContent>
              </DropdownMenu>
              <span className="mx-1 h-6 w-px bg-zinc-200" />
              {isEditing ? (
                <>
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
                  <Button
                    type="button"
                    onClick={() => void handleSave()}
                    disabled={isSaving}
                    className="h-9 rounded-xl bg-zinc-900 px-4 text-[12.5px] font-medium text-white hover:bg-zinc-800"
                  >
                    <Save className="size-4" />
                    {t('products.detail.actions.save', { defaultValue: 'Zapisz zmiany' })}
                  </Button>
                </>
              ) : (
                <Button
                  type="button"
                  onClick={() => setIsEditing(true)}
                  className="h-9 rounded-xl bg-zinc-900 px-4 text-[12.5px] font-medium text-white hover:bg-zinc-800"
                >
                  <Pencil className="size-4" />
                  {t('products.detail.actions.edit', { defaultValue: 'Edytuj' })}
                </Button>
              )}
            </div>
          </div>

          <div className="mt-4 flex items-start gap-5">
            <div
              className="soft-shadow grid size-[72px] shrink-0 place-items-center rounded-2xl bg-white text-[34px]"
              aria-hidden
            >
              ▣
            </div>
            <div className="min-w-0 flex-1">
              <div className="flex items-center gap-2.5 text-[12px] text-zinc-500">
                <span className="font-mono">{skuValue}</span>
                <span className="text-zinc-300">·</span>
                <span className="inline-flex items-center gap-1.5">
                  <span
                    className={cn(
                      'size-1.5 rounded-full',
                      product.enabled ? 'bg-emerald-500' : 'bg-zinc-300',
                    )}
                    aria-hidden
                  />
                  {product.enabled
                    ? t('products.detail.status.active', { defaultValue: 'Aktywny' })
                    : t('products.detail.status.inactive', { defaultValue: 'Nieaktywny' })}
                </span>
              </div>
              {isEditing ? (
                <Input
                  aria-label={t('object_detail.name_placeholder', { defaultValue: 'Nazwa' })}
                  placeholder={t('object_detail.name_placeholder', { defaultValue: 'Nazwa' })}
                  value={
                    typeof fieldValue('name') === 'string'
                      ? (fieldValue('name') as string)
                      : nameValue
                  }
                  onChange={(event) => setFieldValue('name', event.target.value)}
                  className="font-display mt-1 h-11 rounded-lg border-zinc-200 bg-white text-[26px] font-semibold tracking-tight"
                />
              ) : (
                <h1 className="font-display mt-1 text-[26px] font-semibold leading-tight tracking-tight">
                  {nameValue}
                </h1>
              )}
              <div className="mt-2.5 flex flex-wrap items-center gap-2">
                <span className="soft-shadow rounded-full bg-white px-2 py-1 text-[11px] font-medium text-zinc-700">
                  {objectTypeLabel}
                </span>
              </div>
            </div>
            <div className="flex items-center">
              <CompletenessRing pct={completenessPct} size={72} stroke={6} />
            </div>
          </div>
        </div>

        <div className="flex items-center gap-1 border-t border-zinc-100 px-7">
          <div
            className="flex flex-1 items-center gap-1"
            role="tablist"
            aria-label={t('object_detail.tabs.aria', { defaultValue: 'Zakładki obiektu' })}
          >
            {visibleTabs.map((tab) => {
              const isActive = activeTab === tab;
              const badge = tabBadge(tab, groups, stackedGroups);
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
          <LocaleChannelToolbar
            locale={locale}
            channel={channel}
            onLocaleChange={setLocale}
            onChannelChange={setChannel}
            locales={locales}
          />
        </div>
      </header>

      <div className="grid grid-cols-1 gap-5 px-7 py-6 lg:grid-cols-[minmax(0,1fr)_320px]">
        <div className="min-w-0 space-y-3">
          {(() => {
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
                    isEditing={isEditing}
                    isLocked={attr.is_system}
                    onChange={(next) => setFieldValue(attr.code, next)}
                    relationContextProductId={objectId}
                  />
                ))}
              </AttrGroupCard>
            );

            if (activeTab === 'attributes') {
              return stackedGroups.map(renderStackedGroup);
            }
            if (activeTab === 'categories') {
              return (
                <CategoriesPanel
                  data={categoriesQuery.data}
                  isLoading={categoriesQuery.isLoading}
                  objectTypeCode={objectTypeCode}
                />
              );
            }
            if (activeTab === 'multimedia') {
              return <ObjectMultimediaPanel objectId={objectId} />;
            }
            if (activeTab === 'variants') {
              return <ObjectVariantsPanel objectId={objectId} />;
            }
            const tabGroup = tabModeGroups.find((g) => g.code === activeTab);
            if (tabGroup) return renderStackedGroup(tabGroup);
            return null;
          })()}
        </div>

        <aside
          className="space-y-3"
          aria-label={t('object_detail.sidebar.aria', { defaultValue: 'Panel boczny' })}
        >
          <EffectiveModelCard groups={groups} objectTypeName={objectTypeLabel} />
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
              {t('object_detail.delete.confirm_title', { defaultValue: 'Usunąć obiekt?' })}
            </DialogTitle>
            <DialogDescription>
              {t('object_detail.delete.confirm_body', {
                defaultValue:
                  'Czy na pewno chcesz usunąć obiekt {{code}}? Tej operacji nie da się cofnąć.',
                code: product.code ?? '',
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
                ? t('object_detail.delete.deleting', { defaultValue: 'Usuwanie…' })
                : t('object_detail.delete.confirm_submit', { defaultValue: 'Usuń' })}
            </Button>
          </div>
        </DialogContent>
      </Dialog>
    </div>
  );
}

function CategoriesPanel({
  data,
  isLoading,
  objectTypeCode,
}: {
  data: CategoriesResponse | undefined;
  isLoading: boolean;
  objectTypeCode: string;
}) {
  const { t } = useTranslation();
  if (isLoading) {
    return (
      <p className="text-[12.5px] text-muted-foreground">
        {t('app.loading', { defaultValue: 'Ładowanie…' })}
      </p>
    );
  }
  const assignments = data?.assignments ?? [];
  if (assignments.length === 0) {
    return (
      <div className="border-line bg-surface rounded-2xl border border-dashed p-6 text-center">
        <p className="text-ink text-[13px] font-medium">
          {t('object_detail.categories.empty', {
            defaultValue: 'Brak przypisanych kategorii.',
          })}
        </p>
        <p className="mt-1 text-[11.5px] text-muted-foreground">
          {t('object_detail.categories.empty_hint', {
            defaultValue:
              'Edycja kategorii dla custom kindów dochodzi w UP-07 follow-upie (CategoryPickerDialog universal refactor).',
          })}
        </p>
      </div>
    );
  }
  return (
    <section className="space-y-3">
      <p className="text-[11.5px] text-muted-foreground">
        {t('object_detail.categories.read_only_hint', {
          defaultValue:
            'Lista kategorii (read-only w UP-07 MVP — picker dialog dla custom kindów w follow-upie).',
        })}
      </p>
      <ul className="flex flex-wrap gap-2">
        {assignments.map((a) => (
          <li
            key={a.categoryId}
            className={cn(
              'inline-flex items-center gap-2 rounded-full border px-3 py-1.5 text-[12.5px]',
              a.isPrimary
                ? 'border-amber-200 bg-amber-50 text-amber-900'
                : 'border-zinc-200 bg-white text-zinc-700',
            )}
          >
            {a.isPrimary ? <span aria-hidden>★</span> : null}
            <span className="font-medium">{a.code}</span>
          </li>
        ))}
      </ul>
      <p className="text-[11px] text-zinc-400">
        objectType: <span className="font-mono">{objectTypeCode}</span>
      </p>
    </section>
  );
}

function tabLabel(
  tab: TabKey,
  groups: GroupMeta[],
  lang: 'pl' | 'en',
  t: (key: string, options?: { defaultValue?: string }) => string,
): string {
  if (tab === 'attributes') {
    return t('object_detail.tabs.attributes', { defaultValue: 'Atrybuty' });
  }
  if (tab === 'categories') {
    return t('object_detail.tabs.categories', { defaultValue: 'Kategorie' });
  }
  const group = groups.find((g) => g.code === tab);
  if (!group) return tab;
  return group.label[lang] ?? group.code;
}

function tabBadge(tab: TabKey, groups: GroupMeta[], stackedGroups: GroupMeta[]): number | null {
  if (tab === 'attributes') {
    return stackedGroups.length === 0 ? null : stackedGroups.length;
  }
  if (tab === 'categories') return null;
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

/**
 * UX-08 — Multimedia tab driven by `ObjectType.hasMultimedia`.
 *
 * Reuses `ProductMultimediaTab` with the object UUID as the `productId`
 * prop — `product_assets` link table uses CatalogObject UUIDs across
 * every kind (UX-04 poly-kind /api/objects/{id}/assets aliases the
 * underlying handler), so no kind-specific paths leak through.
 */
function ObjectMultimediaPanel({ objectId }: { objectId: string }) {
  return <ProductMultimediaTab productId={objectId} />;
}

interface VariantSummary {
  id: string;
  code?: string;
  attributesIndexed?: Record<string, unknown>;
}

/**
 * UX-08 — Variants tab driven by `ObjectType.hasVariants`.
 *
 * Minimal poly-kind list: queries `/api/objects?parent_id={objectId}`
 * for direct children and links each one back to the detail page via
 * its ObjectType slug. Full editor (axis matrix, generator) stays on
 * the legacy Product detail for now — the universal generator endpoint
 * landed in UP-04 but a generic editor UI is a follow-up.
 */
function ObjectVariantsPanel({ objectId }: { objectId: string }) {
  const { t } = useTranslation();
  const query = useQuery({
    queryKey: ['object', objectId, 'variants'],
    enabled: objectId !== '',
    staleTime: 30_000,
    queryFn: async () => {
      const data = await jsonFetch<{
        member?: VariantSummary[];
        'hydra:member'?: VariantSummary[];
      }>(`/api/objects?parent_id=${objectId}&itemsPerPage=200`, { accept: 'application/ld+json' });
      return data.member ?? data['hydra:member'] ?? [];
    },
  });

  if (query.isLoading) {
    return <p className="text-sm text-muted-foreground">{t('app.loading')}</p>;
  }

  const variants = query.data ?? [];
  if (variants.length === 0) {
    return (
      <p className="text-sm text-muted-foreground">
        {t('object_detail.variants.empty', {
          defaultValue:
            'Ten obiekt nie ma jeszcze wariantów. Generator wariantów ląduje w następnej iteracji.',
        })}
      </p>
    );
  }

  return (
    <ul className="space-y-2">
      {variants.map((variant) => {
        const indexed = variant.attributesIndexed ?? {};
        const sku =
          typeof variant.code === 'string' && variant.code.length > 0 ? variant.code : variant.id;
        return (
          <li key={variant.id} className="rounded-md border bg-card px-3 py-2 text-sm">
            <div className="font-mono text-[12px] text-ink">{sku}</div>
            <div className="text-[11px] text-muted-foreground">
              {Object.entries(indexed)
                .map(([k, v]) => `${k}: ${typeof v === 'string' ? v : JSON.stringify(v)}`)
                .slice(0, 3)
                .join(' · ')}
            </div>
          </li>
        );
      })}
    </ul>
  );
}
