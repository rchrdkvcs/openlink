<script setup lang="ts">
import { BarChart3, Home, Link2, QrCode, Search } from '@lucide/vue';
import { computed } from 'vue';

import SidebarFolders from '@/Components/Shell/SidebarFolders.vue';
import SidebarItem from '@/Components/Shell/SidebarItem.vue';
import UserMenu from '@/Components/Shell/UserMenu.vue';
import WorkspaceMenu from '@/Components/Shell/WorkspaceMenu.vue';
import Kbd from '@/Components/ui/Kbd.vue';
import { isMac, useShell } from '@/lib/shell';

const emit = defineEmits<{ search: []; createWorkspace: [] }>();

const { navigation, query } = useShell();

const allLinksActive = computed(
  () => route().current('links.index') && !query.value.get('folder') && query.value.get('status') !== 'archived',
);

const modifier = isMac() ? '⌘' : 'Ctrl';
</script>

<template>
  <div class="flex h-full flex-col">
    <div class="px-3 pt-3">
      <WorkspaceMenu @create="emit('createWorkspace')" />

      <button
        type="button"
        class="mt-2 flex h-8 w-full items-center gap-2 rounded-lg bg-elevated/60 pl-2.5 pr-1.5 text-left text-[13px] text-faint transition-colors hover:bg-elevated hover:text-muted focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent/40"
        @click="emit('search')"
      >
        <Search class="h-3.5 w-3.5 shrink-0" />
        <span class="min-w-0 flex-1 truncate">Search or paste a URL</span>
        <Kbd>{{ modifier }} K</Kbd>
      </button>
    </div>

    <nav class="mt-4 min-h-0 flex-1 overflow-y-auto px-3 pb-3" aria-label="Main">
      <div class="space-y-px">
        <SidebarItem :href="route('dashboard')" :icon="Home" label="Home" :active="route().current('dashboard')" />
        <SidebarItem
          :href="route('links.index')"
          :icon="Link2"
          label="Links"
          :count="navigation?.links_count ?? null"
          :active="allLinksActive"
        />
        <SidebarItem
          :href="route('qr-codes.index')"
          :icon="QrCode"
          label="QR codes"
          :active="route().current('qr-codes.*')"
        />
        <SidebarItem
          :href="route('analytics.index')"
          :icon="BarChart3"
          label="Analytics"
          :active="route().current('analytics.*')"
        />
      </div>

      <SidebarFolders v-if="navigation" class="mt-5" />
    </nav>

    <div class="px-3 pb-3 pt-2">
      <UserMenu />
    </div>
  </div>
</template>
