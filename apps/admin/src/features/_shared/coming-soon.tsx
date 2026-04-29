import { Construction, ExternalLink } from 'lucide-react';
import { useTranslation } from 'react-i18next';

import { Button } from '@/components/ui/button';

interface ComingSoonProps {
  resource: string;
  epic: string;
  issue: number;
}

export function ComingSoon({ resource, epic, issue }: ComingSoonProps) {
  const { t } = useTranslation();
  const issueUrl = `https://github.com/malipie/PIM/issues/${issue}`;

  return (
    <div className="mx-auto flex max-w-xl flex-col items-center gap-6 py-16 text-center">
      <div className="flex size-16 items-center justify-center rounded-full bg-secondary">
        <Construction className="size-8 text-secondary-foreground" />
      </div>
      <div className="space-y-2">
        <h1 className="text-2xl font-semibold tracking-tight">{t(`nav.${resource}`)}</h1>
        <p className="text-sm text-muted-foreground">{t('coming_soon.body', { epic })}</p>
      </div>
      <Button variant="outline" asChild>
        <a href={issueUrl} target="_blank" rel="noreferrer">
          {t('coming_soon.track_issue', { issue })}
          <ExternalLink className="size-4" />
        </a>
      </Button>
    </div>
  );
}
