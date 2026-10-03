<script setup lang="ts">
import { useForm, usePage } from '@inertiajs/vue3';
import { Check } from '@lucide/vue';
import { computed } from 'vue';

import Badge from '@/Components/ui/Badge.vue';
import SettingsGroup from '@/Components/ui/SettingsGroup.vue';
import UserAvatar from '@/Components/UserAvatar.vue';
import { toast } from '@/lib/toast';
import type { PageProps } from '@/types';

type ConnectedIdentity = {
  id: number;
  provider: string;
  email: string;
  avatar_url: string | null;
  is_valid: boolean;
  is_avatar_source: boolean;
};

const props = defineProps<{
  identities: ConnectedIdentity[];
  profileAvatar: { url: string | null; source_id: number | null };
}>();

const page = usePage<PageProps>();
const user = computed(() => page.props.auth.user);

const form = useForm({
  profile_avatar_social_account_id: props.profileAvatar.source_id as number | null,
});

const providerLabels: Record<string, string> = {
  google: 'Google',
  discord: 'Discord',
};

function providerLabel(provider: string) {
  return providerLabels[provider] ?? provider;
}

function select(identity: ConnectedIdentity | null) {
  const id = identity?.id ?? null;
  if (id === props.profileAvatar.source_id || form.processing) return;

  form.profile_avatar_social_account_id = id;
  form.patch(route('profile.avatar.update'), {
    preserveScroll: true,
    onSuccess: () => toast({ title: 'Avatar updated', tone: 'success' }),
  });
}

const optionClass =
  'flex w-full items-center gap-3 px-4 py-3 text-left transition-colors duration-100 hover:bg-elevated/50 focus-visible:bg-elevated/50 focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50 disabled:hover:bg-transparent sm:px-5';
</script>

<template>
  <SettingsGroup title="Avatar" description="Use your initials or a photo from a connected account.">
    <div class="flex items-center gap-4 px-4 py-4 sm:px-5">
      <UserAvatar :name="user.name" :src="profileAvatar.url" size="lg" />
      <div class="min-w-0">
        <p class="truncate text-sm font-medium text-foreground">{{ user.name }}</p>
        <p class="truncate text-[13px] text-muted">{{ user.email }}</p>
      </div>
    </div>

    <div role="radiogroup" aria-label="Avatar source" class="divide-y divide-border">
      <button
        type="button"
        role="radio"
        :aria-checked="profileAvatar.source_id === null"
        :class="optionClass"
        :disabled="form.processing"
        @click="select(null)"
      >
        <UserAvatar :name="user.name" />
        <span class="min-w-0 flex-1">
          <span class="block text-sm font-medium text-foreground">Initials</span>
          <span class="block text-[13px] text-muted">Generated from your name</span>
        </span>
        <Check v-if="profileAvatar.source_id === null" class="h-4 w-4 text-accent" />
      </button>

      <button
        v-for="identity in identities"
        :key="identity.id"
        type="button"
        role="radio"
        :aria-checked="profileAvatar.source_id === identity.id"
        :class="optionClass"
        :disabled="!identity.is_valid || !identity.avatar_url || form.processing"
        @click="select(identity)"
      >
        <UserAvatar :name="providerLabel(identity.provider)" :src="identity.avatar_url" />
        <span class="min-w-0 flex-1">
          <span class="block truncate text-sm font-medium text-foreground">{{ providerLabel(identity.provider) }}</span>
          <span class="block truncate text-[13px] text-muted">{{ identity.email }}</span>
        </span>
        <Badge v-if="!identity.is_valid" variant="warning">Email mismatch</Badge>
        <span v-else-if="!identity.avatar_url" class="text-xs text-faint">No photo</span>
        <Check v-else-if="profileAvatar.source_id === identity.id" class="h-4 w-4 text-accent" />
      </button>
    </div>
  </SettingsGroup>
</template>
