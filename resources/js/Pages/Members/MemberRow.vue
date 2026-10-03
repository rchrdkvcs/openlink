<script setup lang="ts">
import { Crown, LogOut, MoreHorizontal, Trash2 } from '@lucide/vue';
import { computed } from 'vue';

import Badge from '@/Components/ui/Badge.vue';
import Button from '@/Components/ui/Button.vue';
import Menu from '@/Components/ui/Menu.vue';
import MenuItem from '@/Components/ui/MenuItem.vue';
import MenuSeparator from '@/Components/ui/MenuSeparator.vue';
import Select from '@/Components/ui/Select.vue';
import UserAvatar from '@/Components/UserAvatar.vue';
import { canEditMember, canLeaveAs, isOwnerRole, roleDescription, roleOptions } from '@/lib/permissions';
import { useShell } from '@/lib/shell';
import type { Member } from '@/types/payloads';

import { formatDate } from './inviteLinks';
import { useMemberActions } from './useMemberActions';

const props = defineProps<{ member: Member }>();

const { user, isOwner, workspace, canManage } = useShell();
const { changeRole, removeMember, transferOwnership, leaveWorkspace } = useMemberActions(
  () => workspace.value?.name ?? '',
);

const viewer = computed(() => ({
  userId: user.value.id,
  canManageMembers: canManage.value,
}));
const isSelf = computed(() => props.member.user.id === user.value.id);
const editable = computed(() => canEditMember(props.member, viewer.value));
const leavable = computed(() => canLeaveAs(props.member, viewer.value));
const memberIsOwner = computed(() => isOwnerRole(props.member.role));
</script>

<template>
  <div class="flex items-center gap-3 px-4 py-3 sm:px-5">
    <UserAvatar :name="member.user.name" :src="member.user.profile_avatar_url" />
    <div class="min-w-0 flex-1">
      <p class="truncate text-sm font-medium text-foreground">
        {{ member.user.name }}
        <span v-if="isSelf" class="font-normal text-faint">(you)</span>
      </p>
      <p class="truncate text-[13px] text-muted">
        {{ member.user.email }}
        <span class="hidden text-faint sm:inline"> · Joined {{ formatDate(member.created_at) }}</span>
      </p>
    </div>

    <template v-if="editable">
      <Select
        :model-value="member.role"
        :options="roleOptions"
        size="sm"
        class="w-28 shrink-0"
        :aria-label="`Role for ${member.user.name}`"
        :title="roleDescription(member.role)"
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
      <Badge :variant="memberIsOwner ? 'accent' : 'outline'" class="shrink-0 capitalize">
        <Crown v-if="memberIsOwner" class="h-3 w-3" />
        {{ member.role }}
      </Badge>
      <Button v-if="leavable" variant="ghost" size="sm" type="button" class="shrink-0" @click="leaveWorkspace">
        <LogOut class="h-3.5 w-3.5" /> Leave
      </Button>
      <span v-else-if="canManage" class="w-8 shrink-0" />
    </template>
  </div>
</template>
