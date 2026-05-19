import { cn } from '@/lib/utils';

interface UserAvatarProps {
  initial: string;
  size?: 'sm' | 'md';
  seed?: string;
}

/**
 * Deterministic circular avatar built from the user's first letter and
 * a hashed colour. Avoids an image-upload dependency until the profile
 * editor (RBAC-P5-003 #693) lands — looks consistent across the
 * Settings UI and matches the mockup (`Zrodla/.../settings/users.jsx`)
 * even before per-user uploaded avatars exist.
 */
export function UserAvatar({ initial, size = 'md', seed }: UserAvatarProps) {
  const palette = [
    'bg-violet-100 text-violet-700',
    'bg-cyan-100 text-cyan-700',
    'bg-amber-100 text-amber-700',
    'bg-rose-100 text-rose-700',
    'bg-emerald-100 text-emerald-700',
    'bg-blue-100 text-blue-700',
    'bg-fuchsia-100 text-fuchsia-700',
  ] as const;

  const source = seed ?? initial;
  let hash = 0;
  for (const char of source) {
    hash = (hash + char.charCodeAt(0)) % palette.length;
  }
  const tone = palette[hash];

  const dimensions = size === 'sm' ? 'size-8 text-[12px]' : 'size-9 text-[13px]';

  return (
    <span
      className={cn('inline-grid place-items-center rounded-full font-semibold', dimensions, tone)}
      aria-hidden="true"
    >
      {initial}
    </span>
  );
}
