<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';

import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import type { RoutingSchema, ShortLink } from '@/types/shortLinks';

import RoutingEditor from './routing/RoutingEditor.vue';

defineProps<{ link: ShortLink; routingSchema: RoutingSchema }>();
</script>

<template>
  <Head :title="`Smart routing · ${link.slug}`" />
  <AuthenticatedLayout panels>
    <RoutingEditor
      :link="link"
      :routing-schema="routingSchema"
      class="min-h-[calc(100dvh-3.5rem)] lg:h-full lg:min-h-0 lg:rounded-xl lg:border"
      @close="router.visit(route('links.index', { link: link.id }))"
    />
  </AuthenticatedLayout>
</template>
