import { describe, expect, it } from 'vitest';

import {
  availabilityReasons,
  newPreviewContext,
  previewErrors,
  previewRequest,
  randomSeed,
  ruleStatus,
  skippedReason,
} from './preview';
import { conditional } from './testRouting';

describe('routing preview', () => {
  it('starts from the given local time and timezone with a stable seed', () => {
    const context = newPreviewContext('Europe/Paris', new Date(2026, 5, 1, 9, 5));

    expect(context).toMatchObject({ occurred_at: '2026-06-01T09:05', timezone: 'Europe/Paris', seed: 'visitor-1' });
    expect(context.country).toBe('');
  });

  it('builds a saved request for read-only users and a draft request otherwise', () => {
    const context = newPreviewContext('UTC');
    const rule = conditional('France');

    expect(previewRequest(context, null)).toEqual({ source: 'saved', context });
    expect(previewRequest(context, { destination_url: 'example.com/landing', routing_rules: [rule] })).toEqual({
      source: 'draft',
      destination_url: 'https://example.com/landing',
      routing_rules: [rule],
      context,
    });
  });

  it('generates seeds from the random source', () => {
    expect(randomSeed(() => 0.123456)).toBe('visitor-123456');
  });

  it('labels each rule status', () => {
    expect(ruleStatus('matched')).toEqual({ label: 'Matched', tone: 'success' });
    expect(ruleStatus('not_reached').label).toBe('Not checked');
    expect(ruleStatus('skipped').tone).toBe('warning');
  });

  it('explains why a rule was skipped', () => {
    const base = {
      index: 0,
      id: null,
      name: 'Rule',
      match_mode: 'all' as const,
      status: 'skipped' as const,
      destination_url: null,
      conditions: [],
      slot: null,
      total_weight: 0,
      variants: [],
    };

    expect(skippedReason({ ...base, type: 'split_test' })).toContain('no variant');
    expect(skippedReason({ ...base, type: 'conditional' })).toBe('No destination URL.');
  });

  it('lists why the public link would not resolve', () => {
    const available = {
      status: 'active' as const,
      domain: 'go.example.com',
      domain_usable: true,
      password_protected: false,
      available: true,
    };

    expect(availabilityReasons(available)).toEqual([]);
    expect(availabilityReasons({ ...available, status: 'disabled', domain_usable: false, available: false })).toEqual([
      'The link is disabled.',
      'go.example.com is not active yet.',
    ]);
  });

  it('flattens validation errors', () => {
    expect(
      previewErrors({ errors: { 'routing_rules.0.destination_url': ['Needs a URL.'], 'context.timezone': ['Bad.'] } }),
    ).toEqual(['Needs a URL.', 'Bad.']);
    expect(previewErrors(null)).toEqual([]);
  });
});
