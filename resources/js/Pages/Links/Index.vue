<script setup lang="ts">
import Badge from '@/Components/ui/Badge.vue';
import Button from '@/Components/ui/Button.vue';
import CopyCheckIcon from '@/Components/ui/CopyCheckIcon.vue';
import EmptyState from '@/Components/ui/EmptyState.vue';
import IconButton from '@/Components/ui/IconButton.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import {
    Archive,
    ChevronDown,
    ChevronsDownUp,
    ChevronsUpDown,
    ExternalLink,
    Folder as FolderIcon,
    Globe,
    GripVertical,
    Inbox,
    Link2,
    Lock,
    MoreHorizontal,
    Pencil,
    Plus,
    QrCode,
    Search,
    Settings2,
    Timer,
    Trash2,
} from '@lucide/vue';
import { nextTick, ref } from 'vue';
import CreateLinkDrawer from './CreateLinkDrawer.vue';
import EditLinkDrawer from './EditLinkDrawer.vue';
import type { Folder, LinksPageProps, ShortLink } from './types';
import { useActivationCountdown } from './useActivationCountdown';
import { useLinkForms } from './useLinkForms';
import { useLinkGroups } from './useLinkGroups';

const props = defineProps<LinksPageProps>();

const filters = ref({ search: '', status: '', tag: '' });
const createOpen = ref(false);
const selectedLink = ref<ShortLink | null>(null);
const {
    groups,
    totalMatching,
    hasActiveFilters,
    dragLinkId,
    dropGroupKey,
    toggleCollapse,
    toggleAllCollapse,
    allGroupsCollapsed,
    isCollapsed,
    onDrop,
} = useLinkGroups(props, filters);
const failedFavicons = ref<Set<string>>(new Set());

const {
    copiedLinkId,
    usableDomains,
    linkForm,
    editForm,
    qrForm,
    submitLink,
    updateLink,
    submitQr,
    archiveLink,
    deleteLink,
    copyShortUrl,
    statusVariant,
} = useLinkForms(props, selectedLink, createOpen);

const { countdownFor, activationTitle } = useActivationCountdown(props);

// ── Folder CRUD ──────────────────────────────────────────────────────────────

const folderForm = useForm({ name: '' });
const creatingFolder = ref(false);
const newFolderInput = ref<HTMLInputElement | null>(null);
const renamingFolderId = ref<number | null>(null);
const renameValue = ref('');
const renameInput = ref<HTMLInputElement[]>([]);
const folderMenuFor = ref<number | null>(null);

function startCreateFolder() {
    creatingFolder.value = true;
    nextTick(() => newFolderInput.value?.focus());
}

function submitFolder() {
    if (!folderForm.name.trim()) {
        creatingFolder.value = false;
        return;
    }

    folderForm.post(route('folders.store'), {
        preserveScroll: true,
        onSuccess: () => {
            folderForm.reset();
            creatingFolder.value = false;
        },
    });
}

function startRenameFolder(folder: Folder) {
    folderMenuFor.value = null;
    renamingFolderId.value = folder.id;
    renameValue.value = folder.name;
    nextTick(() => renameInput.value[0]?.focus());
}

function commitRenameFolder() {
    const id = renamingFolderId.value;
    if (id === null) {
        return;
    }

    const name = renameValue.value.trim();
    const current = props.folders.find((folder) => folder.id === id);
    renamingFolderId.value = null;

    if (name && current && name !== current.name) {
        router.patch(route('folders.update', id), { name }, { preserveScroll: true });
    }
}

function deleteFolder(folder: Folder, linkCount: number) {
    folderMenuFor.value = null;
    const detail = linkCount > 0 ? ` Its ${linkCount} link${linkCount > 1 ? 's' : ''} will move to Unfiled.` : '';
    if (confirm(`Delete folder "${folder.name}"?${detail}`)) {
        router.delete(route('folders.destroy', folder.id), { preserveScroll: true });
    }
}

