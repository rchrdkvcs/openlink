<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Archive, Folder, FolderOpen, Inbox, MoreHorizontal, Pencil, Plus, Trash2 } from '@lucide/vue';
import { computed, nextTick, ref } from 'vue';

import SidebarItem from '@/Components/Shell/SidebarItem.vue';
import Menu from '@/Components/ui/Menu.vue';
import MenuItem from '@/Components/ui/MenuItem.vue';
import MenuSeparator from '@/Components/ui/MenuSeparator.vue';
import { confirmAction } from '@/lib/confirm';
import { draggedLink } from '@/lib/dragLink';
import { moveLinkToFolder } from '@/lib/linkActions';
import { useShell } from '@/lib/shell';
import { toast } from '@/lib/toast';
import type { NavigationFolder } from '@/types';

const { navigation, canManage, query } = useShell();

const onLinks = computed(() => route().current('links.index'));
const activeFolder = computed(() => (onLinks.value ? (query.value.get('folder') ?? '') : null));
const activeStatus = computed(() => (onLinks.value ? (query.value.get('status') ?? '') : null));

const folders = computed(() => navigation.value?.folders ?? []);

const creating = ref(false);
const newName = ref('');
const newInput = ref<HTMLInputElement | null>(null);
const renamingId = ref<number | null>(null);
const renameValue = ref('');
const renameInput = ref<HTMLInputElement[]>([]);
const dropKey = ref<string | null>(null);

function startCreate() {
  creating.value = true;
  newName.value = '';
  nextTick(() => newInput.value?.focus());
}

function commitCreate() {
  const name = newName.value.trim();
  creating.value = false;
  if (!name) return;

  router.post(
    route('folders.store'),
    { name },
    {
      preserveScroll: true,
      preserveState: true,
      onSuccess: () => toast({ title: `Folder “${name}” created`, tone: 'success' }),
      onError: (errors) => toast({ title: errors.name ?? 'Could not create folder', tone: 'danger' }),
    },
  );
}

function startRename(folder: NavigationFolder) {
  renamingId.value = folder.id;
  renameValue.value = folder.name;
  nextTick(() => {
    renameInput.value[0]?.focus();
    renameInput.value[0]?.select();
  });
}

function commitRename(folder: NavigationFolder) {
  if (renamingId.value !== folder.id) return;
  const name = renameValue.value.trim();
  renamingId.value = null;
  if (!name || name === folder.name) return;

  router.patch(route('folders.update', folder.id), { name }, { preserveScroll: true, preserveState: true });
}

async function remove(folder: NavigationFolder) {
  const confirmed = await confirmAction({
    title: `Delete “${folder.name}”?`,
    message:
      folder.links_count > 0
        ? `The ${folder.links_count} link${folder.links_count === 1 ? '' : 's'} inside will move to Unfiled. Nothing is deleted.`
        : 'This folder is empty.',
    confirmLabel: 'Delete folder',
    destructive: true,
  });
  if (!confirmed) return;

  router.delete(route('folders.destroy', folder.id), {
    preserveScroll: true,
    onSuccess: () => {
      toast({ title: 'Folder deleted' });
      if (activeFolder.value === String(folder.id)) router.visit(route('links.index'));
    },
  });
}

function onDragOver(key: string, event: DragEvent) {
  if (!draggedLink.value) return;
  event.preventDefault();
  if (event.dataTransfer) event.dataTransfer.dropEffect = 'move';
  dropKey.value = key;
}

function onDrop(folder: NavigationFolder | null) {
  const link = draggedLink.value;
  dropKey.value = null;
  draggedLink.value = null;
  if (!link || link.folderId === (folder?.id ?? null)) return;
  moveLinkToFolder(link, folder?.id ?? null, folder?.name);
}
</script>

