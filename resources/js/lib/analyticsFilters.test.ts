import { describe, expect, it } from 'vitest';

import {
  filterDimensions,
  filterQuery,
  hasActiveFilters,
  initialFilterState,
  isRangeReady,
  type AnalyticsFilterOptions,
} from '@/lib/analyticsFilters';

const emptyOptions: AnalyticsFilterOptions = {
  links: [],
  domains: [],
  folders: [],
  tags: [],
  routingRules: [],
  routingVariants: [],
};

describe('initialFilterState', () => {
  it('defaults to a 30 day range with no filters', () => {
    const state = initialFilterState({});
    expect(state.range).toBe('30d');
    expect(state.link).toBe('');
    expect(hasActiveFilters(state)).toBe(false);
  });

  it('stringifies applied values', () => {
    const state = initialFilterState({ range: '7d', folder: 4 });
    expect(state.folder).toBe('4');
    expect(hasActiveFilters(state)).toBe(true);
  });
});

describe('filterQuery', () => {
  it('omits empty filters and custom bounds outside the custom range', () => {
    const state = initialFilterState({ range: '7d', from: '2026-01-01', tag: '3' });
    expect(filterQuery(state)).toEqual({ range: '7d', tag: '3' });
  });

  it('includes bounds for a custom range', () => {
    const state = initialFilterState({ range: 'custom', from: '2026-01-01', to: '2026-01-31' });
    expect(filterQuery(state)).toEqual({ range: 'custom', from: '2026-01-01', to: '2026-01-31' });
  });
});

describe('isRangeReady', () => {
  it('requires both bounds for a custom range', () => {
    expect(isRangeReady(initialFilterState({ range: 'custom', from: '2026-01-01' }))).toBe(false);
    expect(isRangeReady(initialFilterState({ range: 'custom', from: '2026-01-01', to: '2026-01-02' }))).toBe(true);
    expect(isRangeReady(initialFilterState({ range: '24h' }))).toBe(true);
  });
});

describe('filterDimensions', () => {
  it('keeps dimensions with options or an applied value', () => {
    const options = { ...emptyOptions, tags: [{ id: 2, name: 'Promo' }] };
    const keys = filterDimensions(options, { rule: 9 }).map((dimension) => dimension.key);
    expect(keys).toEqual(['metric', 'tag', 'rule']);
  });

  it('labels options by name or hostname', () => {
    const options = { ...emptyOptions, domains: [{ id: 1, hostname: 'go.example.com' }] };
    const domain = filterDimensions(options, {}).find((dimension) => dimension.key === 'domain');
    expect(domain?.options).toEqual([{ value: '1', label: 'go.example.com' }]);
  });
});
