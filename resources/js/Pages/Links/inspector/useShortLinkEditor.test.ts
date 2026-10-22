import { router } from '@inertiajs/vue3';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { effectScope, nextTick, ref } from 'vue';

import type { ShortLink } from '@/types/shortLinks';

import { useShortLinkEditor } from './useShortLinkEditor';

const original: ShortLink = {
  id: 1,
  slug: 'launch',
  short_url: 'https://go.example.test/launch',
  destination_url: 'https://example.com',
  status: 'active',
  domain: { id: 1, hostname: 'go.example.test', status: 'active', is_default: false },
  tags: [],
  routing_rules: [],
  qr_code_count: 0,
  visits: 0,
  scans: 0,
  successful_visits: 0,
  is_enabled: true,
  has_password: false,
  revision: 1,
};

const scopes: ReturnType<typeof effectScope>[] = [];

function setup(initial = original) {
  vi.stubGlobal('route', () => '/short-links/1');
  const patch = vi.spyOn(router, 'patch').mockImplementation(() => {});
  const current = ref(initial);
  const scope = effectScope();
  scopes.push(scope);
  const editor = scope.run(() =>
    useShortLinkEditor(
      () => current.value,
      () => true,
    ),
  )!;
  return { current, patch, ...editor };
}

afterEach(() => {
  scopes.splice(0).forEach((scope) => scope.stop());
  vi.restoreAllMocks();
  vi.unstubAllGlobals();
});

vi.mock('@/lib/toast', () => ({ toast: vi.fn() }));

describe('drawer draft revision provenance', () => {
  it('submits its original revision after concurrent editing and a failed routing round trip', async () => {
    const editor = setup();
    editor.form.slug = 'draft-by-a';
    await nextTick();
    editor.current.value = { ...original, slug: 'saved-by-b', revision: 2 };
    await nextTick();
    expect(editor.form.slug).toBe('draft-by-a');
    expect(editor.stale.value).toBe(true);
    editor.save();
    expect(editor.patch.mock.calls[0][1]).toMatchObject({ slug: 'draft-by-a', expected_revision: 1 });
    editor.patch.mock.calls[0][2]?.onError?.({ expected_revision: 'This link changed' });
    editor.save();
    expect(editor.patch.mock.calls[1][1]).toMatchObject({ slug: 'draft-by-a', expected_revision: 1 });
  });

  it('advances a drawer draft only for routing saved against its own base revision', async () => {
    const editor = setup();
    editor.form.slug = 'draft-by-a';
    await nextTick();
    editor.current.value = { ...original, destination_url: 'https://example.com/new-routing', revision: 2 };
    await nextTick();
    editor.acceptRoutingSave({ link_id: 1, expected_revision: 1, revision: 2 });
    await nextTick();
    editor.save();
    expect(editor.patch.mock.calls[0][1]).toMatchObject({
      slug: 'draft-by-a',
      destination_url: editor.current.value.destination_url,
      expected_revision: 2,
    });
    expect(editor.stale.value).toBe(false);
  });

  it('keeps an older drawer base when routing was saved from a newer server revision', async () => {
    const editor = setup();
    editor.form.slug = 'draft-by-a';
    await nextTick();
    editor.current.value = { ...original, slug: 'saved-by-b', revision: 2 };
    await nextTick();
    editor.current.value = { ...editor.current.value, destination_url: 'https://example.com/routing', revision: 3 };
    await nextTick();
    editor.acceptRoutingSave({ link_id: 1, expected_revision: 2, revision: 3 });
    editor.save();
    expect(editor.patch.mock.calls[0][1]).toMatchObject({ slug: 'draft-by-a', expected_revision: 1 });
  });

  it('does not accept a newer edit made between our routing mutation and its redirected GET', async () => {
    const editor = setup();
    editor.form.slug = 'draft-by-a';
    await nextTick();
    editor.current.value = { ...original, slug: 'saved-by-b-after-routing', revision: 3 };
    await nextTick();
    editor.acceptRoutingSave({ link_id: 1, expected_revision: 1, revision: 2 });
    editor.save();
    expect(editor.patch.mock.calls[0][1]).toMatchObject({ slug: 'draft-by-a', expected_revision: 1 });
  });

  it('does not mistake another edit for the result of a successful no-op routing mutation', async () => {
    const editor = setup();
    editor.form.slug = 'draft-by-a';
    await nextTick();
    editor.current.value = { ...original, slug: 'saved-by-b', revision: 2 };
    await nextTick();
    editor.acceptRoutingSave({ link_id: 1, expected_revision: 1, revision: 1 });
    editor.save();
    expect(editor.patch.mock.calls[0][1]).toMatchObject({ slug: 'draft-by-a', expected_revision: 1 });
  });

  it('retains the last authorized base if another member edits after routing succeeds', async () => {
    const editor = setup();
    editor.form.slug = 'draft-by-a';
    await nextTick();
    editor.current.value = { ...original, destination_url: 'https://example.com/new', revision: 2 };
    await nextTick();
    editor.acceptRoutingSave({ link_id: 1, expected_revision: 1, revision: 2 });
    await nextTick();
    editor.current.value = { ...editor.current.value, slug: 'saved-by-b', revision: 3 };
    await nextTick();
    editor.save();
    expect(editor.patch.mock.calls[0][1]).toMatchObject({ slug: 'draft-by-a', expected_revision: 2 });
  });

  it('preserves an unsaved destination and staged upload across an authorized routing save', async () => {
    const editor = setup();
    const image = new File(['image'], 'draft.png');
    editor.form.destination_url = 'https://example.com/draft';
    editor.form.preview_image = image;
    await nextTick();
    editor.current.value = { ...original, destination_url: 'https://example.com/routing', revision: 2 };
    await nextTick();
    editor.acceptRoutingSave({ link_id: 1, expected_revision: 1, revision: 2 });
    expect(editor.form.destination_url).toBe('https://example.com/draft');
    expect(editor.form.preview_image).toBe(image);
  });

  it('supports the baseline revision created for a legacy link', async () => {
    const editor = setup({ ...original, revision: 0 });
    editor.form.slug = 'draft-by-a';
    await nextTick();
    editor.current.value = { ...original, destination_url: 'https://example.com/new', revision: 2 };
    await nextTick();
    editor.acceptRoutingSave({ link_id: 1, expected_revision: 0, revision: 2 });
    await nextTick();
    editor.save();
    expect(editor.patch.mock.calls[0][1]).toMatchObject({ expected_revision: 2 });
  });

  it('adopts the latest revision after an explicit discard', async () => {
    const editor = setup();
    editor.form.slug = 'draft-by-a';
    await nextTick();
    editor.current.value = { ...original, slug: 'saved-by-b', revision: 2 };
    await nextTick();
    editor.discard();
    await nextTick();
    expect(editor.form.slug).toBe('saved-by-b');
    expect(editor.form.isDirty).toBe(false);
    editor.form.slug = 'reviewed-by-a';
    await nextTick();
    editor.save();
    expect(editor.patch.mock.calls[0][1]).toMatchObject({ slug: 'reviewed-by-a', expected_revision: 2 });
  });
});
