<script setup lang="ts">
import { useForm, usePage } from '@inertiajs/vue3';
import { Check } from '@lucide/vue';

import Badge from '@/Components/ui/Badge.vue';
import Button from '@/Components/ui/Button.vue';
import UserAvatar from '@/Components/UserAvatar.vue';
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

const user = usePage<PageProps>().props.auth.user;
const form = useForm({
  profile_avatar_social_account_id: props.profileAvatar.source_id as number | null,
});

const providerLabels: Record<string, string> = {
  google: 'Google',
  discord: 'Discord',
};

function select(identity: ConnectedIdentity | null) {
  form.profile_avatar_social_account_id = identity?.id ?? null;
  form.patch(route('profile.avatar.update'), { preserveScroll: true });
}
</script>

<template>
  <section>
    <header>
      <h2 class="text-base font-semibold text-foreground">Profile Avatar</h2>
      <p class="mt-1 text-sm text-muted">Choose the connected identity image shown across Openlink.</p>
    </header>

    <div class="mt-6 flex items-center gap-4">
      <UserAvatar :name="user.name" :src="profileAvatar.url" size="lg" />
      <div class="min-w-0">
        <p class="truncate text-sm font-medium text-foreground">{{ user.name }}</p>
        <p class="truncate text-xs text-faint">{{ user.email }}</p>
      </div>
    </div>

    <div class="mt-5 grid gap-3 sm:grid-cols-2">
      <button
        v-for="identity in identities"
        :key="identity.id"
        type="button"
        class="flex min-w-0 items-center gap-3 rounded-lg border p-3 text-left transition-colors hover:bg-elevated/60 disabled:cursor-not-allowed disabled:opacity-60"
        :class="identity.is_avatar_source ? 'border-accent bg-accent/10' : 'border-border bg-surface'"
        :disabled="!identity.is_valid || !identity.avatar_url || form.processing"
        @click="select(identity)"
      >
        <UserAvatar :name="providerLabels[identity.provider] ?? identity.provider" :src="identity.avatar_url" />
        <span class="min-w-0 flex-1">
          <span class="block truncate text-sm font-medium text-foreground">{{
            providerLabels[identity.provider] ?? identity.provider
          }}</span>
          <span class="block truncate text-xs text-faint">{{ identity.email }}</span>
        </span>
        <Badge v-if="!identity.is_valid" variant="warning">Mismatch</Badge>
        <Check v-else-if="identity.is_avatar_source" class="h-4 w-4 text-accent" />
      </button>
    </div>

    <div class="mt-4">
      <Button
        type="button"
        variant="secondary"
        :disabled="profileAvatar.source_id === null || form.processing"
        @click="select(null)"
      >
        Use initials
      </Button>
    </div>
  </section>
</template>
