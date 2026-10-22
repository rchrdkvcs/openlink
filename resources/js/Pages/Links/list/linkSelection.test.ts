import { describe, expect, it } from 'vitest';

import type { LinkFilters } from '@/Pages/Links/types';

import {
  bulkResultTitle,
  keepVisible,
  MAX_BULK_LINKS,
  pageSelection,
  previewBulkAction,
  selectionScope,
  selectionStatus,
  togglePage,
  toggleSelected,
} from './linkSelection';

function filters(overrides: Partial<LinkFilters> = {}): LinkFilters {
  return { search: '', status: '', tag: '', folder: '', ...overrides };
}

describe('link selection', () => {
  it('toggles individual links by id', () => {
    expect(toggleSelected([], 3)).toEqual([3]);
    expect(toggleSelected([3, 5], 3)).toEqual([5]);
  });

  it('selects the whole page, then clears it', () => {
    expect(togglePage([], [1, 2, 3])).toEqual([1, 2, 3]);
    expect(togglePage([2], [1, 2, 3])).toEqual([1, 2, 3]);
    expect(togglePage([1, 2, 3], [1, 2, 3])).toEqual([]);
  });

  it('never selects more links than one batch allows', () => {
    const ids = Array.from({ length: MAX_BULK_LINKS + 5 }, (_, index) => index + 1);

    expect(togglePage([], ids)).toHaveLength(MAX_BULK_LINKS);
  });

  it('reports whether none, some or all visible links are selected', () => {
    expect(pageSelection([], [1, 2])).toBe('none');
    expect(pageSelection([2], [1, 2])).toBe('some');
    expect(pageSelection([1, 2], [1, 2])).toBe('all');
    expect(pageSelection([9], [1, 2])).toBe('none');
  });

  it('keeps the selection stable while its links stay on the page', () => {
    const selected = [1, 2];

    expect(keepVisible(selected, [2, 1, 3])).toBe(selected);
    expect(keepVisible(selected, [2, 3])).toEqual([2]);
  });

  it('announces the selected count and stays empty without a selection', () => {
    expect(selectionStatus(0)).toBe('');
    expect(selectionStatus(1)).toBe('1 link selected');
    expect(selectionStatus(3)).toBe('3 links selected');
  });

  it('changes scope when the workspace, a filter or the page changes', () => {
    const scope = selectionScope(1, filters(), 1);

    expect(selectionScope(1, filters(), 1)).toBe(scope);
    expect(selectionScope(2, filters(), 1)).not.toBe(scope);
    expect(selectionScope(1, filters({ tag: 'launch' }), 1)).not.toBe(scope);
    expect(selectionScope(1, filters({ folder: '4' }), 1)).not.toBe(scope);
    expect(selectionScope(1, filters(), 2)).not.toBe(scope);
  });
});

describe('bulk action preview', () => {
  it('asks for confirmation before disabling or archiving', () => {
    expect(previewBulkAction('disable', 3)).toMatchObject({
      title: 'Disable 3 links?',
      confirmLabel: 'Disable 3 links',
      needsConfirm: true,
    });
    expect(previewBulkAction('archive', 1)).toMatchObject({
      title: 'Archive 1 link?',
      needsConfirm: true,
    });
    expect(previewBulkAction('archive', 1).message).toContain('keep their slugs, analytics and visit counts');
  });

  it('previews moves and enabling with the affected count before applying them', () => {
    expect(previewBulkAction('move', 2, { folderName: 'Campaigns' })).toMatchObject({
      title: 'Move 2 links to Campaigns?',
      confirmLabel: 'Move 2 links',
      destructive: false,
      needsConfirm: true,
    });
    expect(previewBulkAction('move', 2).title).toBe('Move 2 links out of folders?');
    expect(previewBulkAction('enable', 4)).toMatchObject({ title: 'Enable 4 links?', needsConfirm: true });
  });

  it('previews tag changes inside the tag dialog instead of a second confirmation', () => {
    expect(previewBulkAction('add_tags', 2, { tags: ['launch', 'q3'] })).toMatchObject({
      title: 'Add #launch, #q3 to 2 links',
      needsConfirm: false,
    });
    expect(previewBulkAction('remove_tags', 1, { tags: ['old'] }).title).toBe('Remove #old from 1 link');
  });

  it('explains that enabling does not override lifecycle rules', () => {
    expect(previewBulkAction('enable', 4).message).toContain('does not override lifecycle rules');
  });

  it('describes the outcome with the affected count', () => {
    expect(bulkResultTitle('archive', 3)).toBe('Archived 3 links');
    expect(bulkResultTitle('move', 1, 'Campaigns')).toBe('Moved 1 link to Campaigns');
    expect(bulkResultTitle('move', 2, null)).toBe('Moved 2 links out of folders');
  });
});
