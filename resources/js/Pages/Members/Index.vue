<script setup lang="ts">
import Dropdown from '@/Components/Dropdown.vue';
import Modal from '@/Components/Modal.vue';
import Badge from '@/Components/ui/Badge.vue';
import Button from '@/Components/ui/Button.vue';
import EmptyState from '@/Components/ui/EmptyState.vue';
import Field from '@/Components/ui/Field.vue';
import IconButton from '@/Components/ui/IconButton.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { Check, ChevronDown, Copy, Crown, Link2, LogOut, Trash2, UserPlus, Users } from '@lucide/vue';
import { computed, ref } from 'vue';

type Workspace = { id: number; name: string; slug: string };
type Member = { id: number; role: string; created_at: string; user: { id: number; name: string; email: string } };
type InviteLink = {
    id: number;
    role: string;
    token: string;
    url: string;
    expires_at: string | null;
    max_uses: number | null;
    uses: number;
    is_usable: boolean;
};

const props = defineProps<{
    currentWorkspace: Workspace;
    canManageWorkspace: boolean;
    role: string;
    members: Member[];
    inviteLinks: InviteLink[];
}>();

const page = usePage();
const me = computed(() => (page.props.auth as { user: { id: number } }).user);
const isOwner = computed(() => props.role === 'owner');
const myMembership = computed(() => props.members.find((member) => member.user.id === me.value.id));

const roleOptions = [
    { value: 'admin', label: 'Admin', description: 'Manages members, domains, folders, and settings' },
    { value: 'editor', label: 'Editor', description: 'Creates and edits links and QR codes' },
    { value: 'viewer', label: 'Viewer', description: 'Read-only access to links and analytics' },
];

// --- Invite modal ---

const showInviteModal = ref(false);

const linkForm = useForm({ role: 'editor', expires_in_days: '' as string, max_uses: '' as string });

function createInviteLink() {
    linkForm
        .transform((data) => ({
            role: data.role,
            expires_in_days: data.expires_in_days === '' ? null : Number(data.expires_in_days),
            max_uses: data.max_uses === '' ? null : Number(data.max_uses),
        }))
        .post(route('invite-links.store'), { preserveScroll: true, onSuccess: () => linkForm.reset() });
}

const copiedLinkId = ref<number | null>(null);

async function copyLink(link: InviteLink) {
    await navigator.clipboard.writeText(link.url);
    copiedLinkId.value = link.id;
    setTimeout(() => {
        if (copiedLinkId.value === link.id) copiedLinkId.value = null;
    }, 2000);
}

function revokeLink(link: InviteLink) {
    router.delete(route('invite-links.destroy', link.token), { preserveScroll: true });
}

function linkMeta(link: InviteLink) {
    const parts: string[] = [];
    parts.push(link.expires_at ? `expires ${formatDate(link.expires_at)}` : 'never expires');
    if (link.max_uses !== null) {
        parts.push(`${link.uses}/${link.max_uses} uses`);
    } else if (link.uses > 0) {
        parts.push(`${link.uses} ${link.uses === 1 ? 'use' : 'uses'}`);
    }
    return parts.join(' · ');
}

// --- Member management ---

function canEditMember(member: Member) {
    return props.canManageWorkspace && member.role !== 'owner' && member.user.id !== me.value.id;
}

function changeRole(member: Member, role: string) {
    if (member.role === role) return;
    router.patch(route('members.update', member.id), { role }, { preserveScroll: true });
}

type Confirmation =
    | { type: 'remove'; member: Member }
    | { type: 'transfer'; member: Member }
    | { type: 'leave' };

const confirmation = ref<Confirmation | null>(null);
const confirming = ref(false);

function confirmAction() {
    if (!confirmation.value) return;
    const options = {
        preserveScroll: true,
        onStart: () => (confirming.value = true),
        onFinish: () => {
            confirming.value = false;
            confirmation.value = null;
        },
    };

    if (confirmation.value.type === 'remove') {
        router.delete(route('members.destroy', confirmation.value.member.id), options);
    } else if (confirmation.value.type === 'transfer') {
        router.post(route('members.transfer-ownership', confirmation.value.member.id), {}, options);
    } else {
        router.post(route('members.leave'), {}, options);
    }
}

const confirmText = computed(() => {
    if (!confirmation.value) return { title: '', body: '', action: '' };
    if (confirmation.value.type === 'remove') {
        return {
            title: `Remove ${confirmation.value.member.user.name}?`,
            body: `They will immediately lose access to ${props.currentWorkspace.name}. Links they created stay in the workspace.`,
            action: 'Remove member',
        };
    }
    if (confirmation.value.type === 'transfer') {
        return {
            title: `Transfer ownership to ${confirmation.value.member.user.name}?`,
            body: `They will become the owner of ${props.currentWorkspace.name} and you will become an admin. This cannot be undone by you.`,
            action: 'Transfer ownership',
        };
    }
    return {
        title: `Leave ${props.currentWorkspace.name}?`,
        body: 'You will immediately lose access to this workspace. An owner or admin will have to invite you again.',
        action: 'Leave workspace',
    };
});

