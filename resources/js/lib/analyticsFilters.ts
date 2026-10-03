import { Folder as FolderIcon, GitBranch, Globe, QrCode, Shuffle, Tag as TagIcon, Waypoints } from '@lucide/vue';

import type { LinkOption, RangePreset } from '@/lib/analytics';
import type { SelectOption } from '@/lib/controls';
import type { Domain, Folder, Tag } from '@/types/payloads';

export const FILTER_KEYS = ['link', 'domain', 'folder', 'tag', 'qr', 'rule', 'variant', 'metric'] as const;

export type FilterKey = (typeof FILTER_KEYS)[number];

export type AppliedFilters = Record<string, string | number>;

export type AnalyticsFilterState = { range: RangePreset; from: string; to: string } & Record<FilterKey, string>;

export const RANGES: { value: RangePreset; label: string }[] = [
  { value: '24h', label: '24h' },
  { value: '7d', label: '7d' },
  { value: '14d', label: '14d' },
  { value: '30d', label: '30d' },
  { value: '90d', label: '90d' },
  { value: '12m', label: '12m' },
  { value: 'custom', label: 'Custom' },
];

export function initialFilterState(applied: AppliedFilters): AnalyticsFilterState {
  const state = {
    range: String(applied.range ?? '30d') as RangePreset,
    from: String(applied.from ?? ''),
    to: String(applied.to ?? ''),
  } as AnalyticsFilterState;
  for (const key of FILTER_KEYS) state[key] = String(applied[key] ?? '');
  return state;
}

export function filterQuery(state: AnalyticsFilterState): Record<string, string> {
  const params: Record<string, string> = { range: state.range };
  if (state.range === 'custom') {
    if (state.from) params.from = state.from;
    if (state.to) params.to = state.to;
  }
  for (const key of FILTER_KEYS) {
    if (state[key]) params[key] = state[key];
  }
  return params;
}

export function hasActiveFilters(state: AnalyticsFilterState): boolean {
  return FILTER_KEYS.some((key) => state[key] !== '');
}

export function isRangeReady(state: AnalyticsFilterState): boolean {
  return state.range !== 'custom' || Boolean(state.from && state.to);
}

export type NamedOption = { id: number; name: string };

export type AnalyticsFilterOptions = {
  links: LinkOption[];
  qrCodes?: NamedOption[];
  domains: Pick<Domain, 'id' | 'hostname'>[];
  folders: Folder[];
  tags: Tag[];
  routingRules: NamedOption[];
  routingVariants: NamedOption[];
};

export type DimensionKey = Exclude<FilterKey, 'link'>;

export type Dimension = { key: DimensionKey; label: string; icon: unknown; options: SelectOption[] };

function toOptions<T extends { id: number }>(
  items: T[] | undefined,
  label: (item: T) => string | undefined,
): SelectOption[] {
  return (items ?? []).map((item) => ({ value: String(item.id), label: label(item) ?? String(item.id) }));
}

const METRIC_OPTIONS: SelectOption[] = [
  { value: 'visit', label: 'Visits only' },
  { value: 'scan', label: 'Scans only' },
];

export function filterDimensions(options: AnalyticsFilterOptions, applied: AppliedFilters): Dimension[] {
  const dimensions: Dimension[] = [
    { key: 'metric', label: 'Traffic', icon: Waypoints, options: METRIC_OPTIONS },
    { key: 'domain', label: 'Domain', icon: Globe, options: toOptions(options.domains, (domain) => domain.hostname) },
    { key: 'folder', label: 'Folder', icon: FolderIcon, options: toOptions(options.folders, (folder) => folder.name) },
    { key: 'tag', label: 'Tag', icon: TagIcon, options: toOptions(options.tags, (tag) => tag.name) },
    { key: 'qr', label: 'QR code', icon: QrCode, options: toOptions(options.qrCodes, (qr) => qr.name) },
    {
      key: 'rule',
      label: 'Routing rule',
      icon: GitBranch,
      options: toOptions(options.routingRules, (rule) => rule.name),
    },
    {
      key: 'variant',
      label: 'Variant',
      icon: Shuffle,
      options: toOptions(options.routingVariants, (variant) => variant.name),
    },
  ];
  return dimensions.filter((dimension) => dimension.options.length > 0 || Boolean(applied[dimension.key]));
}
