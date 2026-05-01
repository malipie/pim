import { Lock, Unlock } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';

import { type Provenance, ProvenanceBadge } from '@/components/provenance-badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { jsonFetch } from '@/lib/http';

interface AttributeMeta {
  id: string;
  code: string;
  type: string;
  label: { pl?: string; en?: string };
  is_system: boolean;
  position: number;
  is_required_in_group: boolean;
  visible_when: unknown;
}

interface GroupMeta {
  id: string;
  code: string;
  label: { pl?: string; en?: string };
  position: number;
  attributes: AttributeMeta[];
}

interface DetailDynamicFormProps {
  productId: string;
  initialValues: Record<string, unknown>;
  onSaved?: () => void;
}

const AUTOSAVE_DEBOUNCE_MS = 3000;

/**
 * UI-02.17 (#307) — dynamic form rendered from
 * `effective-attribute-groups` (UI-02.5) for the product detail page.
 *
 * MVP slice scope:
 * - Pulls the schema, renders one section per group with `id` matching
 *   the left-rail anchors (UI-02.16 DetailGroupNav).
 * - Type-aware renderers for `text` / `textarea` / `number` /
 *   `boolean` / fallback. Localizable + scopable tabs are deferred —
 *   the canonical (default-locale, default-channel) field renders here.
 * - Provenance badge + lock toggle per field. Lock toggle is local
 *   state only — backend persistence ships with the publish flow.
 * - Auto-save with 3s debounce → PATCH /api/products/{id}.
 * - Save indicator (`saving / saved / failed`) displayed inline.
 *
 * Out of MVP slice (Faza 1): Localizable PL/EN tabs, Channel sub-tabs
 * (web/baselinker/datasheet), publish-critical diff modal, optimistic
 * UI rollback, RichText editor (TipTap).
 */
export function DetailDynamicForm({ productId, initialValues, onSaved }: DetailDynamicFormProps) {
  const { t, i18n } = useTranslation();
  const [groups, setGroups] = useState<GroupMeta[]>([]);
  const [values, setValues] = useState<Record<string, unknown>>(initialValues);
  const [locked, setLocked] = useState<Record<string, boolean>>({});
  const [saveState, setSaveState] = useState<'idle' | 'saving' | 'saved' | 'failed'>('idle');

  useEffect(() => {
    let cancelled = false;
    jsonFetch<{ groups: GroupMeta[] }>(`/api/products/${productId}/effective-attribute-groups`)
      .then((body) => {
        if (!cancelled) setGroups(body.groups);
      })
      .catch(() => undefined);
    return () => {
      cancelled = true;
    };
  }, [productId]);

  const dirty = useMemo(() => {
    return Object.keys(values).some((k) => values[k] !== initialValues[k]);
  }, [values, initialValues]);

  useEffect(() => {
    if (!dirty) return;
    setSaveState('saving');
    const handle = setTimeout(() => {
      const diff: Record<string, unknown> = {};
      for (const k of Object.keys(values)) {
        if (values[k] !== initialValues[k]) diff[k] = values[k];
      }
      jsonFetch(`/api/products/${productId}`, {
        method: 'PATCH',
        body: { attributesIndexed: { ...initialValues, ...diff } },
        contentType: 'application/merge-patch+json',
      })
        .then(() => {
          setSaveState('saved');
          if (onSaved !== undefined) onSaved();
        })
        .catch(() => setSaveState('failed'));
    }, AUTOSAVE_DEBOUNCE_MS);
    return () => clearTimeout(handle);
  }, [dirty, values, initialValues, productId, onSaved]);

  const lang = i18n.language === 'pl' ? 'pl' : 'en';

  const setValue = (code: string, next: unknown): void => {
    setValues((prev) => ({ ...prev, [code]: next }));
  };

  const toggleLock = (code: string): void => {
    setLocked((prev) => ({ ...prev, [code]: !prev[code] }));
  };

  return (
    <div className="space-y-6">
      <SaveIndicator state={saveState} />
      {groups.map((group) => {
        const groupLabel = group.label[lang] ?? group.code;
        return (
          <section
            key={group.id}
            id={`section-${group.id}`}
            className="space-y-3 rounded-lg border bg-card p-4"
          >
            <h2 className="text-lg font-semibold tracking-tight">{groupLabel}</h2>
            <div className="space-y-3">
              {group.attributes.map((attr) => {
                const label = attr.label[lang] ?? attr.code;
                const value = values[attr.code];
                const provenance: Provenance = attr.is_system ? 'integration' : 'manual';
                const isLocked = locked[attr.code] === true;
                return (
                  <div key={attr.id} className="space-y-1">
                    <div className="flex items-center justify-between gap-2">
                      <label htmlFor={`attr-${attr.code}`} className="text-sm font-medium">
                        {label}
                        {attr.is_required_in_group ? (
                          <span className="ml-1 text-rose-600">*</span>
                        ) : null}
                      </label>
                      <div className="flex items-center gap-1">
                        <ProvenanceBadge provenance={provenance} />
                        <Button
                          type="button"
                          variant="ghost"
                          size="sm"
                          onClick={() => toggleLock(attr.code)}
                          aria-label={t('products.detail.form.toggle_lock', {
                            defaultValue: 'Toggle lock',
                          })}
                        >
                          {isLocked ? <Lock className="size-3" /> : <Unlock className="size-3" />}
                        </Button>
                      </div>
                    </div>
                    <FieldRenderer
                      attrCode={attr.code}
                      type={attr.type}
                      value={value}
                      readOnly={attr.is_system || isLocked}
                      onChange={(next) => setValue(attr.code, next)}
                    />
                  </div>
                );
              })}
            </div>
          </section>
        );
      })}
    </div>
  );
}

function SaveIndicator({ state }: { state: 'idle' | 'saving' | 'saved' | 'failed' }) {
  const { t } = useTranslation();
  if (state === 'idle') return null;
  const tone = state === 'failed' ? 'text-rose-600' : 'text-muted-foreground';
  const label =
    state === 'saving'
      ? t('products.detail.form.saving', { defaultValue: 'Saving…' })
      : state === 'saved'
        ? t('products.detail.form.saved', { defaultValue: 'Saved' })
        : t('products.detail.form.failed', { defaultValue: 'Save failed' });
  return <p className={`sticky top-2 text-xs ${tone}`}>{label}</p>;
}

function FieldRenderer({
  attrCode,
  type,
  value,
  readOnly,
  onChange,
}: {
  attrCode: string;
  type: string;
  value: unknown;
  readOnly: boolean;
  onChange: (next: unknown) => void;
}) {
  const id = `attr-${attrCode}`;

  if (type === 'boolean') {
    return (
      <input
        id={id}
        type="checkbox"
        disabled={readOnly}
        checked={value === true}
        onChange={(e) => onChange(e.target.checked)}
      />
    );
  }
  if (type === 'number') {
    return (
      <Input
        id={id}
        type="number"
        disabled={readOnly}
        value={typeof value === 'number' ? value : ''}
        onChange={(e) => {
          const parsed = Number.parseFloat(e.target.value);
          onChange(Number.isNaN(parsed) ? null : parsed);
        }}
      />
    );
  }
  if (type === 'textarea' || type === 'richtext') {
    return (
      <Textarea
        id={id}
        disabled={readOnly}
        rows={4}
        value={typeof value === 'string' ? value : ''}
        onChange={(e) => onChange(e.target.value)}
      />
    );
  }
  return (
    <Input
      id={id}
      type="text"
      disabled={readOnly}
      value={typeof value === 'string' ? value : ''}
      onChange={(e) => onChange(e.target.value)}
    />
  );
}
