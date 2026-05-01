import { Lock, Unlock } from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';
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
const FALLBACK_GROUP_ID = '__fallback_other__';
const KNOWN_SYSTEM_CODES = new Set(['created_at', 'updated_at', 'created_by', 'updated_by', 'id']);

/**
 * UI-02.17 (#307) + UI-02.22 (#338) — dynamic form rendered from
 * `effective-attribute-groups` (UI-02.5).
 *
 * Reads `attributesIndexed[code].value` (server JSONB shape:
 * `{name: {value: "..."}}`); writes via PATCH `attributes: {code: ...}`
 * (server-side mapper handles the JSONB wrap). When the schema does
 * not cover every key in `attributesIndexed`, a fallback section
 * "Other attributes" lists them so editors are never invisible.
 */
export function DetailDynamicForm({ productId, initialValues, onSaved }: DetailDynamicFormProps) {
  const { t, i18n } = useTranslation();
  const [groups, setGroups] = useState<GroupMeta[]>([]);
  const baseValues = useMemo(() => unwrapValues(initialValues), [initialValues]);
  const [values, setValues] = useState<Record<string, unknown>>(baseValues);
  const [locked, setLocked] = useState<Record<string, boolean>>({});
  const [saveState, setSaveState] = useState<'idle' | 'saving' | 'saved' | 'failed'>('idle');
  const [saveError, setSaveError] = useState<string | null>(null);
  const initialValuesRef = useRef(baseValues);

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

  const dirtyDiff = useMemo(() => {
    const diff: Record<string, unknown> = {};
    for (const k of Object.keys(values)) {
      if (values[k] !== initialValuesRef.current[k]) diff[k] = values[k];
    }
    return diff;
  }, [values]);

  useEffect(() => {
    if (Object.keys(dirtyDiff).length === 0) return;
    setSaveState('saving');
    setSaveError(null);
    const handle = setTimeout(() => {
      jsonFetch(`/api/products/${productId}`, {
        method: 'PATCH',
        body: { attributes: dirtyDiff },
        contentType: 'application/merge-patch+json',
      })
        .then(() => {
          setSaveState('saved');
          initialValuesRef.current = { ...initialValuesRef.current, ...dirtyDiff };
          if (onSaved !== undefined) onSaved();
        })
        .catch((err: unknown) => {
          setSaveState('failed');
          setSaveError(err instanceof Error ? err.message : 'unknown');
        });
    }, AUTOSAVE_DEBOUNCE_MS);
    return () => clearTimeout(handle);
  }, [dirtyDiff, productId, onSaved]);

  const lang = i18n.language === 'pl' ? 'pl' : 'en';

  const setValue = (code: string, next: unknown): void => {
    setValues((prev) => ({ ...prev, [code]: next }));
  };

  const toggleLock = (code: string): void => {
    setLocked((prev) => ({ ...prev, [code]: !prev[code] }));
  };

  const groupedCodes = useMemo(() => {
    const set = new Set<string>();
    for (const g of groups) for (const a of g.attributes) set.add(a.code);
    return set;
  }, [groups]);

  const fallbackKeys = useMemo(() => {
    return Object.keys(baseValues).filter(
      (key) => !groupedCodes.has(key) && !KNOWN_SYSTEM_CODES.has(key),
    );
  }, [baseValues, groupedCodes]);

  const showFallback = fallbackKeys.length > 0;

  return (
    <div className="space-y-6">
      <SaveIndicator state={saveState} error={saveError} />
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
                  <FieldRow
                    key={attr.id}
                    code={attr.code}
                    label={label}
                    type={attr.type}
                    value={value}
                    provenance={provenance}
                    required={attr.is_required_in_group}
                    isReadOnly={attr.is_system || isLocked}
                    isLocked={isLocked}
                    onChange={(next) => setValue(attr.code, next)}
                    onToggleLock={() => toggleLock(attr.code)}
                    toggleLockLabel={t('products.detail.form.toggle_lock', {
                      defaultValue: 'Toggle lock',
                    })}
                  />
                );
              })}
            </div>
          </section>
        );
      })}

      {showFallback ? (
        <section
          id={`section-${FALLBACK_GROUP_ID}`}
          className="space-y-3 rounded-lg border border-dashed bg-card p-4"
        >
          <h2 className="text-lg font-semibold tracking-tight">
            {t('products.detail.form.fallback_group', {
              defaultValue: 'Other attributes',
            })}
          </h2>
          <p className="text-xs text-muted-foreground">
            {t('products.detail.form.fallback_hint', {
              defaultValue:
                'These attributes have values but are not yet attached to any AttributeGroup for this ObjectType.',
            })}
          </p>
          <div className="space-y-3">
            {fallbackKeys.map((code) => {
              const isLocked = locked[code] === true;
              return (
                <FieldRow
                  key={code}
                  code={code}
                  label={code}
                  type="text"
                  value={values[code]}
                  provenance="manual"
                  required={false}
                  isReadOnly={isLocked}
                  isLocked={isLocked}
                  onChange={(next) => setValue(code, next)}
                  onToggleLock={() => toggleLock(code)}
                  toggleLockLabel={t('products.detail.form.toggle_lock', {
                    defaultValue: 'Toggle lock',
                  })}
                />
              );
            })}
          </div>
        </section>
      ) : null}
    </div>
  );
}

