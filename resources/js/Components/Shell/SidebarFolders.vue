<script setup lang="ts">
import { Archive, Folder, FolderOpen, Inbox, MoreHorizontal, Pencil, Plus, Trash2 } from '@lucide/vue';
import { computed } from 'vue';

import SidebarItem from '@/Components/Shell/SidebarItem.vue';
import { useFolderEditing } from '@/Components/Shell/useFolderEditing';
import { useLinkDrop } from '@/Components/Shell/useLinkDrop';
import Menu from '@/Components/ui/Menu.vue';
import MenuItem from '@/Components/ui/MenuItem.vue';
import MenuSeparator from '@/Components/ui/MenuSeparator.vue';
import { useShell } from '@/lib/shell';

const { navigation, canManage, query } = useShell();

const onLinks = computed(() => route().current('links.index'));
const activeFolder = computed(() => (onLinks.value ? (query.value.get('folder') ?? '') : null));
const activeStatus = computed(() => (onLinks.value ? (query.value.get('status') ?? '') : null));

const folders = computed(() => navigation.value?.folders ?? []);

const {
  creating,
  newName,
  newInput,
  renamingId,
  renameValue,
  renameInput,
  startCreate,
  commitCreate,
  startRename,
  commitRename,
  remove,
} = useFolderEditing(activeFolder);

const { dropKey, onDragOver, onDragLeave, onDrop } = useLinkDrop();
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
        @dragleave="onDragLeave"
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
        @dragleave="onDragLeave"
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
