import { describe, expect, it } from 'vitest';

import type { Report } from '@/lib/analytics';
import {
  breakdownSections,
  bucketDescription,
  outcomeBarRows,
  outcomeTotal,
  reportHasEvents,
} from '@/Pages/Analytics/reportSections';

function report(overrides: Partial<Report> = {}): Report {
  const row = (label: string) => ({ label, count: 1, visitors: 1, share: 100 });
  return {
    range: { preset: '30d', bucket: 'day' },
    summary: {
      visits: 0,
      scans: 0,
      visitors: 0,
      blocked: 0,
      bots: 0,
      active_links: 0,
      success_rate: null,
      visits_change: null,
      scans_change: null,
      visitors_change: null,
      blocked_change: null,
    },
    timeseries: [],
    breakdowns: {
      referrers: [],
      channels: [row('search')],
      countries: [row('fr')],
      languages: [],
      devices: [row('mobile')],
      browsers: [],
      os: [],
      utm_sources: [],
      utm_mediums: [],
      utm_campaigns: [],
    },
    outcomes: [
      { outcome: 'success', count: 3, share: 75 },
      { outcome: 'custom', count: 1, share: 25 },
    ],
    routing: [],
    top_links: [],
    top_qr_codes: [],
    ...overrides,
  };
}

describe('breakdownSections', () => {
  it('labels channels, countries and devices', () => {
    const sections = breakdownSections(report());
    expect(sections.sources[1].rows[0].display).toBe('Search engines');
    expect(sections.locations[0].rows[0]).toMatchObject({ display: 'France', prefix: '🇫🇷' });
    expect(sections.devices[0].rows[0].display).toBe('Mobile');
    expect(sections.campaigns.map((tab) => tab.key)).toEqual(['utm_campaigns', 'utm_sources', 'utm_mediums']);
  });
});

describe('outcomes', () => {
  it('labels known outcomes and totals attempts', () => {
    expect(outcomeBarRows(report()).map((row) => row.display)).toEqual(['Successful', 'custom']);
    expect(outcomeTotal(report())).toBe(4);
  });
});

describe('reportHasEvents', () => {
  it('counts bots and blocked attempts as events', () => {
    expect(reportHasEvents(report())).toBe(false);
    expect(reportHasEvents(report({ summary: { ...report().summary, bots: 1 } }))).toBe(true);
  });
});

describe('bucketDescription', () => {
  it('describes the bucket size', () => {
    expect(bucketDescription(report())).toBe('Daily');
    expect(bucketDescription(report({ range: { preset: '24h', bucket: 'hour' } }))).toBe('Hourly');
  });
});
