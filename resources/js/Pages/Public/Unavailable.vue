<script setup lang="ts">
import { Head } from '@inertiajs/vue3';

import PublicStateLayout from '@/Components/PublicState/PublicStateLayout.vue';
import StateArt from '@/Components/PublicState/StateArt.vue';

defineProps<{
  title: string;
  message: string;
}>();
</script>

<template>
  <Head :title="title" />

  <PublicStateLayout :title="title" :description="message" tone="neutral">
    <template #art>
      <StateArt scene="unlinked" tone="neutral" />
    </template>
  </PublicStateLayout>
</template>
