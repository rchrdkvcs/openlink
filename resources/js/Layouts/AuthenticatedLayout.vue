<script setup lang="ts">
import Dropdown from '@/Components/Dropdown.vue';
import DropdownLink from '@/Components/DropdownLink.vue';
import { Link, usePage } from '@inertiajs/vue3';
import { Boxes, ChevronDown, Globe2, LayoutDashboard, Link2, LogOut, Menu, Settings, User, Users, X } from '@lucide/vue';
import { computed, ref } from 'vue';

type Workspace = { id: number; name: string; slug: string };

const showingNavigationDropdown = ref(false);
const page = usePage();
const currentWorkspace = computed(() => page.props.currentWorkspace as Workspace | undefined);
const workspaces = computed(() => (page.props.workspaces ?? []) as Workspace[]);

const navItems = [
    { label: 'Overview', href: route('dashboard'), active: route().current('dashboard'), icon: LayoutDashboard },
    { label: 'Links', href: route('links.index'), active: route().current('links.index'), icon: Link2 },
    { label: 'Domains', href: route('domains.index'), active: route().current('domains.index'), icon: Globe2 },
    { label: 'Members', href: route('members.index'), active: route().current('members.index'), icon: Users },
    { label: 'Workspaces', href: route('workspaces.index'), active: route().current('workspaces.index'), icon: Boxes },
    { label: 'Settings', href: route('settings.index'), active: route().current('settings.index'), icon: Settings },
];
</script>

