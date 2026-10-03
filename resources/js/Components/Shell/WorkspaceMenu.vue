<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Check, ChevronsUpDown, Globe2, Plus, Settings, UserPlus } from '@lucide/vue';
import { computed } from 'vue';

import Menu from '@/Components/ui/Menu.vue';
import MenuItem from '@/Components/ui/MenuItem.vue';
import MenuLabel from '@/Components/ui/MenuLabel.vue';
import MenuSeparator from '@/Components/ui/MenuSeparator.vue';
import WorkspaceAvatar from '@/Components/WorkspaceAvatar.vue';
import { useShell } from '@/lib/shell';

const emit = defineEmits<{ create: [] }>();

const { workspace, workspaces, canManage } = useShell();

const switchDestination = computed(() => {
  const sections = [
    ['links.*', 'links.index'],
    ['qr-codes.*', 'qr-codes.index'],
    ['analytics.*', 'analytics.index'],
    ['domains.*', 'domains.index'],
    ['members.*', 'members.index'],
    ['settings.workspace', 'settings.workspace'],
    ['settings.index', 'settings.index'],
  ];

  return sections.find(([pattern]) => route().current(pattern))?.[1] ?? 'dashboard';
});

function switchTo(id: number) {
  if (id === workspace.value?.id) return;
  router.post(route('workspaces.switch', id), { destination: switchDestination.value }, { preserveState: false });
}
</script>

<template>
  <Menu align="start" width="w-64">
    <template #trigger>
      <button
        type="button"
        class="group flex h-10 w-full items-center gap-2.5 rounded-lg px-2 text-left transition-colors duration-150 hover:bg-elevated/70 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent/40 data-[state=open]:bg-elevated/70"
      >
        <WorkspaceAvatar :name="workspace?.name" :icon="workspace?.icon" />
        <span class="min-w-0 flex-1 truncate text-sm font-semibold tracking-[-0.01em]">{{
          workspace?.name ?? 'Openlink'
        }}</span>
        <ChevronsUpDown class="h-3.5 w-3.5 shrink-0 text-faint transition-colors group-hover:text-muted" />
      </button>
    </template>

    <MenuLabel>Workspaces</MenuLabel>
    <MenuItem v-for="item in workspaces" :key="item.id" @select="switchTo(item.id)">
      <span class="flex items-center gap-2.5">
        <WorkspaceAvatar :name="item.name" :icon="item.icon" size="sm" />
        <span class="min-w-0 flex-1 truncate" :class="item.id === workspace?.id ? 'font-medium' : ''">{{
          item.name
        }}</span>
        <Check v-if="item.id === workspace?.id" class="h-3.5 w-3.5 shrink-0 text-accent" />
      </span>
    </MenuItem>
    <MenuItem :icon="Plus" @select="emit('create')">New workspace</MenuItem>
    <template v-if="canManage">
      <MenuSeparator />
      <MenuItem :icon="Settings" @select="router.visit(route('settings.workspace'))">Workspace settings</MenuItem>
      <MenuItem :icon="Globe2" @select="router.visit(route('domains.index'))">Domains</MenuItem>
      <MenuItem :icon="UserPlus" @select="router.visit(route('members.index'))">Members &amp; invites</MenuItem>
    </template>
  </Menu>
</template>
