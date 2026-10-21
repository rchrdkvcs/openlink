import { describe, expect, it } from 'vitest';

import type { Domain } from '@/types/payloads';
import type { ShortLink } from '@/types/shortLinks';

import {
  newQuickLinkForm,
  PASSWORD_MASK,
  initialDomainId,
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
      routing_rules: [],
    });
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

  it('deep clones Routing Rules so edits never touch the source link', () => {
    const source = link({
      routing_rules: [
        {
          id: 4,
          name: 'France',
          type: 'conditional',
          is_enabled: true,
          match_mode: 'all',
          conditions: [{ type: 'time_of_day', operator: 'between', value: { from: '09:00', to: '18:00' } }],
          destination_url: 'https://example.fr',
          variants: [{ id: 2, name: 'A', is_enabled: true, destination_url: 'https://a.test', weight: 50 }],
        },
      ],
    });
    const form = toForm(source);
    form.routing_rules[0].conditions[0].value = { from: '00:00', to: '01:00' };
    form.routing_rules[0].variants[0].weight = 10;
    form.routing_rules[0].name = 'Changed';

    expect(source.routing_rules[0].conditions[0].value).toEqual({ from: '09:00', to: '18:00' });
    expect(source.routing_rules[0].variants[0].weight).toBe(50);
    expect(source.routing_rules[0].name).toBe('France');
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

describe('sectionFor', () => {
  it.each([
    ['activates_at', 'schedule'],
    ['expires_at', 'schedule'],
    ['visit_limit', 'limit'],
    ['password', 'password'],
    ['fallback_url', 'fallback'],
    ['routing_rules', 'routing'],
    ['routing_rules.0.destination_url', 'routing'],
    ['destination_url', null],
    ['slug', null],
  ])('maps %s to %s', (key, section) => {
    expect(sectionFor(key)).toBe(section);
  });

  it('collects each section with errors once', () => {
    expect(
      sectionsWithErrors({ activates_at: 'x', expires_at: 'y', 'routing_rules.1.variants': 'z', slug: 'taken' }),
    ).toEqual(['schedule', 'routing']);
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
