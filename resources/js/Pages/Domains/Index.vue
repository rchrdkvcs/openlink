<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { Ban, Globe, Plus, RefreshCw } from '@lucide/vue';

type Workspace = { id: number; name: string; slug: string };
type Domain = { id: number; hostname: string; status: string; is_default: boolean; expected_txt?: string; failure_reason?: string | null };

defineProps<{
    currentWorkspace: Workspace;
    canManageWorkspace: boolean;
    domains: Domain[];
}>();

const domainForm = useForm({ hostname: '' });

function submitDomain() {
    domainForm.post(route('domains.store'), { preserveScroll: true, onSuccess: () => domainForm.reset() });
}

function verifyDomain(domain: Domain) {
    useForm({}).post(route('domains.verify', domain.id), { preserveScroll: true });
}

function disableDomain(domain: Domain) {
    useForm({}).post(route('domains.disable', domain.id), { preserveScroll: true });
}

function deleteDomain(domain: Domain) {
    if (confirm(`Delete ${domain.hostname}? Links using this domain will be deleted too.`)) {
        useForm({}).delete(route('domains.destroy', domain.id), { preserveScroll: true });
    }
}

function statusClass(domain: Domain) {
    if (domain.is_default) return 'bg-neutral-100 text-neutral-600';
    if (domain.status === 'verified') return 'bg-neutral-950 text-white';
    return 'bg-amber-50 text-amber-700 ring-1 ring-amber-200';
}
</script>

<template>
    <Head title="Domains" />

    <AuthenticatedLayout>
        <template #header>
            <div class="hidden min-w-0 items-center justify-between gap-4 lg:flex">
                <div>
                    <p class="text-xs font-medium uppercase text-neutral-500">Domains</p>
                    <h1 class="text-base font-semibold text-neutral-950">{{ currentWorkspace.name }}</h1>
                </div>
            </div>
        </template>

        <div class="w-full px-4 py-6 sm:px-6 lg:px-8">
            <div class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <h2 class="text-2xl font-semibold text-neutral-950">Domains</h2>
                    <p class="mt-1 text-sm text-neutral-500">Manage hostnames and DNS verification for this workspace.</p>
                </div>
            </div>

            <section class="rounded-md border border-neutral-200 bg-white">
                <form v-if="canManageWorkspace" class="grid gap-3 border-b border-neutral-200 p-4 sm:grid-cols-[1fr_auto]" @submit.prevent="submitDomain">
                    <label class="grid gap-1.5">
                        <span class="text-sm font-medium text-neutral-800">Hostname</span>
                        <input v-model="domainForm.hostname" class="h-10 rounded-md border-neutral-300 text-sm" placeholder="go.example.com" />
                        <span class="text-xs text-neutral-500">Enter the domain or subdomain used for short URLs.</span>
                    </label>
                    <button class="inline-flex h-10 items-center justify-center gap-2 self-end rounded-md bg-neutral-950 px-4 text-sm font-medium text-white hover:bg-neutral-800">
                        <Plus class="h-4 w-4" /> Add domain
                    </button>
                </form>

                <div class="hidden grid-cols-[minmax(220px,1fr)_120px_minmax(260px,1fr)_132px] border-b border-neutral-200 px-5 py-2 text-xs font-medium uppercase text-neutral-400 lg:grid">
                    <span>Hostname</span>
                    <span>Status</span>
                    <span>DNS record</span>
                    <span class="text-right">Actions</span>
                </div>

                <div class="divide-y divide-neutral-100">
                    <article v-for="domain in domains" :key="domain.id" class="grid gap-3 px-5 py-4 lg:grid-cols-[minmax(220px,1fr)_120px_minmax(260px,1fr)_132px] lg:items-start">
                        <div>
                            <p class="truncate text-sm font-medium text-neutral-950">{{ domain.hostname }}</p>
                            <p class="mt-1 text-xs text-neutral-500">{{ domain.is_default ? 'Application default' : 'Workspace domain' }}</p>
                        </div>
                        <div>
                            <span class="inline-flex rounded-md px-2 py-1 text-xs font-medium" :class="statusClass(domain)">
                                {{ domain.is_default ? 'default' : domain.status }}
                            </span>
                        </div>
                        <div>
                            <p v-if="!domain.is_default" class="break-all rounded-md bg-[#fafafa] px-2 py-1 text-xs text-neutral-500">TXT {{ domain.expected_txt }}</p>
                            <p v-if="domain.failure_reason" class="mt-2 text-xs text-rose-700">{{ domain.failure_reason }}</p>
                        </div>
                        <div class="flex gap-1 lg:justify-end">
                            <button v-if="canManageWorkspace && !domain.is_default" class="grid h-8 w-8 place-items-center rounded-md border border-neutral-200 text-neutral-500 hover:text-neutral-950" @click="verifyDomain(domain)" title="Verify DNS">
                                <RefreshCw class="h-4 w-4" />
                            </button>
                            <button v-if="canManageWorkspace && !domain.is_default" class="grid h-8 w-8 place-items-center rounded-md border border-rose-200 text-rose-700 hover:bg-rose-50" @click="disableDomain(domain)" title="Disable domain">
                                <Ban class="h-4 w-4" />
                            </button>
                            <button v-if="canManageWorkspace && !domain.is_default" class="h-8 rounded-md border border-rose-200 px-2 text-xs font-medium text-rose-700 hover:bg-rose-50" @click="deleteDomain(domain)" title="Delete domain">
                                Delete
                            </button>
                        </div>
                    </article>
                </div>

                <div v-if="domains.length === 0" class="px-5 py-16 text-center">
                    <Globe class="mx-auto h-8 w-8 text-neutral-300" />
                    <p class="mt-3 text-sm font-medium text-neutral-950">No domains configured</p>
                    <p class="mt-1 text-sm text-neutral-500">Add a domain to start using branded short URLs.</p>
                </div>
            </section>
        </div>
    </AuthenticatedLayout>
</template>
