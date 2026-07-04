<script setup lang="ts">
import Badge from '@/Components/ui/Badge.vue';
import Button from '@/Components/ui/Button.vue';
import Field from '@/Components/ui/Field.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import SectionCard from '@/Components/ui/SectionCard.vue';
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

function initial(name: string) {
    return name.slice(0, 1).toUpperCase();
}
</script>

<template>
    <Head title="Workspaces" />

    <AuthenticatedLayout>
        <template #header>
            <PageHeader section="Workspaces" />
        </template>

        <div class="w-full px-4 py-8 sm:px-6 lg:px-8">
            <div class="mb-6">
                <h1 class="text-xl font-semibold tracking-tight">Workspaces</h1>
                <p class="mt-1 text-sm text-muted">Switch between projects or create a new workspace for a separate team, event, or brand.</p>
            </div>

            <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_360px]">
                <SectionCard title="Available workspaces">
                    <div class="divide-y divide-border/60">
                        <article
                            v-for="workspace in workspaces"
                            :key="workspace.id"
                            class="flex items-center justify-between gap-4 px-5 py-3.5 transition-colors duration-100 hover:bg-elevated/40"
                        >
                            <div class="flex min-w-0 items-center gap-3">
                                <span class="grid h-8 w-8 shrink-0 place-items-center rounded-md bg-gradient-to-br from-accent to-accent/60 text-xs font-semibold text-white">
                                    {{ initial(workspace.name) }}
                                </span>
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-medium text-foreground">{{ workspace.name }}</p>
                                    <p class="truncate text-xs text-faint">{{ workspace.slug }}</p>
                                </div>
                            </div>
                            <div class="flex shrink-0 items-center gap-2">
                                <Badge v-if="workspace.id === currentWorkspace.id" variant="accent">
                                    <Check class="h-3 w-3" /> Current
                                </Badge>
                                <Link
                                    v-else
                                    :href="route('workspaces.switch', workspace.id)"
                                    method="post"
                                    as="button"
                                    class="inline-flex h-8 items-center rounded-md border bg-surface px-2.5 text-[13px] font-medium text-foreground transition-colors duration-150 hover:border-border-strong hover:bg-elevated"
                                >
                                    Switch
                                </Link>
                            </div>
                        </article>
                    </div>
                </SectionCard>

                <SectionCard title="Create workspace" description="Use this when links, members, domains, or folders should be isolated.">
                    <template #icon><Boxes class="h-4 w-4 text-faint" /></template>

                    <form class="grid gap-4 p-5" @submit.prevent="submitWorkspace">
                        <Field
                            label="Workspace name"
                            hint="Displayed in the workspace switcher and used to generate the internal slug."
                            :error="workspaceForm.errors.name"
                        >
                            <input v-model="workspaceForm.name" class="h-9" placeholder="Acme Events" />
                        </Field>
                        <Button :loading="workspaceForm.processing">
                            <Plus class="h-4 w-4" /> Create workspace
                        </Button>
                    </form>
                </SectionCard>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
