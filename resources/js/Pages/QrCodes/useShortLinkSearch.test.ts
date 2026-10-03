import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { effectScope, nextTick, ref } from 'vue';

import type { ShortLinkOption } from './types';
import { createLatestOnly, keepSelected, SHORT_LINK_SEARCH_DELAY, useShortLinkSearch } from './useShortLinkSearch';

const link = (id: number): ShortLinkOption => ({
  id,
  short_url: `https://ol.test/${id}`,
  destination_url: `https://example.com/${id}`,
});

describe('keepSelected', () => {
  it('keeps the selected Short Link visible when results omit it', () => {
    expect(keepSelected([link(2)], [link(1), link(2)], '1')).toEqual([link(1), link(2)]);
  });

  it('returns results untouched when the selection is present or empty', () => {
    expect(keepSelected([link(1)], [link(1)], 1)).toEqual([link(1)]);
    expect(keepSelected([link(2)], [link(1)], '')).toEqual([link(2)]);
  });
});

describe('createLatestOnly', () => {
  it('only accepts the most recent request', () => {
    const requests = createLatestOnly();
    const first = requests.next();
    const second = requests.next();

    expect(requests.isLatest(first)).toBe(false);
    expect(requests.isLatest(second)).toBe(true);
  });
});

describe('useShortLinkSearch', () => {
  beforeEach(() => vi.useFakeTimers());
  afterEach(() => vi.useRealTimers());

  it('ignores responses that resolve after a newer search', async () => {
    const pending: Record<string, (links: ShortLinkOption[]) => void> = {};
    const fetcher = (term: string) => new Promise<ShortLinkOption[]>((resolve) => (pending[term] = resolve));
    const scope = effectScope();
    const search = scope.run(() => useShortLinkSearch([link(1)], ref(''), fetcher))!;

    search.search.value = 'a';
    await nextTick();
    await vi.advanceTimersByTimeAsync(SHORT_LINK_SEARCH_DELAY);
    search.search.value = 'ab';
    await nextTick();
    await vi.advanceTimersByTimeAsync(SHORT_LINK_SEARCH_DELAY);

    pending.ab([link(3)]);
    await vi.runAllTimersAsync();
    pending.a([link(2)]);
    await vi.runAllTimersAsync();

    expect(search.links.value).toEqual([link(3)]);
    scope.stop();
  });
});
