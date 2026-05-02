/**
 * MOCK DATA — backend endpoints listed in
 * Project Plan/UI/Wdrozenie_grafiki/dashboard-do-oprogramowania.md.
 *
 * Every export is hard-coded for the handoff mock. Replace with real API
 * calls (TanStack Query via Refine data provider) once the backend ships
 * the corresponding endpoints. Do not import this from production paths
 * other than features/dashboard/.
 */

export type KpiKey =
  | 'products'
  | 'attributes'
  | 'families'
  | 'categories'
  | 'enabled_share'
  | 'completeness_avg'
  | 'last_sync_minutes'
  | 'open_alerts';

export interface KpiTile {
  key: KpiKey;
  value: number;
  delta: number;
  hint: string;
  /** Optional unit suffix shown next to the value (e.g. "%", "min"). */
  unit?: string;
}

/**
 * Catalogue of available KPI tiles. The user picks 4 of these in the KPI
 * settings sheet; selection persists in localStorage (MOCK — no backend).
 */
export const KPI_TILES: KpiTile[] = [
  { key: 'products', value: 12_847, delta: 184, hint: 'last 30 days' },
  { key: 'attributes', value: 312, delta: 12, hint: 'last 30 days' },
  { key: 'families', value: 47, delta: 2, hint: 'last 30 days' },
  { key: 'categories', value: 184, delta: 8, hint: 'last 30 days' },
  { key: 'enabled_share', value: 92, delta: 3, hint: 'enabled / total', unit: '%' },
  { key: 'completeness_avg', value: 87, delta: 4, hint: 'all channels', unit: '%' },
  { key: 'last_sync_minutes', value: 8, delta: -2, hint: 'minutes ago', unit: 'min' },
  { key: 'open_alerts', value: 5, delta: -1, hint: 'last 24h' },
];

/** Default KPI selection used when the user has not customised the dashboard. */
export const KPI_DEFAULT_SELECTION: KpiKey[] = ['products', 'attributes', 'families', 'categories'];

export const KPI_MAX_SELECTION = 4;

export interface ActivityPoint {
  day: number;
  added: number;
  modified: number;
}

/** Range of days the activity chart can render. 30d is the default. */
export type ActivityRange = '7d' | '30d' | '90d';

const buildActivity = (length: number): ActivityPoint[] =>
  Array.from({ length }, (_, i) => {
    const weekend = i % 7 === 5 || i % 7 === 6;
    return {
      day: i + 1,
      added: weekend ? 4 + (i % 3) : 18 + ((i * 7) % 14),
      modified: weekend ? 9 + (i % 4) : 32 + ((i * 11) % 22),
    };
  });

export const ACTIVITY_7D: ActivityPoint[] = buildActivity(7);
export const ACTIVITY_30D: ActivityPoint[] = buildActivity(30);
export const ACTIVITY_90D: ActivityPoint[] = buildActivity(90);

export const ACTIVITY_BY_RANGE: Record<ActivityRange, ActivityPoint[]> = {
  '7d': ACTIVITY_7D,
  '30d': ACTIVITY_30D,
  '90d': ACTIVITY_90D,
};

export interface TopEditedProduct {
  sku: string;
  name: string;
  brand: string;
  family: string;
  edits: number;
  completeness: number;
  channels: number;
}

export const TOP_EDITED: TopEditedProduct[] = [
  {
    sku: 'KLM-7430',
    name: 'Profil aluminiowy LED 2m',
    brand: 'Klimas',
    family: 'Profile',
    edits: 47,
    completeness: 96,
    channels: 4,
  },
  {
    sku: 'KLM-7431',
    name: 'Zasilacz 60W IP67',
    brand: 'Klimas',
    family: 'Zasilacze',
    edits: 38,
    completeness: 92,
    channels: 3,
  },
  {
    sku: 'KLM-2210',
    name: 'Taśma LED COB 480 led/m',
    brand: 'Klimas',
    family: 'Taśmy LED',
    edits: 33,
    completeness: 88,
    channels: 4,
  },
  {
    sku: 'KLM-9981',
    name: 'Kontroler RGB+CCT',
    brand: 'Klimas',
    family: 'Sterowniki',
    edits: 29,
    completeness: 81,
    channels: 2,
  },
  {
    sku: 'KLM-1142',
    name: 'Profil narożny 45° 1m',
    brand: 'Klimas',
    family: 'Profile',
    edits: 26,
    completeness: 94,
    channels: 4,
  },
  {
    sku: 'KLM-6601',
    name: 'Klosz mleczny do P-line',
    brand: 'Klimas',
    family: 'Akcesoria',
    edits: 22,
    completeness: 78,
    channels: 3,
  },
  {
    sku: 'KLM-5503',
    name: 'Złączka prosta 2-pin',
    brand: 'Klimas',
    family: 'Akcesoria',
    edits: 19,
    completeness: 72,
    channels: 2,
  },
  {
    sku: 'KLM-3320',
    name: 'Listwa montażowa stalowa',
    brand: 'Klimas',
    family: 'Mocowania',
    edits: 17,
    completeness: 90,
    channels: 4,
  },
  {
    sku: 'KLM-8801',
    name: 'Ramka rozetowa kwadrat',
    brand: 'Klimas',
    family: 'Ramki',
    edits: 14,
    completeness: 64,
    channels: 1,
  },
  {
    sku: 'KLM-4407',
    name: 'Klips montażowy 10mm',
    brand: 'Klimas',
    family: 'Mocowania',
    edits: 12,
    completeness: 86,
    channels: 3,
  },
];

