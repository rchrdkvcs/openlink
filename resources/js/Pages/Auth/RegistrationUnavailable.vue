<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowRight } from '@lucide/vue';
import { computed } from 'vue';

import PublicStateLayout from '@/Components/PublicState/PublicStateLayout.vue';
import RegistrationArtwork from '@/Components/PublicState/RegistrationArtwork.vue';
import StateBadge from '@/Components/PublicState/StateBadge.vue';

const props = defineProps<{
  mode: 'closed' | 'invite_only';
}>();

const title = computed(() => (props.mode === 'closed' ? 'This instance is private' : 'You’ll need an invite link'));
const description = computed(() =>
  props.mode === 'closed'
    ? 'New accounts are disabled on this Openlink instance, including through invite links. If you already have an account, you can still sign in.'
    : 'To create an account, open a valid invite link shared by a workspace owner or admin. Already have an account? Sign in to continue.',
);
</script>

<template>
  <Head :title="title" />

  <PublicStateLayout :title="title" :description="description" :tone="mode === 'closed' ? 'neutral' : 'accent'">
    <template #art>
      <RegistrationArtwork :mode="mode" />
    </template>

    <template #badge>
      <StateBadge :tone="mode === 'closed' ? 'neutral' : 'accent'">
        {{ mode === 'closed' ? 'Private instance' : 'Invite link only' }}
      </StateBadge>
    </template>

    <Link
      :href="route('login')"
      class="inline-flex h-10 items-center justify-center gap-1.5 rounded-lg bg-foreground px-4 text-sm font-medium text-background transition-colors hover:bg-foreground/90 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent/40"
    >
      Sign in to Openlink
      <ArrowRight class="h-3.5 w-3.5" />
    </Link>
  </PublicStateLayout>
</template>
