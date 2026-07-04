<script setup lang="ts">
import Badge from '@/Components/ui/Badge.vue';
import Button from '@/Components/ui/Button.vue';
import EmptyState from '@/Components/ui/EmptyState.vue';
import Field from '@/Components/ui/Field.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import SectionCard from '@/Components/ui/SectionCard.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { Mail, Plus, Users } from '@lucide/vue';

type Workspace = { id: number; name: string; slug: string };
type Member = { id: number; role: string; user: { id: number; name: string; email: string } };
type Invitation = { id: number; email: string; role: string; accepted_at?: string | null; expires_at?: string | null };

defineProps<{
    currentWorkspace: Workspace;
    canManageWorkspace: boolean;
    members: Member[];
    invitations: Invitation[];
}>();

const inviteForm = useForm({ email: '', role: 'viewer' });

function submitInvitation() {
    inviteForm.post(route('invitations.store'), { preserveScroll: true, onSuccess: () => inviteForm.reset('email') });
}

function initial(name: string) {
    return name.slice(0, 1).toUpperCase();
}
</script>

<template>
    <Head title="Members" />

    <AuthenticatedLayout>
        <template #header>
            <PageHeader section="Members" />
        </template>

        <div class="w-full px-4 py-8 sm:px-6 lg:px-8">
            <div class="mb-6">
                <h1 class="text-xl font-semibold tracking-tight">Members</h1>
                <p class="mt-1 text-sm text-muted">Manage workspace access and pending invitations.</p>
            </div>

            <section class="card-sheen overflow-hidden rounded-lg border bg-surface">
                <form v-if="canManageWorkspace" class="flex flex-col gap-3 border-b p-4 sm:flex-row sm:items-end" @submit.prevent="submitInvitation">
                    <Field label="Email address" :error="inviteForm.errors.email" class="min-w-0 flex-1">
                        <input v-model="inviteForm.email" class="h-9" placeholder="person@example.com" />
                    </Field>
                    <Field label="Role" :error="inviteForm.errors.role" class="sm:w-40">
                        <select v-model="inviteForm.role" class="h-9">
                            <option value="admin">Admin</option>
                            <option value="editor">Editor</option>
                            <option value="viewer">Viewer</option>
                        </select>
                    </Field>
                    <Button class="shrink-0" :loading="inviteForm.processing">
                        <Plus class="h-4 w-4" /> Invite
                    </Button>
                </form>

                <div class="hidden grid-cols-[minmax(240px,1fr)_160px_160px] border-b px-4 py-2 text-[11px] font-medium uppercase tracking-wide text-faint lg:grid">
                    <span>User</span>
                    <span>Role</span>
                    <span>Status</span>
                </div>

                <div class="divide-y divide-border/60">
                    <article
                        v-for="member in members"
                        :key="member.id"
                        class="grid gap-2 px-4 py-3.5 transition-colors duration-100 hover:bg-elevated/40 lg:grid-cols-[minmax(240px,1fr)_160px_160px] lg:items-center"
                    >
                        <div class="flex min-w-0 items-center gap-3">
                            <span class="grid h-8 w-8 shrink-0 place-items-center rounded-full border bg-elevated text-xs font-semibold text-foreground">
                                {{ initial(member.user.name) }}
                            </span>
                            <div class="min-w-0">
                                <p class="truncate text-sm font-medium text-foreground">{{ member.user.name }}</p>
                                <p class="truncate text-xs text-faint">{{ member.user.email }}</p>
                            </div>
                        </div>
                        <Badge variant="outline">{{ member.role }}</Badge>
                        <Badge variant="success" dot>Active</Badge>
                    </article>

                    <article
                        v-for="invitation in invitations"
                        :key="invitation.id"
                        class="grid gap-2 px-4 py-3.5 transition-colors duration-100 hover:bg-elevated/40 lg:grid-cols-[minmax(240px,1fr)_160px_160px] lg:items-center"
                    >
                        <div class="flex min-w-0 items-center gap-3">
                            <span class="grid h-8 w-8 shrink-0 place-items-center rounded-full border border-dashed text-faint">
                                <Mail class="h-3.5 w-3.5" />
                            </span>
                            <div class="min-w-0">
                                <p class="truncate text-sm font-medium text-foreground">{{ invitation.email }}</p>
                                <p class="truncate text-xs text-faint">Invitation pending</p>
                            </div>
                        </div>
                        <Badge variant="outline">{{ invitation.role }}</Badge>
                        <Badge variant="warning" dot>Pending</Badge>
                    </article>
                </div>

                <EmptyState
                    v-if="members.length === 0 && invitations.length === 0"
                    title="No members yet"
                    description="Invite teammates to collaborate on this workspace."
                >
                    <template #icon><Users class="h-5 w-5" /></template>
                </EmptyState>
            </section>

            <SectionCard class="mt-6" title="Access model">
                <template #icon><Mail class="h-4 w-4 text-faint" /></template>
                <p class="px-5 py-4 text-sm text-muted">
                    Workspace roles define global capabilities. Folder grants refine access for project-specific link sets.
                </p>
            </SectionCard>
        </div>
    </AuthenticatedLayout>
</template>
