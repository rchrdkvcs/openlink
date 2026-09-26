<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import {
  BarChart3,
  ChevronsUpDown,
  Globe2,
  LayoutDashboard,
  Link2,
  LogOut,
  Menu,
  QrCode,
  Settings,
  User,
  Users,
  X,
} from '@lucide/vue';
import {
  DialogClose,
  DialogContent,
  DialogOverlay,
  DialogPortal,
  DialogRoot,
  DialogTitle,
  DialogTrigger,
} from 'radix-vue';
import { computed, ref } from 'vue';

import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import Dropdown from '@/Components/Dropdown.vue';
import DropdownLink from '@/Components/DropdownLink.vue';
import UserAvatar from '@/Components/UserAvatar.vue';
import WorkspaceAvatar from '@/Components/WorkspaceAvatar.vue';
import CreateWorkspaceModal from '@/Components/Workspaces/CreateWorkspaceModal.vue';
import WorkspaceSettingsModal from '@/Components/Workspaces/WorkspaceSettingsModal.vue';
import WorkspaceSwitcher from '@/Components/Workspaces/WorkspaceSwitcher.vue';
import type { PageProps } from '@/types';

type Workspace = { id: number; name: string; slug: string; icon?: string | null; color?: string | null };

const mobileNavOpen = ref(false);
const showCreateWorkspace = ref(false);
const settingsWorkspaceId = ref<number | null>(null);
const showWorkspaceSettings = ref(false);

const page = usePage<PageProps>();
const currentWorkspace = computed(() => page.props.currentWorkspace as Workspace | undefined);
const user = computed(() => page.props.auth.user);

const navItems = computed(() => {
  // Track Inertia navigation even when the layout is preserved.
  void page.url;
  return [
    { label: 'Overview', href: route('dashboard'), active: route().current('dashboard'), icon: LayoutDashboard },
    { label: 'Links', href: route('links.index'), active: route().current('links.index'), icon: Link2 },
    { label: 'QR Codes', href: route('qr-codes.index'), active: route().current('qr-codes.*'), icon: QrCode },
    { label: 'Analytics', href: route('analytics.index'), active: route().current('analytics.index'), icon: BarChart3 },
    { label: 'Domains', href: route('domains.index'), active: route().current('domains.*'), icon: Globe2 },
    { label: 'Members', href: route('members.index'), active: route().current('members.index'), icon: Users },
  ];
});

const accountItems = computed(() =>
  user.value.is_instance_admin
    ? [
        {
          label: 'Settings',
          href: route('settings.index'),
          active: route().current('settings.index'),
          icon: Settings,
        },
      ]
    : [],
);

function openWorkspaceSettings(workspaceId: number) {
  settingsWorkspaceId.value = workspaceId;
  showWorkspaceSettings.value = true;
  mobileNavOpen.value = false;
}

function openCreateWorkspace() {
  showCreateWorkspace.value = true;
  mobileNavOpen.value = false;
}
</script>

