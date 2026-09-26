import assert from 'node:assert/strict';
import { test } from 'node:test';

import { router } from '@inertiajs/vue3';
import { effectScope, nextTick, reactive, ref } from 'vue';

import type { LinksPageProps, ShortLink } from '../../resources/js/Pages/Links/types.ts';
import { useLinkGroups } from '../../resources/js/Pages/Links/useLinkGroups.ts';

function fixture() {
  const stored = new Map<string, string>();
  Object.defineProperty(globalThis, 'localStorage', {
    configurable: true,
    value: {
      getItem: (key: string) => stored.get(key) ?? null,
      setItem: (key: string, value: string) => stored.set(key, value),
    },
  });
  const folder = { id: 1, name: 'Campaigns' };
  const link = (id: number, status: string, folder: { id: number; name: string } | null) =>
    ({
      id,
      status,
      folder,
      short_url: `https://go.test/${id}`,
      destination_url: 'https://example.com',
      slug: String(id),
      tags: [],
    }) as ShortLink;
  const props = reactive({
    currentWorkspace: { id: 1 },
    canEditWorkspace: true,
    folders: [folder, { id: 2, name: 'Empty' }],
    links: [link(1, 'active', folder), link(2, 'archived', folder), link(3, 'active', null)],
  } as LinksPageProps);
  const filters = ref({ search: '', status: '', tag: '' });
  const scope = effectScope();
  const model = scope.run(() => useLinkGroups(props, filters))!;
  return { model, props, filters, scope, stored };
}

test('folder navigation retains empty drop targets and filters within the selected folder', async () => {
  const { model, filters, scope } = fixture();
  assert.deepEqual(
    model.groups.value.map((g) => [g.key, g.links.length]),
    [
      ['unfiled', 1],
      ['1', 1],
      ['2', 0],
    ],
  );
  assert.equal(model.totalMatching.value, 2);
  model.selectedFolderKey.value = '1';
  await nextTick();
  assert.deepEqual(
    model.visibleLinks.value.map((l) => l.id),
    [1],
  );
  filters.value.status = 'archived';
  assert.deepEqual(
    model.visibleLinks.value.map((l) => l.id),
    [2],
  );
  filters.value.search = 'no-match';
  assert.equal(model.visibleLinks.value.length, 0);
  assert.equal(model.groups.value.length, 3);
  scope.stop();
});

test('selection is isolated by workspace and falls back when its folder is deleted', async () => {
  const { model, props, scope, stored } = fixture();
  model.selectedFolderKey.value = '1';
  await nextTick();
  assert.equal(stored.get('links.folder.1'), '1');
  props.currentWorkspace.id = 2;
  props.folders = [{ id: 9, name: 'Other workspace' }];
  await nextTick();
  assert.equal(model.selectedFolderKey.value, 'all');
  model.selectedFolderKey.value = '9';
  await nextTick();
  props.currentWorkspace.id = 1;
  props.folders = [{ id: 1, name: 'Campaigns' }];
  await nextTick();
  assert.equal(model.selectedFolderKey.value, '1');
  props.folders = [];
  await nextTick();
  assert.equal(model.selectedFolderKey.value, 'all');
  scope.stop();
});

test('move and drop share the move endpoint, ignore no-ops, and respect read-only access', () => {
  const { model, props, scope } = fixture();
  const calls: unknown[] = [];
  const original = router.post;
  (globalThis as any).route = (name: string, id: number) => `${name}/${id}`;
  router.post = ((...args: unknown[]) => {
    calls.push(args);
  }) as typeof router.post;
  try {
    model.moveLink(props.links[0], 1);
    assert.equal(calls.length, 0);
    model.dragLinkId.value = 1;
    model.onDrop(model.groups.value[0]);
    assert.deepEqual(calls[0], ['short-links.move/1', { folder_id: null }, { preserveScroll: true }]);
    assert.equal(model.dragLinkId.value, null);
    props.canEditWorkspace = false;
    model.moveLink(props.links[0], 2);
    assert.equal(calls.length, 1);
  } finally {
    router.post = original;
    scope.stop();
  }
});
