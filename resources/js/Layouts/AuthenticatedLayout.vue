<script setup lang="ts">
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import Dropdown from '@/Components/Dropdown.vue';
import DropdownLink from '@/Components/DropdownLink.vue';
import UserAvatar from '@/Components/UserAvatar.vue';
import WorkspaceAvatar from '@/Components/WorkspaceAvatar.vue';
import CreateWorkspaceModal from '@/Components/Workspaces/CreateWorkspaceModal.vue';
import WorkspaceSettingsModal from '@/Components/Workspaces/WorkspaceSettingsModal.vue';
import WorkspaceSwitcher from '@/Components/Workspaces/WorkspaceSwitcher.vue';
import type { PageProps } from '@/types';
import { Link, usePage } from '@inertiajs/vue3';
import { BarChart3, ChevronsUpDown, Globe2, LayoutDashboard, Link2, LogOut, Menu, QrCode, Settings, User, Users, X } from '@lucide/vue';
import { computed, ref } from 'vue';

type Workspace = { id: number; name: string; slug: string; icon?: string | null; color?: string | null };

const mobileNavOpen = ref(false);
const showCreateWorkspace = ref(false);
const settingsWorkspaceId = ref<number | null>(null);
const showWorkspaceSettings = ref(false);

const page = usePage<PageProps>();
const currentWorkspace = computed(() => page.props.currentWorkspace as Workspace | undefined);
const user = computed(() => page.props.auth.user);

const navItems = [
    { label: 'Overview', href: route('dashboard'), active: route().current('dashboard'), icon: LayoutDashboard },
    { label: 'Links', href: route('links.index'), active: route().current('links.index'), icon: Link2 },
    { label: 'QR Codes', href: route('qr-codes.index'), active: route().current('qr-codes.*'), icon: QrCode },
    { label: 'Analytics', href: route('analytics.index'), active: route().current('analytics.index'), icon: BarChart3 },
    { label: 'Domains', href: route('domains.index'), active: route().current('domains.index'), icon: Globe2 },
    { label: 'Members', href: route('members.index'), active: route().current('members.index'), icon: Users },
];

