<script setup lang="ts">
import Badge from '@/Components/ui/Badge.vue';
import Button from '@/Components/ui/Button.vue';
import Drawer from '@/Components/ui/Drawer.vue';
import EmptyState from '@/Components/ui/EmptyState.vue';
import Field from '@/Components/ui/Field.vue';
import IconButton from '@/Components/ui/IconButton.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import {
    Archive,
    Check,
    ChevronDown,
    ChevronRight,
    Copy,
    ExternalLink,
    Folder as FolderIcon,
    FolderInput,
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
import { computed, nextTick, ref, watch } from 'vue';

type Workspace = { id: number; name: string; slug: string; preferred_domain_id?: number | null };
type Domain = { id: number; hostname: string; status: string; is_default: boolean };
type Folder = { id: number; name: string };
type Qr = { id: number; name: string; token: string };
type ShortLink = {
    id: number;
    slug: string;
    short_url: string;
    destination_url: string;
    fallback_url?: string | null;
    status: string;
    domain: Domain;
    folder?: Folder | null;
    tags: { id: number; name: string }[];
    qr_codes: Qr[];
    visits: number;
    scans: number;
    is_enabled: boolean;
    activates_at?: string | null;
    expires_at?: string | null;
    visit_limit?: number | null;
    successful_visits: number;
    has_password: boolean;
};

const props = defineProps<{
    currentWorkspace: Workspace;
    canManageWorkspace: boolean;
    canEditWorkspace: boolean;
    domains: Domain[];
    folders: Folder[];
    tags: { id: number; name: string }[];
    links: ShortLink[];
}>();

const filters = ref({ search: '', status: '', tag: '' });
const createOpen = ref(false);
const selectedLink = ref<ShortLink | null>(null);
const copiedLinkId = ref<number | null>(null);
const usableDomains = computed(() => props.domains.filter((domain) => domain.status === 'verified'));

// ── Folder groups ────────────────────────────────────────────────────────────

type Group = { key: string; folder: Folder | null; links: ShortLink[] };

const hasActiveFilters = computed(() => Boolean(filters.value.search || filters.value.status || filters.value.tag));

function matchesFilters(link: ShortLink) {
    const haystack = `${link.short_url} ${link.destination_url} ${link.slug}`.toLowerCase();
    const matchesSearch = !filters.value.search || haystack.includes(filters.value.search.toLowerCase());
    const matchesStatus = !filters.value.status || link.status === filters.value.status;
    const matchesTag = !filters.value.tag || link.tags.some((tag) => tag.name === filters.value.tag);
    return matchesSearch && matchesStatus && matchesTag;
}

const groups = computed<Group[]>(() => {
    const result: Group[] = props.folders.map((folder) => ({
        key: String(folder.id),
        folder,
        links: props.links.filter((link) => link.folder?.id === folder.id && matchesFilters(link)),
    }));

    const unfiled = props.links.filter((link) => !link.folder && matchesFilters(link));
    if (unfiled.length > 0 || props.folders.length === 0) {
        result.push({ key: 'unfiled', folder: null, links: unfiled });
    }

    // While filtering, empty groups are noise; without filters they are managed objects.
    return hasActiveFilters.value ? result.filter((group) => group.links.length > 0) : result;
});

const totalMatching = computed(() => groups.value.reduce((sum, group) => sum + group.links.length, 0));

// ── Collapse state (persisted per workspace) ─────────────────────────────────

const collapseStorageKey = `links.collapsed.${props.currentWorkspace.id}`;

function readCollapsed(): Set<string> {
    try {
        return new Set(JSON.parse(localStorage.getItem(collapseStorageKey) ?? '[]'));
    } catch {
        return new Set();
    }
}

const collapsed = ref<Set<string>>(readCollapsed());

function toggleCollapse(key: string) {
    const next = new Set(collapsed.value);
    next.has(key) ? next.delete(key) : next.add(key);
    collapsed.value = next;
    localStorage.setItem(collapseStorageKey, JSON.stringify([...next]));
}

// Active filters override collapse: search results must never be hidden.
function isCollapsed(key: string) {
    return collapsed.value.has(key) && !hasActiveFilters.value;
}

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

// ── Moving links (menu + drag & drop) ────────────────────────────────────────

const moveMenuFor = ref<number | null>(null);
const dragLinkId = ref<number | null>(null);
const dropGroupKey = ref<string | null>(null);

function moveLink(link: ShortLink, folderId: number | null) {
    moveMenuFor.value = null;
    if ((link.folder?.id ?? null) === folderId) {
        return;
    }

    router.post(route('short-links.move', link.id), { folder_id: folderId }, { preserveScroll: true });
}

