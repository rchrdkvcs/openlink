<script setup lang="ts">
import Badge from '@/Components/ui/Badge.vue';
import Button from '@/Components/ui/Button.vue';
import Drawer from '@/Components/ui/Drawer.vue';
import EmptyState from '@/Components/ui/EmptyState.vue';
import Field from '@/Components/ui/Field.vue';
import IconButton from '@/Components/ui/IconButton.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import {
    Archive,
    Check,
    ChevronDown,
    ChevronRight,
    Copy,
    ExternalLink,
    Folder as FolderIcon,
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
    Trash2,
} from '@lucide/vue';
import { nextTick, ref } from 'vue';
import type { Folder, LinksPageProps, ShortLink } from './types';
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
    isCollapsed,
    onDrop,
} = useLinkGroups(props, filters);
const failedFavicons = ref<Set<string>>(new Set());

const {
    selectedSettingsTab,
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
    qrPreviewUrl,
} = useLinkForms(props, selectedLink, createOpen);

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
    const parsed = parseDisplayUrl(url);

    return parsed ? `${parsed.origin}/favicon.ico` : null;
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
        <template #header>
            <PageHeader section="Links">
                <Button v-if="canEditWorkspace" size="sm" type="button" @click="createOpen = true">
                    <Plus class="h-3.5 w-3.5" /> New link
                </Button>
            </PageHeader>
        </template>

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

                <div v-if="canManageWorkspace" class="ml-auto">
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
                </div>
            </div>

            <!-- Folder groups -->
            <div class="space-y-3">
                <section
                    v-for="group in groups"
                    :key="group.key"
                    class="card-sheen overflow-hidden rounded-lg border bg-surface transition-shadow"
                    :class="dropGroupKey === group.key && dragLinkId !== null ? 'ring-1 ring-accent' : ''"
                    @dragover.prevent="dropGroupKey = group.key"
                    @dragleave="dropGroupKey = null"
                    @drop.prevent="onDrop(group)"
                >
                    <!-- Group header -->
                    <header class="group/h flex h-10 items-center gap-2 px-3" :class="isCollapsed(group.key) ? '' : 'border-b'">
                        <button
                            class="grid h-6 w-6 place-items-center rounded text-faint transition-colors hover:bg-elevated hover:text-foreground"
                            :title="isCollapsed(group.key) ? 'Expand' : 'Collapse'"
                            @click="toggleCollapse(group.key)"
                        >
                            <component :is="isCollapsed(group.key) ? ChevronRight : ChevronDown" class="h-4 w-4" />
                        </button>
                        <component :is="group.folder ? FolderIcon : Inbox" class="h-4 w-4 text-faint" />

                        <input
                            v-if="group.folder && renamingFolderId === group.folder.id"
                            ref="renameInput"
                            v-model="renameValue"
                            class="h-7 w-64 px-2 text-[13px]"
                            @keydown.enter="commitRenameFolder"
                            @keydown.escape="renamingFolderId = null"
                            @blur="commitRenameFolder"
                        />
                        <span v-else class="text-[13px] font-semibold text-foreground">{{ group.folder?.name ?? 'Unfiled' }}</span>
                        <span class="text-xs tabular-nums text-faint">{{ group.links.length }}</span>

                        <div v-if="group.folder && canManageWorkspace" class="relative ml-auto opacity-0 transition-opacity focus-within:opacity-100 group-hover/h:opacity-100">
                            <button
                                class="grid h-7 w-7 place-items-center rounded-md text-faint transition-colors hover:bg-elevated hover:text-foreground"
                                title="Folder actions"
                                @click="folderMenuFor = folderMenuFor === group.folder.id ? null : group.folder.id"
                            >
                                <MoreHorizontal class="h-4 w-4" />
                            </button>
                            <template v-if="folderMenuFor === group.folder.id">
                                <button class="fixed inset-0 z-20 cursor-default" tabindex="-1" @click="folderMenuFor = null" />
                                <div class="absolute right-0 top-full z-30 mt-1 w-44 rounded-lg bg-overlay p-1 shadow-popover">
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
                            </template>
                        </div>
                    </header>

                    <!-- Rows -->
                    <div v-if="!isCollapsed(group.key)" class="divide-y divide-border/60">
                        <article
                            v-for="link in group.links"
                            :key="link.id"
                            class="group/r grid items-center gap-x-3 gap-y-1 px-3 py-2.5 transition-colors hover:bg-elevated/40 lg:grid-cols-[20px_minmax(180px,1.3fr)_minmax(150px,1fr)_110px_150px_minmax(90px,0.5fr)_168px]"
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
                                    <Check v-if="copiedLinkId === link.id" class="h-3.5 w-3.5 text-success" />
                                    <Copy v-else class="h-3.5 w-3.5" />
                                </button>
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
                                    <Link2 class="h-3 w-3 text-faint" />
                                </span>
                                <span class="min-w-0 truncate">{{ destinationHost(link.destination_url) }}</span>
                            </a>

                            <div class="flex items-center gap-2">
                                <Badge :variant="statusVariant(link.status)" dot>{{ link.status }}</Badge>
                                <Lock v-if="link.has_password" class="h-3.5 w-3.5 text-warning" />
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

        <!-- Create drawer -->
        <Drawer :show="createOpen" eyebrow="New link" title="Create short URL" @close="createOpen = false">
            <form class="grid gap-5 p-5" @submit.prevent="submitLink">
                <div class="grid gap-4 sm:grid-cols-[minmax(0,1fr)_minmax(0,1fr)]">
                    <Field label="Domain" hint="The hostname used for the short URL." :error="linkForm.errors.domain_id">
                        <select v-model="linkForm.domain_id" class="h-9">
                            <option v-for="domain in usableDomains" :key="domain.id" :value="domain.id">{{ domain.hostname }}</option>
                        </select>
                    </Field>
                    <Field label="Custom slug" hint="Leave empty to generate one automatically." :error="linkForm.errors.slug">
                        <input v-model="linkForm.slug" class="h-9" placeholder="summer-launch" />
                    </Field>
                </div>
                <Field label="Destination URL" :error="linkForm.errors.destination_url">
                    <input v-model="linkForm.destination_url" class="h-9" placeholder="https://destination.example" />
                </Field>
                <Field label="Folder" :error="linkForm.errors.folder_id">
                    <select v-model="linkForm.folder_id" class="h-9">
                        <option value="">No folder</option>
                        <option v-for="folder in folders" :key="folder.id" :value="folder.id">{{ folder.name }}</option>
                    </select>
                </Field>
                <Field label="Fallback URL" hint="Optional URL used when the link is expired or unavailable." :error="linkForm.errors.fallback_url">
                    <input v-model="linkForm.fallback_url" class="h-9" placeholder="https://fallback.example" />
                </Field>

                <div class="border-t pt-5">
                    <p class="mb-4 text-[13px] font-semibold text-foreground">Lifecycle</p>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <Field label="Activation date" :error="linkForm.errors.activates_at">
                            <input v-model="linkForm.activates_at" type="datetime-local" class="h-9" />
                        </Field>
                        <Field label="Expiration date" :error="linkForm.errors.expires_at">
                            <input v-model="linkForm.expires_at" type="datetime-local" class="h-9" />
                        </Field>
                        <Field label="Visit limit" :error="linkForm.errors.visit_limit">
                            <input v-model="linkForm.visit_limit" type="number" min="1" class="h-9" placeholder="1000" />
                        </Field>
                        <Field label="Password" :error="linkForm.errors.password">
                            <input v-model="linkForm.password" type="password" class="h-9" placeholder="Optional" />
                        </Field>
                    </div>
                </div>

                <Field label="Tags" hint="Comma-separated tags for filtering." :error="linkForm.errors.tags">
                    <input v-model="linkForm.tags" class="h-9" placeholder="event, vip" />
                </Field>

                <div class="flex justify-end gap-2 border-t pt-5">
                    <Button variant="secondary" type="button" @click="createOpen = false">Cancel</Button>
                    <Button :loading="linkForm.processing">Create link</Button>
                </div>
            </form>
        </Drawer>

        <!-- Edit drawer -->
        <Drawer :show="Boolean(selectedLink)" eyebrow="Link settings" :title="selectedLink?.short_url" @close="selectedLink = null">
            <div v-if="selectedLink" class="space-y-6 p-5">
                <div class="grid grid-cols-2 rounded-lg border bg-elevated/30 p-1">
                    <button
                        type="button"
                        class="h-8 rounded-md text-[13px] font-medium transition-colors"
                        :class="selectedSettingsTab === 'link' ? 'bg-surface text-foreground shadow-sm' : 'text-muted hover:text-foreground'"
                        @click="selectedSettingsTab = 'link'"
                    >
                        Link
                    </button>
                    <button
                        type="button"
                        class="h-8 rounded-md text-[13px] font-medium transition-colors"
                        :class="selectedSettingsTab === 'qr' ? 'bg-surface text-foreground shadow-sm' : 'text-muted hover:text-foreground'"
                        @click="selectedSettingsTab = 'qr'"
                    >
                        QR code
                    </button>
                </div>

                <form v-if="selectedSettingsTab === 'link'" class="grid gap-5" @submit.prevent="updateLink">
                    <Field label="Folder" hint="Move this link into a project folder." :error="editForm.errors.folder_id">
                        <select v-model="editForm.folder_id" class="h-9">
                            <option value="">No folder</option>
                            <option v-for="folder in folders" :key="folder.id" :value="folder.id">{{ folder.name }}</option>
                        </select>
                    </Field>
                    <Field label="Destination URL" :error="editForm.errors.destination_url">
                        <input v-model="editForm.destination_url" class="h-9" placeholder="Destination URL" />
                    </Field>
                    <Field label="Fallback URL" :error="editForm.errors.fallback_url">
                        <input v-model="editForm.fallback_url" class="h-9" placeholder="Fallback URL" />
                    </Field>

                    <label class="flex items-center justify-between gap-3 rounded-md border bg-elevated/40 px-3 py-2.5">
                        <span>
                            <span class="block text-[13px] font-medium text-foreground">Enabled</span>
                            <span class="block text-xs text-faint">Allow this link to resolve when its lifecycle rules allow it.</span>
                        </span>
                        <input v-model="editForm.is_enabled" type="checkbox" class="h-4 w-4 rounded" />
                    </label>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <Field label="Activation date" :error="editForm.errors.activates_at">
                            <input v-model="editForm.activates_at" type="datetime-local" class="h-9" />
                        </Field>
                        <Field label="Expiration date" :error="editForm.errors.expires_at">
                            <input v-model="editForm.expires_at" type="datetime-local" class="h-9" />
                        </Field>
                        <Field label="Visit limit" :error="editForm.errors.visit_limit">
                            <input v-model="editForm.visit_limit" type="number" min="1" class="h-9" placeholder="1000" />
                        </Field>
                        <Field label="Password" hint="Masked when already set. Empty this field to remove protection." :error="editForm.errors.password">
                            <input v-model="editForm.password" type="password" class="h-9" />
                        </Field>
                    </div>

                    <div class="flex justify-end">
                        <Button :loading="editForm.processing">Save changes</Button>
                    </div>
                </form>

                <div v-if="selectedSettingsTab === 'qr'" class="grid gap-5 rounded-lg border border-border/70 bg-elevated/20 p-4 sm:p-5">
                    <h4 class="flex items-center gap-2 text-[13px] font-semibold text-foreground"><QrCode class="h-4 w-4 text-faint" /> QR codes</h4>

                    <form class="flex items-end gap-2" @submit.prevent="submitQr">
                        <Field label="QR name" :error="qrForm.errors.name" class="flex-1">
                            <input v-model="qrForm.name" class="h-9" placeholder="Poster, badge, flyer…" />
                        </Field>
                        <Button variant="secondary" :loading="qrForm.processing">Create &amp; customize</Button>
                    </form>

                    <div class="grid gap-3">
                        <article v-for="qr in selectedLink.qr_codes" :key="qr.id" class="grid gap-4 rounded-lg border bg-elevated/30 p-3 sm:grid-cols-[112px_1fr]">
                            <Link :href="route('qr-codes.show', qr.token)">
                                <img :src="qrPreviewUrl(qr)" :alt="`${qr.name} QR code`" class="h-28 w-28 rounded-md bg-white p-1.5 transition-opacity hover:opacity-80" />
                            </Link>
                            <div class="min-w-0">
                                <p class="truncate text-sm font-medium text-foreground">{{ qr.name }}</p>
                                <p class="mt-1 text-xs text-faint">{{ qr.scans }} scans · tracked separately from visits.</p>
                                <div class="mt-3 flex flex-wrap gap-2">
                                    <Link
                                        :href="route('qr-codes.show', qr.token)"
                                        class="inline-flex items-center gap-1 rounded-md border px-2.5 py-1 text-xs font-medium text-foreground transition-colors hover:border-border-strong hover:bg-elevated"
                                    >
                                        <Settings2 class="h-3 w-3" /> Customize
                                    </Link>
                                    <a
                                        :href="route('qr-codes.export', [qr.token, 'svg'])"
                                        class="rounded-md border px-2.5 py-1 text-xs font-medium text-muted transition-colors hover:border-border-strong hover:text-foreground"
                                    >
                                        SVG
                                    </a>
                                    <a
                                        :href="route('qr-codes.export', [qr.token, 'png'])"
                                        class="rounded-md border px-2.5 py-1 text-xs font-medium text-muted transition-colors hover:border-border-strong hover:text-foreground"
                                    >
                                        PNG
                                    </a>
                                </div>
                            </div>
                        </article>
                        <p v-if="selectedLink.qr_codes.length === 0" class="rounded-md border border-dashed px-3 py-4 text-center text-[13px] text-faint">
                            No QR code yet for this link.
                        </p>
                    </div>
                </div>
            </div>
        </Drawer>
    </AuthenticatedLayout>
</template>
