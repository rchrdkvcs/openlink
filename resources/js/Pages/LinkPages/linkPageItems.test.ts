import { describe, expect, it } from 'vitest';

import {
  addItem,
  type EditableItem,
  itemLabel,
  MAX_ITEMS,
  moveItem,
  normalizePath,
  previewPage,
  removeItem,
  toItemsPayload,
} from './linkPageItems';

function item(id: number, overrides: Partial<EditableItem> = {}): EditableItem {
  return {
    short_link_id: id,
    label: '',
    short_url: `https://go.example.test/l${id}`,
    destination_url: `https://example.com/${id}`,
    availability: 'active',
    ...overrides,
  };
}

const option = (id: number) => ({
  id,
  short_url: `https://go.example.test/l${id}`,
  destination_url: 'https://example.com',
});

describe('link page items', () => {
  it('adds a short link once and respects the limit', () => {
    const once = addItem([item(1)], option(2));
    expect(once.map((entry) => entry.short_link_id)).toEqual([1, 2]);
    expect(addItem(once, option(2))).toBe(once);

    const full = Array.from({ length: MAX_ITEMS }, (_, index) => item(index + 1));
    expect(addItem(full, option(999))).toBe(full);
  });

  it('moves items within bounds', () => {
    const items = [item(1), item(2), item(3)];

    expect(moveItem(items, 0, 1).map((entry) => entry.short_link_id)).toEqual([2, 1, 3]);
    expect(moveItem(items, 2, -1).map((entry) => entry.short_link_id)).toEqual([1, 3, 2]);
    expect(moveItem(items, 0, -1)).toBe(items);
    expect(moveItem(items, 2, 1)).toBe(items);
  });

  it('removes an item by position', () => {
    expect(removeItem([item(1), item(2)], 0).map((entry) => entry.short_link_id)).toEqual([2]);
  });

  it('labels items with their custom label or short URL', () => {
    expect(itemLabel(item(1, { label: '  Tickets ' }))).toBe('Tickets');
    expect(itemLabel(item(1))).toBe('go.example.test/l1');
  });

  it('sends ordered ids with trimmed labels', () => {
    expect(toItemsPayload([item(2, { label: ' Two ' }), item(1)])).toEqual([
      { short_link_id: 2, label: 'Two' },
      { short_link_id: 1, label: '' },
    ]);
  });

  it('previews only the links visitors can see', () => {
    const page = previewPage({ title: 'Spring', description: '  ' }, null, [
      item(1, { label: 'Open' }),
      item(2, { availability: 'archived' }),
    ]);

    expect(page).toEqual({
      title: 'Spring',
      description: null,
      logo: null,
      links: [{ label: 'Open', url: 'https://go.example.test/l1' }],
    });
  });

  it('normalizes typed paths', () => {
    expect(normalizePath('Spring Conference 2026!')).toBe('spring-conference-2026-');
    expect(normalizePath('--Hello__World')).toBe('hello-world');
  });
});
