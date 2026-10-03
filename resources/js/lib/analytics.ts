export type RangePreset = '24h' | '7d' | '14d' | '30d' | '90d' | '12m' | 'custom';

export type ReportRange = {
  preset: RangePreset;
  from?: string;
  to?: string;
  bucket: 'hour' | 'day' | 'month';
};

export type Summary = {
  visits: number;
  scans: number;
  visitors: number;
  blocked: number;
  bots: number;
  active_links: number;
  success_rate: number | null;
  visits_change: number | null;
  scans_change: number | null;
  visitors_change: number | null;
  blocked_change: number | null;
};

export type TimePoint = {
  bucket: string;
  visits: number;
  scans: number;
  visitors: number;
  blocked: number;
};

export type BreakdownRow = {
  label: string;
  count: number;
  visitors: number;
  share: number;
};

export type OutcomeRow = {
  outcome: string;
  count: number;
  share: number;
};

export type TopLink = {
  id: number;
  slug: string;
  short_url: string | null;
  destination_url: string | null;
  visits: number;
  scans: number;
  visitors: number;
  total: number;
};

export type TopQrCode = {
  id: number;
  name: string;
  link_slug: string | null;
  scans: number;
  visitors: number;
};

export type RoutingPerformanceRow = {
  routing_rule_id: number | null;
  routing_variant_id: number | null;
  rule_name: string;
  variant_name: string | null;
  visits: number;
  scans: number;
  visitors: number;
  total: number;
};

export type BarListRow = {
  label: string;
  display?: string;
  prefix?: string;
  count: number;
  share: number;
};

export type BreakdownTab = {
  key: string;
  label: string;
  rows: BarListRow[];
  empty?: string;
};

export type LinkOption = {
  id: number;
  slug: string;
  hostname: string | null;
  short_url: string | null;
  destination_url: string | null;
};

export function shortLabel(link: Pick<LinkOption, 'slug' | 'hostname'>): string {
  return link.hostname ? `${link.hostname}/${link.slug}` : `/${link.slug}`;
}

export type Report = {
  range: ReportRange;
  summary: Summary;
  timeseries: TimePoint[];
  breakdowns: {
    referrers: BreakdownRow[];
    channels: BreakdownRow[];
    countries: BreakdownRow[];
    languages: BreakdownRow[];
    devices: BreakdownRow[];
    browsers: BreakdownRow[];
    os: BreakdownRow[];
    utm_sources: BreakdownRow[];
    utm_mediums: BreakdownRow[];
    utm_campaigns: BreakdownRow[];
  };
  outcomes: OutcomeRow[];
  routing: RoutingPerformanceRow[];
  top_links: TopLink[];
  top_qr_codes: TopQrCode[];
};

export const SERIES_COLORS = {
  visits: '#707bdb',
  scans: '#c47d20',
} as const;

export * from '@/lib/analyticsFormat';
