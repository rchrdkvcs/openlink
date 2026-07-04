<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { Archive, ExternalLink, Folder as FolderIcon, Link2, Lock, Plus, QrCode, Search, Settings2, Trash2, X } from '@lucide/vue';
import { computed, ref, watch } from 'vue';

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
const selectedFolderId = ref<'all' | 'unfiled' | number>('all');
const createOpen = ref(false);
const selectedLink = ref<ShortLink | null>(null);
const usableDomains = computed(() => props.domains.filter((domain) => domain.status === 'verified'));
const folderForm = useForm({ name: '' });

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

const filteredLinks = computed(() => {
    return props.links.filter((link) => {
        const haystack = `${link.short_url} ${link.destination_url} ${link.slug}`.toLowerCase();
        const matchesSearch = !filters.value.search || haystack.includes(filters.value.search.toLowerCase());
        const matchesStatus = !filters.value.status || link.status === filters.value.status;
        const matchesFolder = selectedFolderId.value === 'all'
            || (selectedFolderId.value === 'unfiled' && !link.folder)
            || link.folder?.id === selectedFolderId.value;
        const matchesTag = !filters.value.tag || link.tags.some((tag) => tag.name === filters.value.tag);

        return matchesSearch && matchesStatus && matchesFolder && matchesTag;
    });
});

const unfiledCount = computed(() => props.links.filter((link) => !link.folder).length);

function folderLinks(folder: Folder) {
    return props.links.filter((link) => link.folder?.id === folder.id);
}

function submitFolder() {
    folderForm.post(route('folders.store'), { preserveScroll: true, onSuccess: () => folderForm.reset() });
}

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

function statusClass(status: string) {
    if (status === 'active') return 'bg-neutral-950 text-white';
    if (status === 'scheduled') return 'bg-blue-50 text-blue-700 ring-1 ring-blue-200';
    if (status === 'expired') return 'bg-amber-50 text-amber-700 ring-1 ring-amber-200';
    if (status === 'archived') return 'bg-neutral-100 text-neutral-500';
    return 'bg-rose-50 text-rose-700 ring-1 ring-rose-200';
}

function qrPreviewUrl(qr: Qr) {
    return route('qr-codes.preview', qr.token);
}
</script>

