import { Boxes, FolderTree, Image as ImageIcon, Layers, Package, Tag } from 'lucide-react';
import type { ComponentType, SVGProps } from 'react';

import { cn } from '@/lib/utils';

/**
 * VIEW-01 (#372) — shared rendering of an ObjectType icon badge.
 *
 * Pixel-perfect mockup uses inline emoji (Tailwind `text-[26px]`); we
 * keep the same visual language but accept either a lucide icon name
 * (the modeling list / detail uses Boxes/FolderTree/etc.) or a string
 * the BE stored as the user-set icon. The container takes the type's
 * tint color at 18% opacity behind the icon.
 */
const KIND_ICON_MAP: Record<string, ComponentType<SVGProps<SVGSVGElement>>> = {
  product: Boxes,
  category: FolderTree,
  asset: ImageIcon,
  brand: Tag,
  custom: Package,
};

interface ObjectTypeIconProps {
  kind?: string | null;
  /** Stored icon string from BE — when present and is a string, renders directly (could be emoji or lucide token). */
  icon?: string | null;
  color?: string | null;
  size?: 'sm' | 'md' | 'lg';
  className?: string;
}

const SIZE = {
  sm: { box: 'size-10 rounded-2xl', text: 'text-[18px]' },
  md: { box: 'size-12 rounded-2xl', text: 'text-[22px]' },
  lg: { box: 'size-14 rounded-2xl', text: 'text-[26px]' },
};

export function ObjectTypeIcon({ kind, icon, color, size = 'md', className }: ObjectTypeIconProps) {
  const accent = color ?? defaultAccent(kind ?? null);
  const sizes = SIZE[size];

  const lucideIcon =
    icon && KIND_ICON_MAP[icon] ? KIND_ICON_MAP[icon] : KIND_ICON_MAP[kind ?? 'custom'];
  const renderEmoji = icon && !KIND_ICON_MAP[icon] && !/^[A-Z]/.test(icon);

  return (
    <span
      aria-hidden
      className={cn('grid place-items-center shrink-0', sizes.box, sizes.text, className)}
      style={{ backgroundColor: `${accent}1f`, color: accent }}
    >
      {renderEmoji
        ? icon
        : (() => {
            const Icon = lucideIcon ?? Layers;
            return <Icon className="size-1/2" />;
          })()}
    </span>
  );
}

export function defaultAccent(kind: string | null): string {
  switch (kind) {
    case 'product':
      return '#3b82f6';
    case 'category':
      return '#a855f7';
    case 'asset':
      return '#ec4899';
    case 'brand':
      return '#f59e0b';
    default:
      return '#71717a';
  }
}
