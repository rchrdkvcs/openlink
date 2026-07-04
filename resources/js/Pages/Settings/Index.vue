<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { Settings, ShieldCheck } from '@lucide/vue';

type Workspace = { id: number; name: string; slug: string; preferred_domain_id?: number | null };
type Domain = { id: number; hostname: string; status: string; is_default: boolean };

const props = defineProps<{
    currentWorkspace: Workspace;
    canManageWorkspace: boolean;
    domains: Domain[];
    settings: Record<string, any>;
}>();

const workspaceSettingsForm = useForm({
    name: props.currentWorkspace.name,
    preferred_domain_id: props.currentWorkspace.preferred_domain_id ?? '',
});
const settingsForm = useForm({
    registration_mode: props.settings.registration_mode ?? 'invite_only',
    default_domain: props.settings.default_domain ?? 'localhost',
    slug_length: props.settings.slug_length ?? 6,
    analytics_retention_days: props.settings.analytics_retention_days ?? 365,
    reserved_slugs: (props.settings.reserved_slugs ?? []).join('\n'),
    reserved_prefixes: (props.settings.reserved_prefixes ?? []).join('\n'),
    public_unavailable_title: props.settings.public_unavailable_title ?? 'This link is unavailable',
    public_unavailable_message: props.settings.public_unavailable_message ?? 'The link cannot be opened right now.',
});

function updateWorkspaceSettings() {
    workspaceSettingsForm.patch(route('workspaces.update-current'), { preserveScroll: true });
}

function updateSettings() {
    settingsForm.patch(route('instance-settings.update'), { preserveScroll: true });
}
</script>

<template>
    <Head title="Settings" />

    <AuthenticatedLayout>
        <template #header>
            <div class="hidden min-w-0 items-center justify-between gap-4 lg:flex">
                <div>
                    <p class="text-xs font-medium uppercase text-neutral-500">Settings</p>
                    <h1 class="text-base font-semibold text-neutral-950">{{ currentWorkspace.name }}</h1>
                </div>
            </div>
        </template>

        <div class="w-full px-4 py-6 sm:px-6 lg:px-8">
            <div class="mb-6">
                <h2 class="text-2xl font-semibold text-neutral-950">Settings</h2>
                <p class="mt-1 text-sm text-neutral-500">Workspace defaults and instance-level behaviour. Modules live in their own pages.</p>
            </div>

            <div class="space-y-5">
                <section class="rounded-md border border-neutral-200 bg-white">
                    <div class="border-b border-neutral-200 px-5 py-4">
                        <h3 class="flex items-center gap-2 text-sm font-semibold text-neutral-950"><ShieldCheck class="h-4 w-4" /> Workspace</h3>
                    </div>
                    <div class="p-5">
                        <form v-if="canManageWorkspace" class="grid max-w-2xl gap-4" @submit.prevent="updateWorkspaceSettings">
                            <label class="grid gap-1.5">
                                <span class="text-sm font-medium text-neutral-800">Workspace name</span>
                                <input v-model="workspaceSettingsForm.name" class="h-10 rounded-md border-neutral-300 text-sm" placeholder="Acme Events" />
                                <span class="text-xs text-neutral-500">Displayed in the sidebar workspace switcher.</span>
                            </label>
                            <label class="grid gap-1.5">
                                <span class="text-sm font-medium text-neutral-800">Preferred domain</span>
                                <select v-model="workspaceSettingsForm.preferred_domain_id" class="h-10 rounded-md border-neutral-300 text-sm">
                                <option value="">No preferred domain</option>
                                <option v-for="domain in domains" :key="domain.id" :value="domain.id">{{ domain.hostname }}</option>
                                </select>
                                <span class="text-xs text-neutral-500">Used as the default domain when creating new short links.</span>
                            </label>
                            <button class="h-10 w-fit rounded-md bg-neutral-950 px-4 text-sm font-medium text-white hover:bg-neutral-800">Save workspace settings</button>
                        </form>
                        <p v-else class="text-sm text-neutral-500">Only workspace managers can edit workspace settings.</p>
                    </div>
                </section>

                <section v-if="Object.keys(settings).length" class="rounded-md border border-neutral-200 bg-white">
                    <div class="border-b border-neutral-200 px-5 py-4">
                        <h3 class="flex items-center gap-2 text-sm font-semibold text-neutral-950"><Settings class="h-4 w-4" /> Instance</h3>
                    </div>
                    <form class="grid gap-4 p-5 md:grid-cols-4" @submit.prevent="updateSettings">
                        <label class="grid gap-1.5">
                            <span class="text-sm font-medium text-neutral-800">Registration mode</span>
                            <select v-model="settingsForm.registration_mode" class="h-10 rounded-md border-neutral-300 text-sm">
                                <option value="closed">Closed</option>
                                <option value="invite_only">Invite-only</option>
                                <option value="open">Open</option>
                            </select>
                        </label>
                        <label class="grid gap-1.5">
                            <span class="text-sm font-medium text-neutral-800">Default domain</span>
                            <input v-model="settingsForm.default_domain" class="h-10 rounded-md border-neutral-300 text-sm" placeholder="localhost" />
                        </label>
                        <label class="grid gap-1.5">
                            <span class="text-sm font-medium text-neutral-800">Generated slug length</span>
                            <input v-model="settingsForm.slug_length" type="number" class="h-10 rounded-md border-neutral-300 text-sm" min="4" max="32" />
                        </label>
                        <label class="grid gap-1.5">
                            <span class="text-sm font-medium text-neutral-800">Analytics retention</span>
                            <input v-model="settingsForm.analytics_retention_days" type="number" class="h-10 rounded-md border-neutral-300 text-sm" min="30" />
                        </label>
                        <label class="grid gap-1.5 md:col-span-2">
                            <span class="text-sm font-medium text-neutral-800">Unavailable page title</span>
                            <input v-model="settingsForm.public_unavailable_title" class="h-10 rounded-md border-neutral-300 text-sm" placeholder="This link is unavailable" />
                        </label>
                        <label class="grid gap-1.5 md:col-span-2">
                            <span class="text-sm font-medium text-neutral-800">Unavailable page message</span>
                            <input v-model="settingsForm.public_unavailable_message" class="h-10 rounded-md border-neutral-300 text-sm" placeholder="The link cannot be opened right now." />
                        </label>
                        <label class="grid gap-1.5 md:col-span-2">
                            <span class="text-sm font-medium text-neutral-800">Reserved slugs</span>
                            <textarea v-model="settingsForm.reserved_slugs" class="rounded-md border-neutral-300 text-sm" rows="4" placeholder="admin&#10;login&#10;settings" />
                        </label>
                        <label class="grid gap-1.5 md:col-span-2">
                            <span class="text-sm font-medium text-neutral-800">Reserved prefixes</span>
                            <textarea v-model="settingsForm.reserved_prefixes" class="rounded-md border-neutral-300 text-sm" rows="4" placeholder="api/&#10;qr/" />
                        </label>
                        <button class="h-10 rounded-md bg-neutral-950 px-4 text-sm font-medium text-white hover:bg-neutral-800 md:col-span-4">Save instance settings</button>
                    </form>
                </section>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
