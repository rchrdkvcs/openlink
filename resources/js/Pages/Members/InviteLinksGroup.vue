<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Copy, Link2, Trash2 } from '@lucide/vue';

import Badge from '@/Components/ui/Badge.vue';
import Button from '@/Components/ui/Button.vue';
import IconButton from '@/Components/ui/IconButton.vue';
import SettingsGroup from '@/Components/ui/SettingsGroup.vue';
import { confirmAction } from '@/lib/confirm';
import { copyToClipboard, toast } from '@/lib/toast';
import type { InviteLink } from '@/types/payloads';

import { inviteLinkMeta } from './inviteLinks';

defineProps<{ inviteLinks: InviteLink[] }>();
const emit = defineEmits<{ create: [] }>();

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
</script>

<template>
  <SettingsGroup title="Invite links" description="Anyone with an active link joins with the link's role.">
    <div v-for="link in inviteLinks" :key="link.id" class="flex items-center gap-3 px-4 py-3 sm:px-5">
      <span class="grid h-8 w-8 shrink-0 place-items-center rounded-lg border bg-elevated text-faint">
        <Link2 class="h-4 w-4" />
      </span>
      <div class="min-w-0 flex-1">
        <p class="truncate font-mono text-[13px] text-foreground" :title="link.url">{{ link.url }}</p>
        <p class="truncate text-xs" :class="link.is_usable ? 'text-faint' : 'text-danger'">
          {{ link.is_usable ? inviteLinkMeta(link) : 'No longer usable' }}
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
      <Button variant="secondary" size="sm" type="button" @click="emit('create')">Create link</Button>
    </div>
  </SettingsGroup>
</template>
