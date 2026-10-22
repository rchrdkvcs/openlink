import { beforeEach, describe, expect, it, vi } from 'vitest';
import { computed, createSSRApp, defineComponent, h, ref } from 'vue';
import { renderToString } from 'vue/server-renderer';

import { initialEditorFields } from './qrForm';
import Show from './Show.vue';
import type { QrCodeRecord } from './types';

const access = vi.hoisted(() => ({ canEdit: true }));

vi.mock('@/lib/shell', () => ({
  useShell: () => ({ canEdit: computed(() => access.canEdit), canManage: ref(false) }),
}));
vi.mock('@/lib/useSaveShortcut', () => ({ useSaveShortcut: vi.fn() }));
vi.mock('@inertiajs/vue3', () => ({
  Head: defineComponent({ render: () => null }),
  Link: defineComponent({
    setup:
      (_, { slots }) =>
      () =>
        h('a', slots.default?.()),
  }),
}));
vi.mock('@/Layouts/AuthenticatedLayout.vue', () => ({
  default: defineComponent({
    setup:
      (_, { slots }) =>
      () =>
        h('main', slots.default?.()),
  }),
}));
vi.mock('./Studio/ContentPanel.vue', () => ({
  default: defineComponent({ render: () => h('div', 'Content fields') }),
}));
vi.mock('./Studio/StylePanel.vue', () => ({ default: defineComponent({ render: () => h('div', 'Style fields') }) }));
vi.mock('./Studio/QrCodeActions.vue', () => ({ default: defineComponent({ render: () => null }) }));
vi.mock('./Studio/useQrCodeEditor', () => ({
  useQrCodeEditor: (qr: QrCodeRecord) => ({
    form: { ...initialEditorFields(qr, {}), errors: {}, processing: false, hasErrors: false },
    panel: ref('advanced'),
    isDirty: ref(false),
    previewUrl: ref('/preview.svg'),
    previewVersion: ref(0),
    appliedTemplate: ref(null),
  }),
}));
vi.mock('./Studio/useReadabilityCheck', () => ({
  useReadabilityCheck: () => ({ state: ref({ status: 'idle' }), check: vi.fn() }),
}));

const qr: QrCodeRecord = {
  id: 1,
  name: 'Poster',
  token: 'poster',
  public_url: 'https://example.com/qr/poster',
  content: 'https://example.com',
  payload_type: 'url',
  payload: { url: 'https://example.com' },
  is_direct: true,
  short_link_id: null,
  short_link: null,
  scans: 0,
  size: 1024,
  has_logo: false,
  foreground_color: '#000000',
  background_color: '#ffffff',
  background_transparent: false,
  margin: 4,
  error_correction: 'medium',
  style: 'square',
  eye_style: 'square',
};

function render() {
  const app = createSSRApp(Show, { qr, payloadTypes: {}, payloadDescriptors: {}, shortLinks: [], templates: [] });
  app.config.globalProperties.route = (() => '/qr-codes') as typeof route;
  return renderToString(app);
}

beforeEach(() => {
  access.canEdit = true;
});

describe('QR code studio', () => {
  it('groups export and readability in settings and keeps the preview focused', async () => {
    const html = await render();
    const [preview, settings] = html.split('<aside');

    expect(preview).toContain('Poster QR code preview');
    expect(preview).not.toContain('Export size');
    expect(preview).not.toContain('Check readability');
    expect(settings).toContain('Export and sharing');
    expect(settings).toContain('Copy public URL');
    expect(settings).toContain('Export size');
    expect(settings).toContain('Check readability');
  });

  it('keeps export and readability outside the edit form for viewers', async () => {
    access.canEdit = false;
    const html = await render();
    const form = html.match(/<form[\s\S]*?<\/form>/)?.[0];
    const exportSection = html.match(/<section[^>]*aria-labelledby="export-heading"[\s\S]*?<\/section>/)?.[0];
    const readabilitySection = html.match(/<section[^>]*aria-labelledby="readability-heading"[\s\S]*?<\/section>/)?.[0];

    expect(form).toMatch(/<fieldset[^>]*disabled/);
    expect(form).toContain('Encoding');
    expect(form).not.toContain('aria-label="Export size"');
    expect(form).not.toContain('Print width');
    expect(exportSection).toBeDefined();
    expect(readabilitySection).toBeDefined();
    expect(exportSection).not.toMatch(/\sdisabled(?:[=>\s])/);
    expect(readabilitySection).not.toMatch(/\sdisabled(?:[=>\s])/);
    expect(readabilitySection).not.toContain('Use readable settings');
  });
});
