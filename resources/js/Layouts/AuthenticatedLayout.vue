<script setup lang="ts">
import { Search } from '@lucide/vue';
import { useStorage } from '@vueuse/core';
import { onMounted, onUnmounted, ref } from 'vue';

import CommandPalette from '@/Components/Shell/CommandPalette.vue';
import SidebarContent from '@/Components/Shell/SidebarContent.vue';
import SidebarResize from '@/Components/Shell/SidebarResize.vue';
import ConfirmHost from '@/Components/ui/ConfirmHost.vue';
import { Sidebar, SidebarInset, SidebarProvider, SidebarTrigger } from '@/Components/ui/sidebar';
import Toaster from '@/Components/ui/Toaster.vue';
import WorkspaceAvatar from '@/Components/WorkspaceAvatar.vue';
import CreateWorkspaceModal from '@/Components/Workspaces/CreateWorkspaceModal.vue';
import { useShell } from '@/lib/shell';

const props = withDefaults(defineProps<{ panels?: boolean }>(), { panels: false });

const sidebarWidth = useStorage('openlink-sidebar-width', 240);
sidebarWidth.value = Number.isFinite(sidebarWidth.value) ? Math.min(384, Math.max(208, sidebarWidth.value)) : 240;
const resizingSidebar = ref(false);
const paletteOpen = ref(false);
const showCreateWorkspace = ref(false);

const { workspace } = useShell();

function openPalette() {
  paletteOpen.value = true;
}

function openCreateWorkspace() {
  showCreateWorkspace.value = true;
}

function onKeydown(event: KeyboardEvent) {
  if ((event.metaKey || event.ctrlKey) && event.key.toLowerCase() === 'k') {
    event.preventDefault();
    paletteOpen.value = !paletteOpen.value;
  }
}

onMounted(() => {
  document.addEventListener('keydown', onKeydown);
});

onUnmounted(() => {
  document.removeEventListener('keydown', onKeydown);
});
</script>

<template>
  <SidebarProvider
    class="min-h-screen bg-background text-foreground lg:h-dvh lg:min-h-0 lg:overflow-hidden"
    :data-resizing="resizingSidebar"
    :style="{ '--sidebar-width': `${sidebarWidth}px`, '--sidebar-width-icon': '3.5rem' }"
  >
    <Sidebar collapsible="icon" class="group-data-[side=left]:border-r-0">
      <SidebarContent @search="openPalette" @create-workspace="openCreateWorkspace" />
      <SidebarResize v-model="sidebarWidth" @resizing="resizingSidebar = $event" />
    </Sidebar>

    <SidebarInset class="min-w-0 bg-background lg:h-dvh lg:min-h-0 lg:py-2 lg:pr-2">
      <header class="material sticky top-0 z-20 flex h-14 shrink-0 items-center gap-3 border-b px-3 lg:hidden">
        <SidebarTrigger class="h-9 w-9" />
        <span class="flex min-w-0 flex-1 items-center gap-2.5">
          <WorkspaceAvatar :name="workspace?.name" :icon="workspace?.icon" />
          <span class="truncate text-sm font-semibold">{{ workspace?.name ?? 'Openlink' }}</span>
        </span>
        <button
          type="button"
          class="grid h-9 w-9 place-items-center rounded-lg text-muted transition-colors hover:bg-elevated hover:text-foreground"
          aria-label="Search"
          @click="openPalette"
        >
          <Search class="h-[18px] w-[18px]" />
        </button>
      </header>

      <main
        id="app-scroll"
        :class="[
          'flex-1 bg-canvas lg:min-h-0 lg:overflow-y-auto lg:overscroll-contain',
          props.panels ? 'lg:bg-transparent' : '[scrollbar-gutter:stable] lg:rounded-xl lg:border',
        ]"
      >
        <div class="lg:h-full">
          <slot />
        </div>
      </main>
    </SidebarInset>

    <CommandPalette v-model:open="paletteOpen" />
    <CreateWorkspaceModal :show="showCreateWorkspace" @close="showCreateWorkspace = false" />
    <ConfirmHost />
    <Toaster />
  </SidebarProvider>
</template>
