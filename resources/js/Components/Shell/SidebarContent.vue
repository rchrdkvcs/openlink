<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { BarChart3, Home, LayoutList, Link2, QrCode, Search, TriangleAlert } from '@lucide/vue';
import { computed, onMounted, onUnmounted } from 'vue';

import SidebarFolders from '@/Components/Shell/SidebarFolders.vue';
import SidebarItem from '@/Components/Shell/SidebarItem.vue';
import UserMenu from '@/Components/Shell/UserMenu.vue';
import WorkspaceMenu from '@/Components/Shell/WorkspaceMenu.vue';
import Kbd from '@/Components/ui/Kbd.vue';
import {
  SidebarContent,
  SidebarFooter,
  SidebarHeader,
  SidebarMenu,
  SidebarTrigger,
  useSidebar,
} from '@/Components/ui/sidebar';
import { isMac, useShell } from '@/lib/shell';

const emit = defineEmits<{ search: []; createWorkspace: [] }>();

const { setOpenMobile } = useSidebar();
let removeNavigateListener: (() => void) | undefined;
onMounted(() => {
  removeNavigateListener = router.on('navigate', () => setOpenMobile(false));
});
onUnmounted(() => removeNavigateListener?.());

function search() {
  setOpenMobile(false);
  emit('search');
}

function createWorkspace() {
  setOpenMobile(false);
  emit('createWorkspace');
}

const { navigation, query } = useShell();

const allLinksActive = computed(
  () => route().current('links.index') && !query.value.get('folder') && query.value.get('status') !== 'archived',
);

const modifier = isMac() ? '⌘' : 'Ctrl';
</script>

<template>
  <div class="flex h-full flex-col">
    <SidebarHeader class="gap-2 px-3 pt-3">
      <div class="flex items-center gap-1 group-data-[collapsible=icon]:flex-col">
        <div class="min-w-0 flex-1 group-data-[collapsible=icon]:flex-none">
          <WorkspaceMenu @create="createWorkspace" />
        </div>
        <SidebarTrigger class="shrink-0" />
      </div>

      <button
        type="button"
        class="flex h-8 w-full items-center gap-2 rounded-lg bg-elevated/60 pl-2.5 pr-1.5 text-left text-[13px] text-faint transition-colors hover:bg-elevated hover:text-muted focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent/40 group-data-[collapsible=icon]:w-8 group-data-[collapsible=icon]:justify-center group-data-[collapsible=icon]:p-0"
        aria-label="Search or paste a URL"
        title="Search or paste a URL"
        @click="search"
      >
        <Search class="h-3.5 w-3.5 shrink-0" />
        <span class="min-w-0 flex-1 truncate group-data-[collapsible=icon]:hidden">Search or paste a URL</span>
        <Kbd class="group-data-[collapsible=icon]:hidden">{{ modifier }} K</Kbd>
      </button>
    </SidebarHeader>

    <SidebarContent class="gap-0">
      <nav class="mt-4 min-h-0 flex-1 px-3 pb-3" aria-label="Main">
        <SidebarMenu class="gap-px">
          <SidebarItem :href="route('dashboard')" :icon="Home" label="Home" :active="route().current('dashboard')" />
          <SidebarItem
            :href="route('links.index')"
            :icon="Link2"
            label="Links"
            :count="navigation?.links_count ?? null"
            :active="allLinksActive"
          />
          <SidebarItem
            v-if="navigation?.destination_issues_count"
            :href="route('destination-issues.index')"
            :icon="TriangleAlert"
            label="Issues"
            :count="navigation.destination_issues_count"
            :active="route().current('destination-issues.index')"
          />
          <SidebarItem
            :href="route('qr-codes.index')"
            :icon="QrCode"
            label="QR codes"
            :active="route().current('qr-codes.*')"
          />
          <SidebarItem
            :href="route('link-pages.index')"
            :icon="LayoutList"
            label="Link pages"
            :active="route().current('link-pages.*')"
          />
          <SidebarItem
            :href="route('analytics.index')"
            :icon="BarChart3"
            label="Analytics"
            :active="route().current('analytics.*')"
          />
        </SidebarMenu>

        <SidebarFolders v-if="navigation" class="mt-5" />
      </nav>
    </SidebarContent>

    <SidebarFooter class="px-3 pb-3 pt-2">
      <UserMenu />
    </SidebarFooter>
  </div>
</template>
