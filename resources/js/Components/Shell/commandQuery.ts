import { Search, Sparkles } from '@lucide/vue';

import type { Command, CommandActions } from '@/Components/Shell/commands';
import { displayUrl, isLikelyUrl, normalizeUrl } from '@/lib/links';

export type IndexedCommand = { command: Command; index: number };

export function queryCommands(
  query: string,
  canEdit: boolean,
  available: Command[],
  actions: CommandActions,
): Command[] {
  const term = query.trim().toLowerCase();
  const url = normalizeUrl(query);
  const urlLike = isLikelyUrl(url);
  const result: Command[] = [];

  if (canEdit && urlLike) {
    result.push({
      id: 'shorten',
      group: 'Create',
      label: `Shorten ${displayUrl(url)}`,
      hint: 'Creates and copies the short link',
      icon: Sparkles,
      run: () => actions.shorten(url),
    });
  }

  if (!term) return [...result, ...available];

  if (canEdit && !urlLike) {
    result.push({
      id: 'search-links',
      group: 'Search',
      label: `Search links for “${query.trim()}”`,
      icon: Search,
      run: () => actions.visit('links.index', { search: query.trim() }),
    });
  }

  return [
    ...result,
    ...available.filter((command) =>
      `${command.label} ${command.group} ${command.keywords ?? ''}`.toLowerCase().includes(term),
    ),
  ];
}

export function groupCommands(commands: Command[]): [string, IndexedCommand[]][] {
  const groups = new Map<string, IndexedCommand[]>();
  commands.forEach((command, index) => {
    const bucket = groups.get(command.group) ?? [];
    bucket.push({ command, index });
    groups.set(command.group, bucket);
  });
  return [...groups.entries()];
}

export function cycleIndex(current: number, delta: number, count: number): number {
  return count ? (current + delta + count) % count : current;
}
