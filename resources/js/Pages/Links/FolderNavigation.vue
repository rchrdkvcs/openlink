<script setup lang="ts">
import { Folder, Inbox, Layers, MoreHorizontal, Pencil, Plus, Trash2 } from '@lucide/vue';

import Dropdown from '@/Components/Dropdown.vue';
import Select from '@/Components/ui/Select.vue';
import SelectOption from '@/Components/ui/SelectOption.vue';

import type { LinkGroup } from './types';
defineProps<{
  groups: LinkGroup[];
  selected: string;
  total: number;
  canManage: boolean;
  canMove: boolean;
  dropKey: string | null;
}>();
const emit = defineEmits<{
  select: [key: string];
  create: [];
  rename: [group: LinkGroup];
  delete: [group: LinkGroup];
  dragOver: [key: string | null];
  drop: [group: LinkGroup];
}>();
</script>
<template>
  <nav aria-label="Link folders" class="min-w-0">
    <div class="mb-3 flex items-center justify-between px-2">
      <h2 class="text-xs font-medium text-muted">Library</h2>
      <button
        v-if="canManage"
        type="button"
        class="ui-icon-button h-7 w-7"
        aria-label="New folder"
        @click="emit('create')"
      >
        <Plus class="h-3.5 w-3.5" />
      </button>
    </div>
    <Select
      class="xl:hidden"
      aria-label="Folder navigation"
      :model-value="selected"
      @update:model-value="emit('select', String($event))"
    >
      <SelectOption value="all">All links · {{ total }}</SelectOption>
      <SelectOption v-for="group in groups" :key="group.key" :value="group.key"
        >{{ group.folder?.name ?? 'Unfiled' }} · {{ group.links.length }}</SelectOption
      >
    </Select>
    <div class="hidden space-y-1 xl:block">
      <button
        type="button"
        class="ui-folder-item pe-9"
        :aria-current="selected === 'all' ? 'page' : undefined"
        @click="emit('select', 'all')"
      >
        <Layers class="h-4 w-4 shrink-0" /><span class="flex-1 text-start">All links</span
        ><span class="text-xs tabular-nums text-faint">{{ total }}</span>
      </button>
      <div
        v-for="group in groups"
        :key="group.key"
        class="flex min-w-0 items-center rounded-xl"
        :class="dropKey === group.key ? 'bg-elevated ring-1 ring-accent' : ''"
        @dragover.prevent="canMove && emit('dragOver', group.key)"
        @dragleave="emit('dragOver', null)"
        @drop.prevent="canMove && emit('drop', group)"
      >
        <button
          type="button"
          class="ui-folder-item"
          :aria-current="selected === group.key ? 'page' : undefined"
          :title="group.folder?.name ?? 'Unfiled'"
          @click="emit('select', group.key)"
        >
          <component :is="group.folder ? Folder : Inbox" class="h-4 w-4 shrink-0" /><span
            class="min-w-0 flex-1 truncate text-start"
            >{{ group.folder?.name ?? 'Unfiled' }}</span
          ><span class="text-xs tabular-nums text-faint">{{ group.links.length }}</span>
        </button>
        <Dropdown v-if="group.folder && canManage" align="left">
          <template #trigger
            ><button type="button" class="ui-icon-button h-7 w-7" :aria-label="`Actions for ${group.folder.name}`">
              <MoreHorizontal class="h-3.5 w-3.5" /></button
          ></template>
          <template #content>
            <button
              type="button"
              class="ui-menu-item flex w-full items-center gap-2 px-3 py-2 text-[13px] hover:bg-elevated"
              @click="emit('rename', group)"
            >
              <Pencil class="h-3.5 w-3.5" /> Rename
            </button>
            <button
              type="button"
              class="ui-menu-item flex w-full items-center gap-2 px-3 py-2 text-[13px] text-danger hover:bg-danger/10"
              @click="emit('delete', group)"
            >
              <Trash2 class="h-3.5 w-3.5" /> Delete folder
            </button>
          </template>
        </Dropdown>
        <span v-else class="w-7 shrink-0" aria-hidden="true" />
      </div>
    </div>
    <p v-if="canMove" class="mt-5 hidden px-2 text-xs leading-relaxed text-faint xl:block">
      Drag links into a folder, or use the move action on a link.
    </p>
  </nav>
</template>
