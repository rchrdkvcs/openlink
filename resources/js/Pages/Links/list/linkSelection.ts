import type { BulkLinkAction } from '@/lib/linkActions';
import type { LinkFilters } from '@/Pages/Links/types';

export const MAX_BULK_LINKS = 100;

export type PageSelection = 'none' | 'some' | 'all';

export type BulkActionPreview = {
  title: string;
  message: string;
  confirmLabel: string;
  destructive: boolean;
  needsConfirm: boolean;
};

export function selectionScope(workspaceId: number | null | undefined, filters: LinkFilters, page: number): string {
  return JSON.stringify([workspaceId ?? null, filters.search, filters.status, filters.tag, filters.folder, page]);
}

export function toggleSelected(selected: number[], id: number): number[] {
  return selected.includes(id) ? selected.filter((entry) => entry !== id) : [...selected, id];
}

export function keepVisible(selected: number[], visibleIds: number[]): number[] {
  const visible = new Set(visibleIds);
  const kept = selected.filter((id) => visible.has(id));

  return kept.length === selected.length ? selected : kept;
}

export function pageSelection(selected: number[], visibleIds: number[]): PageSelection {
  const chosen = new Set(selected);
  const count = visibleIds.filter((id) => chosen.has(id)).length;
  if (count === 0) return 'none';

  return count === visibleIds.length ? 'all' : 'some';
}

export function togglePage(selected: number[], visibleIds: number[]): number[] {
  return pageSelection(selected, visibleIds) === 'all' ? [] : visibleIds.slice(0, MAX_BULK_LINKS);
}

export function countLabel(count: number): string {
  return `${count.toLocaleString()} link${count === 1 ? '' : 's'}`;
}

export function selectionStatus(count: number): string {
  return count > 0 ? `${countLabel(count)} selected` : '';
}

function tagList(tags: string[]): string {
  return tags.map((tag) => `#${tag}`).join(', ');
}

export function previewBulkAction(
  action: BulkLinkAction,
  count: number,
  target: { folderName?: string | null; tags?: string[] } = {},
): BulkActionPreview {
  const links = countLabel(count);
  const tags = tagList(target.tags ?? []);
  const base = { destructive: false, needsConfirm: true };

  switch (action) {
    case 'move':
      return {
        ...base,
        title: target.folderName ? `Move ${links} to ${target.folderName}?` : `Move ${links} out of folders?`,
        message: 'Folders organise links and do not change who can access them.',
        confirmLabel: `Move ${links}`,
      };
    case 'add_tags':
      return {
        ...base,
        needsConfirm: false,
        title: `Add ${tags} to ${links}`,
        message: 'Existing tags on these links are kept.',
        confirmLabel: 'Add tags',
      };
    case 'remove_tags':
      return {
        ...base,
        needsConfirm: false,
        title: `Remove ${tags} from ${links}`,
        message: 'Other tags on these links are kept.',
        confirmLabel: 'Remove tags',
      };
    case 'enable':
      return {
        ...base,
        title: `Enable ${links}?`,
        message:
          'Enabling does not override lifecycle rules: scheduled, expired and archived links still won’t resolve.',
        confirmLabel: `Enable ${links}`,
      };
    case 'disable':
      return {
        title: `Disable ${links}?`,
        message: `${count === 1 ? 'This link stops' : 'These links stop'} resolving immediately. Slugs, analytics and visit counts are kept, and you can enable them again later.`,
        confirmLabel: `Disable ${links}`,
        destructive: true,
        needsConfirm: true,
      };
    case 'archive':
      return {
        title: `Archive ${links}?`,
        message: `Archived links leave your main views and stop resolving. They keep their slugs, analytics and visit counts.`,
        confirmLabel: `Archive ${links}`,
        destructive: true,
        needsConfirm: true,
      };
  }
}

export function bulkResultTitle(action: BulkLinkAction, count: number, folderName?: string | null): string {
  const links = countLabel(count);
  const outcomes: Record<BulkLinkAction, string> = {
    move: folderName ? `Moved ${links} to ${folderName}` : `Moved ${links} out of folders`,
    add_tags: `Tagged ${links}`,
    remove_tags: `Removed tags from ${links}`,
    enable: `Enabled ${links}`,
    disable: `Disabled ${links}`,
    archive: `Archived ${links}`,
  };

  return outcomes[action];
}
