import { describe, expect, it } from 'vitest';

import { folderDeleteMessage, folderRename, linkCountLabel, needsMove } from '@/Components/Shell/folders';

describe('linkCountLabel', () => {
  it('pluralizes link counts', () => {
    expect(linkCountLabel(0)).toBe('0 links');
    expect(linkCountLabel(1)).toBe('1 link');
    expect(linkCountLabel(3)).toBe('3 links');
  });
});

describe('folderDeleteMessage', () => {
  it('explains where links go', () => {
    expect(folderDeleteMessage(1)).toBe('The 1 link inside will move to Unfiled. Nothing is deleted.');
    expect(folderDeleteMessage(4)).toBe('The 4 links inside will move to Unfiled. Nothing is deleted.');
  });

  it('describes empty folders', () => {
    expect(folderDeleteMessage(0)).toBe('This folder is empty.');
  });
});

describe('folderRename', () => {
  it('returns the trimmed new name', () => {
    expect(folderRename('Old', '  New  ')).toBe('New');
  });

  it('ignores blank or unchanged names', () => {
    expect(folderRename('Old', '   ')).toBeNull();
    expect(folderRename('Old', ' Old ')).toBeNull();
  });
});

describe('needsMove', () => {
  it('requires a link in a different folder', () => {
    expect(needsMove(null, 1)).toBe(false);
    expect(needsMove({ folderId: 1 }, 1)).toBe(false);
    expect(needsMove({ folderId: null }, null)).toBe(false);
    expect(needsMove({ folderId: 1 }, null)).toBe(true);
    expect(needsMove({ folderId: null }, 2)).toBe(true);
  });
});