<template>
    <Head title="Links" />

    <AuthenticatedLayout>
        <template #header>
            <div class="hidden min-w-0 items-center justify-between gap-4 lg:flex">
                <div>
                    <p class="text-xs font-medium uppercase text-neutral-500">Links</p>
                    <h1 class="text-base font-semibold text-neutral-950">{{ currentWorkspace.name }}</h1>
                </div>
                <button v-if="canEditWorkspace" class="inline-flex h-9 items-center gap-2 rounded-md bg-neutral-950 px-3 text-sm font-medium text-white hover:bg-neutral-800" @click="createOpen = true">
                    <Plus class="h-4 w-4" /> New link
                </button>
            </div>
        </template>

        <div class="w-full px-4 py-6 sm:px-6 lg:px-8">
            <div class="mb-5 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <h2 class="text-2xl font-semibold text-neutral-950">Links</h2>
                    <p class="mt-1 text-sm text-neutral-500">Create, filter, and maintain short URLs for this workspace.</p>
                </div>
                <button v-if="canEditWorkspace" class="inline-flex h-9 w-fit items-center gap-2 rounded-md bg-neutral-950 px-3 text-sm font-medium text-white hover:bg-neutral-800 lg:hidden" @click="createOpen = true">
                    <Plus class="h-4 w-4" /> New link
                </button>
            </div>

            <section class="grid overflow-hidden rounded-md border border-neutral-200 bg-white xl:grid-cols-[300px_minmax(0,1fr)]">
                <aside class="border-b border-neutral-200 xl:border-b-0 xl:border-r">
                    <div class="border-b border-neutral-200 p-4">
                        <form v-if="canManageWorkspace" class="grid gap-2" @submit.prevent="submitFolder">
                            <label class="grid gap-1.5">
                                <span class="text-sm font-medium text-neutral-800">New folder</span>
                                <input v-model="folderForm.name" class="h-9 rounded-md border-neutral-300 text-sm" placeholder="Campaign, Event, Client..." />
                            </label>
                            <button class="inline-flex h-9 items-center justify-center gap-2 rounded-md border border-neutral-200 px-3 text-sm font-medium text-neutral-950 hover:bg-neutral-100">
                                <Plus class="h-4 w-4" /> Create folder
                            </button>
                        </form>
                    </div>

                    <nav class="space-y-1 p-3">
                        <button
                            class="flex w-full items-center justify-between gap-3 rounded-md px-3 py-2 text-left text-sm"
                            :class="selectedFolderId === 'all' ? 'bg-neutral-100 text-neutral-950' : 'text-neutral-500 hover:bg-neutral-100 hover:text-neutral-950'"
                            @click="selectedFolderId = 'all'"
                        >
                            <span class="inline-flex min-w-0 items-center gap-2"><FolderIcon class="h-4 w-4" /> All links</span>
                            <span class="text-xs">{{ links.length }}</span>
                        </button>
                        <button
                            class="flex w-full items-center justify-between gap-3 rounded-md px-3 py-2 text-left text-sm"
                            :class="selectedFolderId === 'unfiled' ? 'bg-neutral-100 text-neutral-950' : 'text-neutral-500 hover:bg-neutral-100 hover:text-neutral-950'"
                            @click="selectedFolderId = 'unfiled'"
                        >
                            <span class="inline-flex min-w-0 items-center gap-2"><FolderIcon class="h-4 w-4" /> Unfiled</span>
                            <span class="text-xs">{{ unfiledCount }}</span>
                        </button>

                        <div class="my-2 border-t border-neutral-200" />

                        <button
                            v-for="folder in folders"
                            :key="folder.id"
                            class="flex w-full items-center justify-between gap-3 rounded-md px-3 py-2 text-left text-sm"
                            :class="selectedFolderId === folder.id ? 'bg-neutral-100 text-neutral-950' : 'text-neutral-500 hover:bg-neutral-100 hover:text-neutral-950'"
                            @click="selectedFolderId = folder.id"
                        >
                            <span class="inline-flex min-w-0 items-center gap-2">
                                <FolderIcon class="h-4 w-4 shrink-0" />
                                <span class="truncate">{{ folder.name }}</span>
                            </span>
                            <span class="text-xs">{{ folderLinks(folder).length }}</span>
                        </button>
                    </nav>
                </aside>

                <div class="min-w-0">
                    <div class="grid gap-3 border-b border-neutral-200 p-4 lg:grid-cols-[minmax(260px,1fr)_150px_150px]">
                        <label class="grid gap-1.5">
                            <span class="text-xs font-medium uppercase text-neutral-500">Search</span>
                            <span class="relative block">
                            <Search class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-neutral-400" />
                            <input v-model="filters.search" class="h-9 w-full rounded-md border-neutral-300 pl-9 text-sm" placeholder="Search by URL, slug, destination" />
                            </span>
                        </label>
                        <label class="grid gap-1.5">
                            <span class="text-xs font-medium uppercase text-neutral-500">Status</span>
                            <select v-model="filters.status" class="h-9 rounded-md border-neutral-300 text-sm">
                                <option value="">All statuses</option>
                                <option value="active">Active</option>
                                <option value="scheduled">Scheduled</option>
                                <option value="expired">Expired</option>
                                <option value="disabled">Disabled</option>
                                <option value="archived">Archived</option>
                            </select>
                        </label>
                        <label class="grid gap-1.5">
                            <span class="text-xs font-medium uppercase text-neutral-500">Tag</span>
                            <select v-model="filters.tag" class="h-9 rounded-md border-neutral-300 text-sm">
                                <option value="">All tags</option>
                                <option v-for="tag in tags" :key="tag.id" :value="tag.name">{{ tag.name }}</option>
                            </select>
                        </label>
                    </div>

                    <div class="hidden grid-cols-[minmax(220px,1.2fr)_minmax(240px,1fr)_120px_120px_112px] border-b border-neutral-200 px-4 py-2 text-xs font-medium uppercase text-neutral-400 lg:grid">
                        <span>Short URL</span>
                        <span>Destination</span>
                        <span>Status</span>
                        <span>Activity</span>
                        <span class="text-right">Actions</span>
                    </div>

                    <div class="divide-y divide-neutral-100">
                        <article v-for="link in filteredLinks" :key="link.id" class="grid gap-3 px-4 py-4 lg:grid-cols-[minmax(220px,1.2fr)_minmax(240px,1fr)_120px_120px_112px] lg:items-center">
                        <div class="min-w-0">
                            <a :href="link.short_url" target="_blank" class="truncate text-sm font-semibold text-neutral-950 hover:underline">{{ link.short_url }}</a>
                            <div class="mt-1 flex flex-wrap gap-1.5">
                                <span v-if="link.folder" class="rounded bg-neutral-100 px-1.5 py-0.5 text-xs text-neutral-500">{{ link.folder.name }}</span>
                                <span v-for="tag in link.tags" :key="tag.id" class="rounded bg-neutral-100 px-1.5 py-0.5 text-xs text-neutral-500">#{{ tag.name }}</span>
                            </div>
                        </div>
                        <p class="min-w-0 truncate text-sm text-neutral-500">{{ link.destination_url }}</p>
                        <div class="flex items-center gap-2">
                            <span class="rounded-md px-2 py-1 text-xs font-medium" :class="statusClass(link.status)">{{ link.status }}</span>
                            <Lock v-if="link.has_password" class="h-4 w-4 text-amber-600" />
                        </div>
                        <div class="text-sm text-neutral-500">
                            <span class="font-medium text-neutral-950">{{ link.visits }}</span> visits
                            <span class="mx-1 text-neutral-300">/</span>
                            <span class="font-medium text-neutral-950">{{ link.scans }}</span> scans
                        </div>
                        <div class="flex justify-start gap-1 lg:justify-end">
                            <a :href="link.short_url" target="_blank" class="grid h-8 w-8 place-items-center rounded-md border border-neutral-200 text-neutral-500 hover:text-neutral-950" title="Open">
                                <ExternalLink class="h-4 w-4" />
                            </a>
                            <button class="grid h-8 w-8 place-items-center rounded-md border border-neutral-200 text-neutral-500 hover:text-neutral-950" title="Edit" @click="selectedLink = link">
                                <Settings2 class="h-4 w-4" />
                            </button>
                            <button v-if="canEditWorkspace" class="grid h-8 w-8 place-items-center rounded-md border border-neutral-200 text-neutral-500 hover:text-neutral-950" title="Archive" @click="archiveLink(link)">
                                <Archive class="h-4 w-4" />
                            </button>
                            <button v-if="canManageWorkspace" class="grid h-8 w-8 place-items-center rounded-md border border-rose-200 text-rose-700 hover:bg-rose-50" title="Delete" @click="deleteLink(link)">
                                <Trash2 class="h-4 w-4" />
                            </button>
                        </div>
                        </article>

                        <div v-if="filteredLinks.length === 0" class="px-5 py-16 text-center">
                            <Link2 class="mx-auto h-8 w-8 text-neutral-300" />
                            <p class="mt-3 text-sm font-medium text-neutral-950">No links found</p>
                            <p class="mt-1 text-sm text-neutral-500">Create a link, pick another folder, or clear the filters.</p>
                        </div>
                    </div>
                </div>
            </section>
        </div>

        <div v-if="createOpen || selectedLink" class="fixed inset-0 z-50">
            <button class="absolute inset-0 bg-neutral-950/20" @click="createOpen = false; selectedLink = null" />
            <aside class="absolute right-0 top-0 h-full w-full max-w-xl overflow-y-auto border-l border-neutral-200 bg-[#fafafa] shadow-2xl">
                <div class="sticky top-0 z-10 flex items-center justify-between border-b border-neutral-200 bg-[#fafafa] px-5 py-4">
                    <div>
                        <p class="text-xs font-medium uppercase text-neutral-500">{{ selectedLink ? 'Link settings' : 'New link' }}</p>
                        <h3 class="text-base font-semibold text-neutral-950">{{ selectedLink?.short_url ?? 'Create short URL' }}</h3>
                    </div>
                    <button class="grid h-9 w-9 place-items-center rounded-md border border-neutral-200 bg-white text-neutral-500 hover:text-neutral-950" @click="createOpen = false; selectedLink = null">
                        <X class="h-4 w-4" />
                    </button>
                </div>

                <form v-if="!selectedLink" class="grid gap-4 p-5" @submit.prevent="submitLink">
                    <label class="grid gap-1.5">
                        <span class="text-sm font-medium text-neutral-800">Domain</span>
                        <select v-model="linkForm.domain_id" class="h-10 rounded-md border-neutral-300 text-sm">
                            <option v-for="domain in usableDomains" :key="domain.id" :value="domain.id">{{ domain.hostname }}</option>
                        </select>
                        <span class="text-xs text-neutral-500">The hostname used for the short URL.</span>
                    </label>
                    <label class="grid gap-1.5">
                        <span class="text-sm font-medium text-neutral-800">Custom slug</span>
                        <input v-model="linkForm.slug" class="h-10 rounded-md border-neutral-300 text-sm" placeholder="summer-launch" />
                        <span class="text-xs text-neutral-500">Leave empty to generate one automatically.</span>
                    </label>
                    <label class="grid gap-1.5">
                        <span class="text-sm font-medium text-neutral-800">Destination URL</span>
                        <input v-model="linkForm.destination_url" class="h-10 rounded-md border-neutral-300 text-sm" placeholder="https://destination.example" />
                    </label>
                    <label class="grid gap-1.5">
                        <span class="text-sm font-medium text-neutral-800">Folder</span>
                        <select v-model="linkForm.folder_id" class="h-10 rounded-md border-neutral-300 text-sm">
                            <option value="">No folder</option>
                            <option v-for="folder in folders" :key="folder.id" :value="folder.id">{{ folder.name }}</option>
                        </select>
                    </label>
                    <label class="grid gap-1.5">
                        <span class="text-sm font-medium text-neutral-800">Fallback URL</span>
                        <input v-model="linkForm.fallback_url" class="h-10 rounded-md border-neutral-300 text-sm" placeholder="https://fallback.example" />
                        <span class="text-xs text-neutral-500">Optional URL used when the link is expired or unavailable.</span>
                    </label>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <label class="grid gap-1.5">
                            <span class="text-sm font-medium text-neutral-800">Activation date</span>
                            <input v-model="linkForm.activates_at" type="datetime-local" class="h-10 rounded-md border-neutral-300 text-sm" />
                        </label>
                        <label class="grid gap-1.5">
                            <span class="text-sm font-medium text-neutral-800">Expiration date</span>
                            <input v-model="linkForm.expires_at" type="datetime-local" class="h-10 rounded-md border-neutral-300 text-sm" />
                        </label>
                    </div>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <label class="grid gap-1.5">
                            <span class="text-sm font-medium text-neutral-800">Visit limit</span>
                            <input v-model="linkForm.visit_limit" type="number" min="1" class="h-10 rounded-md border-neutral-300 text-sm" placeholder="1000" />
                        </label>
                        <label class="grid gap-1.5">
                            <span class="text-sm font-medium text-neutral-800">Password</span>
                            <input v-model="linkForm.password" type="password" class="h-10 rounded-md border-neutral-300 text-sm" placeholder="Optional" />
                        </label>
                    </div>
                    <label class="grid gap-1.5">
                        <span class="text-sm font-medium text-neutral-800">Tags</span>
                        <input v-model="linkForm.tags" class="h-10 rounded-md border-neutral-300 text-sm" placeholder="event, vip" />
                        <span class="text-xs text-neutral-500">Comma-separated tags for filtering.</span>
                    </label>
                    <button class="mt-2 h-10 rounded-md bg-neutral-950 px-4 text-sm font-medium text-white hover:bg-neutral-800">Create link</button>
                </form>

                <div v-else class="space-y-6 p-5">
                    <form class="grid gap-4" @submit.prevent="updateLink">
                        <label class="grid gap-1.5">
                            <span class="text-sm font-medium text-neutral-800">Folder</span>
                            <select v-model="editForm.folder_id" class="h-10 rounded-md border-neutral-300 text-sm">
                                <option value="">No folder</option>
                                <option v-for="folder in folders" :key="folder.id" :value="folder.id">{{ folder.name }}</option>
                            </select>
                            <span class="text-xs text-neutral-500">Move this link into a project folder.</span>
                        </label>
                        <label class="grid gap-1.5">
                            <span class="text-sm font-medium text-neutral-800">Destination URL</span>
                            <input v-model="editForm.destination_url" class="h-10 rounded-md border-neutral-300 text-sm" placeholder="Destination URL" />
                        </label>
                        <label class="grid gap-1.5">
                            <span class="text-sm font-medium text-neutral-800">Fallback URL</span>
                            <input v-model="editForm.fallback_url" class="h-10 rounded-md border-neutral-300 text-sm" placeholder="Fallback URL" />
                        </label>
                        <label class="flex h-10 items-center gap-2 rounded-md border border-neutral-300 bg-white px-3 text-sm text-neutral-600">
                            <input v-model="editForm.is_enabled" type="checkbox" class="rounded border-neutral-300" />
                            Enabled
                        </label>
                        <div class="grid gap-3 sm:grid-cols-2">
                            <label class="grid gap-1.5">
                                <span class="text-sm font-medium text-neutral-800">Activation date</span>
                                <input v-model="editForm.activates_at" type="datetime-local" class="h-10 rounded-md border-neutral-300 text-sm" />
                            </label>
                            <label class="grid gap-1.5">
                                <span class="text-sm font-medium text-neutral-800">Expiration date</span>
                                <input v-model="editForm.expires_at" type="datetime-local" class="h-10 rounded-md border-neutral-300 text-sm" />
                            </label>
                        </div>
                        <div class="grid gap-3 sm:grid-cols-2">
                            <label class="grid gap-1.5">
                                <span class="text-sm font-medium text-neutral-800">Visit limit</span>
                                <input v-model="editForm.visit_limit" type="number" min="1" class="h-10 rounded-md border-neutral-300 text-sm" placeholder="1000" />
                            </label>
                            <label class="grid gap-1.5">
                                <span class="text-sm font-medium text-neutral-800">New password</span>
                                <input v-model="editForm.password" type="password" class="h-10 rounded-md border-neutral-300 text-sm" placeholder="Leave empty to keep current password" />
                            </label>
                        </div>
                        <button class="h-10 rounded-md bg-neutral-950 px-4 text-sm font-medium text-white hover:bg-neutral-800">Save changes</button>
                    </form>

                    <form class="grid gap-4 border-t border-neutral-200 pt-5" @submit.prevent="submitQr">
                        <h4 class="flex items-center gap-2 text-sm font-semibold text-neutral-950"><QrCode class="h-4 w-4" /> QR codes</h4>
                        <label class="grid gap-1.5">
                            <span class="text-sm font-medium text-neutral-800">QR name</span>
                            <input v-model="qrForm.name" class="h-10 rounded-md border-neutral-300 text-sm" placeholder="Poster, badge, flyer..." />
                        </label>
                        <div class="grid gap-3 sm:grid-cols-2">
                            <label class="grid gap-1.5">
                                <span class="text-sm font-medium text-neutral-800">Size</span>
                                <input v-model="qrForm.size" type="number" min="128" max="4096" class="h-10 rounded-md border-neutral-300 text-sm" />
                            </label>
                            <label class="grid gap-1.5">
                                <span class="text-sm font-medium text-neutral-800">Error correction</span>
                                <select v-model="qrForm.error_correction" class="h-10 rounded-md border-neutral-300 text-sm">
                                    <option value="low">Low</option>
                                    <option value="medium">Medium</option>
                                    <option value="quartile">Quartile</option>
                                    <option value="high">High</option>
                                </select>
                            </label>
                        </div>
                        <div class="grid gap-3 sm:grid-cols-3">
                            <label class="grid gap-1.5">
                                <span class="text-sm font-medium text-neutral-800">Foreground</span>
                                <input v-model="qrForm.foreground_color" type="color" class="h-10 rounded-md border border-neutral-300 p-1" />
                            </label>
                            <label class="grid gap-1.5">
                                <span class="text-sm font-medium text-neutral-800">Background</span>
                                <input v-model="qrForm.background_color" type="color" class="h-10 rounded-md border border-neutral-300 p-1" />
                            </label>
                            <label class="grid gap-1.5">
                                <span class="text-sm font-medium text-neutral-800">Margin</span>
                                <input v-model="qrForm.margin" type="number" min="0" max="20" class="h-10 rounded-md border-neutral-300 text-sm" />
                            </label>
                        </div>
                        <button class="h-10 rounded-md border border-neutral-200 bg-white px-4 text-sm font-medium text-neutral-950 hover:bg-neutral-50">Add QR code</button>
                        <div class="grid gap-3">
                            <article v-for="qr in selectedLink.qr_codes" :key="qr.id" class="grid gap-3 rounded-md border border-neutral-200 bg-white p-3 sm:grid-cols-[128px_1fr]">
                                <img :src="qrPreviewUrl(qr)" :alt="`${qr.name} QR code`" class="h-32 w-32 rounded-md border border-neutral-200 bg-white p-2" />
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-medium text-neutral-950">{{ qr.name }}</p>
                                    <p class="mt-1 text-xs text-neutral-500">Scans through this QR are tracked separately.</p>
                                    <div class="mt-3 flex flex-wrap gap-2">
                                        <a :href="qrPreviewUrl(qr)" target="_blank" class="rounded-md border border-neutral-200 px-2.5 py-1.5 text-xs font-medium text-neutral-950 hover:bg-neutral-100">View</a>
                                        <a :href="route('qr-codes.export', [qr.token, 'svg'])" class="rounded-md border border-neutral-200 px-2.5 py-1.5 text-xs font-medium text-neutral-950 hover:bg-neutral-100">Download SVG</a>
                                        <a :href="route('qr-codes.export', [qr.token, 'png'])" class="rounded-md border border-neutral-200 px-2.5 py-1.5 text-xs font-medium text-neutral-950 hover:bg-neutral-100">Download PNG</a>
                                    </div>
                                </div>
                            </article>
                            <p v-if="selectedLink.qr_codes.length === 0" class="rounded-md border border-neutral-200 px-3 py-4 text-sm text-neutral-500">No QR code yet for this link.</p>
                        </div>
                    </form>
                </div>
            </aside>
        </div>
    </AuthenticatedLayout>
</template>
