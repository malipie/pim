import { Plus, Trash2 } from 'lucide-react';
import { useTranslation } from 'react-i18next';

import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';

export interface RelationAdvancedField {
  code: string;
  type: 'text' | 'number' | 'boolean';
  label: Record<string, string>;
  required: boolean;
}

export interface RelationConfigValue {
  targetObjectTypeIds: string[];
  cardinality: 'one' | 'many';
  advanced: boolean;
  advancedFields: RelationAdvancedField[];
  /**
   * MODR-08 (#930) — list of target attribute codes surfaced inside the
   * relation widget's preview card. Empty list keeps the default
   * (target object code + name). Persisted as `relation_preview_fields`
   * on the Attribute entity; PATCH plumbing wired since MODR-08.
   */
  previewFields: string[];
}

interface RelationConfigPanelProps {
  value: RelationConfigValue;
  objectTypes: Array<{
    id: string;
    code: string;
    kind: string;
    label?: Record<string, string> | string | null;
  }>;
  disabled?: boolean;
  onChange: (next: RelationConfigValue) => void;
}

/**
 * ADR-014 / MOD-13 (#905) — relation-attribute config card rendered inside
 * the AttributeShowPage editor when `attribute.type === 'relation'`. Three
 * controls:
 *   - target ObjectType multi-select (1+ allowed targets);
 *   - cardinality radio (`one` / `many`);
 *   - advanced toggle → when ON the per-link metadata fields editor
 *     surfaces (add/remove rows of kod / typ / label / required).
 *
 * Backed by the MOD-05 (#897) handler: every field below is serialised
 * straight into the PATCH /api/attributes/{id} body
 * (`relationTargetObjectTypeIds`, `relationCardinality`, `relationAdvanced`)
 * plus `validationRules.advanced_fields` for the metadata schema.
 *
 * The component is fully controlled; the parent owns the value + persists
 * via the existing save flow. No local effects, no auto-save.
 */
