import type { AppliedFilters, FilterKey } from '@/lib/analyticsFilters';

export type SavedView = { id: number; name: string; filters: AppliedFilters };

export type ActiveView = { id: number; name: string };

const RANGE_LABELS: Record<string, string> = {
  '24h': 'Last 24 hours',
  '7d': 'Last 7 days',
  '14d': 'Last 14 days',
  '30d': 'Last 30 days',
  '90d': 'Last 90 days',
  '12m': 'Last 12 months',
};

const FILTER_LABELS: Record<FilterKey, string> = {
  link: 'link',
  qr: 'QR code',
  domain: 'domain',
  folder: 'folder',
  tag: 'tag',
  rule: 'routing rule',
  variant: 'variant',
  metric: 'traffic',
};

export function viewPeriod(filters: AppliedFilters): string {
  if (filters.range === 'custom') return `${filters.from} to ${filters.to}, fixed dates`;
  return `${RANGE_LABELS[String(filters.range)] ?? RANGE_LABELS['30d']}, rolling`;
}

export function unavailableMessage(keys: string[]): string | null {
  if (keys.length === 0) return null;
  const names = keys.map((key) => FILTER_LABELS[key as FilterKey] ?? key);
  const list = names.length === 1 ? names[0] : `${names.slice(0, -1).join(', ')} and ${names[names.length - 1]}`;
  const verb = names.length === 1 ? 'is' : 'are';
  return `The selected ${list} ${verb} no longer available in this workspace, so no data is shown. Remove the filter or choose another one.`;
}

export function viewMatches(view: SavedView, current: Record<string, string>): boolean {
  const saved = Object.fromEntries(Object.entries(view.filters).map(([key, value]) => [key, String(value)]));
  const keys = new Set([...Object.keys(saved), ...Object.keys(current)]);
  return [...keys].every((key) => (saved[key] ?? '') === (current[key] ?? ''));
}
