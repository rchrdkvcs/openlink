<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { UserPlus, Users } from '@lucide/vue';
import { computed, ref } from 'vue';

import Button from '@/Components/ui/Button.vue';
import EmptyState from '@/Components/ui/EmptyState.vue';
import SettingsGroup from '@/Components/ui/SettingsGroup.vue';
import SettingsLayout from '@/Layouts/SettingsLayout.vue';
import { sortMembersByRole } from '@/lib/permissions';
import { useShell } from '@/lib/shell';
import type { InviteLink, Member } from '@/types/payloads';

import InviteDialog from './InviteDialog.vue';
import { memberCountLabel } from './inviteLinks';
import InviteLinksGroup from './InviteLinksGroup.vue';
import MemberRow from './MemberRow.vue';

const props = defineProps<{
  members: Member[];
  inviteLinks: InviteLink[];
}>();

const { workspace, canManage } = useShell();
const workspaceName = computed(() => workspace.value?.name ?? '');

const inviteOpen = ref(false);
const sortedMembers = computed(() => sortMembersByRole(props.members));
const memberCount = computed(() => memberCountLabel(props.members.length));
</script>

<template>
  <Head title="Members" />

  <SettingsLayout title="Members" :description="`People with access to ${workspaceName}.`">
    <template v-if="canManage" #actions>
      <Button type="button" @click="inviteOpen = true"><UserPlus class="h-4 w-4" /> Invite people</Button>
    </template>

    <SettingsGroup :title="memberCount">
      <MemberRow v-for="member in sortedMembers" :key="member.id" :member="member" />

      <EmptyState
        v-if="members.length === 0"
        title="No members yet"
        description="Invite people to work in this workspace."
      >
        <template #icon><Users class="h-5 w-5" /></template>
      </EmptyState>
    </SettingsGroup>

    <InviteLinksGroup v-if="canManage" :invite-links="inviteLinks" @create="inviteOpen = true" />

    <InviteDialog v-model:open="inviteOpen" :workspace-name="workspaceName" :invite-links="inviteLinks" />
  </SettingsLayout>
</template>
