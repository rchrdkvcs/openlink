import { describe, expect, it } from 'vitest';

import type { LinkFilters } from '@/Pages/Links/types';

import { clearedSearch, emptyCopy, hasSearchFilters, linksView } from './linksView';

const folders = [{ id: 4, name: 'Campaigns' }];

function filters(overrides: Partial<LinkFilters> = {}): LinkFilters {
  return { search: '', status: '', tag: '', folder: '', ...overrides };
}

describe('linksView', () => {
  it('describes the whole workspace', () => {
    expect(linksView(filters(), folders, 1)).toMatchObject({
      scope: 'all',
      title: 'Links',
      eyebrow: null,
      description: '1 link across this workspace',
    });
  });

  it('describes a folder, unfiled links and the archive', () => {
    expect(linksView(filters({ folder: '4' }), folders, 2)).toMatchObject({
      scope: 'folder',
      folder: folders[0],
      title: 'Campaigns',
      eyebrow: 'Folder',
      description: '2 links',
    });
    expect(linksView(filters({ folder: 'unfiled' }), folders, 0)).toMatchObject({
      scope: 'unfiled',
      title: 'Unfiled',
      eyebrow: 'Folder',
    });
    expect(linksView(filters({ status: 'archived', folder: '4' }), folders, 3)).toMatchObject({
      scope: 'archive',
      title: 'Archived',
      eyebrow: 'Library',
      description: '3 links · Archived links don’t resolve but keep their slug and analytics.',
    });
  });

  it('falls back to the workspace when the folder is unknown', () => {
    expect(linksView(filters({ folder: '99' }), folders, 0)).toMatchObject({ scope: 'all', folder: null });
  });
});

describe('search filters', () => {
  it('ignores the archived status inside the archive', () => {
    expect(hasSearchFilters(filters({ status: 'archived' }), 'archive')).toBe(false);
    expect(hasSearchFilters(filters({ status: 'expired' }), 'all')).toBe(true);
    expect(hasSearchFilters(filters({ tag: 'promo' }), 'archive')).toBe(true);
  });

  it('clears search while staying in the archive', () => {
    expect(clearedSearch('archive')).toEqual({ search: '', tag: '', status: 'archived' });
    expect(clearedSearch('folder')).toEqual({ search: '', tag: '', status: '' });
  });

  it('picks empty state copy', () => {
    expect(emptyCopy('archive', true).title).toBe('No links match');
    expect(emptyCopy('archive', false).title).toBe('Nothing archived');
    expect(emptyCopy('folder', false).title).toBe('This folder is empty');
    expect(emptyCopy('unfiled', false).title).toBe('No links yet');
  });
});
