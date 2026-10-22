import { beforeEach, describe, expect, it, vi } from 'vitest';
import { createSSRApp, defineComponent, h, ref } from 'vue';
import { renderToString } from 'vue/server-renderer';

import type { ShortLink } from '@/types/shortLinks';

import InspectorHeader from './inspector/InspectorHeader.vue';
import LinkRowMenu from './list/LinkRowMenu.vue';

const editable = ref(true);

vi.mock('@/lib/shell', () => ({ useShell: () => ({ canEdit: editable }) }));
vi.mock('@/Components/ui/Menu.vue', () => ({
  default: defineComponent(
    (_, { slots }) =>
      () =>
        h('div', slots.default?.()),
  ),
}));
vi.mock('@/Components/ui/MenuItem.vue', () => ({
  default: defineComponent(
    (_, { slots }) =>
      () =>
        h('button', slots.default?.()),
  ),
}));

const link: ShortLink = {
  id: 9,
  slug: 'launch',
  short_url: 'https://go.example.test/launch',
  destination_url: 'https://example.com',
  status: 'archived',
  archived_at: '2026-10-21T12:00:00Z',
  domain: { id: 1, hostname: 'go.example.test', status: 'active', is_default: false },
  tags: [],
  routing_rules: [],
  qr_code_count: 0,
  visits: 0,
  scans: 0,
  successful_visits: 0,
  is_enabled: false,
  has_password: false,
  revision: 1,
};

async function render(status: ShortLink['status'] = 'archived') {
  return renderToString(
    createSSRApp({
      render: () =>
        h('div', [
          h(InspectorHeader, { link: { ...link, status }, previewUrl: '' }),
          h(LinkRowMenu, { link: { ...link, status }, folders: [], selected: false }),
        ]),
    }),
  );
}

beforeEach(() => {
  editable.value = true;
  vi.stubGlobal('route', () => '/favicons');
});

describe('Archived link actions', () => {
  it('offers Unarchive in the inspector and list menu', async () => {
    const html = await render();

    expect(html.match(/>\s*Unarchive\s*</g)).toHaveLength(2);
    expect(html).not.toContain('title="Archive"');
  });

  it('keeps Archive for primary links', async () => {
    const html = await render('disabled');

    expect(html).toContain('title="Archive"');
    expect(html).not.toContain('Unarchive');
  });

  it('hides archive mutations from viewers', async () => {
    editable.value = false;
    const html = await render();

    expect(html).not.toContain('Unarchive');
    expect(html).not.toContain('title="Archive"');
    expect(html).toContain('View details');
  });
});
