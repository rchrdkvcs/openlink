import { describe, expect, it } from 'vitest';

import { changedFields } from './inspectorDraft';

describe('link details draft while editing routing', () => {
  it('keeps local edits while accepting a newly saved routing destination', () => {
    const saved = { slug: 'launch', destination_url: 'https://example.com', is_enabled: true };
    const draft = { ...saved, slug: 'campaign', is_enabled: false };
    const changes = changedFields(draft, saved);
    const refreshed = { ...saved, destination_url: 'https://example.com/new-default' };

    expect({ ...refreshed, ...changes }).toEqual({ ...draft, destination_url: refreshed.destination_url });
  });

  it('keeps an unsaved destination and staged image by reference on return to the drawer', () => {
    const image = new File(['image'], 'preview.png');
    const saved = { destination_url: 'https://example.com', preview_image: null as File | null };
    const draft = { destination_url: 'https://example.com/draft', preview_image: image };
    const changes = changedFields(draft, saved);

    expect(changes.destination_url).toBe(draft.destination_url);
    expect(changes.preview_image).toBe(image);
    expect(changedFields(saved, saved)).toEqual({});
  });
});
