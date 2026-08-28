<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { Check, ChevronsUpDown, Plus, Settings2 } from '@lucide/vue';
import { computed } from 'vue';

import Dropdown from '@/Components/Dropdown.vue';
import WorkspaceAvatar from '@/Components/WorkspaceAvatar.vue';

type Workspace = {
  id: number;
  name: string;
  slug: string;
  icon?: string | null;
  color?: string | null;
  pivot?: { role?: string };
};

const props = withDefaults(
  defineProps<{
    /** 'hover' reveals the settings gear on row hover (pointer devices); 'always' keeps it visible (touch). */
    gearVisibility?: 'hover' | 'always';
  }>(),
  { gearVisibility: 'hover' },
);

const emit = defineEmits<{ openSettings: [workspaceId: number]; create: [] }>();

const page = usePage();
const currentWorkspace = computed(() => page.props.currentWorkspace as Workspace | undefined);
const workspaces = computed(() => (page.props.workspaces ?? []) as Workspace[]);

const gearClass = computed(() =>
  props.gearVisibility === 'hover' ? 'opacity-0 focus-visible:opacity-100 group-hover:opacity-100' : '',
);

function canManage(workspace: Workspace) {
  return ['owner', 'admin'].includes(workspace.pivot?.role ?? '');
}

const switchDestination = computed(() => {
  const sections = [
    ['links.*', 'links.index'],
    ['qr-codes.*', 'qr-codes.index'],
    ['analytics.*', 'analytics.index'],
    ['domains.*', 'domains.index'],
    ['members.*', 'members.index'],
    ['settings.*', 'settings.index'],
  ];

  return sections.find(([pattern]) => route().current(pattern))?.[1] ?? 'dashboard';
});
</script>

<template>
  <Dropdown align="left" width="64" contentClasses="p-1">
    <template #trigger>
      <button
        class="flex h-10 w-full items-center gap-2.5 rounded-md px-2 text-left transition-colors duration-150 hover:bg-elevated focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent/40"
      >
        <WorkspaceAvatar
          :name="currentWorkspace?.name"
          :icon="currentWorkspace?.icon"
          :color="currentWorkspace?.color"
        />
        <span class="min-w-0 flex-1 truncate text-sm font-medium">{{ currentWorkspace?.name ?? 'Openlink' }}</span>
        <ChevronsUpDown class="h-3.5 w-3.5 shrink-0 text-faint" />
      </button>
    </template>

    <template #content>
      <p class="px-2.5 pb-1 pt-2 text-[11px] font-medium uppercase tracking-wide text-faint">Workspaces</p>
      <div
        v-for="workspace in workspaces"
        :key="workspace.id"
        class="group flex items-center gap-1 rounded-[5px] transition-colors duration-100 hover:bg-elevated"
      >
        <Link
          :href="route('workspaces.switch', workspace.id)"
          method="post"
          as="button"
          :data="{ destination: switchDestination }"
          :preserve-state="false"
          class="flex min-w-0 flex-1 items-center gap-2.5 px-2.5 py-1.5 text-left text-[13px] text-muted hover:text-foreground"
        >
          <WorkspaceAvatar :name="workspace.name" :icon="workspace.icon" :color="workspace.color" size="sm" />
          <span
            class="min-w-0 flex-1 truncate"
            :class="workspace.id === currentWorkspace?.id ? 'font-medium text-foreground' : ''"
          >
            {{ workspace.name }}
          </span>
          <Check v-if="workspace.id === currentWorkspace?.id" class="h-3.5 w-3.5 shrink-0 text-accent" />
        </Link>
        <button
          v-if="canManage(workspace)"
          type="button"
          title="Workspace settings"
          class="mr-1 grid h-6 w-6 shrink-0 place-items-center rounded text-faint transition-opacity duration-100 hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent/40"
          :class="gearClass"
          @click="emit('openSettings', workspace.id)"
        >
          <Settings2 class="h-3.5 w-3.5" />
        </button>
      </div>
      <div class="mx-1 my-1 border-t" />
      <button
        type="button"
        class="block w-full rounded-[5px] px-2.5 py-1.5 text-start text-[13px] text-muted transition-colors duration-100 hover:bg-elevated hover:text-foreground focus:bg-elevated focus:text-foreground focus:outline-none"
        @click="emit('create')"
      >
        <span class="inline-flex items-center gap-2"><Plus class="h-3.5 w-3.5" /> Create workspace</span>
      </button>
    </template>
  </Dropdown>
</template>
