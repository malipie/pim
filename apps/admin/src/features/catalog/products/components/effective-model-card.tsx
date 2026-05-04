import { Lock } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { Link } from 'react-router';

import { Card } from '@/components/ui/card';

import type { GroupMeta } from './types';

export interface EffectiveModelCardProps {
  groups: GroupMeta[];
  objectTypeName?: string | null;
  categoryName?: string | null;
}

/**
 * VIEW-07 (#420) — sidebar "Efektywny model" card mirrored from
 * `detail-view.jsx` lines 325–346. Lists each group with the source
 * that contributes its attributes (ObjectType / Category / system).
 * Lock icon flags system-owned + locked groups.
 */
export function EffectiveModelCard({
  groups,
  objectTypeName,
  categoryName,
}: EffectiveModelCardProps) {
  const { t, i18n } = useTranslation();
  const lang = i18n.language === 'pl' ? 'pl' : 'en';

  return (
    <Card className="rounded-2xl border-line bg-surface p-5 soft-shadow">
      <div className="mb-2.5 text-[12px] font-medium text-zinc-500">
        {t('products.detail.sidebar.effective_model', { defaultValue: 'Efektywny model' })}
      </div>
      <p className="mb-2 text-[12px] text-zinc-500">
        {t('products.detail.sidebar.effective_model.intro', {
          defaultValue: 'Atrybuty obiektu pochodzą z:',
        })}
      </p>
      <ul className="space-y-1.5 text-[12px]">
        {groups.map((group) => {
          const label = group.label[lang] ?? group.code;
          const source = resolveSource(group, objectTypeName, categoryName);
          const isSystem = group.code === 'audit' || group.code === 'identification';
          return (
            <li key={group.id} className="flex items-center gap-2 rounded-lg px-2 py-1">
              {isSystem ? <Lock className="size-3 text-zinc-300" aria-hidden /> : null}
              <span className="truncate font-medium text-zinc-800">{label}</span>
              <span className="ml-auto truncate text-[10.5px] text-zinc-500">{source}</span>
            </li>
          );
        })}
      </ul>
      <Link
        to="/modeling/object-types"
        className="mt-2.5 block text-[11.5px] font-medium text-violet-700 hover:underline"
      >
        {t('products.detail.sidebar.effective_model.see_in_modeling', {
          defaultValue: 'Zobacz w Modelowanie →',
        })}
      </Link>
    </Card>
  );
}

function resolveSource(
  group: GroupMeta,
  objectTypeName?: string | null,
  categoryName?: string | null,
): string {
  if (group.code === 'audit') return 'system';
  if (categoryName !== null && categoryName !== undefined && group.code.startsWith('category_')) {
    return `Kat: ${categoryName}`;
  }
  if (objectTypeName !== null && objectTypeName !== undefined && objectTypeName !== '') {
    return `ObjectType: ${objectTypeName}`;
  }
  return 'ObjectType: Product';
}
