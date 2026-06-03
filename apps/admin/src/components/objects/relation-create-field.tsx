/*
 * #1102 — picker for `type='relation'` attributes during object create.
 *
 * The detail-page editor (`RelationInlineEditor`) needs an existing object
 * id to read `/api/objects/{id}/relations`. In create flow there is no id
 * yet, so we collect target ids into the form's dirtyFields and the
 * parent (`UniversalCreatePage`) writes them via PUT
 * `/api/objects/{newId}/relations/{attributeCode}` after the main POST
 * succeeds.
 *
 * Candidates come from the same poly-kind `GET /api/objects?sku=` endpoint
 * the detail-page ObjectPickerDialog uses; we filter by the attribute's
 * `relation_target_object_type_ids` client-side. itemsPerPage=200 is
 * generous for MVP (~50k SKU max per the planning doc) and gets replaced
 * with a BE `objectTypeIds[]=` filter when scale demands it.
 */
import { useQuery } from '@tanstack/react-query';
import { useTranslation } from 'react-i18next';

import { Combobox, type ComboboxOption } from '@/components/ui/combobox';
import { MultiSelect, type MultiSelectOption } from '@/components/ui/multi-select';
import type { AttributeMeta } from '@/features/catalog/products/components/types';
import { jsonFetch } from '@/lib/http';

interface CandidateRow {
  id: string;
  code?: string;
  objectType?: { id?: string } | null;
}

interface ObjectsListResponse {
  member?: CandidateRow[];
  'hydra:member'?: CandidateRow[];
}

export interface RelationCreateFieldProps {
  attribute: AttributeMeta;
  value: unknown;
  onChange: (next: unknown) => void;
}

export function RelationCreateField({
  attribute,
  value,
  onChange,
}: RelationCreateFieldProps): React.ReactElement {
  const { t } = useTranslation();
  const allowedTypeIds = attribute.relation_target_object_type_ids ?? [];
  const cardinality = attribute.relation_cardinality ?? 'many';

  const candidatesQuery = useQuery<ObjectsListResponse>({
    queryKey: ['relation-candidates', allowedTypeIds.join(',')],
    queryFn: () =>
      jsonFetch<ObjectsListResponse>('/api/objects?itemsPerPage=200', {
        accept: 'application/ld+json',
      }),
    staleTime: 30_000,
  });

  const candidates = candidatesQuery.data?.member ?? candidatesQuery.data?.['hydra:member'] ?? [];
  const filtered =
    allowedTypeIds.length === 0
      ? candidates
      : candidates.filter((row) => {
          const otId = row.objectType?.id;
          return typeof otId === 'string' && allowedTypeIds.includes(otId);
        });

  if (candidatesQuery.isLoading) {
    return (
      <p className="text-xs text-muted-foreground">
        {t('relation_create_field.loading', { defaultValue: 'Ładowanie kandydatów…' })}
      </p>
    );
  }

  if (cardinality === 'one') {
    const options: ComboboxOption[] = filtered.map((row) => ({
      value: row.id,
      label: row.code ?? row.id,
    }));
    const currentValue = typeof value === 'string' && value !== '' ? value : null;
    return (
      <Combobox
        options={options}
        value={currentValue}
        onChange={(next) => onChange(next)}
        placeholder={t('relation_create_field.placeholder', { defaultValue: 'Wybierz…' })}
        className="rounded-xl text-[13.5px]"
      />
    );
  }

  const options: MultiSelectOption[] = filtered.map((row) => ({
    value: row.id,
    label: row.code ?? row.id,
  }));
  const currentValues = readMultiValue(value);
  return (
    <MultiSelect
      options={options}
      value={currentValues}
      onChange={(next) => onChange(next)}
      placeholder={t('relation_create_field.placeholder', { defaultValue: 'Wybierz…' })}
      className="rounded-xl text-[13.5px]"
    />
  );
}

function readMultiValue(value: unknown): string[] {
  if (!Array.isArray(value)) return [];
  return value.filter((v): v is string => typeof v === 'string' && v !== '');
}
