import { useOne } from '@refinedev/core';
import { ArrowLeft, Pencil } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Link, useParams } from 'react-router';

import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { resolveLabel } from '@/features/catalog/attributes/list';
import { cn } from '@/lib/utils';

import { ChannelCategoryMappingEditor } from './category-mapping-editor';
import { ChannelTreeEditor } from './channel-tree-editor';
import { ChannelMappingEditor } from './mapping-editor';

interface LocaleRef {
  id: string;
  code: string;
  label?: string;
}

interface ChannelDetail {
  id: string;
  code: string;
  label?: Record<string, string> | string | null;
  locales?: LocaleRef[];
  categoryTreeRootId?: string | null;
}

type TabKey = 'overview' | 'locales' | 'mapping' | 'channelTree' | 'categoryMapping' | 'preview';

const TABS: TabKey[] = [
  'overview',
  'locales',
  'mapping',
  'channelTree',
  'categoryMapping',
  'preview',
];

export function ChannelShowPage() {
  const { t, i18n } = useTranslation();
  const params = useParams<{ id: string }>();
  const id = params.id ?? '';
  const { result, query } = useOne<ChannelDetail>({
    resource: 'channels',
    id,
    queryOptions: { enabled: id.length > 0 },
  });
  const [activeTab, setActiveTab] = useState<TabKey>('overview');

  if (query.isLoading || !result) {
    return <p className="text-sm text-muted-foreground">{t('app.loading')}</p>;
  }

  const channel = result;
  const label = resolveLabel(channel.label, i18n.language);

  return (
    <div className="space-y-6">
      <div className="flex items-start justify-between gap-4">
        <div className="space-y-1">
          <Button asChild variant="ghost" size="sm" className="-ml-3">
            <Link to="/settings/channels">
              <ArrowLeft className="size-4" />
              {t('channels.back')}
            </Link>
          </Button>
          <h1 className="text-2xl font-semibold tracking-tight">{label}</h1>
          <p className="font-mono text-xs text-muted-foreground">{channel.code}</p>
        </div>
        <Button asChild>
          <Link to={`/settings/channels/${channel.id}/edit`}>
            <Pencil className="size-4" />
            {t('channels.list.actions.edit')}
          </Link>
        </Button>
      </div>

      <div className="border-b">
        <nav className="-mb-px flex flex-wrap gap-1" aria-label={t('channels.show.tabs_aria')}>
          {TABS.map((tab) => (
            <button
              key={tab}
              type="button"
              onClick={() => setActiveTab(tab)}
              className={cn(
                'border-b-2 px-3 py-2 text-sm font-medium transition-colors',
                activeTab === tab
                  ? 'border-primary text-foreground'
                  : 'border-transparent text-muted-foreground hover:text-foreground',
              )}
              aria-pressed={activeTab === tab}
            >
              {t(`channels.show.tabs.${tab}`)}
            </button>
          ))}
        </nav>
      </div>

      {activeTab === 'mapping' ? (
        <ChannelMappingEditor channelId={channel.id} />
      ) : activeTab === 'channelTree' ? (
        <ChannelTreeEditor channelId={channel.id} />
      ) : activeTab === 'categoryMapping' ? (
        <ChannelCategoryMappingEditor channelId={channel.id} />
      ) : (
        <Card>
          <CardContent className="pt-6">
            {activeTab === 'overview' ? (
              <OverviewTab channel={channel} />
            ) : activeTab === 'locales' ? (
              <ListTab
                values={(channel.locales ?? []).map((l) => l.code)}
                emptyKey="channels.show.no_locales"
              />
            ) : (
              <PlaceholderTab tab="preview" />
            )}
          </CardContent>
        </Card>
      )}
    </div>
  );
}

function OverviewTab({ channel }: { channel: ChannelDetail }) {
  const { t } = useTranslation();
  return (
    <dl className="grid gap-3 sm:grid-cols-2">
      <Row label={t('channels.fields.code')}>
        <span className="font-mono text-xs">{channel.code}</span>
      </Row>
      <Row label={t('channels.fields.locales_count')}>{channel.locales?.length ?? 0}</Row>
      {channel.categoryTreeRootId ? (
        <Row label={t('channels.fields.category_root')}>
          <span className="font-mono text-xs">{channel.categoryTreeRootId}</span>
        </Row>
      ) : null}
    </dl>
  );
}

function ListTab({ values, emptyKey }: { values: string[]; emptyKey: string }) {
  const { t } = useTranslation();
  if (values.length === 0) {
    return <p className="text-sm text-muted-foreground">{t(emptyKey)}</p>;
  }
  return (
    <ul className="flex flex-wrap gap-2">
      {values.map((value) => (
        <li
          key={value}
          className="rounded bg-muted px-2 py-1 font-mono text-xs uppercase tracking-wide"
        >
          {value}
        </li>
      ))}
    </ul>
  );
}

function PlaceholderTab({ tab }: { tab: 'preview' }) {
  const { t } = useTranslation();
  return <p className="text-sm text-muted-foreground">{t(`channels.show.placeholder.${tab}`)}</p>;
}

function Row({ label, children }: { label: string; children: React.ReactNode }) {
  return (
    <div className="space-y-1">
      <dt className="text-xs uppercase tracking-wide text-muted-foreground">{label}</dt>
      <dd className="text-sm font-medium">{children}</dd>
    </div>
  );
}
