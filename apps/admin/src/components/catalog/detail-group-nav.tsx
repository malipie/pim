import { useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';

import { jsonFetch } from '@/lib/http';

interface AttributeGroup {
  id: string;
  code: string;
  label: { pl?: string; en?: string };
  icon: string | null;
  color: string | null;
  is_system_group: boolean;
  position: number;
  attributes: Array<{ id: string; code: string }>;
}

/**
 * UI-02.16 (#306) — left-side sticky nav for the product detail page.
 *
 * Reads `GET /api/products/{id}/effective-attribute-groups` (UI-02.5)
 * and renders the groups as anchor links into `#section-{id}` targets
 * within the main content. Per `Project Plan/UI/epik-02-produkty.md`
 * §5.1 left rail.
 */
export function DetailGroupNav({
  productId,
  activeGroupId,
}: {
  productId: string;
  activeGroupId?: string | null;
}) {
  const { t, i18n } = useTranslation();
  const [groups, setGroups] = useState<AttributeGroup[]>([]);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    let cancelled = false;
    jsonFetch<{ groups: AttributeGroup[] }>(`/api/products/${productId}/effective-attribute-groups`)
      .then((body) => {
        if (!cancelled) setGroups(body.groups);
      })
      .catch((err: unknown) => {
        if (cancelled) return;
        setError(err instanceof Error ? err.message : 'unknown');
      });
    return () => {
      cancelled = true;
    };
  }, [productId]);

  if (error !== null) {
    return <p className="text-xs text-rose-600">{error}</p>;
  }

  if (groups.length === 0) {
    return (
      <p className="text-xs text-muted-foreground">
        {t('products.detail.nav_empty', { defaultValue: 'No attribute groups configured.' })}
      </p>
    );
  }

  const lang = i18n.language === 'pl' ? 'pl' : 'en';

  return (
    <nav
      className="flex flex-col gap-1 text-sm"
      aria-label={t('products.detail.nav_label', { defaultValue: 'Attribute groups' })}
    >
      {groups.map((group) => {
        const label = group.label[lang] ?? group.code;
        const isActive = activeGroupId === group.id;
        return (
          <a
            key={group.id}
            href={`#section-${group.id}`}
            className={`flex items-center gap-2 rounded px-2 py-1.5 text-sm transition-colors ${
              isActive
                ? 'bg-secondary font-medium text-secondary-foreground'
                : 'text-muted-foreground hover:bg-accent hover:text-accent-foreground'
            }`}
          >
            <span className="flex-1 truncate">{label}</span>
            <span className="text-[10px] tabular-nums text-muted-foreground">
              {group.attributes.length}
            </span>
          </a>
        );
      })}
    </nav>
  );
}
