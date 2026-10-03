<script setup lang="ts">
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import {
  Copy,
  Crown,
  Eye,
  Link2,
  LogOut,
  MoreHorizontal,
  PencilLine,
  ShieldCheck,
  Trash2,
  UserPlus,
  Users,
} from '@lucide/vue';
import { computed, ref } from 'vue';

import Badge from '@/Components/ui/Badge.vue';
import Button from '@/Components/ui/Button.vue';
import Dialog from '@/Components/ui/Dialog.vue';
import EmptyState from '@/Components/ui/EmptyState.vue';
import IconButton from '@/Components/ui/IconButton.vue';
import Menu from '@/Components/ui/Menu.vue';
import MenuItem from '@/Components/ui/MenuItem.vue';
import MenuSeparator from '@/Components/ui/MenuSeparator.vue';
import SegmentedControl from '@/Components/ui/SegmentedControl.vue';
import Select from '@/Components/ui/Select.vue';
import SettingsGroup from '@/Components/ui/SettingsGroup.vue';
import UserAvatar from '@/Components/UserAvatar.vue';
import SettingsLayout from '@/Layouts/SettingsLayout.vue';
import { confirmAction } from '@/lib/confirm';
import { copyToClipboard, toast } from '@/lib/toast';

type Workspace = { id: number; name: string; slug: string };
type Member = {
  id: number;
  role: string;
  created_at: string;
  user: { id: number; name: string; email: string; profile_avatar_url?: string | null };
};
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
  canManageMembers: boolean;
  role: string;
  members: Member[];
  inviteLinks: InviteLink[];
}>();

const page = usePage();
const me = computed(() => (page.props.auth as { user: { id: number } }).user);
const isOwner = computed(() => props.role === 'owner');

const roleOptions = [
  { value: 'admin', label: 'Admin' },
  { value: 'editor', label: 'Editor' },
  { value: 'viewer', label: 'Viewer' },
];

const roleDescriptions: Record<string, string> = {
  admin: 'Manages members, domains, folders and settings',
  editor: 'Creates and edits links and QR codes',
  viewer: 'Read-only access to links and analytics',
};

const inviteRoles = [
  { value: 'editor', label: 'Editor', icon: PencilLine },
  { value: 'viewer', label: 'Viewer', icon: Eye },
  { value: 'admin', label: 'Admin', icon: ShieldCheck },
];

const expiryOptions = [
  { value: '', label: 'Never' },
  { value: '1', label: '1 day' },
  { value: '7', label: '7 days' },
  { value: '30', label: '30 days' },
];

const usesOptions = [
  { value: '', label: 'Unlimited' },
  { value: '1', label: '1' },
  { value: '10', label: '10' },
  { value: '100', label: '100' },
];

const inviteOpen = ref(false);
const linkForm = useForm({ role: 'editor', expires_in_days: '' as string, max_uses: '' as string });

function openInvite() {
  linkForm.reset();
  linkForm.clearErrors();
  inviteOpen.value = true;
}

function createInviteLink() {
  const existing = new Set(props.inviteLinks.map((link) => link.id));

  linkForm
    .transform((data) => ({
      role: data.role,
      expires_in_days: data.expires_in_days === '' ? null : Number(data.expires_in_days),
      max_uses: data.max_uses === '' ? null : Number(data.max_uses),
    }))
    .post(route('invite-links.store'), {
      preserveScroll: true,
      onSuccess: () => {
        linkForm.reset();
        inviteOpen.value = false;
        const created = props.inviteLinks.find((link) => !existing.has(link.id));
        toast({
          title: 'Invite link created',
          description: created?.url,
          tone: 'success',
          duration: 8000,
          action: created
            ? { label: 'Copy', run: () => void copyToClipboard(created.url, 'Invite link copied') }
            : undefined,
        });
      },
    });
}

async function revokeLink(link: InviteLink) {
  const confirmed = await confirmAction({
    title: 'Revoke this invite link?',
    message: 'Anyone who has not used it yet will no longer be able to join.',
    confirmLabel: 'Revoke link',
    destructive: true,
  });

  if (!confirmed) return;

  router.delete(route('invite-links.destroy', link.token), {
    preserveScroll: true,
    onSuccess: () => toast({ title: 'Invite link revoked' }),
  });
}

function linkMeta(link: InviteLink) {
  const parts: string[] = [];
  parts.push(link.expires_at ? `Expires ${formatDate(link.expires_at)}` : 'Never expires');
  if (link.max_uses !== null) {
    parts.push(`${link.uses}/${link.max_uses} uses`);
  } else if (link.uses > 0) {
    parts.push(`${link.uses} ${link.uses === 1 ? 'use' : 'uses'}`);
  }
  return parts.join(' · ');
}

function canEditMember(member: Member) {
  return props.canManageMembers && member.role !== 'owner' && member.user.id !== me.value.id;
}

function canLeave(member: Member) {
  return member.user.id === me.value.id && member.role !== 'owner';
}

