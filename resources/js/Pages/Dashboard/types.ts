import type { ReportRange, Summary, TimePoint, TopLink } from '@/lib/analytics';

export type RecentLink = {
  id: number;
  slug: string;
  short_url: string;
  destination_url: string;
  visits: number;
  scans: number;
  status: string;
  created_at?: string | null;
};

export type LinkCounts = { total: number; active: number };

export type DashboardAnalytics = {
  range: { preset: string; bucket: ReportRange['bucket'] };
  summary: Summary;
  timeseries: TimePoint[];
  top_links: TopLink[];
};