export function RelationConfigPanel({
  value,
  objectTypes,
  disabled = false,
  onChange,
}: RelationConfigPanelProps) {
  const { t } = useTranslation();

  const toggleTarget = (id: string) => {
    if (disabled) return;
    const has = value.targetObjectTypeIds.includes(id);
    onChange({
      ...value,
      targetObjectTypeIds: has
        ? value.targetObjectTypeIds.filter((x) => x !== id)
        : [...value.targetObjectTypeIds, id],
    });
  };

  const setCardinality = (next: 'one' | 'many') => {
    if (disabled) return;
    onChange({ ...value, cardinality: next });
  };

  const setAdvanced = (next: boolean) => {
    if (disabled) return;
    onChange({
      ...value,
      advanced: next,
      // Clear advanced_fields when turning OFF so a stale schema doesn't
      // confuse MOD-08 validation downstream.
      advancedFields: next ? value.advancedFields : [],
    });
  };

  const setField = (index: number, patch: Partial<RelationAdvancedField>) => {
    if (disabled) return;
    onChange({
      ...value,
      advancedFields: value.advancedFields.map((field, i) =>
        i === index ? { ...field, ...patch } : field,
      ),
    });
  };

  const addField = () => {
    if (disabled) return;
    onChange({
      ...value,
      advancedFields: [
        ...value.advancedFields,
        { code: '', type: 'text', label: {}, required: false },
      ],
    });
  };

  const removeField = (index: number) => {
    if (disabled) return;
    onChange({
      ...value,
      advancedFields: value.advancedFields.filter((_, i) => i !== index),
    });
  };

  return (
    <Card className="border-zinc-200">
      <CardContent className="space-y-5 p-6">
        <div>
          <div className="text-[15px] font-semibold text-foreground">
            {t('attributes.relation_config_title', {
              defaultValue: 'Konfiguracja relacji (ADR-014)',
            })}
          </div>
          <p className="mt-1 text-[12.5px] text-muted-foreground">
            {t('attributes.relation_config_intro', {
              defaultValue:
                'Atrybut typu „relation" łączy obiekt z innym obiektem. Wybierz dozwolone ObjectType, kardynalność i ewentualnie pola metadanych dla każdego powiązania.',
            })}
          </p>
        </div>

        {/* Target ObjectType — multi-select chip strip */}
        <div>
          <div className="mb-2 text-[12px] font-medium text-zinc-600">
            {t('attributes.relation_targets_label', {
              defaultValue: 'Dozwolone ObjectType (cele relacji)',
            })}
          </div>
          <div className="flex flex-wrap gap-2">
            {objectTypes.length === 0 ? (
              <span className="text-[12px] text-muted-foreground">
                {t('attributes.relation_targets_empty', {
                  defaultValue: 'Brak ObjectType w tenancie — najpierw je utwórz.',
                })}
              </span>
            ) : null}
            {objectTypes.map((ot) => {
              const selected = value.targetObjectTypeIds.includes(ot.id);
              const labelText =
                typeof ot.label === 'object' && ot.label
                  ? (ot.label.pl ?? ot.label.en ?? ot.code)
                  : (ot.label ?? ot.code);
              return (
                <button
                  key={ot.id}
                  type="button"
                  disabled={disabled}
                  onClick={() => toggleTarget(ot.id)}
                  className={
                    selected
                      ? 'inline-flex items-center gap-1.5 rounded-full border border-accent-violet bg-accent-violet/10 px-3 py-1 text-[12px] font-medium text-accent-violet'
                      : 'inline-flex items-center gap-1.5 rounded-full border border-zinc-300 bg-white px-3 py-1 text-[12px] text-zinc-600 hover:border-zinc-400'
                  }
                >
                  {labelText}
                  <span className="text-[10px] text-muted-foreground">({ot.kind})</span>
                </button>
              );
            })}
          </div>
        </div>

        {/* Cardinality radio */}
        <div>
          <div className="mb-2 text-[12px] font-medium text-zinc-600">
            {t('attributes.relation_cardinality_label', { defaultValue: 'Kardynalność' })}
          </div>
          <div className="flex items-center gap-3">
            {(['one', 'many'] as const).map((opt) => (
              <label
                key={opt}
                className={
                  value.cardinality === opt
                    ? 'flex items-center gap-2 rounded-lg border border-accent-violet bg-accent-violet/5 px-3 py-2 text-[13px] text-accent-violet'
                    : 'flex items-center gap-2 rounded-lg border border-zinc-200 bg-white px-3 py-2 text-[13px] text-zinc-700'
                }
              >
                <input
                  type="radio"
                  name="relation-cardinality"
                  checked={value.cardinality === opt}
                  disabled={disabled}
                  onChange={() => setCardinality(opt)}
                />
                {opt === 'one'
                  ? t('attributes.relation_card_one', {
                      defaultValue: 'one — pojedyncza referencja',
                    })
                  : t('attributes.relation_card_many', { defaultValue: 'many — lista referencji' })}
              </label>
            ))}
          </div>
        </div>

        {/* Advanced toggle + per-link fields editor */}
        <div className="border-t border-zinc-100 pt-5">
          <label className="flex items-start gap-3">
            <input
              type="checkbox"
              checked={value.advanced}
              disabled={disabled}
              onChange={(e) => setAdvanced(e.target.checked)}
              className="mt-0.5"
            />
            <span>
              <span className="block text-[13px] font-medium text-zinc-700">
                {t('attributes.relation_advanced_label', {
                  defaultValue: 'Advanced — relacja z metadanymi na powiązaniu',
                })}
              </span>
              <span className="mt-0.5 block text-[12px] text-muted-foreground">
                {t('attributes.relation_advanced_desc', {
                  defaultValue:
                    'Każde powiązanie nosi własne pola (np. priorytet, rekomendowane). Wartości walidowane przeciw schemie zdefiniowanej poniżej (MOD-08).',
                })}
              </span>
            </span>
          </label>

          {value.advanced ? (
            <div className="mt-4 space-y-3">
              <div className="text-[12px] font-medium text-zinc-600">
                {t('attributes.relation_advanced_fields_label', {
                  defaultValue: 'Pola metadanych per powiązanie',
                })}
              </div>
              {value.advancedFields.length === 0 ? (
                <div className="rounded-lg border border-dashed border-zinc-300 px-4 py-3 text-[12px] text-muted-foreground">
                  {t('attributes.relation_advanced_empty', {
                    defaultValue: 'Brak pól — kliknij „+ Pole" by dodać definicję.',
                  })}
                </div>
              ) : null}
              {value.advancedFields.map((field, idx) => (
                <div
                  // Index-key is acceptable: rows are append/explicit-remove
                  // only and operate on `idx` directly. Code may be empty
                  // mid-edit so it cannot be the key.
                  // biome-ignore lint/suspicious/noArrayIndexKey: append-only editor with idx-based mutations
                  key={idx}
                  className="grid grid-cols-[1fr,140px,1fr,auto,auto] items-center gap-2 rounded-lg border border-zinc-200 bg-zinc-50 px-3 py-2"
                >
                  <Input
                    placeholder={t('attributes.relation_advanced_field_code_placeholder', {
                      defaultValue: 'kod (np. priority)',
                    })}
                    value={field.code}
                    disabled={disabled}
                    onChange={(e) => setField(idx, { code: e.target.value })}
                  />
                  <select
                    value={field.type}
                    disabled={disabled}
                    onChange={(e) =>
                      setField(idx, { type: e.target.value as RelationAdvancedField['type'] })
                    }
                    className="rounded-md border border-zinc-300 bg-white px-2 py-1.5 text-[13px]"
                  >
                    <option value="text">text</option>
                    <option value="number">number</option>
                    <option value="boolean">boolean</option>
                  </select>
                  <Input
                    placeholder={t('attributes.relation_advanced_field_label_placeholder', {
                      defaultValue: 'label PL',
                    })}
                    value={field.label.pl ?? ''}
                    disabled={disabled}
                    onChange={(e) =>
                      setField(idx, {
                        label: { ...field.label, pl: e.target.value },
                      })
                    }
                  />
                  <label className="flex items-center gap-1 text-[11px] text-muted-foreground">
                    <input
                      type="checkbox"
                      checked={field.required}
                      disabled={disabled}
                      onChange={(e) => setField(idx, { required: e.target.checked })}
                    />
                    required
                  </label>
                  <Button
                    variant="ghost"
                    size="sm"
                    type="button"
                    disabled={disabled}
                    onClick={() => removeField(idx)}
                    aria-label={t('attributes.relation_advanced_remove', {
                      defaultValue: 'Usuń pole',
                    })}
                  >
                    <Trash2 className="size-4" />
                  </Button>
                </div>
              ))}
              <Button
                variant="ghost"
                size="sm"
                type="button"
                disabled={disabled}
                onClick={addField}
              >
                <Plus className="size-4" />
                {t('attributes.relation_advanced_add', { defaultValue: '+ Pole' })}
              </Button>
            </div>
          ) : null}
        </div>

        {/* MODR-08 (#930) — preview fields editor. Drives the rich
            preview card layout inside the relation widget. Empty list =
            default (target object code + name). */}
        <div className="border-t border-zinc-100 pt-5">
          <PreviewFieldsEditor
            value={value.previewFields}
            disabled={disabled}
            onChange={(next) => onChange({ ...value, previewFields: next })}
          />
        </div>
      </CardContent>
    </Card>
  );
}