function changeRole(member: Member, role: string) {
  if (member.role === role) return;
  const label = roleOptions.find((option) => option.value === role)?.label ?? role;

  router.patch(
    route('members.update', member.id),
    { role },
    {
      preserveScroll: true,
      onSuccess: () => toast({ title: `${member.user.name} is now ${label.toLowerCase()}`, tone: 'success' }),
    },
  );
}

async function removeMember(member: Member) {
  const confirmed = await confirmAction({
    title: `Remove ${member.user.name}?`,
    message: `They lose access to ${props.currentWorkspace.name} immediately. Links they created stay in the workspace.`,
    confirmLabel: 'Remove member',
    destructive: true,
  });

  if (!confirmed) return;

  router.delete(route('members.destroy', member.id), {
    preserveScroll: true,
    onSuccess: () => toast({ title: `${member.user.name} removed` }),
  });
}

async function transferOwnership(member: Member) {
  const confirmed = await confirmAction({
    title: `Transfer ownership to ${member.user.name}?`,
    message: `They become the owner of ${props.currentWorkspace.name} and you become an admin. Only the new owner can undo this.`,
    confirmLabel: 'Transfer ownership',
  });

  if (!confirmed) return;

  router.post(
    route('members.transfer-ownership', member.id),
    {},
    {
      preserveScroll: true,
      onSuccess: () => toast({ title: `${member.user.name} is now the owner`, tone: 'success' }),
    },
  );
}

async function leaveWorkspace() {
  const confirmed = await confirmAction({
    title: `Leave ${props.currentWorkspace.name}?`,
    message: 'You lose access immediately. An owner or admin will have to invite you again.',
    confirmLabel: 'Leave workspace',
    destructive: true,
  });

  if (confirmed) router.post(route('members.leave'));
}

function formatDate(value: string) {
  return new Date(value).toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' });
}

const roleRank: Record<string, number> = { owner: 0, admin: 1, editor: 2, viewer: 3 };
const sortedMembers = computed(() =>
  props.members.toSorted(
    (a, b) => (roleRank[a.role] ?? 9) - (roleRank[b.role] ?? 9) || a.user.name.localeCompare(b.user.name),
  ),
);

const memberCount = computed(() => `${props.members.length} ${props.members.length === 1 ? 'person' : 'people'}`);
</script>