<template>
  <div>
    <div class="flex h-7 items-center justify-between pl-2 pr-1">
      <span class="text-xs font-medium text-faint">Folders</span>
      <button
        v-if="canManage"
        type="button"
        class="grid h-6 w-6 place-items-center rounded-md text-faint transition-colors hover:bg-elevated hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent/40"
        title="New folder"
        aria-label="New folder"
        @click="startCreate"
      >
        <Plus class="h-3.5 w-3.5" />
      </button>
    </div>

    <div class="space-y-px">
      <div
        v-for="folder in folders"
        :key="folder.id"
        @dragover="onDragOver(String(folder.id), $event)"
        @dragleave="dropKey = null"
        @drop.prevent="onDrop(folder)"
      >
        <div v-if="renamingId === folder.id" class="flex h-8 items-center gap-2.5 rounded-lg bg-elevated px-2">
          <FolderOpen class="h-4 w-4 shrink-0 text-faint" />
          <input
            ref="renameInput"
            v-model="renameValue"
            class="h-6 min-w-0 flex-1 rounded-md border border-accent/60 bg-background px-1.5 text-[13px] text-foreground outline-none ring-2 ring-accent/15"
            aria-label="Folder name"
            @keydown.enter.prevent="commitRename(folder)"
            @keydown.escape.prevent="renamingId = null"
            @blur="commitRename(folder)"
          />
        </div>
        <SidebarItem
          v-else
          :href="route('links.index', { folder: folder.id })"
          :icon="activeFolder === String(folder.id) ? FolderOpen : Folder"
          :label="folder.name"
          :count="folder.links_count"
          :active="activeFolder === String(folder.id)"
          :drop-active="dropKey === String(folder.id)"
          @dblclick="canManage && startRename(folder)"
        >
          <template v-if="canManage" #actions>
            <Menu align="start" width="w-44">
              <template #trigger>
                <button
                  type="button"
                  class="grid h-6 w-6 place-items-center rounded-md text-faint transition-colors hover:bg-border-strong/60 hover:text-foreground"
                  :aria-label="`Actions for ${folder.name}`"
                >
                  <MoreHorizontal class="h-3.5 w-3.5" />
                </button>
              </template>
              <MenuItem :icon="Pencil" @select="startRename(folder)">Rename</MenuItem>
              <MenuSeparator />
              <MenuItem :icon="Trash2" destructive @select="remove(folder)">Delete folder</MenuItem>
            </Menu>
          </template>
        </SidebarItem>
      </div>

      <form v-if="creating" class="flex h-8 items-center gap-2.5 px-2" @submit.prevent="commitCreate">
        <Folder class="h-4 w-4 shrink-0 text-faint" />
        <input
          ref="newInput"
          v-model="newName"
          placeholder="Folder name"
          aria-label="New folder name"
          class="h-6 min-w-0 flex-1 rounded-md border border-accent/60 bg-background px-1.5 text-[13px] text-foreground outline-none ring-2 ring-accent/15 placeholder:text-faint"
          @keydown.escape.prevent="creating = false"
          @blur="commitCreate"
        />
      </form>

      <button
        v-if="!folders.length && !creating && canManage"
        type="button"
        class="flex h-8 w-full items-center gap-2.5 rounded-lg px-2 text-left text-[13px] text-faint transition-colors hover:bg-elevated/50 hover:text-muted"
        @click="startCreate"
      >
        <Plus class="h-4 w-4" /> New folder
      </button>

      <div
        v-if="folders.length"
        @dragover="onDragOver('unfiled', $event)"
        @dragleave="dropKey = null"
        @drop.prevent="onDrop(null)"
      >
        <SidebarItem
          :href="route('links.index', { folder: 'unfiled' })"
          :icon="Inbox"
          label="Unfiled"
          :count="navigation?.unfiled_count ?? 0"
          :active="activeFolder === 'unfiled'"
          :drop-active="dropKey === 'unfiled'"
        />
      </div>
      <SidebarItem
        v-if="(navigation?.archived_count ?? 0) > 0"
        :href="route('links.index', { status: 'archived' })"
        :icon="Archive"
        label="Archived"
        :count="navigation?.archived_count ?? 0"
        :active="activeStatus === 'archived'"
      />
    </div>
  </div>
</template>
