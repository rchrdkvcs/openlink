<script setup lang="ts">
import Dropdown from '@/Components/Dropdown.vue';
import DropdownLink from '@/Components/DropdownLink.vue';
import { Link, usePage } from '@inertiajs/vue3';
import { BarChart3, Boxes, Check, ChevronsUpDown, Globe2, LayoutDashboard, Link2, LogOut, Menu, QrCode, Settings, User, Users, X } from '@lucide/vue';
import { computed, ref } from 'vue';

type Workspace = { id: number; name: string; slug: string };

const mobileNavOpen = ref(false);
const page = usePage();
const currentWorkspace = computed(() => page.props.currentWorkspace as Workspace | undefined);
const workspaces = computed(() => (page.props.workspaces ?? []) as Workspace[]);

const navItems = [
    { label: 'Overview', href: route('dashboard'), active: route().current('dashboard'), icon: LayoutDashboard },
    { label: 'Links', href: route('links.index'), active: route().current('links.index'), icon: Link2 },
    { label: 'QR Codes', href: route('qr-codes.index'), active: route().current('qr-codes.*'), icon: QrCode },
    { label: 'Analytics', href: route('analytics.index'), active: route().current('analytics.index'), icon: BarChart3 },
    { label: 'Domains', href: route('domains.index'), active: route().current('domains.index'), icon: Globe2 },
    { label: 'Members', href: route('members.index'), active: route().current('members.index'), icon: Users },
];

const accountItems = [
    { label: 'Workspaces', href: route('workspaces.index'), active: route().current('workspaces.index'), icon: Boxes },
    { label: 'Settings', href: route('settings.index'), active: route().current('settings.index'), icon: Settings },
];

function initial(name?: string) {
    return String(name ?? 'O').slice(0, 1).toUpperCase();
}
</script>

<template>
    <div class="min-h-screen bg-background text-foreground">
        <!-- Sidebar (desktop) — floating card -->
        <aside class="card-sheen fixed bottom-3 left-3 top-3 z-30 hidden w-60 flex-col rounded-lg border bg-surface lg:flex">
            <!-- Workspace switcher -->
            <div class="px-2.5 pb-2 pt-2.5">
                <Dropdown align="left" width="64" contentClasses="p-1">
                    <template #trigger>
                        <button
                            class="flex h-10 w-full items-center gap-2.5 rounded-md px-2 text-left transition-colors duration-150 hover:bg-elevated focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent/40"
                        >
                            <span class="grid h-6 w-6 shrink-0 place-items-center rounded-[6px] border bg-elevated text-xs font-semibold text-foreground">
                                {{ initial(currentWorkspace?.name) }}
                            </span>
                            <span class="min-w-0 flex-1 truncate text-sm font-medium">{{ currentWorkspace?.name ?? 'Openlink' }}</span>
                            <ChevronsUpDown class="h-3.5 w-3.5 shrink-0 text-faint" />
                        </button>
                    </template>

                    <template #content>
                        <p class="px-2.5 pb-1 pt-2 text-[11px] font-medium uppercase tracking-wide text-faint">Workspaces</p>
                        <Link
                            v-for="workspace in workspaces"
                            :key="workspace.id"
                            :href="route('workspaces.switch', workspace.id)"
                            method="post"
                            as="button"
                            class="flex w-full items-center gap-2.5 rounded-[5px] px-2.5 py-1.5 text-left text-[13px] text-muted transition-colors duration-100 hover:bg-elevated hover:text-foreground"
                        >
                            <span class="grid h-5 w-5 shrink-0 place-items-center rounded bg-elevated text-[10px] font-semibold text-muted">
                                {{ initial(workspace.name) }}
                            </span>
                            <span class="min-w-0 flex-1 truncate" :class="workspace.id === currentWorkspace?.id ? 'font-medium text-foreground' : ''">
                                {{ workspace.name }}
                            </span>
                            <Check v-if="workspace.id === currentWorkspace?.id" class="h-3.5 w-3.5 shrink-0 text-accent" />
                        </Link>
                        <div class="mx-1 my-1 border-t" />
                        <DropdownLink :href="route('workspaces.index')">Manage workspaces</DropdownLink>
                    </template>
                </Dropdown>
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

                <p class="px-2 pb-1 pt-5 text-[11px] font-medium uppercase tracking-wide text-faint">Manage</p>
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
                            <span class="grid h-7 w-7 shrink-0 place-items-center rounded-full border bg-elevated text-xs font-semibold text-foreground">
                                {{ initial($page.props.auth.user.name) }}
                            </span>
                            <span class="min-w-0 flex-1">
                                <span class="block truncate text-[13px] font-medium">{{ $page.props.auth.user.name }}</span>
                                <span class="block truncate text-xs text-faint">{{ $page.props.auth.user.email }}</span>
                            </span>
                            <ChevronsUpDown class="h-3.5 w-3.5 shrink-0 text-faint" />
                        </button>
                    </template>

                    <template #content>
                        <DropdownLink :href="route('profile.edit')">
                            <span class="inline-flex items-center gap-2"><User class="h-3.5 w-3.5" /> Profile</span>
                        </DropdownLink>
                        <DropdownLink :href="route('settings.index')">
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
                leave-active-class="transition-opacity ease-in duration-150"
                leave-from-class="opacity-100"
                leave-to-class="opacity-0"
            >
                <div v-if="mobileNavOpen" class="fixed inset-0 z-40 bg-background/70 backdrop-blur-[2px] lg:hidden" @click="mobileNavOpen = false" />
            </Transition>

            <Transition
                enter-active-class="transition-transform ease-emphasized-out duration-300"
                enter-from-class="-translate-x-full"
                enter-to-class="translate-x-0"
                leave-active-class="transition-transform ease-in duration-200"
                leave-from-class="translate-x-0"
                leave-to-class="-translate-x-full"
            >
                <aside v-if="mobileNavOpen" class="fixed inset-y-0 left-0 z-50 flex w-72 flex-col border-r bg-overlay lg:hidden">
                    <div class="flex h-14 items-center justify-between border-b px-4">
                        <span class="inline-flex items-center gap-2.5">
                            <span class="grid h-6 w-6 place-items-center rounded-[6px] border bg-elevated text-xs font-semibold text-foreground">
                                {{ initial(currentWorkspace?.name) }}
                            </span>
                            <span class="text-sm font-medium">{{ currentWorkspace?.name ?? 'Openlink' }}</span>
                        </span>
                        <button class="grid h-8 w-8 place-items-center rounded-md text-muted hover:bg-elevated hover:text-foreground" @click="mobileNavOpen = false">
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
                    <span class="grid h-6 w-6 shrink-0 place-items-center rounded-[6px] border bg-elevated text-xs font-semibold text-foreground">
                        {{ initial(currentWorkspace?.name) }}
                    </span>
                    <span class="truncate text-sm font-medium">{{ currentWorkspace?.name ?? 'Openlink' }}</span>
                </span>
            </header>

            <main class="flex-1">
                <div class="animate-slide-up">
                    <slot />
                </div>
            </main>
        </div>
    </div>
</template>
