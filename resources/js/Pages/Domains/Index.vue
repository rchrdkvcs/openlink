<script setup lang="ts">
import Badge from '@/Components/ui/Badge.vue';
import Button from '@/Components/ui/Button.vue';
import EmptyState from '@/Components/ui/EmptyState.vue';
import Field from '@/Components/ui/Field.vue';
import IconButton from '@/Components/ui/IconButton.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { ArrowRightLeft, Ban, Globe, Plus, RefreshCw, Trash2 } from '@lucide/vue';
import { ref } from 'vue';

type Workspace = { id: number; name: string; slug: string };
type Domain = { id: number; hostname: string; status: string; is_default: boolean; expected_txt?: string; failure_reason?: string | null };

const props = defineProps<{
    currentWorkspace: Workspace;
    workspaces: Workspace[];
    canManageWorkspace: boolean;
    domains: Domain[];
}>();

const domainForm = useForm({ hostname: '' });
const transferMenuFor = ref<number | null>(null);
const transferForm = useForm({ workspace_id: '' });

function submitDomain() {
    domainForm.post(route('domains.store'), { preserveScroll: true, onSuccess: () => domainForm.reset() });
}

function verifyDomain(domain: Domain) {
    useForm({}).post(route('domains.verify', domain.id), { preserveScroll: true });
}

function disableDomain(domain: Domain) {
    useForm({}).post(route('domains.disable', domain.id), { preserveScroll: true });
}

function openTransfer(domain: Domain) {
    transferForm.clearErrors();
    transferForm.workspace_id = '';
    transferMenuFor.value = transferMenuFor.value === domain.id ? null : domain.id;
}

function transferDomain(domain: Domain) {
    transferForm.post(route('domains.transfer', domain.id), {
        preserveScroll: true,
        onSuccess: () => {
            transferMenuFor.value = null;
            transferForm.reset();
        },
    });
}

function deleteDomain(domain: Domain) {
    if (confirm(`Delete ${domain.hostname}? Links using this domain will be deleted too.`)) {
        useForm({}).delete(route('domains.destroy', domain.id), { preserveScroll: true });
    }
}

function statusVariant(domain: Domain) {
    if (domain.is_default) return 'outline';
    if (domain.status === 'verified') return 'success';
    return 'warning';
}

function targetWorkspaces() {
    return props.workspaces.filter((workspace) => workspace.id !== props.currentWorkspace.id);
}
</script>

<template>
    <Head title="Domains" />

    <AuthenticatedLayout>
        <template #header>
            <PageHeader section="Domains" />
        </template>

        <div class="w-full px-4 py-8 sm:px-6 lg:px-8">
            <div class="mb-6">
                <h1 class="text-xl font-semibold tracking-tight">Domains</h1>
                <p class="mt-1 text-sm text-muted">Manage hostnames and DNS verification for this workspace.</p>
            </div>

            <section class="card-sheen overflow-hidden rounded-lg border bg-surface">
                <form v-if="canManageWorkspace" class="flex flex-col gap-3 border-b p-4 sm:flex-row sm:items-end" @submit.prevent="submitDomain">
                    <Field
                        label="Hostname"
                        hint="Enter the domain or subdomain used for short URLs."
                        :error="domainForm.errors.hostname"
                        class="min-w-0 flex-1"
                    >
                        <input v-model="domainForm.hostname" class="h-9" placeholder="go.example.com" />
                    </Field>
                    <Button class="mb-[22px] shrink-0" :loading="domainForm.processing">
                        <Plus class="h-4 w-4" /> Add domain
                    </Button>
                </form>

                <div class="hidden grid-cols-[minmax(220px,1fr)_120px_minmax(260px,1fr)_160px] border-b px-4 py-2 text-[11px] font-medium uppercase tracking-wide text-faint lg:grid">
                    <span>Hostname</span>
                    <span>Status</span>
                    <span>DNS record</span>
                    <span class="text-right">Actions</span>
                </div>

                <div class="divide-y divide-border/60">
                    <article
                        v-for="domain in domains"
                        :key="domain.id"
                        class="grid gap-3 px-4 py-3.5 transition-colors duration-100 hover:bg-elevated/40 lg:grid-cols-[minmax(220px,1fr)_120px_minmax(260px,1fr)_160px] lg:items-start"
                    >
                        <div>
                            <p class="truncate text-sm font-medium text-foreground">{{ domain.hostname }}</p>
                            <p class="mt-0.5 text-xs text-faint">{{ domain.is_default ? 'Application default' : 'Workspace domain' }}</p>
                        </div>
                        <div>
                            <Badge :variant="statusVariant(domain)" dot>{{ domain.is_default ? 'default' : domain.status }}</Badge>
                        </div>
                        <div class="min-w-0">
                            <code v-if="!domain.is_default" class="block break-all rounded-md bg-elevated px-2 py-1 font-mono text-xs text-muted">
                                TXT {{ domain.expected_txt }}
                            </code>
                            <p v-if="domain.failure_reason" class="mt-1.5 text-xs text-danger">{{ domain.failure_reason }}</p>
                        </div>
                        <div class="flex gap-0.5 lg:justify-end">
                            <IconButton v-if="canManageWorkspace && !domain.is_default" title="Verify DNS" @click="verifyDomain(domain)">
                                <RefreshCw class="h-4 w-4" />
                            </IconButton>
                            <div v-if="canManageWorkspace && !domain.is_default" class="relative">
                                <IconButton title="Transfer domain" @click="openTransfer(domain)">
                                    <ArrowRightLeft class="h-4 w-4" />
                                </IconButton>
                                <template v-if="transferMenuFor === domain.id">
                                    <button class="fixed inset-0 z-20 cursor-default" tabindex="-1" @click="transferMenuFor = null" />
                                    <form class="absolute right-0 top-full z-30 mt-1 grid w-64 gap-2 rounded-lg bg-overlay p-3 shadow-popover" @submit.prevent="transferDomain(domain)">
                                        <Field label="Transfer to" :error="transferForm.errors.workspace_id">
                                            <select v-model="transferForm.workspace_id" class="h-9">
                                                <option value="">Choose workspace</option>
                                                <option v-for="workspace in targetWorkspaces()" :key="workspace.id" :value="workspace.id">{{ workspace.name }}</option>
                                            </select>
                                        </Field>
                                        <Button size="sm" :loading="transferForm.processing" :disabled="!transferForm.workspace_id">Transfer</Button>
                                    </form>
                                </template>
                            </div>
                            <IconButton v-if="canManageWorkspace && !domain.is_default" variant="danger" title="Disable domain" @click="disableDomain(domain)">
                                <Ban class="h-4 w-4" />
                            </IconButton>
                            <IconButton v-if="canManageWorkspace && !domain.is_default" variant="danger" title="Delete domain" @click="deleteDomain(domain)">
                                <Trash2 class="h-4 w-4" />
                            </IconButton>
                        </div>
                    </article>
                </div>

                <EmptyState v-if="domains.length === 0" title="No domains configured" description="Add a domain to start using branded short URLs.">
                    <template #icon><Globe class="h-5 w-5" /></template>
                </EmptyState>
            </section>
        </div>
    </AuthenticatedLayout>
</template>
