<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { ChevronsUpDown, KeyRound, LogOut, Server, ShieldCheck, User } from '@lucide/vue';

import Menu from '@/Components/ui/Menu.vue';
import MenuItem from '@/Components/ui/MenuItem.vue';
import MenuSeparator from '@/Components/ui/MenuSeparator.vue';
import UserAvatar from '@/Components/UserAvatar.vue';
import { useShell } from '@/lib/shell';

const { user } = useShell();

function account(tab: string) {
  router.visit(route('profile.edit', { tab }));
}
</script>

<template>
  <Menu align="start" side="top" width="w-60">
    <template #trigger>
      <button
        type="button"
        class="flex w-full items-center gap-2.5 rounded-lg px-2 py-1.5 text-left transition-colors duration-150 hover:bg-elevated/70 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent/40 data-[state=open]:bg-elevated/70"
      >
        <UserAvatar :name="user.name" :src="user.profile_avatar_url" size="sm" />
        <span class="min-w-0 flex-1">
          <span class="block truncate text-[13px] font-medium">{{ user.name }}</span>
          <span class="block truncate text-xs text-faint">{{ user.email }}</span>
        </span>
        <ChevronsUpDown class="h-3.5 w-3.5 shrink-0 text-faint" />
      </button>
    </template>

    <MenuItem :icon="User" @select="account('profile')">Profile</MenuItem>
    <MenuItem :icon="ShieldCheck" @select="account('security')">Security</MenuItem>
    <MenuItem :icon="KeyRound" @select="account('api-tokens')">API tokens</MenuItem>
    <MenuItem v-if="user.is_instance_admin" :icon="Server" @select="router.visit(route('settings.index'))">
      Instance settings
    </MenuItem>
    <MenuSeparator />
    <MenuItem :icon="LogOut" @select="router.post(route('logout'))">Log out</MenuItem>
  </Menu>
</template>
