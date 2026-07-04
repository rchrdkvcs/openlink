<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { Boxes, Check, Plus } from '@lucide/vue';

type Workspace = { id: number; name: string; slug: string; preferred_domain_id?: number | null };

const props = defineProps<{
    currentWorkspace: Workspace;
    workspaces: Workspace[];
}>();

const workspaceForm = useForm({ name: '' });

function submitWorkspace() {
    workspaceForm.post(route('workspaces.store'), { preserveScroll: true, onSuccess: () => workspaceForm.reset() });
}
</script>

<template>
    <Head title="Workspaces" />

    <AuthenticatedLayout>
        <template #header>
            <div class="hidden min-w-0 items-center justify-between gap-4 lg:flex">
                <div>
                    <p class="text-xs font-medium uppercase text-neutral-500">Workspaces</p>
                    <h1 class="text-base font-semibold text-neutral-950">{{ currentWorkspace.name }}</h1>
                </div>
            </div>
        </template>

        <div class="w-full px-4 py-6 sm:px-6 lg:px-8">
            <div class="mb-6">
                <h2 class="text-2xl font-semibold text-neutral-950">Workspaces</h2>
                <p class="mt-1 text-sm text-neutral-500">Switch between projects or create a new workspace for a separate team, event, or brand.</p>
            </div>

            <div class="grid gap-5 lg:grid-cols-[minmax(0,1fr)_360px]">
                <section class="rounded-md border border-neutral-200 bg-white">
                    <div class="border-b border-neutral-200 px-5 py-3 text-xs font-medium uppercase text-neutral-400">Available workspaces</div>
                    <div class="divide-y divide-neutral-100">
                        <article v-for="workspace in workspaces" :key="workspace.id" class="flex items-center justify-between gap-4 px-5 py-4">
                            <div class="min-w-0">
                                <p class="truncate text-sm font-medium text-neutral-950">{{ workspace.name }}</p>
                                <p class="mt-1 truncate text-xs text-neutral-500">{{ workspace.slug }}</p>
                            </div>
                            <div class="flex items-center gap-2">
                                <span v-if="workspace.id === currentWorkspace.id" class="inline-flex items-center gap-1 rounded-md bg-neutral-100 px-2 py-1 text-xs font-medium text-neutral-700">
                                    <Check class="h-3.5 w-3.5" /> Current
                                </span>
                                <Link
                                    v-else
                                    :href="route('workspaces.switch', workspace.id)"
                                    method="post"
                                    as="button"
                                    class="h-8 rounded-md border border-neutral-200 bg-white px-3 text-sm font-medium text-neutral-800 hover:bg-neutral-50"
                                >
                                    Switch
                                </Link>
                            </div>
                        </article>
                    </div>
                </section>

                <section class="rounded-md border border-neutral-200 bg-white">
                    <div class="border-b border-neutral-200 px-5 py-4">
                        <h3 class="flex items-center gap-2 text-sm font-semibold text-neutral-950"><Boxes class="h-4 w-4" /> Create workspace</h3>
                        <p class="mt-1 text-sm text-neutral-500">Use this when links, members, domains, or folders should be isolated.</p>
                    </div>
                    <form class="grid gap-3 p-5" @submit.prevent="submitWorkspace">
                        <label class="grid gap-1.5">
                            <span class="text-sm font-medium text-neutral-800">Workspace name</span>
                            <input v-model="workspaceForm.name" class="h-10 rounded-md border-neutral-300 text-sm" placeholder="Acme Events" />
                            <span class="text-xs text-neutral-500">Displayed in the workspace switcher and used to generate the internal slug.</span>
                        </label>
                        <button class="inline-flex h-10 items-center justify-center gap-2 rounded-md bg-neutral-950 px-4 text-sm font-medium text-white hover:bg-neutral-800">
                            <Plus class="h-4 w-4" /> Create workspace
                        </button>
                    </form>
                </section>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
