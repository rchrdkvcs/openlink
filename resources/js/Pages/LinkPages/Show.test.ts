import { beforeAll, describe, expect, it, vi } from 'vitest';
import { computed, createSSRApp, defineComponent, h, ref } from 'vue';
import { renderToString } from 'vue/server-renderer';

import type { LinkPageRecord } from './types';

const canEdit = ref(false);

vi.mock('@/lib/shell', () => ({ useShell: () => ({ canEdit: computed(() => canEdit.value) }) }));

vi.mock('@/Layouts/AuthenticatedLayout.vue', () => ({
  default: defineComponent(
    (_, { slots }) =>
      () =>
        h('div', slots.default?.()),
  ),
}));

vi.mock('@inertiajs/vue3', async (importOriginal) => {
  const slot = defineComponent(
    (_, { slots }) =>
      () =>
        h('span', slots.default?.()),
  );

  return { ...(await importOriginal<object>()), Head: slot, Link: slot };
});

beforeAll(() => {
  vi.stubGlobal('route', () => '/link-pages');
});

const linkPage: LinkPageRecord = {
  id: 1,
  name: 'Spring',
  path: 'spring',
  title: 'Spring conference',
  is_published: false,
  public_url: 'https://openlink.test/p/spring',
  items_count: 1,
  description: null,
  display: { show_icons: true, show_labels: true, show_urls: false },
  has_logo: false,
  logo: null,
  published_at: null,
  items: [
    {
      short_link_id: 7,
      label: 'Tickets',
      short_url: 'https://go.example.test/tickets',
      destination_url: 'https://tickets.example.com',
      display_label: 'Tickets',
      availability: 'active',
      icon: null,
    },
  ],
};

async function render(editable: boolean) {
  canEdit.value = editable;
  const { default: Show } = await import('./Show.vue');

  const app = createSSRApp({
    render: () => h(Show, { linkPage, shortLinks: [], pathPrefix: 'https://openlink.test/p/' }),
  });
  app.config.globalProperties.route = route;

  return renderToString(app);
}

describe('Link page editor', () => {
  it('disables every field and hides editing actions for viewers', async () => {
    const html = await render(false);

    expect(html).toMatch(/<fieldset[^>]*disabled/);
    expect(html).not.toContain('Publish');
    expect(html).not.toContain('Search short links to add');
    expect(html).toContain('Spring conference');
  });

  it('enables fields and publishing for editors', async () => {
    const html = await render(true);

    expect(html).not.toMatch(/<fieldset[^>]*disabled/);
    expect(html).toContain('Publish');
    expect(html).toContain('Search short links to add');
  });
});