function onDrop(group: Group) {
    const link = props.links.find((candidate) => candidate.id === dragLinkId.value);
    dragLinkId.value = null;
    dropGroupKey.value = null;

    if (link) {
        moveLink(link, group.folder?.id ?? null);
    }
}

// ── Link forms & actions ─────────────────────────────────────────────────────

const linkForm = useForm({
    domain_id: props.currentWorkspace.preferred_domain_id ?? usableDomains.value[0]?.id ?? '',
    folder_id: '',
    slug: '',
    destination_url: '',
    fallback_url: '',
    is_enabled: true,
    activates_at: '',
    expires_at: '',
    visit_limit: '',
    password: '',
    tags: '',
});

const editForm = useForm({
    folder_id: '',
    destination_url: '',
    fallback_url: '',
    is_enabled: true,
    activates_at: '',
    expires_at: '',
    visit_limit: '',
    password: '',
});

const qrForm = useForm({
    name: '',
    size: 1024,
    foreground_color: '#171717',
    background_color: '#fafafa',
    margin: 2,
    error_correction: 'medium',
});

watch(selectedLink, (link) => {
    if (!link) {
        return;
    }

    editForm.defaults({
        folder_id: link.folder?.id ? String(link.folder.id) : '',
        destination_url: link.destination_url,
        fallback_url: link.fallback_url ?? '',
        is_enabled: link.is_enabled,
        activates_at: link.activates_at ? String(link.activates_at).slice(0, 16) : '',
        expires_at: link.expires_at ? String(link.expires_at).slice(0, 16) : '',
        visit_limit: link.visit_limit ? String(link.visit_limit) : '',
        password: '',
    });
    editForm.reset();
    qrForm.reset();
});

watch(() => props.links, (links) => {
    if (!selectedLink.value) {
        return;
    }

    selectedLink.value = links.find((link) => link.id === selectedLink.value?.id) ?? null;
});

function submitLink() {
    linkForm.post(route('short-links.store'), {
        preserveScroll: true,
        onSuccess: () => {
            linkForm.reset('slug', 'destination_url', 'fallback_url', 'password', 'tags');
            createOpen.value = false;
        },
    });
}

function updateLink() {
    if (!selectedLink.value) {
        return;
    }

    editForm.patch(route('short-links.update', selectedLink.value.id), { preserveScroll: true });
}

function submitQr() {
    if (!selectedLink.value) {
        return;
    }

    qrForm.post(route('qr-codes.store', selectedLink.value.id), {
        preserveScroll: true,
        onSuccess: () => qrForm.reset('name'),
    });
}

function archiveLink(link: ShortLink) {
    useForm({}).post(route('short-links.archive', link.id), { preserveScroll: true });
}

function deleteLink(link: ShortLink) {
    if (confirm(`Permanently delete ${link.short_url}? This frees its slug.`)) {
        useForm({}).delete(route('short-links.destroy', link.id), { preserveScroll: true });
    }
}

async function copyShortUrl(link: ShortLink) {
    try {
        await navigator.clipboard.writeText(link.short_url);
        copiedLinkId.value = link.id;
        setTimeout(() => {
            if (copiedLinkId.value === link.id) {
                copiedLinkId.value = null;
            }
        }, 1500);
    } catch {
        // Clipboard unavailable (insecure context) — ignore silently.
    }
}

function statusVariant(status: string) {
    if (status === 'active') return 'success';
    if (status === 'scheduled') return 'accent';
    if (status === 'expired') return 'warning';
    if (status === 'archived') return 'default';
    return 'danger';
}

