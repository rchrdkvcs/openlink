import { shortLabel, type LinkOption } from '@/lib/analytics';

export const RESULT_LIMIT = 50;
export const RECENT_LIMIT = 8;
export const TOP_LIMIT = 5;

export type LinkEntry = { key: string; link: LinkOption | null };
export type LinkGroup = { label: string | null; entries: LinkEntry[] };

export function entryValue(entry: LinkEntry): string {
  return entry.link ? String(entry.link.id) : '';
}

export function matchRank(link: LinkOption, term: string): number {
  const slug = link.slug.toLowerCase();
  const short = shortLabel(link).toLowerCase();
  const destination = (link.destination_url ?? '').toLowerCase();
  if (slug === term) return 0;
  if (slug.startsWith(term)) return 1;
  if (short.includes(term)) return 2;
  if (destination.includes(term)) return 3;
  return -1;
}

function toEntry(link: LinkOption): LinkEntry {
  return { key: String(link.id), link };
}

export function linkGroups(links: LinkOption[], topLinkIds: number[], search: string): LinkGroup[] {
  const term = search.trim().toLowerCase();

  if (term === '') {
    const byId = new Map(links.map((link) => [link.id, link]));
    const top = topLinkIds
      .map((id) => byId.get(id))
      .filter((link): link is LinkOption => Boolean(link))
      .slice(0, TOP_LIMIT);
    const topIds = new Set(top.map((link) => link.id));
    const recent = links.filter((link) => !topIds.has(link.id)).slice(0, RECENT_LIMIT);

    return [
      { label: null, entries: [{ key: 'all', link: null }] },
      { label: 'Top in this period', entries: top.map(toEntry) },
      { label: 'Recent', entries: recent.map(toEntry) },
    ].filter((group) => group.entries.length > 0);
  }

  const matches = links
    .map((link) => ({ link, rank: matchRank(link, term) }))
    .filter((match) => match.rank >= 0)
    .toSorted((a, b) => a.rank - b.rank || a.link.slug.localeCompare(b.link.slug))
    .slice(0, RESULT_LIMIT)
    .map(({ link }) => toEntry(link));

  return matches.length > 0 ? [{ label: null, entries: matches }] : [];
}

export function groupOffsets(groups: LinkGroup[]): number[] {
  let offset = 0;
  return groups.map((group) => {
    const start = offset;
    offset += group.entries.length;
    return start;
  });
}

export function listboxIndex(key: string, index: number, count: number, searching: boolean): number | undefined {
  if (key === 'ArrowDown' || key === 'ArrowUp') {
    if (count === 0) return index;
    return (index + (key === 'ArrowDown' ? 1 : -1) + count) % count;
  }
  if (searching) return undefined;
  if (key === 'Home') return 0;
  if (key === 'End') return Math.max(count - 1, 0);
  return undefined;
}
