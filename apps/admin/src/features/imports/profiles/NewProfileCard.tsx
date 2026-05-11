import { Plus } from 'lucide-react';
import { useTranslation } from 'react-i18next';

interface NewProfileCardProps {
  onClick: () => void;
}

export function NewProfileCard({ onClick }: NewProfileCardProps) {
  const { t } = useTranslation();
  return (
    <button
      type="button"
      onClick={onClick}
      className="rounded-2xl border-2 border-dashed border-zinc-200 hover:border-zinc-400 hover:bg-zinc-50/50 p-4 min-h-[220px] flex flex-col items-center justify-center text-zinc-500 hover:text-zinc-900 transition group"
    >
      <div className="h-12 w-12 rounded-2xl bg-zinc-100 group-hover:bg-zinc-900 group-hover:text-white grid place-items-center transition">
        <Plus className="h-6 w-6" aria-hidden="true" />
      </div>
      <div className="mt-3 text-[14px] font-medium">
        {t('imports.profiles.new_profile_card.title')}
      </div>
      <div className="text-[12px] text-zinc-500 mt-1 max-w-[220px] text-center">
        {t('imports.profiles.new_profile_card.subtitle')}
      </div>
    </button>
  );
}
