<script setup lang="ts">
import { ArrowRight, Search, Settings } from '@lucide/vue';
import { computed, nextTick, ref, watch } from 'vue';

import { commandActions } from '@/Components/Shell/commandActions';
import { cycleIndex, groupCommands, queryCommands } from '@/Components/Shell/commandQuery';
import { type Command, navigationCommands } from '@/Components/Shell/commands';
import Dialog from '@/Components/ui/Dialog.vue';
import Kbd from '@/Components/ui/Kbd.vue';
import WorkspaceAvatar from '@/Components/WorkspaceAvatar.vue';
import { useShell } from '@/lib/shell';

const open = defineModel<boolean>('open', { default: false });

const { user, workspace, workspaces, navigation, canManage, canEdit } = useShell();

const query = ref('');
const activeIndex = ref(0);
const input = ref<HTMLInputElement | null>(null);
const list = ref<HTMLElement | null>(null);

const available = computed(() =>
  navigationCommands(
    {
      folders: navigation.value?.folders ?? [],
      canManage: canManage.value,
      isInstanceAdmin: Boolean(user.value.is_instance_admin),
      workspaces: workspaces.value,
      currentWorkspaceId: workspace.value?.id,
    },
    commandActions,
  ),
);

const commands = computed(() => queryCommands(query.value, canEdit.value, available.value, commandActions));

const grouped = computed(() => groupCommands(commands.value));

watch(query, () => (activeIndex.value = 0));
watch(open, (value) => {
  if (!value) return;
  query.value = '';
  activeIndex.value = 0;
  nextTick(() => input.value?.focus());
});

function run(command: Command | undefined) {
  if (!command) return;
  open.value = false;
  command.run();
}

function move(delta: number) {
  const count = commands.value.length;
  if (!count) return;
  activeIndex.value = cycleIndex(activeIndex.value, delta, count);
  nextTick(() => list.value?.querySelector('[data-active="true"]')?.scrollIntoView({ block: 'nearest' }));
}
</script>

<template>
  <Dialog v-model:open="open" size="lg" label="Command palette" class="top-[12vh] overflow-hidden">
    <div class="flex h-14 items-center gap-3 border-b px-4">
      <Search class="h-4 w-4 shrink-0 text-faint" />
      <input
        ref="input"
        v-model="query"
        type="text"
        spellcheck="false"
        autocomplete="off"
        aria-label="Search or paste a URL"
        placeholder="Paste a URL to shorten, or search…"
        class="h-full min-w-0 flex-1 bg-transparent text-[15px] text-foreground outline-none placeholder:text-faint"
        @keydown.down.prevent="move(1)"
        @keydown.up.prevent="move(-1)"
        @keydown.enter.prevent="run(commands[activeIndex])"
      />
      <Kbd>Esc</Kbd>
    </div>

    <div ref="list" class="max-h-[min(420px,60vh)] overflow-y-auto p-1.5" role="listbox">
      <div v-for="[group, items] in grouped" :key="group" class="pb-1">
        <p class="px-2.5 pb-1 pt-2 text-xs font-medium text-faint">{{ group }}</p>
        <button
          v-for="{ command, index } in items"
          :key="command.id"
          type="button"
          role="option"
          :aria-selected="index === activeIndex"
          :data-active="index === activeIndex"
          class="flex h-10 w-full items-center gap-3 rounded-lg px-2.5 text-left text-sm transition-colors duration-75"
          :class="index === activeIndex ? 'bg-elevated text-foreground' : 'text-muted'"
          @mousemove="activeIndex = index"
          @click="run(command)"
        >
          <WorkspaceAvatar
            v-if="command.workspace"
            :name="command.workspace.name"
            :icon="command.workspace.icon"
            size="sm"
          />
          <component
            :is="command.icon"
            v-else
            class="h-4 w-4 shrink-0"
            :class="command.id === 'shorten' ? 'text-accent' : ''"
          />
          <span class="min-w-0 flex-1 truncate" :class="index === activeIndex ? 'text-foreground' : ''">{{
            command.label
          }}</span>
          <span v-if="command.hint" class="shrink-0 text-xs text-faint">{{ command.hint }}</span>
          <ArrowRight v-if="index === activeIndex" class="h-3.5 w-3.5 shrink-0 text-faint" />
        </button>
      </div>
      <p v-if="!commands.length" class="px-3 py-10 text-center text-sm text-faint">No results</p>
    </div>

    <div class="flex items-center gap-4 border-t px-4 py-2.5 text-xs text-faint">
      <span class="flex items-center gap-1.5"><Kbd>↑</Kbd><Kbd>↓</Kbd> navigate</span>
      <span class="flex items-center gap-1.5"><Kbd>↵</Kbd> open</span>
      <span class="ml-auto hidden items-center gap-1.5 sm:flex"
        ><Settings class="h-3 w-3" /> Tip: paste any URL here</span
      >
    </div>
  </Dialog>
</template>
