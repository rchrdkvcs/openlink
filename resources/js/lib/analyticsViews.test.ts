import { describe, expect, it } from 'vitest';

import { unavailableMessage, viewMatches, viewPeriod } from '@/lib/analyticsViews';

describe('viewPeriod', () => {
  it('describes rolling presets as rolling', () => {
    expect(viewPeriod({ range: '7d' })).toBe('Last 7 days, rolling');
  });

  it('describes custom ranges as fixed dates', () => {
    expect(viewPeriod({ range: 'custom', from: '2026-03-01', to: '2026-03-09' })).toBe(
      '2026-03-01 to 2026-03-09, fixed dates',
    );
  });
});

describe('unavailableMessage', () => {
  it('returns nothing when every target exists', () => {
    expect(unavailableMessage([])).toBeNull();
  });

  it('names a single missing target', () => {
    expect(unavailableMessage(['folder'])).toContain('selected folder is no longer available');
  });

  it('lists several missing targets', () => {
    expect(unavailableMessage(['link', 'tag', 'variant'])).toContain('link, tag and variant are no longer available');
  });
});

describe('viewMatches', () => {
  it('matches the current query regardless of number types', () => {
    expect(viewMatches({ id: 1, name: 'A', filters: { range: '7d', link: 4 } }, { range: '7d', link: '4' })).toBe(true);
  });

  it('does not match when a filter differs', () => {
    expect(viewMatches({ id: 1, name: 'A', filters: { range: '7d' } }, { range: '7d', tag: '2' })).toBe(false);
  });
});