function qrPreviewUrl(qr: Qr) {
    return route('qr-codes.preview', qr.token);
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
                                    {{ link.short_url }}
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

                            <p class="min-w-0 truncate text-[13px] text-muted">{{ link.destination_url }}</p>

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

                            <div class="flex justify-start gap-0.5 lg:justify-end">
                                <a
                                    :href="link.short_url"
                                    target="_blank"
                                    class="grid h-8 w-8 place-items-center rounded-md text-muted transition-colors duration-150 hover:bg-elevated hover:text-foreground"
                                    title="Open"
                                >
                                    <ExternalLink class="h-4 w-4" />
                                </a>

                                <div v-if="canEditWorkspace" class="relative">
                                    <IconButton title="Move to folder" @click="moveMenuFor = moveMenuFor === link.id ? null : link.id">
                                        <FolderInput class="h-4 w-4" />
                                    </IconButton>
                                    <template v-if="moveMenuFor === link.id">
                                        <button class="fixed inset-0 z-20 cursor-default" tabindex="-1" @click="moveMenuFor = null" />
                                        <div class="absolute right-0 top-full z-30 mt-1 max-h-56 w-48 overflow-y-auto rounded-lg bg-overlay p-1 shadow-popover">
                                            <button
                                                v-if="link.folder"
                                                class="flex w-full items-center gap-2 rounded-[5px] px-2.5 py-1.5 text-left text-[13px] italic text-muted transition-colors hover:bg-elevated hover:text-foreground"
                                                @click="moveLink(link, null)"
                                            >
                                                <Inbox class="h-3.5 w-3.5 text-faint" /> Unfiled
                                            </button>
                                            <button
                                                v-for="folder in folders.filter((candidate) => candidate.id !== link.folder?.id)"
                                                :key="folder.id"
                                                class="flex w-full items-center gap-2 rounded-[5px] px-2.5 py-1.5 text-left text-[13px] text-muted transition-colors hover:bg-elevated hover:text-foreground"
                                                @click="moveLink(link, folder.id)"
                                            >
                                                <FolderIcon class="h-3.5 w-3.5 shrink-0 text-faint" />
                                                <span class="truncate">{{ folder.name }}</span>
                                            </button>
                                        </div>
                                    </template>
                                </div>

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
                <form class="grid gap-5" @submit.prevent="updateLink">
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
                        <Field label="New password" hint="Leave empty to keep the current password." :error="editForm.errors.password">
                            <input v-model="editForm.password" type="password" class="h-9" />
                        </Field>
                    </div>

                    <div class="flex justify-end">
                        <Button :loading="editForm.processing">Save changes</Button>
                    </div>
                </form>

                <form class="grid gap-5 border-t pt-6" @submit.prevent="submitQr">
                    <h4 class="flex items-center gap-2 text-[13px] font-semibold text-foreground"><QrCode class="h-4 w-4 text-faint" /> QR codes</h4>

                    <Field label="QR name" :error="qrForm.errors.name">
                        <input v-model="qrForm.name" class="h-9" placeholder="Poster, badge, flyer…" />
                    </Field>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <Field label="Size" :error="qrForm.errors.size">
                            <input v-model="qrForm.size" type="number" min="128" max="4096" class="h-9" />
                        </Field>
                        <Field label="Error correction" :error="qrForm.errors.error_correction">
                            <select v-model="qrForm.error_correction" class="h-9">
                                <option value="low">Low</option>
                                <option value="medium">Medium</option>
                                <option value="quartile">Quartile</option>
                                <option value="high">High</option>
                            </select>
                        </Field>
                    </div>
                    <div class="grid gap-4 sm:grid-cols-3">
                        <Field label="Foreground">
                            <input v-model="qrForm.foreground_color" type="color" class="h-9 w-full cursor-pointer rounded-md border bg-surface p-1" />
                        </Field>
                        <Field label="Background">
                            <input v-model="qrForm.background_color" type="color" class="h-9 w-full cursor-pointer rounded-md border bg-surface p-1" />
                        </Field>
                        <Field label="Margin" :error="qrForm.errors.margin">
                            <input v-model="qrForm.margin" type="number" min="0" max="20" class="h-9" />
                        </Field>
                    </div>
                    <div>
                        <Button variant="secondary" :loading="qrForm.processing">Add QR code</Button>
                    </div>

                    <div class="grid gap-3">
                        <article v-for="qr in selectedLink.qr_codes" :key="qr.id" class="grid gap-4 rounded-lg border bg-elevated/30 p-3 sm:grid-cols-[112px_1fr]">
                            <img :src="qrPreviewUrl(qr)" :alt="`${qr.name} QR code`" class="h-28 w-28 rounded-md bg-white p-1.5" />
                            <div class="min-w-0">
                                <p class="truncate text-sm font-medium text-foreground">{{ qr.name }}</p>
                                <p class="mt-1 text-xs text-faint">Scans through this QR are tracked separately.</p>
                                <div class="mt-3 flex flex-wrap gap-2">
                                    <a
                                        :href="qrPreviewUrl(qr)"
                                        target="_blank"
                                        class="rounded-md border px-2.5 py-1 text-xs font-medium text-muted transition-colors hover:border-border-strong hover:text-foreground"
                                    >
                                        View
                                    </a>
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
                </form>
            </div>
        </Drawer>
    </AuthenticatedLayout>
</template>