<template>
    <div class="min-h-screen bg-[#fafafa] text-neutral-950">
        <aside class="fixed inset-y-0 left-0 z-30 hidden w-64 border-r border-neutral-200 bg-white lg:flex lg:flex-col">
            <div class="flex h-16 items-center border-b border-neutral-200 px-3">
                <Dropdown align="left" width="72" contentClasses="bg-white py-1">
                    <template #trigger>
                        <button class="flex h-10 w-full items-center gap-3 rounded-md px-2 text-left transition hover:bg-neutral-100">
                            <span class="grid h-8 w-8 shrink-0 place-items-center rounded-md bg-neutral-950 text-sm font-semibold text-white">
                                {{ String(currentWorkspace?.name ?? 'S').slice(0, 1).toUpperCase() }}
                            </span>
                            <span class="min-w-0 flex-1">
                                <span class="block truncate text-sm font-medium text-neutral-950">{{ currentWorkspace?.name ?? 'Openlink' }}</span>
                                <span class="block truncate text-xs text-neutral-500">Workspace</span>
                            </span>
                            <ChevronDown class="h-4 w-4 text-neutral-500" />
                        </button>
                    </template>

                    <template #content>
                        <div class="px-2 py-1 text-xs font-medium uppercase text-neutral-400">Switch workspace</div>
                        <Link
                            v-for="workspace in workspaces"
                            :key="workspace.id"
                            :href="route('workspaces.switch', workspace.id)"
                            method="post"
                            as="button"
                            class="block w-full px-3 py-2 text-left text-sm text-neutral-700 hover:bg-neutral-100"
                            :class="workspace.id === currentWorkspace?.id ? 'font-medium text-neutral-950' : ''"
                        >
                            {{ workspace.name }}
                        </Link>
                        <div class="my-1 border-t border-neutral-200" />
                        <Link :href="route('workspaces.index')" class="block px-3 py-2 text-sm text-neutral-700 hover:bg-neutral-100">
                            Manage workspaces
                        </Link>
                    </template>
                </Dropdown>
            </div>

            <nav class="flex-1 space-y-1 px-3 py-4">
                <Link
                    v-for="item in navItems"
                    :key="item.label"
                    :href="item.href"
                    class="group flex h-9 items-center gap-3 rounded-md px-3 text-sm font-medium text-neutral-500 transition hover:bg-neutral-100 hover:text-neutral-950"
                    :class="item.active ? 'bg-neutral-100 text-neutral-950' : ''"
                >
                    <component :is="item.icon" class="h-4 w-4 shrink-0" />
                    <span>{{ item.label }}</span>
                </Link>
            </nav>

            <div class="border-t border-neutral-200 p-3">
                <Dropdown align="right" width="48" placement="top">
                    <template #trigger>
                        <button class="flex w-full items-center gap-3 rounded-md px-2 py-2 text-left transition hover:bg-neutral-100">
                            <span class="grid h-8 w-8 shrink-0 place-items-center rounded-md bg-white text-xs font-semibold text-neutral-950 ring-1 ring-neutral-200">
                                {{ String($page.props.auth.user.name).slice(0, 1).toUpperCase() }}
                            </span>
                            <span class="min-w-0 flex-1">
                                <span class="block truncate text-sm font-medium text-neutral-950">{{ $page.props.auth.user.name }}</span>
                                <span class="block truncate text-xs text-neutral-500">{{ $page.props.auth.user.email }}</span>
                            </span>
                            <ChevronDown class="h-4 w-4 text-neutral-500" />
                        </button>
                    </template>

                    <template #content>
                        <DropdownLink :href="route('profile.edit')">
                            <span class="inline-flex items-center gap-2"><User class="h-4 w-4" /> Profile</span>
                        </DropdownLink>
                        <DropdownLink :href="route('settings.index')">
                            <span class="inline-flex items-center gap-2"><Settings class="h-4 w-4" /> Settings</span>
                        </DropdownLink>
                        <DropdownLink :href="route('logout')" method="post" as="button">
                            <span class="inline-flex items-center gap-2"><LogOut class="h-4 w-4" /> Log out</span>
                        </DropdownLink>
                    </template>
                </Dropdown>
            </div>
        </aside>

        <div class="lg:pl-64">
            <header class="sticky top-0 z-20 border-b border-neutral-800 bg-[#050505]/95 backdrop-blur">
                <div class="flex h-16 items-center justify-between px-4 sm:px-6 lg:px-8">
                    <div class="flex items-center gap-3 lg:hidden">
                        <button
                            type="button"
                            class="grid h-9 w-9 place-items-center rounded-md border border-neutral-200 bg-white text-neutral-950"
                            @click="showingNavigationDropdown = !showingNavigationDropdown"
                        >
                            <Menu v-if="!showingNavigationDropdown" class="h-4 w-4" />
                            <X v-else class="h-4 w-4" />
                        </button>
                        <Link :href="route('dashboard')" class="text-sm font-semibold tracking-[0.18em]">OPENLINK</Link>
                    </div>

                    <div class="min-w-0 flex-1">
                        <slot name="header" />
                    </div>

                    <div class="hidden items-center gap-2 lg:flex">
                        <Link
                            :href="route('profile.edit')"
                            class="grid h-9 w-9 place-items-center rounded-md border border-neutral-200 bg-white text-neutral-500 transition hover:text-neutral-950"
                            title="Profile"
                        >
                            <Settings class="h-4 w-4" />
                        </Link>
                    </div>
                </div>

                <div v-if="showingNavigationDropdown" class="border-t border-neutral-200 bg-white px-3 py-3 lg:hidden">
                    <nav class="space-y-1">
                        <Link
                            v-for="item in navItems"
                            :key="item.label"
                            :href="item.href"
                            class="flex h-10 items-center gap-3 rounded-md px-3 text-sm font-medium text-neutral-500"
                            :class="item.active ? 'bg-neutral-100 text-neutral-950' : 'hover:bg-neutral-100'"
                            @click="showingNavigationDropdown = false"
                        >
                            <component :is="item.icon" class="h-4 w-4" />
                            {{ item.label }}
                        </Link>
                    </nav>
                    <div class="mt-3 border-t border-neutral-200 pt-3">
                        <Link :href="route('profile.edit')" class="flex h-10 items-center gap-3 rounded-md px-3 text-sm text-neutral-500 hover:bg-neutral-100">
                            <User class="h-4 w-4" /> Profile
                        </Link>
                        <Link :href="route('logout')" method="post" as="button" class="flex h-10 w-full items-center gap-3 rounded-md px-3 text-sm text-neutral-500 hover:bg-neutral-100">
                            <LogOut class="h-4 w-4" /> Log out
                        </Link>
                    </div>
                </div>
            </header>

            <main>
                <slot />
            </main>
        </div>
    </div>
</template>