<template>
  <div class="min-h-dvh bg-background text-foreground">
    <a
      href="#main-content"
      class="sr-only focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:z-[60] focus:rounded-full focus:bg-foreground focus:px-4 focus:py-2 focus:text-background"
      >Skip to content</a
    >
    <!-- Quiet navigation, with the workspace as the primary anchor. -->
    <aside class="fixed inset-y-0 start-0 z-30 hidden w-60 flex-col px-6 py-8 lg:flex">
      <WorkspaceSwitcher @open-settings="openWorkspaceSettings" @create="openCreateWorkspace" />

      <!-- Navigation -->
      <nav aria-label="Workspace navigation" class="my-auto min-h-0 space-y-1.5 overflow-y-auto py-10">
        <Link
          v-for="item in navItems"
          :key="item.label"
          :href="item.href"
          :aria-current="item.active ? 'page' : undefined"
          class="ui-nav-link group"
          :class="
            item.active
              ? 'bg-elevated font-medium text-foreground'
              : 'text-muted hover:bg-elevated/60 hover:text-foreground'
          "
        >
          <component
            :is="item.icon"
            class="h-4 w-4 shrink-0"
            :stroke-width="1.5"
            :class="item.active ? 'text-foreground' : 'text-faint group-hover:text-muted'"
          />
          <span>{{ item.label }}</span>
        </Link>

        <p v-if="accountItems.length" class="px-2 pb-1 pt-5 text-[11px] font-medium uppercase tracking-wide text-faint">
          Manage
        </p>
        <Link
          v-for="item in accountItems"
          :key="item.label"
          :href="item.href"
          :aria-current="item.active ? 'page' : undefined"
          class="ui-nav-link group"
          :class="
            item.active
              ? 'bg-elevated font-medium text-foreground'
              : 'text-muted hover:bg-elevated/60 hover:text-foreground'
          "
        >
          <component
            :is="item.icon"
            class="h-4 w-4 shrink-0"
            :stroke-width="1.5"
            :class="item.active ? 'text-foreground' : 'text-faint group-hover:text-muted'"
          />
          <span>{{ item.label }}</span>
        </Link>
      </nav>

      <!-- User menu -->
      <div class="shrink-0">
        <Dropdown align="left" width="64" placement="top" contentClasses="p-1">
          <template #trigger>
            <button
              class="flex w-full items-center gap-2.5 rounded-full px-2 py-2 text-left transition-colors duration-150 hover:bg-elevated focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent/40"
            >
              <UserAvatar :name="user.name" :src="user.profile_avatar_url" size="sm" />
              <span class="min-w-0 flex-1">
                <span class="block truncate text-[13px] font-medium">{{ user.name }}</span>
              </span>
              <ChevronsUpDown class="h-3.5 w-3.5 shrink-0 text-faint" />
            </button>
          </template>

          <template #content>
            <DropdownLink :href="route('profile.edit')">
              <span class="inline-flex items-center gap-2"><User class="h-3.5 w-3.5" /> Profile</span>
            </DropdownLink>
            <DropdownLink v-if="user.is_instance_admin" :href="route('settings.index')">
              <span class="inline-flex items-center gap-2"><Settings class="h-3.5 w-3.5" /> Settings</span>
            </DropdownLink>
            <div class="mx-1 my-1 border-t" />
            <DropdownLink :href="route('logout')" method="post" as="button">
              <span class="inline-flex items-center gap-2"><LogOut class="h-3.5 w-3.5" /> Log out</span>
            </DropdownLink>
          </template>
        </Dropdown>
      </div>
    </aside>

    <DialogRoot v-model:open="mobileNavOpen">
      <DialogPortal>
        <DialogOverlay class="ui-overlay fixed inset-0 z-40" />
        <DialogContent
          :aria-describedby="undefined"
          class="ui-drawer fixed inset-y-2 start-2 z-50 flex w-72 max-w-[calc(100vw-1rem)] flex-col p-3"
        >
          <DialogTitle class="sr-only">Navigation</DialogTitle>
          <div class="flex items-center gap-1 pb-3">
            <div class="min-w-0 flex-1">
              <WorkspaceSwitcher
                gear-visibility="always"
                @open-settings="openWorkspaceSettings"
                @create="openCreateWorkspace"
              />
            </div>
            <DialogClose
              aria-label="Close navigation"
              class="grid h-11 w-11 shrink-0 place-items-center rounded-full text-muted hover:bg-elevated"
            >
              <X class="h-4 w-4" />
            </DialogClose>
          </div>
          <nav aria-label="Workspace navigation" class="flex-1 space-y-1 overflow-y-auto py-4">
            <Link
              v-for="item in [...navItems, ...accountItems]"
              :key="item.label"
              :href="item.href"
              :aria-current="item.active ? 'page' : undefined"
              class="ui-nav-link"
              :class="
                item.active ? 'bg-elevated text-foreground' : 'text-muted hover:bg-elevated/60 hover:text-foreground'
              "
              @click="mobileNavOpen = false"
            >
              <component :is="item.icon" class="h-4 w-4" />
              {{ item.label }}
            </Link>
          </nav>
          <div class="space-y-0.5 border-t p-3">
            <Link :href="route('profile.edit')" class="ui-nav-link" @click="mobileNavOpen = false">
              <User class="h-4 w-4" /> Profile
            </Link>
            <Link :href="route('logout')" method="post" as="button" class="ui-nav-link w-full">
              <LogOut class="h-4 w-4" /> Log out
            </Link>
          </div>
        </DialogContent>
      </DialogPortal>

      <!-- Main column — no desktop top bar -->
      <div class="flex min-h-dvh min-w-0 flex-col lg:ps-60">
        <header
          class="sticky top-0 z-20 flex h-14 shrink-0 items-center gap-3 border-b bg-background/80 px-4 backdrop-blur-md sm:px-6 lg:hidden"
        >
          <DialogTrigger
            aria-label="Open navigation"
            class="grid h-11 w-11 shrink-0 place-items-center rounded-full text-muted transition-colors hover:bg-elevated hover:text-foreground"
          >
            <Menu class="h-5 w-5" />
          </DialogTrigger>

          <span class="inline-flex min-w-0 items-center gap-2.5">
            <WorkspaceAvatar
              :name="currentWorkspace?.name"
              :icon="currentWorkspace?.icon"
              :color="currentWorkspace?.color"
            />
            <span class="truncate text-sm font-medium">{{ currentWorkspace?.name ?? 'Openlink' }}</span>
          </span>

          <Link
            :href="route('dashboard')"
            aria-label="Openlink"
            class="ml-auto shrink-0 rounded-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent/40"
          >
            <ApplicationLogo class="h-4 w-auto" />
          </Link>
        </header>

        <main id="main-content" tabindex="-1" class="min-w-0 flex-1 outline-none">
          <div class="mx-auto w-full max-w-[1600px]">
            <slot />
          </div>
        </main>
      </div>
    </DialogRoot>

    <CreateWorkspaceModal :show="showCreateWorkspace" @close="showCreateWorkspace = false" />
    <WorkspaceSettingsModal
      :show="showWorkspaceSettings"
      :workspace-id="settingsWorkspaceId"
      @close="showWorkspaceSettings = false"
    />
  </div>
</template>
