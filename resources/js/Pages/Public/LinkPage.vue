<script setup lang="ts">
import { Head } from '@inertiajs/vue3';

import LinkPageContent from '@/Components/LinkPages/LinkPageContent.vue';
import type { PublicLinkPage } from '@/Components/LinkPages/types';

defineProps<{
  page: PublicLinkPage;
}>();
</script>

<template>
  <Head :title="page.title">
    <meta v-if="page.description" name="description" :content="page.description" />
  </Head>

  <main class="min-h-screen bg-background px-4 py-12 sm:py-16">
    <LinkPageContent :page="page" />
  </main>
</template>