function parseDisplayUrl(url: string): URL | null {
    try {
        return new URL(url.includes('://') ? url : `https://${url}`);
    } catch {
        return null;
    }
}

function urlWithoutProtocol(url: string) {
    const parsed = parseDisplayUrl(url);

    if (!parsed) {
        return url.replace(/^https?:\/\//, '');
    }

    const path = parsed.pathname === '/' ? '' : parsed.pathname;

    return `${parsed.host}${path}${parsed.search}`;
}

function destinationHost(url: string) {
    return parseDisplayUrl(url)?.host ?? urlWithoutProtocol(url).split('/')[0];
}

function faviconUrl(url: string) {
    return route('favicons.show', { url });
}

function hasFavicon(url: string) {
    const favicon = faviconUrl(url);

    return Boolean(favicon && !failedFavicons.value.has(favicon));
}

function markFaviconFailed(url: string) {
    const favicon = faviconUrl(url);

    if (!favicon) {
        return;
    }

    const next = new Set(failedFavicons.value);
    next.add(favicon);
    failedFavicons.value = next;
}
</script>

<template>
    <Head title="Links" />

    <AuthenticatedLayout>
        <div class="w-full px-4 py-8 sm:px-6 lg:px-8">
            <div class="mb-6">
                <h1 class="text-xl font-semibold tracking-tight">Links</h1>
                <p class="mt-1 text-sm text-muted">Short URLs grouped by folder. Drag a link onto a folder to move it.</p>
            </div>

            <!-- Toolbar -->
            <div class="mb-4 flex flex-wrap items-center gap-2">
                <div class="relative w-72">
                    <Search class="pointer-events-none absolute left-2.5 top-1/2 h-4 w-4 -translate-y-1/2 text-faint" />
                    <input v-model="filters.search" class="h-8 pl-8 text-[13px]" placeholder="Search across all folders…" />
                </div>
                <select v-model="filters.status" class="h-8 w-36 py-0 text-[13px]">
                    <option value="">All statuses</option>
                    <option value="active">Active</option>
                    <option value="scheduled">Scheduled</option>
                    <option value="expired">Expired</option>
                    <option value="disabled">Disabled</option>
                    <option value="archived">Archived</option>
                </select>
                <select v-model="filters.tag" class="h-8 w-32 py-0 text-[13px]">
                    <option value="">All tags</option>
                    <option v-for="tag in tags" :key="tag.id" :value="tag.name">{{ tag.name }}</option>
                </select>

                <span v-if="hasActiveFilters" class="text-[13px] tabular-nums text-faint">{{ totalMatching }} result{{ totalMatching === 1 ? '' : 's' }}</span>
                <Button v-if="groups.length > 0 && !hasActiveFilters" variant="ghost" size="sm" type="button" @click="toggleAllCollapse">
                    <component :is="allGroupsCollapsed ? ChevronsUpDown : ChevronsDownUp" class="h-3.5 w-3.5" />
                    {{ allGroupsCollapsed ? 'Open all' : 'Close all' }}
                </Button>

                <div v-if="canEditWorkspace || canManageWorkspace" class="ml-auto flex items-center gap-2">
                    <template v-if="canManageWorkspace">
                        <form v-if="creatingFolder" class="flex items-center gap-2" @submit.prevent="submitFolder">
                            <input
                                ref="newFolderInput"
                                v-model="folderForm.name"
                                class="h-8 w-48 text-[13px]"
                                placeholder="Folder name…"
                                @keydown.escape="creatingFolder = false; folderForm.reset()"
                                @blur="submitFolder"
                            />
                            <p v-if="folderForm.errors.name" class="text-xs text-danger">{{ folderForm.errors.name }}</p>
                        </form>
                        <button
                            v-else
                            class="inline-flex h-8 items-center gap-1.5 rounded-md border px-3 text-[13px] font-medium text-muted transition-colors hover:border-border-strong hover:text-foreground"
                            @click="startCreateFolder"
                        >
                            <Plus class="h-3.5 w-3.5" /> New folder
                        </button>
                    </template>
                    <Button v-if="canEditWorkspace" size="sm" type="button" @click="createOpen = true">
                        <Plus class="h-3.5 w-3.5" /> New link
                    </Button>
                </div>
            </div>

            <!-- Folder groups -->
            <div class="space-y-3">
                <section
                    v-for="group in groups"
                    :key="group.key"
                    class="card-sheen rounded-lg border bg-surface transition-shadow"
                    :class="dropGroupKey === group.key && dragLinkId !== null ? 'ring-1 ring-accent' : ''"
                    @dragover.prevent="dropGroupKey = group.key"
                    @dragleave="dropGroupKey = null"
                    @drop.prevent="onDrop(group)"
                >
                    <!-- Group header -->
                    <header
                        class="group/h flex h-10 cursor-pointer items-center gap-2 px-3 transition-colors hover:bg-elevated/40"
                        :class="isCollapsed(group.key) ? '' : 'border-b'"
                        :title="isCollapsed(group.key) ? 'Expand' : 'Collapse'"
                        role="button"
                        tabindex="0"
                        @click="toggleCollapse(group.key)"
                        @keydown.enter.prevent="toggleCollapse(group.key)"
                        @keydown.space.prevent="toggleCollapse(group.key)"
                    >
                        <span
                            class="pointer-events-none grid h-6 w-6 place-items-center rounded text-faint transition-colors group-hover/h:text-foreground"
                            aria-hidden="true"
                        >
                            <ChevronDown class="h-4 w-4 transition-transform duration-200 ease-emphasized-out" :class="isCollapsed(group.key) ? '-rotate-90' : ''" />
                        </span>
                        <component :is="group.folder ? FolderIcon : Inbox" class="h-4 w-4 text-faint" />

                        <input
                            v-if="group.folder && renamingFolderId === group.folder.id"
                            ref="renameInput"
                            v-model="renameValue"
                            class="h-7 w-64 px-2 text-[13px]"
                            @keydown.enter.stop="commitRenameFolder"
                            @keydown.escape.stop="renamingFolderId = null"
                            @blur="commitRenameFolder"
                            @click.stop
                        />
                        <span v-else class="text-[13px] font-semibold text-foreground">{{ group.folder?.name ?? 'Unfiled' }}</span>
                        <span class="text-xs tabular-nums text-faint">{{ group.links.length }}</span>

                        <div
                            v-if="group.folder && canManageWorkspace"
                            class="relative ml-auto opacity-0 transition-opacity focus-within:opacity-100 group-hover/h:opacity-100"
                            @click.stop
                        >
                            <button
                                class="grid h-7 w-7 place-items-center rounded-md text-faint transition-colors hover:bg-elevated hover:text-foreground"
                                title="Folder actions"
                                @click="folderMenuFor = folderMenuFor === group.folder.id ? null : group.folder.id"
                            >
                                <MoreHorizontal class="h-4 w-4" />
                            </button>
                            <button
                                v-if="folderMenuFor === group.folder.id"
                                class="fixed inset-0 z-20 cursor-default"
                                tabindex="-1"
                                @click="folderMenuFor = null"
                            />
                            <Transition
                                enter-active-class="transition ease-emphasized-out duration-150"
                                enter-from-class="opacity-0 scale-[0.97] -translate-y-0.5"
                                enter-to-class="opacity-100 scale-100 translate-y-0"
                                leave-active-class="transition ease-out duration-100"
                                leave-from-class="opacity-100 scale-100"
                                leave-to-class="opacity-0 scale-[0.97]"
                            >
                                <div v-if="folderMenuFor === group.folder.id" class="absolute right-0 top-full z-30 mt-1 w-44 origin-top-right rounded-lg bg-overlay p-1 shadow-popover">
                                    <button
                                        class="flex w-full items-center gap-2 rounded-[5px] px-2.5 py-1.5 text-left text-[13px] text-muted transition-colors hover:bg-elevated hover:text-foreground"
                                        @click="startRenameFolder(group.folder)"
                                    >
                                        <Pencil class="h-3.5 w-3.5" /> Rename
                                    </button>
                                    <button
                                        class="flex w-full items-center gap-2 rounded-[5px] px-2.5 py-1.5 text-left text-[13px] text-danger transition-colors hover:bg-danger/15"
                                        @click="deleteFolder(group.folder, group.links.length)"
                                    >
                                        <Trash2 class="h-3.5 w-3.5" /> Delete folder
                                    </button>
                                </div>
                            </Transition>
                        </div>
                    </header>

                    <!-- Rows (rounded + clipped here so the folder menu can overflow the card) -->
                    <div v-if="!isCollapsed(group.key)" class="divide-y divide-border/60 overflow-hidden rounded-b-lg">
                        <article
                            v-for="link in group.links"
                            :key="link.id"
                            class="group/r grid items-center gap-x-3 gap-y-1 px-3 py-2.5 transition-colors hover:bg-elevated/40 lg:grid-cols-[20px_minmax(180px,1.3fr)_minmax(150px,1fr)_190px_150px_minmax(90px,0.5fr)_168px]"
                            :class="[dragLinkId === link.id ? 'opacity-40' : '', canEditWorkspace ? 'cursor-grab active:cursor-grabbing' : '']"
                            :draggable="canEditWorkspace"
                            @dragstart="dragLinkId = link.id"
                            @dragend="dragLinkId = null; dropGroupKey = null"
                        >
                            <GripVertical v-if="canEditWorkspace" class="hidden h-3.5 w-3.5 text-faint opacity-0 transition-opacity group-hover/r:opacity-100 lg:block" />
                            <span v-else class="hidden lg:block" />

                            <div class="flex min-w-0 items-center gap-1.5">
                                <a :href="link.short_url" target="_blank" class="truncate text-sm font-medium text-foreground hover:text-accent">
                                    {{ urlWithoutProtocol(link.short_url) }}
                                </a>
                                <button
                                    class="shrink-0 rounded p-1 text-faint opacity-0 transition-opacity hover:text-foreground focus-visible:opacity-100 group-hover/r:opacity-100"
                                    title="Copy short URL"
                                    @click="copyShortUrl(link)"
                                >
                                    <CopyCheckIcon :copied="copiedLinkId === link.id" />
                                </button>
                                <span
                                    v-if="link.qr_codes.length > 0"
                                    class="grid h-5 w-5 shrink-0 place-items-center rounded text-accent"
                                    :title="`${link.qr_codes.length} QR code${link.qr_codes.length === 1 ? '' : 's'} attached`"
                                    :aria-label="`${link.qr_codes.length} QR code${link.qr_codes.length === 1 ? '' : 's'} attached`"
                                >
                                    <QrCode class="h-3.5 w-3.5" aria-hidden="true" />
                                </span>
                            </div>

                            <a
                                :href="link.destination_url"
                                target="_blank"
                                rel="noopener"
                                class="flex min-w-0 items-center gap-2 text-[13px] text-muted transition-colors hover:text-foreground"
                                :title="urlWithoutProtocol(link.destination_url)"
                            >
                                <img
                                    v-if="hasFavicon(link.destination_url)"
                                    :src="faviconUrl(link.destination_url) ?? undefined"
                                    alt=""
                                    class="h-4 w-4 shrink-0 rounded-[3px] bg-elevated"
                                    loading="lazy"
                                    @error="markFaviconFailed(link.destination_url)"
                                />
                                <span v-else class="grid h-4 w-4 shrink-0 place-items-center rounded-[3px] bg-elevated">
                                    <Globe class="h-3 w-3 text-faint" />
                                </span>
                                <span class="min-w-0 truncate">{{ destinationHost(link.destination_url) }}</span>
                            </a>

                            <div class="flex min-w-0 items-center gap-2">
                                <Badge :variant="statusVariant(link.status)" dot>{{ link.status }}</Badge>
                                <span
                                    v-if="countdownFor(link)"
                                    class="inline-flex w-20 shrink-0 items-center gap-1 whitespace-nowrap text-xs tabular-nums text-faint"
                                    :title="activationTitle(link)"
                                >
                                    <Timer class="h-3 w-3 shrink-0" />
                                    {{ countdownFor(link) }}
                                </span>
                                <Lock v-if="link.has_password" class="h-3.5 w-3.5 shrink-0 text-warning" />
                            </div>

                            <div class="text-[13px] tabular-nums text-muted">
                                <span class="font-medium text-foreground">{{ link.visits }}</span> visits
                                <span class="mx-1 text-faint">·</span>
                                <span class="font-medium text-foreground">{{ link.scans }}</span> scans
                            </div>

                            <div class="flex min-w-0 flex-wrap gap-1">
                                <span v-for="tag in link.tags" :key="tag.id" class="rounded bg-elevated px-1.5 py-0.5 text-[11px] text-muted">#{{ tag.name }}</span>
                            </div>

                            <div
                                class="pointer-events-none flex justify-start gap-0.5 opacity-0 transition-opacity focus-within:pointer-events-auto focus-within:opacity-100 group-hover/r:pointer-events-auto group-hover/r:opacity-100 lg:justify-end"
                            >
                                <a
                                    :href="link.short_url"
                                    target="_blank"
                                    class="grid h-8 w-8 place-items-center rounded-md text-muted transition-colors duration-150 hover:bg-elevated hover:text-foreground"
                                    title="Open"
                                >
                                    <ExternalLink class="h-4 w-4" />
                                </a>

                                <IconButton title="Edit" @click="selectedLink = link">
                                    <Settings2 class="h-4 w-4" />
                                </IconButton>
                                <IconButton v-if="canEditWorkspace" title="Archive" @click="archiveLink(link)">
                                    <Archive class="h-4 w-4" />
                                </IconButton>
                                <IconButton v-if="canManageWorkspace" variant="danger" title="Delete" @click="deleteLink(link)">
                                    <Trash2 class="h-4 w-4" />
                                </IconButton>
                            </div>
                        </article>

                        <p v-if="group.links.length === 0" class="px-4 py-4 text-[13px] italic text-faint">
                            {{ canEditWorkspace ? 'Empty — drag links here.' : 'Empty.' }}
                        </p>
                    </div>
                </section>

                <!-- Global empty state -->
                <section v-if="groups.length === 0 || (links.length === 0 && !hasActiveFilters)" class="card-sheen rounded-lg border bg-surface">
                    <EmptyState
                        :title="hasActiveFilters ? 'No links match' : 'No links yet'"
                        :description="hasActiveFilters ? 'Try another search, status, or tag.' : 'Create your first short link to get started.'"
                    >
                        <template #icon><Link2 class="h-5 w-5" /></template>
                        <template #action>
                            <Button v-if="canEditWorkspace && !hasActiveFilters" variant="secondary" size="sm" type="button" @click="createOpen = true">
                                <Plus class="h-3.5 w-3.5" /> New link
                            </Button>
                        </template>
                    </EmptyState>
                </section>
            </div>
        </div>

        <CreateLinkDrawer
            :show="createOpen"
            :form="linkForm"
            :domains="usableDomains"
            :folders="folders"
            :known-tags="tags"
            :routing-schema="routingSchema"
            @close="createOpen = false"
            @submit="submitLink"
        />

        <EditLinkDrawer
            :link="selectedLink"
            :edit-form="editForm"
            :qr-form="qrForm"
            :domains="usableDomains"
            :folders="folders"
            :routing-schema="routingSchema"
            @close="selectedLink = null"
            @submit="updateLink"
            @submit-qr="submitQr"
        />
    </AuthenticatedLayout>
</template>
