import { Construction } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { Link } from 'react-router';

import { Button } from '@/components/ui/button';

interface ComingSoonPlaceholderProps {
  titleKey: string;
  descriptionKey?: string;
  backTo?: string;
}

export function ComingSoonPlaceholder({
  titleKey,
  descriptionKey = 'settings.placeholder.description',
  backTo = '/dashboard',
}: ComingSoonPlaceholderProps) {
  const { t } = useTranslation();

  return (
    <div className="flex min-h-[420px] flex-col items-center justify-center gap-4 rounded-lg border border-dashed bg-background p-8 text-center">
      <div className="flex size-12 items-center justify-center rounded-full bg-accent-violet/10 text-accent-violet">
        <Construction className="size-6" />
      </div>
      <div className="space-y-1">
        <h2 className="display text-xl font-semibold tracking-tight">{t(titleKey)}</h2>
        <p className="max-w-md text-sm text-muted-foreground">
          {t('settings.placeholder.title')} — {t(descriptionKey)}
        </p>
      </div>
      <Button asChild variant="outline" size="sm">
        <Link to={backTo}>{t('settings.placeholder.back')}</Link>
      </Button>
    </div>
  );
}
