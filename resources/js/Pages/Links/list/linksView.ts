import type { LinkFilters } from '@/Pages/Links/types';
import type { Folder } from '@/types/payloads';

export type LinksScope = 'all' | 'archive' | 'unfiled' | 'folder';

export type LinksView = {
  scope: LinksScope;
  folder: Folder | null;
  title: string;
  eyebrow: string | null;
  description: string;
};

export type EmptyCopy = { title: string; description: string };

function scopeOf(filters: LinkFilters, folder: Folder | null): LinksScope {
  if (filters.status === 'archived') return 'archive';
  if (filters.folder === 'unfiled') return 'unfiled';
  return folder ? 'folder' : 'all';
}

export function linksView(filters: LinkFilters, folders: Folder[], total: number): LinksView {
  const folder =
    filters.folder && filters.folder !== 'unfiled'
      ? (folders.find((entry) => String(entry.id) === filters.folder) ?? null)
      : null;
  const scope = scopeOf(filters, folder);
  const count = `${total.toLocaleString()} link${total === 1 ? '' : 's'}`;

  const titles: Record<LinksScope, string> = {
    archive: 'Archived',
    unfiled: 'Unfiled',
    folder: folder?.name ?? 'Links',
    all: 'Links',
  };
  const descriptions: Record<LinksScope, string> = {
    archive: `${count} · Archived links don’t resolve but keep their slug and analytics.`,
    unfiled: count,
    folder: count,
    all: `${count} across this workspace`,
  };

  return {
    scope,
    folder,
    title: titles[scope],
    eyebrow: scope === 'archive' ? 'Library' : scope === 'all' ? null : 'Folder',
    description: descriptions[scope],
  };
}

export function emptyCopy(scope: LinksScope, searching: boolean): EmptyCopy {
  if (searching) return { title: 'No links match', description: 'Try another search, status or tag.' };
  if (scope === 'archive') return { title: 'Nothing archived', description: 'Links you archive appear here.' };
  if (scope === 'folder') {
    return {
      title: 'This folder is empty',
      description: 'Shorten a URL above to add it here, or drag links onto the folder in the sidebar.',
    };
  }

  return {
    title: 'No links yet',
    description: 'Paste a long URL above — your short link is created and copied in one step.',
  };
}

export function hasSearchFilters(filters: LinkFilters, scope: LinksScope): boolean {
  return Boolean(filters.search || filters.tag || (filters.status && scope !== 'archive'));
}

export function clearedSearch(scope: LinksScope): Pick<LinkFilters, 'search' | 'tag' | 'status'> {
  return { search: '', tag: '', status: scope === 'archive' ? 'archived' : '' };
}
