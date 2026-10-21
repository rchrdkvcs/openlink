import { describe, expect, it } from 'vitest';

import {
  type DestinationHealth,
  healthByUrl,
  healthLabel,
  healthNote,
  healthVariant,
  linkDestinations,
  linkHealthSummary,
} from '@/lib/destinationHealth';
import type { RoutingRuleDraft } from '@/types/shortLinks';

const health = (overrides: Partial<DestinationHealth> = {}): DestinationHealth => ({
  url: 'https://example.com/a',
  status: 'reachable',
  http_status: 200,
  detail: null,
  consecutive_failures: 0,
  checked_at: '2026-10-21T10:00:00Z',
  failing_since: null,
  incident_opened_at: null,
  recovered_at: null,
  has_incident: false,
  pending: false,
  stale: false,
  ...overrides,
});

const rule = (overrides: Partial<RoutingRuleDraft>): RoutingRuleDraft => ({
  name: 'Rule',
  type: 'conditional',
  is_enabled: true,
  match_mode: 'all',
  conditions: [],
  destination_url: '',
  variants: [],
  ...overrides,
});

const link = {
  destination_url: 'https://example.com/a',
  fallback_url: 'https://example.com/fallback',
  routing_rules: [
    rule({ name: 'iOS', destination_url: 'https://example.com/ios' }),
    rule({ name: 'Off', is_enabled: false, destination_url: 'https://example.com/off' }),
    rule({
      name: 'Split',
      type: 'split_test',
      variants: [
        { name: 'B', is_enabled: true, destination_url: 'https://example.com/b', weight: 50 },
        { name: 'C', is_enabled: false, destination_url: 'https://example.com/c', weight: 50 },
      ],
    }),
  ],
};

describe('destination health', () => {
  it('lists monitored destinations from enabled rules and variants only', () => {
    expect(linkDestinations(link)).toEqual([
      { url: 'https://example.com/a', role: 'destination', label: 'Default destination' },
      { url: 'https://example.com/fallback', role: 'fallback', label: 'Fallback URL' },
      { url: 'https://example.com/ios', role: 'routing_rule', label: 'iOS' },
      { url: 'https://example.com/b', role: 'routing_variant', label: 'Split · B' },
    ]);
  });

  it('labels results without calling inconclusive results failures', () => {
    expect(healthLabel(undefined)).toBe('Not checked');
    expect(healthLabel(health({ status: 'http_failure', http_status: 404 }))).toBe('HTTP 404');
    expect(healthLabel(health({ status: 'inconclusive', http_status: 403 }))).toBe('Inconclusive');
    expect(healthVariant(health({ status: 'inconclusive' }))).toBe('outline');
    expect(healthVariant(health({ status: 'timeout', consecutive_failures: 1 }))).toBe('warning');
    expect(healthVariant(health({ status: 'timeout', has_incident: true }))).toBe('danger');
    expect(healthVariant(health())).toBe('success');
  });

  it('summarises a link from its current destinations', () => {
    const map = (entries: DestinationHealth[]) => healthByUrl(entries);
    const all = ['a', 'fallback', 'ios', 'b'].map((path) => health({ url: `https://example.com/${path}` }));

    expect(linkHealthSummary(link, map([]))).toBe('unchecked');
    expect(linkHealthSummary(link, map(all))).toBe('healthy');
    expect(linkHealthSummary(link, map([...all.slice(1), health({ status: 'dns_failure' })]))).toBe('confirming');
    expect(
      linkHealthSummary(link, map([health({ url: 'https://example.com/b', status: 'timeout', has_incident: true })])),
    ).toBe('incident');
    expect(linkHealthSummary(link, map([health({ url: 'https://example.com/off', has_incident: true })]))).toBe(
      'unchecked',
    );
  });

  it('explains confirmation, recovery, pending and stale results', () => {
    const relative = (value: string) => `at ${value}`;

    expect(healthNote(undefined, relative)).toBe('Never checked');
    expect(healthNote(health({ pending: true }), relative)).toBe('Checking…');
    expect(healthNote(health({ status: 'timeout', consecutive_failures: 1 }), relative)).toBe(
      'Failed once, confirming',
    );
    expect(healthNote(health({ status: 'timeout', has_incident: true, failing_since: 'x' }), relative)).toBe(
      'Failing since at x',
    );
    expect(healthNote(health({ recovered_at: 'y' }), relative)).toBe('Recovered at y');
    expect(healthNote(health({ stale: true, checked_at: 'z' }), relative)).toBe('Checked at z · may be outdated');
  });
});
