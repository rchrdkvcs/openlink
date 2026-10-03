<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { computed } from 'vue';

import Badge from '@/Components/ui/Badge.vue';
import Button from '@/Components/ui/Button.vue';
import SettingsGroup from '@/Components/ui/SettingsGroup.vue';
import SettingsRow from '@/Components/ui/SettingsRow.vue';
import UserAvatar from '@/Components/UserAvatar.vue';
import { confirmAction } from '@/lib/confirm';
import { toast } from '@/lib/toast';

type ConnectedIdentity = {
  id: number;
  provider: string;
  email: string;
  email_verified: boolean;
  avatar_url: string | null;
  is_valid: boolean;
  is_avatar_source: boolean;
};

const props = defineProps<{
  identities: ConnectedIdentity[];
  providers: Record<string, boolean>;
}>();

const providerLabels: Record<string, string> = {
  google: 'Google',
  discord: 'Discord',
};

function providerLabel(provider: string) {
  return providerLabels[provider] ?? provider;
}

const availableProviders = computed(() =>
  Object.entries(props.providers)
    .filter(([provider]) => !props.identities.some((identity) => identity.provider === provider))
    .map(([provider, enabled]) => ({ provider, enabled })),
);

async function unlink(identity: ConnectedIdentity) {
  const label = providerLabel(identity.provider);
  const confirmed = await confirmAction({
    title: `Disconnect ${label}?`,
    message: `You will no longer be able to sign in with ${identity.email} on ${label}.`,
    confirmLabel: 'Disconnect',
    destructive: true,
  });

  if (!confirmed) return;

  router.delete(route('profile.connected-identities.destroy', identity.id), {
    preserveScroll: true,
    onSuccess: () => toast({ title: `${label} disconnected`, tone: 'success' }),
  });
}

const connectClass =
  ' inline-flex h-8 items-center justify-center gap-1.5 whitespace-nowrap rounded-lg border border-border bg-elevated/60 px-3 text-[13px] font-medium text-foreground transition-[color,background-color,border-color,box-shadow,transform] duration-150 ease-out hover:border-border-strong hover:bg-elevated focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent/25 active:scale-[0.96]';
</script>

<template>
  <SettingsGroup title="Connected accounts">
    <div v-for="identity in identities" :key="identity.id" class="flex items-center gap-3 px-4 py-3 sm:px-5">
      <UserAvatar :name="providerLabel(identity.provider)" :src="identity.avatar_url" />
      <div class="min-w-0 flex-1">
        <div class="flex flex-wrap items-center gap-2">
          <p class="text-sm font-medium text-foreground">{{ providerLabel(identity.provider) }}</p>
          <Badge v-if="!identity.is_valid" variant="warning" dot>Email mismatch</Badge>
          <Badge v-if="identity.is_avatar_source" variant="default">Avatar</Badge>
        </div>
        <p class="truncate text-xs text-faint">{{ identity.email }}</p>
      </div>
      <Button type="button" variant="secondary" size="sm" @click="unlink(identity)">Disconnect</Button>
    </div>
    <p v-if="identities.length === 0" class="px-4 py-6 text-center text-[13px] text-muted sm:px-5">
      No accounts connected. You sign in with your email and password.
    </p>
  </SettingsGroup>

  <SettingsGroup v-if="availableProviders.length" title="Add a sign-in method">
    <SettingsRow
      v-for="item in availableProviders"
      :key="item.provider"
      :label="providerLabel(item.provider)"
      :description="
        item.enabled ? `Sign in with your ${providerLabel(item.provider)} account.` : 'Not configured on this instance.'
      "
    >
      <div class="flex sm:justify-end">
        <a
          v-if="item.enabled"
          :href="route('oauth.redirect', { provider: item.provider, intent: 'link' })"
          :class="connectClass"
        >
          Connect
        </a>
        <Button v-else type="button" variant="secondary" size="sm" disabled>Connect</Button>
      </div>
    </SettingsRow>
  </SettingsGroup>
</template>
