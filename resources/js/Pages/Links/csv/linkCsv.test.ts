import { describe, expect, it } from 'vitest';

import { exportQuery, mappingOptions, mappingPayload, previewHeadline, resultHeadline } from './linkCsv';

describe('exportQuery', () => {
  it('keeps only the list filters that are set', () => {
    expect(exportQuery({ search: 'promo', status: '', tag: 'spring', folder: '4' })).toEqual({
      search: 'promo',
      tag: 'spring',
      folder: '4',
    });
  });
});

describe('mappingOptions', () => {
  it('offers every file column by position plus a skip option', () => {
    expect(mappingOptions(['Long URL', ''])).toEqual([
      { value: '', label: 'Don’t import' },
      { value: '0', label: 'Long URL' },
      { value: '1', label: 'Column 2' },
    ]);
  });
});

describe('mappingPayload', () => {
  it('turns selected columns into indexes and drops skipped ones', () => {
    expect(mappingPayload({ destination_url: '3', slug: '', folder: '0' })).toEqual({ destination_url: 3, folder: 0 });
  });
});

describe('headlines', () => {
  it('summarises a preview', () => {
    expect(previewHeadline({ total: 3, ready: 2, invalid: 1, new_folders: [], new_tags: [] })).toBe(
      '2 of 3 links are ready to import · 1 will be skipped',
    );
    expect(previewHeadline({ total: 1, ready: 1, invalid: 0, new_folders: [], new_tags: [] })).toBe(
      '1 of 1 link is ready to import',
    );
  });

  it('summarises a finished import', () => {
    expect(resultHeadline({ total: 3, created: 3, skipped: 0 })).toBe('Imported 3 links');
    expect(resultHeadline({ total: 2, created: 1, skipped: 1 })).toBe('Imported 1 link · 1 skipped');
  });
});
