import { describe, expect, it } from 'vitest';

import { groupOffsets, linkGroups, listboxIndex, matchRank } from '@/Components/analytics/linkFilterGroups';
import type { LinkOption } from '@/lib/analytics';

function link(id: number, slug: string, destination: string | null = null): LinkOption {
  return { id, slug, hostname: 'go.test', short_url: null, destination_url: destination };
}

const links = [link(1, 'alpha'), link(2, 'beta', 'https://docs.example.com'), link(3, 'alphabet')];

describe('matchRank', () => {
  it('prefers exact and prefix slug matches', () => {
    expect(matchRank(links[0], 'alpha')).toBe(0);
    expect(matchRank(links[2], 'alpha')).toBe(1);
    expect(matchRank(links[1], 'go.test/be')).toBe(2);
    expect(matchRank(links[1], 'docs')).toBe(3);
    expect(matchRank(links[0], 'zzz')).toBe(-1);
  });
});

describe('linkGroups', () => {
  it('lists all, top and recent links without a search', () => {
    const groups = linkGroups(links, [3], '');
    expect(groups.map((group) => group.label)).toEqual([null, 'Top in this period', 'Recent']);
    expect(groups[1].entries.map((entry) => entry.key)).toEqual(['3']);
    expect(groups[2].entries.map((entry) => entry.key)).toEqual(['1', '2']);
    expect(groupOffsets(groups)).toEqual([0, 1, 2]);
  });

  it('ranks search matches', () => {
    const groups = linkGroups(links, [], ' ALPHA ');
    expect(groups[0].entries.map((entry) => entry.key)).toEqual(['1', '3']);
    expect(linkGroups(links, [], 'nothing')).toEqual([]);
  });
});

describe('listboxIndex', () => {
  it('wraps arrows and handles home and end only without a search', () => {
    expect(listboxIndex('ArrowDown', 2, 3, false)).toBe(0);
    expect(listboxIndex('ArrowUp', 0, 3, false)).toBe(2);
    expect(listboxIndex('End', 0, 3, false)).toBe(2);
    expect(listboxIndex('Home', 2, 3, false)).toBe(0);
    expect(listboxIndex('Home', 2, 3, true)).toBeUndefined();
    expect(listboxIndex('a', 0, 3, false)).toBeUndefined();
  });
});