const accountItems = computed(() =>
    user.value.is_instance_admin
        ? [{ label: 'Settings', href: route('settings.index'), active: route().current('settings.index'), icon: Settings }]
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
    <div class="min-h-screen bg-background text-foreground">
        <!-- Sidebar (desktop) — floating card -->
        <aside class="card-sheen fixed bottom-3 left-3 top-3 z-30 hidden w-60 flex-col rounded-lg border bg-surface lg:flex">
            <div class="flex h-12 shrink-0 items-center px-4">
                <Link
                    :href="route('dashboard')"
                    aria-label="Openlink"
                    class="rounded-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent/40"
                >
                    <ApplicationLogo class="h-4 w-auto" />
                </Link>
            </div>

            <div class="mx-2.5 border-t" />

            <!-- Workspace switcher -->
            <div class="px-2.5 pb-2 pt-2.5">
                <WorkspaceSwitcher @open-settings="openWorkspaceSettings" @create="openCreateWorkspace" />
            </div>

            <div class="mx-2.5 border-t" />

            <!-- Navigation -->
            <nav class="flex-1 space-y-0.5 overflow-y-auto px-2.5 py-2.5">
                <Link
                    v-for="item in navItems"
                    :key="item.label"
                    :href="item.href"
                    class="group flex h-8 items-center gap-2.5 rounded-md px-2 text-[13px] font-medium transition-colors duration-100"
                    :class="item.active ? 'bg-elevated text-foreground ring-1 ring-inset ring-border' : 'text-muted hover:bg-elevated/60 hover:text-foreground'"
                >
                    <component :is="item.icon" class="h-4 w-4 shrink-0" :class="item.active ? 'text-foreground' : 'text-faint group-hover:text-muted'" />
                    <span>{{ item.label }}</span>
                </Link>

                <p v-if="accountItems.length" class="px-2 pb-1 pt-5 text-[11px] font-medium uppercase tracking-wide text-faint">Manage</p>
                <Link
                    v-for="item in accountItems"
                    :key="item.label"
                    :href="item.href"
                    class="group flex h-8 items-center gap-2.5 rounded-md px-2 text-[13px] font-medium transition-colors duration-100"
                    :class="item.active ? 'bg-elevated text-foreground ring-1 ring-inset ring-border' : 'text-muted hover:bg-elevated/60 hover:text-foreground'"
                >
                    <component :is="item.icon" class="h-4 w-4 shrink-0" :class="item.active ? 'text-foreground' : 'text-faint group-hover:text-muted'" />
                    <span>{{ item.label }}</span>
                </Link>
            </nav>

            <!-- User menu -->
            <div class="border-t p-2">
                <Dropdown align="left" width="64" placement="top" contentClasses="p-1">
                    <template #trigger>
                        <button
                            class="flex w-full items-center gap-2.5 rounded-md px-2 py-1.5 text-left transition-colors duration-150 hover:bg-elevated focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent/40"
                        >
                            <UserAvatar :name="user.name" :src="user.profile_avatar_url" size="sm" />
                            <span class="min-w-0 flex-1">
                                <span class="block truncate text-[13px] font-medium">{{ user.name }}</span>
                                <span class="block truncate text-xs text-faint">{{ user.email }}</span>
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

        <!-- Mobile nav overlay -->
        <Teleport to="body">
            <Transition
                enter-active-class="transition-opacity ease-out duration-200"
                enter-from-class="opacity-0"
                enter-to-class="opacity-100"
                leave-active-class="transition-opacity ease-out duration-150"
                leave-from-class="opacity-100"
                leave-to-class="opacity-0"
            >
                <div v-if="mobileNavOpen" class="fixed inset-0 z-40 bg-background/70 backdrop-blur-[2px] lg:hidden" @click="mobileNavOpen = false" />
            </Transition>

            <Transition
                enter-active-class="transition-transform ease-emphasized-out duration-300"
                enter-from-class="-translate-x-full"
                enter-to-class="translate-x-0"
                leave-active-class="transition-transform ease-drawer duration-200"
                leave-from-class="translate-x-0"
                leave-to-class="-translate-x-full"
            >
                <aside v-if="mobileNavOpen" class="fixed inset-y-0 left-0 z-50 flex w-72 flex-col border-r bg-overlay lg:hidden">
                    <div class="flex h-14 items-center gap-1 border-b px-2">
                        <div class="min-w-0 flex-1">
                            <WorkspaceSwitcher gear-visibility="always" @open-settings="openWorkspaceSettings" @create="openCreateWorkspace" />
                        </div>
                        <button
                            class="grid h-8 w-8 shrink-0 place-items-center rounded-md text-muted hover:bg-elevated hover:text-foreground"
                            @click="mobileNavOpen = false"
                        >
                            <X class="h-4 w-4" />
                        </button>
                    </div>
                    <nav class="flex-1 space-y-0.5 overflow-y-auto p-3">
                        <Link
                            v-for="item in [...navItems, ...accountItems]"
                            :key="item.label"
                            :href="item.href"
                            class="flex h-9 items-center gap-2.5 rounded-md px-2.5 text-sm font-medium transition-colors duration-100"
                            :class="item.active ? 'bg-elevated text-foreground' : 'text-muted hover:bg-elevated/60 hover:text-foreground'"
                            @click="mobileNavOpen = false"
                        >
                            <component :is="item.icon" class="h-4 w-4" />
                            {{ item.label }}
                        </Link>
                    </nav>
                    <div class="space-y-0.5 border-t p-3">
                        <Link
                            :href="route('profile.edit')"
                            class="flex h-9 items-center gap-2.5 rounded-md px-2.5 text-sm text-muted hover:bg-elevated hover:text-foreground"
                            @click="mobileNavOpen = false"
                        >
                            <User class="h-4 w-4" /> Profile
                        </Link>
                        <Link
                            :href="route('logout')"
                            method="post"
                            as="button"
                            class="flex h-9 w-full items-center gap-2.5 rounded-md px-2.5 text-sm text-muted hover:bg-elevated hover:text-foreground"
                        >
                            <LogOut class="h-4 w-4" /> Log out
                        </Link>
                    </div>
                </aside>
            </Transition>
        </Teleport>

        <!-- Main column — no desktop top bar -->
        <div class="flex min-h-screen flex-col lg:pl-[16.5rem]">
            <header class="sticky top-0 z-20 flex h-14 shrink-0 items-center gap-3 border-b bg-background/80 px-4 backdrop-blur-md sm:px-6 lg:hidden">
                <button
                    type="button"
                    class="grid h-8 w-8 place-items-center rounded-md text-muted transition-colors hover:bg-elevated hover:text-foreground"
                    @click="mobileNavOpen = true"
                >
                    <Menu class="h-4 w-4" />
                </button>

                <span class="inline-flex min-w-0 items-center gap-2.5">
                    <WorkspaceAvatar :name="currentWorkspace?.name" :icon="currentWorkspace?.icon" :color="currentWorkspace?.color" />
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

            <main class="flex-1">
                <div class="animate-slide-up">
                    <slot />
                </div>
            </main>
        </div>

        <CreateWorkspaceModal :show="showCreateWorkspace" @close="showCreateWorkspace = false" />
        <WorkspaceSettingsModal :show="showWorkspaceSettings" :workspace-id="settingsWorkspaceId" @close="showWorkspaceSettings = false" />
    </div>
</template>
