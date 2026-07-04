<script setup lang="ts">
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
</script>

<template>
    <Head title="Members" />

    <AuthenticatedLayout>
        <template #header>
            <div class="hidden min-w-0 items-center justify-between gap-4 lg:flex">
                <div>
                    <p class="text-xs font-medium uppercase text-neutral-500">Members</p>
                    <h1 class="text-base font-semibold text-neutral-950">{{ currentWorkspace.name }}</h1>
                </div>
            </div>
        </template>

        <div class="w-full px-4 py-6 sm:px-6 lg:px-8">
            <div class="mb-6">
                <h2 class="text-2xl font-semibold text-neutral-950">Members</h2>
                <p class="mt-1 text-sm text-neutral-500">Manage workspace access and pending invitations.</p>
            </div>

            <section class="rounded-md border border-neutral-200 bg-white">
                <form v-if="canManageWorkspace" class="grid gap-3 border-b border-neutral-200 p-4 sm:grid-cols-[1fr_160px_auto]" @submit.prevent="submitInvitation">
                    <label class="grid gap-1.5">
                        <span class="text-sm font-medium text-neutral-800">Email address</span>
                        <input v-model="inviteForm.email" class="h-10 rounded-md border-neutral-300 text-sm" placeholder="person@example.com" />
                    </label>
                    <label class="grid gap-1.5">
                        <span class="text-sm font-medium text-neutral-800">Role</span>
                        <select v-model="inviteForm.role" class="h-10 rounded-md border-neutral-300 text-sm">
                            <option value="admin">Admin</option>
                            <option value="editor">Editor</option>
                            <option value="viewer">Viewer</option>
                        </select>
                    </label>
                    <button class="inline-flex h-10 items-center justify-center gap-2 self-end rounded-md bg-neutral-950 px-4 text-sm font-medium text-white hover:bg-neutral-800">
                        <Plus class="h-4 w-4" /> Invite
                    </button>
                </form>

                <div class="hidden grid-cols-[minmax(240px,1fr)_160px_160px] border-b border-neutral-200 px-5 py-2 text-xs font-medium uppercase text-neutral-400 lg:grid">
                    <span>User</span>
                    <span>Role</span>
                    <span>Status</span>
                </div>

                <div class="divide-y divide-neutral-100">
                    <article v-for="member in members" :key="member.id" class="grid gap-2 px-5 py-4 lg:grid-cols-[minmax(240px,1fr)_160px_160px] lg:items-center">
                        <div>
                            <p class="text-sm font-medium text-neutral-950">{{ member.user.name }}</p>
                            <p class="truncate text-xs text-neutral-500">{{ member.user.email }}</p>
                        </div>
                        <span class="w-fit rounded-md bg-neutral-100 px-2 py-1 text-xs font-medium text-neutral-600">{{ member.role }}</span>
                        <span class="w-fit rounded-md bg-neutral-950 px-2 py-1 text-xs font-medium text-white">Active</span>
                    </article>

                    <article v-for="invitation in invitations" :key="invitation.id" class="grid gap-2 px-5 py-4 lg:grid-cols-[minmax(240px,1fr)_160px_160px] lg:items-center">
                        <div>
                            <p class="text-sm font-medium text-neutral-950">{{ invitation.email }}</p>
                            <p class="truncate text-xs text-neutral-500">Invitation pending</p>
                        </div>
                        <span class="w-fit rounded-md bg-neutral-100 px-2 py-1 text-xs font-medium text-neutral-600">{{ invitation.role }}</span>
                        <span class="w-fit rounded-md bg-amber-50 px-2 py-1 text-xs font-medium text-amber-700 ring-1 ring-amber-200">Pending</span>
                    </article>
                </div>

                <div v-if="members.length === 0 && invitations.length === 0" class="px-5 py-16 text-center">
                    <Users class="mx-auto h-8 w-8 text-neutral-300" />
                    <p class="mt-3 text-sm font-medium text-neutral-950">No members yet</p>
                    <p class="mt-1 text-sm text-neutral-500">Invite teammates to collaborate on this workspace.</p>
                </div>
            </section>

            <section class="mt-5 rounded-md border border-neutral-200 bg-[#fafafa] p-5">
                <h3 class="flex items-center gap-2 text-sm font-semibold text-neutral-950"><Mail class="h-4 w-4" /> Access model</h3>
                <p class="mt-2 text-sm text-neutral-500">Workspace roles define global capabilities. Folder grants refine access for project-specific link sets.</p>
            </section>
        </div>
    </AuthenticatedLayout>
</template>
