import { describe, expect, it } from 'vitest';

import type { ConversionReport } from '@/lib/analytics';
import { conversionRate, evidenceNote, hasRoutedConversions, pendingNote, windowNote } from '@/lib/conversions';

function report(overrides: Partial<ConversionReport> = {}): ConversionReport {
  return {
    enabled: true,
    window_hours: 24,
    minimum_attributed_visits: 30,
    totals: { attributed_visits: 0, conversions: 0, pending: 0, rate: null },
    links: [],
    routing: [],
    ...overrides,
  };
}

describe('conversion labels', () => {
  it('shows no rate without attributed visits', () => {
    expect(conversionRate({ attributed_visits: 0, conversions: 0, pending: 0, rate: null })).toBe('—');
    expect(conversionRate({ attributed_visits: 46, conversions: 11, pending: 2, rate: 23.9 })).toBe('23.9%');
  });

  it('names the attribution window in hours', () => {
    expect(windowNote(report())).toBe('Conversions reported within 24 hours of an attributed visit');
    expect(windowNote(report({ window_hours: 1 }))).toBe('Conversions reported within 1 hour of an attributed visit');
  });

  it('warns about thin evidence only below the minimum', () => {
    expect(evidenceNote(report(), { insufficient_evidence: true })).toBe('Fewer than 30 attributed visits');
    expect(evidenceNote(report(), { insufficient_evidence: false })).toBeNull();
  });

  it('counts tokens still inside their window', () => {
    expect(pendingNote(0)).toBeNull();
    expect(pendingNote(1)).toBe('1 still in window');
    expect(pendingNote(1200)).toBe('1,200 still in window');
  });

  it('shows routing only when a rule or variant served attributed visits', () => {
    const row = {
      short_link_id: 1,
      slug: 'pricing',
      routing_rule_id: null,
      routing_variant_id: null,
      rule_name: 'Default destination',
      variant_name: null,
      attributed_visits: 3,
      conversions: 1,
      pending: 0,
      rate: 33.3,
      insufficient_evidence: true,
    };

    expect(hasRoutedConversions(report({ routing: [row] }))).toBe(false);
    expect(hasRoutedConversions(report({ routing: [row, { ...row, routing_rule_id: 4, rule_name: 'Test' }] }))).toBe(
      true,
    );
  });
});
