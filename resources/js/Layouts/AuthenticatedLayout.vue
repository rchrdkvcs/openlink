<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Menu, Search } from '@lucide/vue';
import { onMounted, onUnmounted, ref } from 'vue';

import CommandPalette from '@/Components/Shell/CommandPalette.vue';
import SidebarContent from '@/Components/Shell/SidebarContent.vue';
import ConfirmHost from '@/Components/ui/ConfirmHost.vue';
import Toaster from '@/Components/ui/Toaster.vue';
import WorkspaceAvatar from '@/Components/WorkspaceAvatar.vue';
import CreateWorkspaceModal from '@/Components/Workspaces/CreateWorkspaceModal.vue';
import { useShell } from '@/lib/shell';

const mobileNavOpen = ref(false);
const paletteOpen = ref(false);
const showCreateWorkspace = ref(false);

const { workspace } = useShell();

function openPalette() {
  mobileNavOpen.value = false;
  paletteOpen.value = true;
}

function openCreateWorkspace() {
  mobileNavOpen.value = false;
  showCreateWorkspace.value = true;
}

function onKeydown(event: KeyboardEvent) {
  if ((event.metaKey || event.ctrlKey) && event.key.toLowerCase() === 'k') {
    event.preventDefault();
    paletteOpen.value = !paletteOpen.value;
  }
}

let removeNavigateListener: (() => void) | undefined;

onMounted(() => {
  document.addEventListener('keydown', onKeydown);
  removeNavigateListener = router.on('navigate', () => (mobileNavOpen.value = false));
});

onUnmounted(() => {
  document.removeEventListener('keydown', onKeydown);
  removeNavigateListener?.();
});
</script>

<template>
  <div class="min-h-screen bg-background text-foreground lg:h-dvh lg:min-h-0 lg:overflow-hidden">
    <aside class="fixed inset-y-0 left-0 z-30 hidden w-60 lg:block" aria-label="Sidebar">
      <SidebarContent @search="openPalette" @create-workspace="openCreateWorkspace" />
    </aside>

    <Teleport to="body">
      <Transition
        enter-active-class="transition-opacity ease-out duration-200"
        enter-from-class="opacity-0"
        leave-active-class="transition-opacity ease-out duration-150"
        leave-to-class="opacity-0"
      >
        <div
          v-if="mobileNavOpen"
          class="fixed inset-0 z-40 bg-black/60 backdrop-blur-[2px] lg:hidden"
          @click="mobileNavOpen = false"
        />
      </Transition>
      <Transition
        enter-active-class="transition-transform ease-emphasized-out duration-300"
        enter-from-class="-translate-x-full"
        leave-active-class="transition-transform ease-drawer duration-200"
        leave-to-class="-translate-x-full"
      >
        <aside
          v-if="mobileNavOpen"
          class="fixed inset-y-0 left-0 z-50 w-72 border-r bg-background shadow-drawer lg:hidden"
          aria-label="Sidebar"
        >
          <SidebarContent @search="openPalette" @create-workspace="openCreateWorkspace" />
        </aside>
      </Transition>
    </Teleport>

    <div class="flex min-h-screen flex-col lg:h-dvh lg:min-h-0 lg:py-2 lg:pl-60 lg:pr-2">
      <header class="material sticky top-0 z-20 flex h-14 shrink-0 items-center gap-3 border-b px-3 lg:hidden">
        <button
          type="button"
          class="grid h-9 w-9 place-items-center rounded-lg text-muted transition-colors hover:bg-elevated hover:text-foreground"
          aria-label="Open navigation"
          @click="mobileNavOpen = true"
        >
          <Menu class="h-5 w-5" />
        </button>
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
        class="flex-1 bg-canvas [scrollbar-gutter:stable] lg:min-h-0 lg:overflow-y-auto lg:overscroll-contain lg:rounded-xl lg:border"
      >
        <div class="lg:h-full">
          <slot />
        </div>
      </main>
    </div>

    <CommandPalette v-model:open="paletteOpen" />
    <CreateWorkspaceModal :show="showCreateWorkspace" @close="showCreateWorkspace = false" />
    <ConfirmHost />
    <Toaster />
  </div>
</template>
