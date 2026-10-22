import { describe, expect, it } from 'vitest';

import type { Domain } from '@/types/payloads';
import type { ShortLink } from '@/types/shortLinks';

import {
  newQuickLinkForm,
  PASSWORD_MASK,
  initialDomainId,
  linkPreviewCard,
  needsMultipart,
  sectionFor,
  sectionsWithErrors,
  selectableDomains,
  toForm,
  toPayload,
} from './shortLinkForm';

const domain: Domain = { id: 3, hostname: 'go.example.com', status: 'active', is_default: true };

function link(overrides: Partial<ShortLink> = {}): ShortLink {
  return {
    id: 1,
    slug: 'launch',
    short_url: 'https://go.example.com/launch',
    destination_url: 'https://example.com/launch',
    status: 'active',
    domain,
    tags: [],
    qr_code_count: 0,
    visits: 0,
    scans: 0,
    is_enabled: true,
    successful_visits: 0,
    has_password: false,
    revision: 1,
    routing_rules: [],
    ...overrides,
  };
}

describe('toForm', () => {
  it('maps a minimal Short Link to empty optional fields', () => {
    expect(toForm(link())).toEqual({
      folder_id: '',
      domain_id: 3,
      slug: 'launch',
      destination_url: 'https://example.com/launch',
      fallback_url: '',
      is_enabled: true,
      activates_at: '',
      expires_at: '',
      visit_limit: '',
      password: '',
      tags: '',
      preview_title: '',
      preview_description: '',
      preview_image: null,
      remove_preview_image: false,
    });
  });

  it('maps the saved Link Preview text', () => {
    const form = toForm(link({ preview: { title: 'Spring launch', description: 'New season', image_url: '/img' } }));

    expect(form.preview_title).toBe('Spring launch');
    expect(form.preview_description).toBe('New season');
    expect(form.preview_image).toBeNull();
  });

  it('slices schedule dates to the datetime-local wire format', () => {
    const form = toForm(link({ activates_at: '2026-05-01T09:30:00.000000Z', expires_at: '2026-06-01T18:00:59Z' }));

    expect(form.activates_at).toBe('2026-05-01T09:30');
    expect(form.expires_at).toBe('2026-06-01T18:00');
  });

  it('stringifies folder and visit limit, joins tags and masks passwords', () => {
    const form = toForm(
      link({
        folder: { id: 9, name: 'Campaigns' },
        visit_limit: 500,
        has_password: true,
        fallback_url: 'https://example.com/expired',
        tags: [
          { id: 1, name: 'spring' },
          { id: 2, name: 'promo' },
        ],
      }),
    );

    expect(form.folder_id).toBe('9');
    expect(form.visit_limit).toBe('500');
    expect(form.password).toBe(PASSWORD_MASK);
    expect(form.tags).toBe('spring, promo');
    expect(form.fallback_url).toBe('https://example.com/expired');
  });

  it('treats a zero visit limit as unlimited', () => {
    expect(toForm(link({ visit_limit: 0 })).visit_limit).toBe('');
  });

  it('leaves Routing Rules to the routing editor', () => {
    expect(toForm(link())).not.toHaveProperty('routing_rules');
  });
});

describe('toPayload', () => {
  it('normalizes the Destination URL', () => {
    expect(toPayload({ destination_url: ' example.com/page ' }).destination_url).toBe('https://example.com/page');
  });

  it('omits the untouched password mask for protected links', () => {
    const payload = toPayload(toForm(link({ has_password: true })), { hasPassword: true });

    expect(payload).not.toHaveProperty('password');
  });

  it('sends a changed or cleared password', () => {
    const form = toForm(link({ has_password: true }));

    expect(toPayload({ ...form, password: 'secret' }, { hasPassword: true })).toHaveProperty('password', 'secret');
    expect(toPayload({ ...form, password: '' }, { hasPassword: true })).toHaveProperty('password', '');
  });

  it('keeps a literal mask value when the link had no password', () => {
    expect(toPayload({ ...toForm(link()), password: PASSWORD_MASK })).toHaveProperty('password', PASSWORD_MASK);
  });

  it('keeps every other field in its wire format', () => {
    const form = { ...toForm(link()), tags: 'a, b', visit_limit: '10', activates_at: '2026-05-01T09:30' };
    const payload = toPayload(form);

    expect(payload).toMatchObject({ tags: 'a, b', visit_limit: '10', activates_at: '2026-05-01T09:30' });
  });
});