function FieldRow({
  code,
  label,
  type,
  value,
  provenance,
  required,
  isReadOnly,
  isLocked,
  onChange,
  onToggleLock,
  toggleLockLabel,
}: {
  code: string;
  label: string;
  type: string;
  value: unknown;
  provenance: Provenance;
  required: boolean;
  isReadOnly: boolean;
  isLocked: boolean;
  onChange: (next: unknown) => void;
  onToggleLock: () => void;
  toggleLockLabel: string;
}) {
  return (
    <div className="space-y-1">
      <div className="flex items-center justify-between gap-2">
        <label htmlFor={`attr-${code}`} className="text-sm font-medium">
          {label}
          {required ? <span className="ml-1 text-rose-600">*</span> : null}
        </label>
        <div className="flex items-center gap-1">
          <ProvenanceBadge provenance={provenance} />
          <Button
            type="button"
            variant="ghost"
            size="sm"
            onClick={onToggleLock}
            aria-label={toggleLockLabel}
          >
            {isLocked ? <Lock className="size-3" /> : <Unlock className="size-3" />}
          </Button>
        </div>
      </div>
      <FieldRenderer
        attrCode={code}
        type={type}
        value={value}
        readOnly={isReadOnly}
        onChange={onChange}
      />
    </div>
  );
}

function SaveIndicator({
  state,
  error,
}: {
  state: 'idle' | 'saving' | 'saved' | 'failed';
  error: string | null;
}) {
  const { t } = useTranslation();
  if (state === 'idle') return null;
  const tone = state === 'failed' ? 'text-rose-600' : 'text-muted-foreground';
  const label =
    state === 'saving'
      ? t('products.detail.form.saving', { defaultValue: 'Saving…' })
      : state === 'saved'
        ? t('products.detail.form.saved', { defaultValue: 'Saved' })
        : t('products.detail.form.failed', { defaultValue: 'Save failed' });
  return (
    <p className={`sticky top-2 text-xs ${tone}`}>
      {label}
      {state === 'failed' && error !== null ? `: ${error}` : ''}
    </p>
  );
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

function unwrapValues(raw: Record<string, unknown>): Record<string, unknown> {
  const out: Record<string, unknown> = {};
  for (const [k, v] of Object.entries(raw)) {
    if (v !== null && typeof v === 'object' && !Array.isArray(v) && 'value' in v) {
      out[k] = (v as { value: unknown }).value;
    } else {
      out[k] = v;
    }
  }
  return out;
}
