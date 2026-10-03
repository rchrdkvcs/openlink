import {
  CHANNEL_LABELS,
  DEVICE_LABELS,
  OUTCOME_LABELS,
  countryFlag,
  countryName,
  languageName,
  type BarListRow,
  type BreakdownTab,
  type Report,
} from '@/lib/analytics';

export type BreakdownSections = {
  sources: BreakdownTab[];
  locations: BreakdownTab[];
  devices: BreakdownTab[];
  campaigns: BreakdownTab[];
};

export function breakdownSections({ breakdowns }: Report): BreakdownSections {
  return {
    sources: [
      {
        key: 'referrers',
        label: 'Referrers',
        rows: breakdowns.referrers,
        empty: 'No referrers yet. Direct visits carry none.',
      },
      {
        key: 'channels',
        label: 'Channels',
        rows: breakdowns.channels.map((row) => ({ ...row, display: CHANNEL_LABELS[row.label] ?? row.label })),
      },
    ],
    locations: [
      {
        key: 'countries',
        label: 'Countries',
        rows: breakdowns.countries.map((row) => ({
          ...row,
          display: countryName(row.label),
          prefix: countryFlag(row.label),
        })),
        empty: 'No country data yet. Detection needs a geo header from your proxy or CDN.',
      },
      {
        key: 'languages',
        label: 'Languages',
        rows: breakdowns.languages.map((row) => ({ ...row, display: languageName(row.label) })),
      },
    ],
    devices: [
      {
        key: 'devices',
        label: 'Devices',
        rows: breakdowns.devices.map((row) => ({ ...row, display: DEVICE_LABELS[row.label] ?? row.label })),
      },
      { key: 'browsers', label: 'Browsers', rows: breakdowns.browsers },
      { key: 'os', label: 'OS', rows: breakdowns.os },
    ],
    campaigns: [
      {
        key: 'utm_campaigns',
        label: 'Campaigns',
        rows: breakdowns.utm_campaigns,
        empty: 'No UTM campaigns yet. Add ?utm_campaign=… to shared links.',
      },
      { key: 'utm_sources', label: 'Sources', rows: breakdowns.utm_sources, empty: 'No utm_source values yet.' },
      { key: 'utm_mediums', label: 'Mediums', rows: breakdowns.utm_mediums, empty: 'No utm_medium values yet.' },
    ],
  };
}

export function outcomeBarRows(report: Report): BarListRow[] {
  return report.outcomes.map((row) => ({
    label: row.outcome,
    display: OUTCOME_LABELS[row.outcome] ?? row.outcome,
    count: row.count,
    share: row.share,
  }));
}

export function outcomeTotal(report: Report): number {
  return report.outcomes.reduce((sum, row) => sum + row.count, 0);
}

export function reportHasEvents(report: Report): boolean {
  const { visits, scans, blocked, bots } = report.summary;
  return visits + scans + blocked + bots > 0;
}

export function bucketDescription(report: Report): string {
  const { bucket } = report.range;
  return bucket === 'hour' ? 'Hourly' : bucket === 'month' ? 'Monthly' : 'Daily';
}