describe('Link Preview image payloads', () => {
  const file = new File(['image'], 'cover.png', { type: 'image/png' });

  it('stays JSON without a staged image and omits the empty file field', () => {
    const form = toForm(link());

    expect(needsMultipart(form)).toBe(false);
    expect(toPayload(form)).not.toHaveProperty('preview_image');
    expect(toPayload({ ...form, remove_preview_image: true })).toHaveProperty('remove_preview_image', true);
  });

  it('spoofs PATCH when an image is staged and leaves Routing Rules to the routing editor', () => {
    const form = { ...toForm(link()), preview_image: file };
    const payload = toPayload(form);

    expect(needsMultipart(form)).toBe(true);
    expect(payload).toMatchObject({ _method: 'patch', preview_image: file });
    expect(payload).not.toHaveProperty('routing_rules');
  });
});

describe('linkPreviewCard', () => {
  const saved = link({ preview: { title: 'Saved', description: null, image_url: '/saved.jpg' } });

  it('falls back to the Short URL when no title is set', () => {
    expect(linkPreviewCard(link(), toForm(link()), null)).toEqual({
      title: 'go.example.com/launch',
      description: '',
      host: 'go.example.com',
      imageUrl: null,
    });
  });

  it('shows the staged image, the saved image, or none when removed', () => {
    const form = toForm(saved);

    expect(linkPreviewCard(saved, form, 'blob:staged').imageUrl).toBe('blob:staged');
    expect(linkPreviewCard(saved, form, null).imageUrl).toBe('/saved.jpg');
    expect(linkPreviewCard(saved, { ...form, remove_preview_image: true }, null).imageUrl).toBeNull();
    expect(linkPreviewCard(saved, form, null).title).toBe('Saved');
  });
});

describe('sectionFor', () => {
  it.each([
    ['activates_at', 'schedule'],
    ['expires_at', 'schedule'],
    ['visit_limit', 'limit'],
    ['password', 'password'],
    ['fallback_url', 'fallback'],
    ['routing_rules', null],
    ['routing_rules.0.destination_url', null],
    ['preview_title', 'preview'],
    ['preview_image', 'preview'],
    ['destination_url', null],
    ['slug', null],
  ])('maps %s to %s', (key, section) => {
    expect(sectionFor(key)).toBe(section);
  });

  it('collects each section with errors once', () => {
    expect(
      sectionsWithErrors({ activates_at: 'x', expires_at: 'y', 'routing_rules.1.variants': 'z', slug: 'taken' }),
    ).toEqual(['schedule']);
  });
});

describe('domain selection', () => {
  const domains: Domain[] = [
    { id: 1, hostname: 'pending.test', status: 'pending', is_default: false },
    { id: 2, hostname: 'a.test', status: 'active', is_default: true },
    { id: 5, hostname: 'b.test', status: 'active', is_default: false },
  ];

  it('keeps only active Domains', () => {
    expect(selectableDomains(domains).map((entry) => entry.id)).toEqual([2, 5]);
  });

  it('prefers the workspace Domain, then the first, then none', () => {
    const active = selectableDomains(domains);

    expect(initialDomainId(active, 5)).toBe(5);
    expect(initialDomainId(active, 1)).toBe(2);
    expect(initialDomainId([], 5)).toBe('');
  });

  it('builds a blank quick link form', () => {
    expect(newQuickLinkForm(2, 7)).toEqual({
      destination_url: '',
      domain_id: 2,
      folder_id: '7',
      slug: '',
      is_enabled: true,
    });
    expect(newQuickLinkForm('', null).folder_id).toBe('');
  });
});
