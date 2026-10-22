import { describe, expect, it } from 'vitest';

import { attentionSummary, heartbeatFootnote, needsAttention, stateLabel, stateVariant } from './diagnostics';

describe('instance diagnostics', () => {
  it('keeps every check state distinguishable', () => {
    const labels = (['healthy', 'stale', 'missing', 'unavailable', 'disabled', 'not_configured'] as const).map(
      stateLabel,
    );
    expect(labels).toEqual(['Healthy', 'Stale', 'Missing', 'Unavailable', 'Disabled', 'Not configured']);
    expect(new Set(labels).size).toBe(6);
  });

  it('uses warning and danger tones for problems', () => {
    expect(stateVariant('healthy')).toBe('success');
    expect(stateVariant('stale')).toBe('warning');
    expect(stateVariant('missing')).toBe('danger');
    expect(stateVariant('disabled')).toBe('outline');
  });

  it('treats disabled services as not needing attention', () => {
    expect(needsAttention({ state: 'disabled' })).toBe(false);
    expect(needsAttention({ state: 'not_configured' })).toBe(true);
    expect(attentionSummary([{ state: 'healthy' }, { state: 'disabled' }])).toBe(
      'All services are healthy or not in use.',
    );
    expect(attentionSummary([{ state: 'stale' }, { state: 'missing' }])).toBe('2 services need attention.');
  });

  it('describes the heartbeat thresholds', () => {
    expect(heartbeatFootnote(1, 3)).toBe(
      'The scheduler and queue worker record a heartbeat every minute. A heartbeat older than 3 minutes is stale.',
    );
    expect(heartbeatFootnote(5, 15)).toContain('every 5 minutes');
  });
});