<template>
  <Head title="Members" />

  <SettingsLayout title="Members" :description="`People with access to ${currentWorkspace.name}.`">
    <template v-if="canManageMembers" #actions>
      <Button type="button" @click="openInvite"><UserPlus class="h-4 w-4" /> Invite people</Button>
    </template>

    <SettingsGroup :title="memberCount">
      <div v-for="member in sortedMembers" :key="member.id" class="flex items-center gap-3 px-4 py-3 sm:px-5">
        <UserAvatar :name="member.user.name" :src="member.user.profile_avatar_url" />
        <div class="min-w-0 flex-1">
          <p class="truncate text-sm font-medium text-foreground">
            {{ member.user.name }}
            <span v-if="member.user.id === me.id" class="font-normal text-faint">(you)</span>
          </p>
          <p class="truncate text-[13px] text-muted">
            {{ member.user.email }}
            <span class="hidden text-faint sm:inline"> · Joined {{ formatDate(member.created_at) }}</span>
          </p>
        </div>

        <template v-if="canEditMember(member)">
          <Select
            :model-value="member.role"
            :options="roleOptions"
            size="sm"
            class="w-28 shrink-0"
            :aria-label="`Role for ${member.user.name}`"
            :title="roleDescriptions[member.role]"
            @update:model-value="changeRole(member, $event)"
          />
          <Menu width="w-56">
            <template #trigger>
              <button
                type="button"
                class="grid h-8 w-8 shrink-0 place-items-center rounded-lg text-muted transition-colors duration-150 hover:bg-elevated hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent/25 data-[state=open]:bg-elevated data-[state=open]:text-foreground"
                :aria-label="`Actions for ${member.user.name}`"
              >
                <MoreHorizontal class="h-4 w-4" />
              </button>
            </template>
            <MenuItem v-if="isOwner" :icon="Crown" @select="transferOwnership(member)">Transfer ownership…</MenuItem>
            <MenuSeparator v-if="isOwner" />
            <MenuItem :icon="Trash2" destructive @select="removeMember(member)">Remove from workspace…</MenuItem>
          </Menu>
        </template>

        <template v-else>
          <Badge :variant="member.role === 'owner' ? 'accent' : 'outline'" class="shrink-0 capitalize">
            <Crown v-if="member.role === 'owner'" class="h-3 w-3" />
            {{ member.role }}
          </Badge>
          <Button
            v-if="canLeave(member)"
            variant="ghost"
            size="sm"
            type="button"
            class="shrink-0"
            @click="leaveWorkspace"
          >
            <LogOut class="h-3.5 w-3.5" /> Leave
          </Button>
          <span v-else-if="canManageMembers" class="w-8 shrink-0" />
        </template>
      </div>

      <EmptyState
        v-if="members.length === 0"
        title="No members yet"
        description="Invite people to work in this workspace."
      >
        <template #icon><Users class="h-5 w-5" /></template>
      </EmptyState>
    </SettingsGroup>

    <SettingsGroup
      v-if="canManageMembers"
      title="Invite links"
      description="Anyone with an active link joins with the link's role."
    >
      <div v-for="link in inviteLinks" :key="link.id" class="flex items-center gap-3 px-4 py-3 sm:px-5">
        <span class="grid h-8 w-8 shrink-0 place-items-center rounded-lg border bg-elevated text-faint">
          <Link2 class="h-4 w-4" />
        </span>
        <div class="min-w-0 flex-1">
          <p class="truncate font-mono text-[13px] text-foreground" :title="link.url">{{ link.url }}</p>
          <p class="truncate text-xs" :class="link.is_usable ? 'text-faint' : 'text-danger'">
            {{ link.is_usable ? linkMeta(link) : 'No longer usable' }}
          </p>
        </div>
        <Badge variant="outline" class="shrink-0 capitalize">{{ link.role }}</Badge>
        <IconButton title="Copy invite link" @click="copyToClipboard(link.url, 'Invite link copied')">
          <Copy class="h-3.5 w-3.5" />
        </IconButton>
        <IconButton variant="danger" title="Revoke invite link" @click="revokeLink(link)">
          <Trash2 class="h-3.5 w-3.5" />
        </IconButton>
      </div>

      <div v-if="inviteLinks.length === 0" class="flex items-center justify-between gap-4 px-4 py-3.5 sm:px-5">
        <p class="text-[13px] text-muted">No active invite links.</p>
        <Button variant="secondary" size="sm" type="button" @click="openInvite">Create link</Button>
      </div>
    </SettingsGroup>

    <Dialog
      v-model:open="inviteOpen"
      size="lg"
      :title="`Invite people to ${currentWorkspace.name}`"
      description="Create a link and share it. People who open it join with the role you pick."
    >
      <form class="space-y-5 px-5 pb-5 pt-4" @submit.prevent="createInviteLink">
        <fieldset>
          <legend class="mb-2 text-[13px] font-medium text-foreground">Join as</legend>
          <div class="grid gap-1.5" role="radiogroup">
            <label
              v-for="option in inviteRoles"
              :key="option.value"
              class="flex cursor-pointer items-center gap-3 rounded-lg border px-3 py-2.5 transition-colors has-[:focus-visible]:ring-2 has-[:focus-visible]:ring-accent/40"
              :class="
                linkForm.role === option.value
                  ? 'border-accent/50 bg-accent/[0.06]'
                  : 'border-border hover:border-border-strong hover:bg-elevated/40'
              "
            >
              <input v-model="linkForm.role" type="radio" name="invite-role" :value="option.value" class="sr-only" />
              <component
                :is="option.icon"
                class="h-4 w-4 shrink-0"
                :class="linkForm.role === option.value ? 'text-accent' : 'text-faint'"
              />
              <span class="min-w-0 flex-1">
                <span class="block text-[13px] font-medium text-foreground">{{ option.label }}</span>
                <span class="block text-xs text-muted">{{ roleDescriptions[option.value] }}</span>
              </span>
              <span
                class="grid h-4 w-4 shrink-0 place-items-center rounded-full border"
                :class="linkForm.role === option.value ? 'border-accent bg-accent' : 'border-border-strong'"
              >
                <span v-if="linkForm.role === option.value" class="h-1.5 w-1.5 rounded-full bg-white" />
              </span>
            </label>
          </div>
          <p v-if="linkForm.errors.role" class="mt-1.5 text-xs text-danger">{{ linkForm.errors.role }}</p>
        </fieldset>

        <div class="grid gap-4 sm:grid-cols-2">
          <div>
            <p class="mb-2 text-[13px] font-medium text-foreground">Expires</p>
            <SegmentedControl
              v-model="linkForm.expires_in_days"
              :options="expiryOptions"
              label="Expires"
              class="w-full"
            />
            <p v-if="linkForm.errors.expires_in_days" class="mt-1.5 text-xs text-danger">
              {{ linkForm.errors.expires_in_days }}
            </p>
          </div>
          <div>
            <p class="mb-2 text-[13px] font-medium text-foreground">Number of uses</p>
            <SegmentedControl
              v-model="linkForm.max_uses"
              :options="usesOptions"
              label="Number of uses"
              class="w-full"
            />
            <p v-if="linkForm.errors.max_uses" class="mt-1.5 text-xs text-danger">{{ linkForm.errors.max_uses }}</p>
          </div>
        </div>

        <div class="flex justify-end gap-2 pt-1">
          <Button variant="ghost" type="button" @click="inviteOpen = false">Cancel</Button>
          <Button :loading="linkForm.processing"><Link2 v-if="!linkForm.processing" /> Create invite link</Button>
        </div>
      </form>
    </Dialog>
  </SettingsLayout>
</template>