function initial(name: string) {
    return name.slice(0, 1).toUpperCase();
}

function formatDate(value: string) {
    return new Date(value).toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' });
}

const roleRank: Record<string, number> = { owner: 0, admin: 1, editor: 2, viewer: 3 };
const sortedMembers = computed(() =>
    [...props.members].sort((a, b) => (roleRank[a.role] ?? 9) - (roleRank[b.role] ?? 9) || a.user.name.localeCompare(b.user.name)),
);
</script>

<template>
    <Head title="Members" />

    <AuthenticatedLayout>
        <div class="w-full px-4 py-8 sm:px-6 lg:px-8">
            <div class="mb-6 flex flex-wrap items-end justify-between gap-4">
                <div>
                    <h1 class="text-xl font-semibold tracking-tight">Members</h1>
                    <p class="mt-1 text-sm text-muted">People with access to {{ currentWorkspace.name }}.</p>
                </div>
                <Button v-if="canManageWorkspace" type="button" @click="showInviteModal = true">
                    <UserPlus class="h-4 w-4" /> Invite members
                </Button>
            </div>

            <section class="card-sheen rounded-lg border bg-surface">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b text-left text-[11px] font-medium uppercase tracking-wide text-faint">
                            <th class="px-4 py-2.5 font-medium">User</th>
                            <th class="hidden px-4 py-2.5 font-medium sm:table-cell">Member since</th>
                            <th class="w-44 px-4 py-2.5 font-medium">Role</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border/60">
                        <tr v-for="member in sortedMembers" :key="member.id" class="transition-colors duration-100 hover:bg-elevated/40">
                            <td class="px-4 py-3">
                                <div class="flex min-w-0 items-center gap-3">
                                    <span class="grid h-8 w-8 shrink-0 place-items-center rounded-full border bg-elevated text-xs font-semibold text-foreground">
                                        {{ initial(member.user.name) }}
                                    </span>
                                    <div class="min-w-0">
                                        <p class="truncate font-medium text-foreground">
                                            {{ member.user.name }}
                                            <span v-if="member.user.id === me.id" class="text-xs font-normal text-faint">(you)</span>
                                        </p>
                                        <p class="truncate text-xs text-faint">{{ member.user.email }}</p>
                                    </div>
                                </div>
                            </td>
                            <td class="hidden px-4 py-3 text-muted sm:table-cell">{{ formatDate(member.created_at) }}</td>
                            <td class="px-4 py-3">
                                <Dropdown v-if="canEditMember(member)" align="right" width="72">
                                    <template #trigger>
                                        <button
                                            type="button"
                                            class="inline-flex h-8 items-center gap-1.5 rounded-md border border-transparent px-2.5 text-sm capitalize text-foreground transition-colors duration-100 hover:border-border hover:bg-elevated"
                                        >
                                            {{ member.role }}
                                            <ChevronDown class="h-3.5 w-3.5 text-faint" />
                                        </button>
                                    </template>
                                    <template #content>
                                        <button
                                            v-for="option in roleOptions"
                                            :key="option.value"
                                            type="button"
                                            class="flex w-full items-start gap-2.5 rounded-md px-2.5 py-2 text-left transition-colors duration-100 hover:bg-elevated"
                                            @click="changeRole(member, option.value)"
                                        >
                                            <Check class="mt-0.5 h-3.5 w-3.5 shrink-0" :class="member.role === option.value ? 'text-foreground' : 'text-transparent'" />
                                            <span class="min-w-0">
                                                <span class="block text-sm font-medium text-foreground">{{ option.label }}</span>
                                                <span class="block text-xs text-faint">{{ option.description }}</span>
                                            </span>
                                        </button>
                                        <div class="mx-2 my-1 border-t border-border/60" />
                                        <button
                                            v-if="isOwner"
                                            type="button"
                                            class="flex w-full items-center gap-2.5 rounded-md px-2.5 py-2 text-left text-sm text-foreground transition-colors duration-100 hover:bg-elevated"
                                            @click="confirmation = { type: 'transfer', member }"
                                        >
                                            <Crown class="h-3.5 w-3.5 text-faint" /> Transfer ownership…
                                        </button>
                                        <button
                                            type="button"
                                            class="flex w-full items-center gap-2.5 rounded-md px-2.5 py-2 text-left text-sm text-danger transition-colors duration-100 hover:bg-danger/10"
                                            @click="confirmation = { type: 'remove', member }"
                                        >
                                            <Trash2 class="h-3.5 w-3.5" /> Remove from workspace…
                                        </button>
                                    </template>
                                </Dropdown>

                                <div v-else class="flex items-center gap-2">
                                    <Badge :variant="member.role === 'owner' ? 'accent' : 'outline'">
                                        <Crown v-if="member.role === 'owner'" class="h-3 w-3" />
                                        {{ member.role }}
                                    </Badge>
                                    <IconButton
                                        v-if="member.user.id === me.id && myMembership && myMembership.role !== 'owner'"
                                        variant="danger"
                                        title="Leave workspace"
                                        @click="confirmation = { type: 'leave' }"
                                    >
                                        <LogOut class="h-3.5 w-3.5" />
                                    </IconButton>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <EmptyState
                    v-if="members.length === 0"
                    title="No members yet"
                    description="Invite teammates to collaborate on this workspace."
                >
                    <template #icon><Users class="h-5 w-5" /></template>
                </EmptyState>
            </section>

            <!-- Invite members modal -->
            <Modal :show="showInviteModal" max-width="lg" @close="showInviteModal = false">
                <div class="p-6">
                    <div class="flex items-start gap-3">
                        <span class="grid h-9 w-9 shrink-0 place-items-center rounded-lg bg-accent/15 text-accent">
                            <UserPlus class="h-4.5 w-4.5" />
                        </span>
                        <div>
                            <h2 class="text-base font-semibold text-foreground">Invite to {{ currentWorkspace.name }}</h2>
                            <p class="mt-0.5 text-sm text-muted">Anyone with a link joins this workspace with the link's role.</p>
                        </div>
                    </div>

                    <form class="mt-5 rounded-lg border bg-elevated/30 p-4" @submit.prevent="createInviteLink">
                        <div class="grid gap-3 sm:grid-cols-3">
                            <Field label="Role" :error="linkForm.errors.role">
                                <select v-model="linkForm.role" class="h-9">
                                    <option value="admin">Admin</option>
                                    <option value="editor">Editor</option>
                                    <option value="viewer">Viewer</option>
                                </select>
                            </Field>
                            <Field label="Expires" :error="linkForm.errors.expires_in_days">
                                <select v-model="linkForm.expires_in_days" class="h-9">
                                    <option value="">Never</option>
                                    <option value="1">In 1 day</option>
                                    <option value="7">In 7 days</option>
                                    <option value="30">In 30 days</option>
                                </select>
                            </Field>
                            <Field label="Max uses" :error="linkForm.errors.max_uses">
                                <input v-model="linkForm.max_uses" type="number" min="1" class="h-9" placeholder="Unlimited" />
                            </Field>
                        </div>
                        <Button class="mt-3 w-full" :loading="linkForm.processing">
                            <Link2 class="h-4 w-4" /> Generate invite link
                        </Button>
                    </form>

                    <div v-if="inviteLinks.length" class="mt-5">
                        <p class="text-[11px] font-medium uppercase tracking-wide text-faint">Active links</p>
                        <div class="mt-2 divide-y divide-border/60 rounded-lg border">
                            <div v-for="link in inviteLinks" :key="link.id" class="flex items-center gap-3 px-3 py-2.5">
                                <Badge variant="outline">{{ link.role }}</Badge>
                                <div class="min-w-0 flex-1">
                                    <code class="block truncate text-xs text-muted">{{ link.url }}</code>
                                    <p class="text-[11px]" :class="link.is_usable ? 'text-faint' : 'text-danger'">
                                        {{ link.is_usable ? linkMeta(link) : 'No longer usable' }}
                                    </p>
                                </div>
                                <IconButton :title="copiedLinkId === link.id ? 'Copied' : 'Copy link'" @click="copyLink(link)">
                                    <Check v-if="copiedLinkId === link.id" class="h-3.5 w-3.5 text-success" />
                                    <Copy v-else class="h-3.5 w-3.5" />
                                </IconButton>
                                <IconButton variant="danger" title="Revoke link" @click="revokeLink(link)">
                                    <Trash2 class="h-3.5 w-3.5" />
                                </IconButton>
                            </div>
                        </div>
                    </div>

                    <div class="mt-6 flex justify-end">
                        <Button variant="secondary" type="button" @click="showInviteModal = false">Done</Button>
                    </div>
                </div>
            </Modal>

            <!-- Confirmation modal -->
            <Modal :show="confirmation !== null" max-width="md" @close="confirmation = null">
                <div class="p-6">
                    <h2 class="text-base font-semibold text-foreground">{{ confirmText.title }}</h2>
                    <p class="mt-2 text-sm text-muted">{{ confirmText.body }}</p>
                    <div class="mt-6 flex justify-end gap-3">
                        <Button variant="secondary" type="button" @click="confirmation = null">Cancel</Button>
                        <Button
                            :variant="confirmation?.type === 'transfer' ? 'primary' : 'danger'"
                            type="button"
                            :loading="confirming"
                            @click="confirmAction"
                        >
                            {{ confirmText.action }}
                        </Button>
                    </div>
                </div>
            </Modal>
        </div>
    </AuthenticatedLayout>
</template>