function PreviewFieldsEditor({
  value,
  disabled,
  onChange,
}: {
  value: string[];
  disabled: boolean;
  onChange: (next: string[]) => void;
}) {
  const { t } = useTranslation();
  const update = (idx: number, code: string) => {
    if (disabled) return;
    const next = [...value];
    next[idx] = code;
    onChange(next);
  };
  const add = () => {
    if (disabled) return;
    onChange([...value, '']);
  };
  const remove = (idx: number) => {
    if (disabled) return;
    onChange(value.filter((_, i) => i !== idx));
  };

  return (
    <div className="space-y-3">
      <div>
        <div className="text-[13px] font-medium text-zinc-700">
          {t('attributes.relation_preview_fields_label', {
            defaultValue: 'Pola podglądu w karcie powiązania',
          })}
        </div>
        <div className="mt-0.5 text-[12px] text-muted-foreground">
          {t('attributes.relation_preview_fields_desc', {
            defaultValue:
              'Kody atrybutów obiektu docelowego (np. „sku", „price") wyświetlanych w karcie. Pusta lista — pokazuje sam code + name.',
          })}
        </div>
      </div>
      {value.length === 0 ? (
        <div className="rounded-lg border border-dashed border-zinc-300 px-4 py-3 text-[12px] text-muted-foreground">
          {t('attributes.relation_preview_fields_empty', {
            defaultValue: 'Brak pól — klik „+ Pole" doda wiersz z kodem atrybutu targetu.',
          })}
        </div>
      ) : null}
      {value.map((code, idx) => (
        <div
          // biome-ignore lint/suspicious/noArrayIndexKey: append-only editor with idx-based mutations
          key={idx}
          className="grid grid-cols-[1fr,auto] items-center gap-2 rounded-lg border border-zinc-200 bg-zinc-50 px-3 py-2"
        >
          <Input
            placeholder={t('attributes.relation_preview_fields_placeholder', {
              defaultValue: 'kod atrybutu (np. price, sku)',
            })}
            value={code}
            disabled={disabled}
            onChange={(e) => update(idx, e.target.value)}
            className="font-mono"
          />
          <Button
            variant="ghost"
            size="sm"
            type="button"
            disabled={disabled}
            onClick={() => remove(idx)}
            aria-label={t('attributes.relation_preview_fields_remove', {
              defaultValue: 'Usuń pole podglądu',
            })}
          >
            <Trash2 className="size-4" />
          </Button>
        </div>
      ))}
      <Button variant="ghost" size="sm" type="button" disabled={disabled} onClick={add}>
        <Plus className="size-4" />
        {t('attributes.relation_preview_fields_add', { defaultValue: '+ Pole' })}
      </Button>
    </div>
  );
}
