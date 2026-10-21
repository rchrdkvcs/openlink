<script setup lang="ts">
import { Archive, Ban, CircleCheck, Folder as FolderIcon, FolderInput, Inbox, Tags, X } from '@lucide/vue';
import { computed, ref } from 'vue';

import Button from '@/Components/ui/Button.vue';
import Dialog from '@/Components/ui/Dialog.vue';
import Menu from '@/Components/ui/Menu.vue';
import MenuItem from '@/Components/ui/MenuItem.vue';
import MenuSeparator from '@/Components/ui/MenuSeparator.vue';
import SegmentedControl from '@/Components/ui/SegmentedControl.vue';
import TagInput from '@/Components/ui/TagInput.vue';
import { confirmAction } from '@/lib/confirm';
import { type BulkLinkAction, bulkUpdateLinks } from '@/lib/linkActions';
import { toast } from '@/lib/toast';
import type { Folder, Tag } from '@/types/payloads';

import { bulkResultTitle, countLabel, previewBulkAction } from './linkSelection';

const props = defineProps<{ ids: number[]; folders: Folder[]; knownTags: Tag[]; archiveView: boolean }>();

const emit = defineEmits<{ clear: [] }>();

const busy = ref(false);
const tagsOpen = ref(false);
const tagMode = ref<'add_tags' | 'remove_tags'>('add_tags');
const tagDraft = ref('');

const count = computed(() => props.ids.length);
const draftTags = computed(() =>
  tagDraft.value
    .split(',')
    .map((tag) => tag.trim())
    .filter(Boolean),
);
const tagPreview = computed(() =>
  draftTags.value.length ? previewBulkAction(tagMode.value, count.value, { tags: draftTags.value }) : null,
);
const tagModes = [
  { value: 'add_tags' as const, label: 'Add' },
  { value: 'remove_tags' as const, label: 'Remove' },
];

async function run(
  action: BulkLinkAction,
  target: { folderId?: number | null; folderName?: string | null; tags?: string[] } = {},
) {
  if (busy.value || count.value === 0) return;

  const preview = previewBulkAction(action, count.value, target);
  if (preview.needsConfirm) {
    const confirmed = await confirmAction({
      title: preview.title,
      message: preview.message,
      confirmLabel: preview.confirmLabel,
      destructive: preview.destructive,
    });
    if (!confirmed) return;
  }

  const affected = count.value;
  busy.value = true;
  bulkUpdateLinks(
    {
      action,
      ids: props.ids,
      ...(action === 'move' ? { folder_id: target.folderId ?? null } : {}),
      ...(target.tags ? { tags: target.tags } : {}),
    },
    {
      onSuccess: () => {
        toast({
          title: bulkResultTitle(action, affected, target.folderName),
          tone: 'success',
        });
        tagsOpen.value = false;
        tagDraft.value = '';
        emit('clear');
      },
      onFinish: () => {
        busy.value = false;
      },
    },
  );
}

function submitTags() {
  if (!draftTags.value.length) return;
  void run(tagMode.value, { tags: draftTags.value });
}
</script>

<template>
  <div
    role="region"
    aria-label="Bulk actions"
    class="sticky top-2 z-10 mt-3 flex flex-wrap items-center gap-1.5 rounded-xl border bg-overlay px-2 py-1.5 shadow-popover"
  >
    <p role="status" class="px-1.5 text-[13px] font-medium tabular-nums text-foreground">
      {{ countLabel(count) }} selected
    </p>
    <Button variant="ghost" size="sm" type="button" aria-label="Clear selection" @click="emit('clear')">
      <X /> Clear
    </Button>
    <span class="mx-1 h-4 w-px bg-border-strong" aria-hidden="true" />

    <Menu align="start" width="w-56">
      <template #trigger>
        <Button variant="secondary" size="sm" type="button" :disabled="busy"><FolderInput /> Move to</Button>
      </template>
      <MenuItem
        v-for="folder in folders"
        :key="folder.id"
        :icon="FolderIcon"
        @select="run('move', { folderId: folder.id, folderName: folder.name })"
        >{{ folder.name }}</MenuItem
      >
      <MenuSeparator v-if="folders.length" />
      <MenuItem :icon="Inbox" @select="run('move', { folderId: null })">Unfiled</MenuItem>
    </Menu>
    <Button variant="secondary" size="sm" type="button" :disabled="busy" @click="tagsOpen = true">
      <Tags /> Tags
    </Button>
    <Button variant="secondary" size="sm" type="button" :disabled="busy" @click="run('enable')">
      <CircleCheck /> Enable
    </Button>
    <Button variant="secondary" size="sm" type="button" :disabled="busy" @click="run('disable')">
      <Ban /> Disable
    </Button>
    <Button v-if="!archiveView" variant="danger" size="sm" type="button" :disabled="busy" @click="run('archive')">
      <Archive /> Archive
    </Button>

    <Dialog
      v-model:open="tagsOpen"
      size="sm"
      title="Edit tags"
      :description="`Applies to ${countLabel(count)}. Tags not listed here stay as they are.`"
    >
      <form class="space-y-3 px-5 pb-5 pt-3" @submit.prevent="submitTags">
        <SegmentedControl v-model="tagMode" :options="tagModes" size="sm" label="Tag change" />
        <TagInput
          v-model="tagDraft"
          :suggestions="knownTags"
          :placeholder="tagMode === 'add_tags' ? 'Tags to add, press Enter' : 'Tags to remove, press Enter'"
        />
        <p class="min-h-4 text-xs text-muted" aria-live="polite">{{ tagPreview?.title }}</p>
        <div class="flex justify-end gap-2">
          <Button variant="secondary" type="button" @click="tagsOpen = false">Cancel</Button>
          <Button type="submit" :loading="busy" :disabled="!draftTags.length">
            {{ tagPreview?.confirmLabel ?? (tagMode === 'add_tags' ? 'Add tags' : 'Remove tags') }}
          </Button>
        </div>
      </form>
    </Dialog>
  </div>
</template>