export type SyncStatus = 'ok' | 'warn' | 'err';

export interface IntegrationSync {
  id: 'shopify' | 'baselinker' | 'google_shopping' | 'comarch_xl';
  label: string;
  status: SyncStatus;
  lastSync: string;
  pushed: number;
  failed: number;
}

export const SYNCS: IntegrationSync[] = [
  { id: 'shopify', label: 'Shopify', status: 'ok', lastSync: '2 min temu', pushed: 487, failed: 0 },
  {
    id: 'baselinker',
    label: 'BaseLinker',
    status: 'warn',
    lastSync: '17 min temu',
    pushed: 1_204,
    failed: 14,
  },
  {
    id: 'google_shopping',
    label: 'Google Shopping',
    status: 'err',
    lastSync: '4 godz. temu',
    pushed: 0,
    failed: 312,
  },
  {
    id: 'comarch_xl',
    label: 'Comarch ERP XL',
    status: 'ok',
    lastSync: '8 min temu',
    pushed: 92,
    failed: 0,
  },
];

export interface CompletenessSlice {
  key: 'overall' | 'shopify' | 'baselinker' | 'google_shopping' | 'comarch_xl';
  label: string;
  percent: number;
}

export const COMPLETENESS: CompletenessSlice[] = [
  { key: 'overall', label: 'Ogólna', percent: 87 },
  { key: 'shopify', label: 'Shopify', percent: 94 },
  { key: 'baselinker', label: 'BaseLinker', percent: 81 },
  { key: 'google_shopping', label: 'Google Shopping', percent: 76 },
  { key: 'comarch_xl', label: 'Comarch ERP XL', percent: 99 },
];

export type AgentStatus = 'approved' | 'rejected' | 'pending';

export interface AgentLogEntry {
  id: string;
  who: string;
  when: string;
  title: string;
  hint: string;
  tools: string[];
  status: AgentStatus;
}

export const AGENT_ACTIVITY: AgentLogEntry[] = [
  {
    id: 'a1',
    who: 'Marcin Lipiec',
    when: '5 min temu',
    title: 'Zatwierdzono uzupełnienie atrybutów EN dla 24 produktów',
    hint: 'KLM-7430..KLM-7454',
    tools: ['fill_attribute', 'translate'],
    status: 'approved',
  },
  {
    id: 'a2',
    who: 'agent.sonnet',
    when: '12 min temu',
    title: 'Zaproponowano migrację typu atrybutu „packaging"',
    hint: 'text → select (8 wartości)',
    tools: ['migrate_attribute_type'],
    status: 'pending',
  },
  {
    id: 'a3',
    who: 'Anna Wiśniewska',
    when: '36 min temu',
    title: 'Odrzucono auto-generację opisów dla 3 SKU',
    hint: 'powód: niska jakość',
    tools: ['generate_description'],
    status: 'rejected',
  },
  {
    id: 'a4',
    who: 'agent.sonnet',
    when: '1 godz. temu',
    title: 'Wzbogacono kody HS dla 47 produktów',
    hint: 'taryfa UE 2026',
    tools: ['fill_attribute'],
    status: 'approved',
  },
  {
    id: 'a5',
    who: 'agent.sonnet',
    when: '3 godz. temu',
    title: 'Zsynchronizowano cennik z Comarch ERP XL',
    hint: '92 produkty zaktualizowane',
    tools: ['sync_pricing'],
    status: 'approved',
  },
  {
    id: 'a6',
    who: 'agent.opus',
    when: '5 godz. temu',
    title: 'Dodano grupę atrybutów „certyfikaty"',
    hint: '5 nowych atrybutów, 12 produktów',
    tools: ['create_attribute_group'],
    status: 'pending',
  },
];

export type AlertSeverity = 'err' | 'warn' | 'info';

export interface AlertItem {
  id: string;
  severity: AlertSeverity;
  title: string;
  source: string;
  when: string;
  cta: string;
}

export const ALERTS: AlertItem[] = [
  {
    id: 'al1',
    severity: 'err',
    title: 'Synchronizacja Google Shopping nie powiodła się',
    source: 'integracje',
    when: '4 godz. temu',
    cta: 'Zobacz log',
  },
  {
    id: 'al2',
    severity: 'warn',
    title: '14 produktów BaseLinker odrzuconych przez walidację',
    source: 'integracje',
    when: '17 min temu',
    cta: 'Pokaż produkty',
  },
  {
    id: 'al3',
    severity: 'warn',
    title: '23 produkty bez obrazu głównego',
    source: 'completeness',
    when: '1 godz. temu',
    cta: 'Filtruj listę',
  },
  {
    id: 'al4',
    severity: 'info',
    title: 'Nowa wersja schematu atrybutów (rev 47)',
    source: 'modelowanie',
    when: '2 godz. temu',
    cta: 'Zobacz diff',
  },
  {
    id: 'al5',
    severity: 'info',
    title: 'Backup pgBackRest zakończony pomyślnie',
    source: 'system',
    when: '6 godz. temu',
    cta: 'Szczegóły',
  },
];

export interface ChannelSlice {
  label: string;
  count: number;
}

export const CHANNEL_DISTRIBUTION: ChannelSlice[] = [
  { label: '5 kanałów', count: 1_842 },
  { label: '3–4 kanały', count: 5_113 },
  { label: '1–2 kanały', count: 4_287 },
  { label: 'tylko PIM', count: 1_605 },
];
