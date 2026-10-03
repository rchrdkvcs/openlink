<script setup lang="ts">
import { Link } from '@inertiajs/vue3';

import ApplicationLogo from '@/Components/ApplicationLogo.vue';

withDefaults(
  defineProps<{
    title?: string;
    description?: string;
    width?: 'sm' | 'md';
  }>(),
  { width: 'sm' },
);
</script>

<template>
  <div class="relative flex min-h-screen flex-col items-center justify-center overflow-hidden bg-background px-4 py-12">
    <div
      class="pointer-events-none absolute inset-x-0 top-0 h-96 bg-[radial-gradient(ellipse_at_top,hsl(var(--accent)/0.1),transparent_65%)]"
    />

    <div class="relative w-full animate-slide-up" :class="width === 'md' ? 'max-w-md' : 'max-w-[380px]'">
      <div class="mb-8 flex justify-center">
        <Link
          href="/"
          aria-label="Openlink"
          class="rounded-lg p-1 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent/25"
        >
          <ApplicationLogo class="h-9 w-auto" />
        </Link>
      </div>

      <slot name="header" />

      <div class="rounded-2xl border bg-surface p-6 sm:p-8">
        <div v-if="title" class="mb-6 text-center">
          <h1 class="text-[22px] font-semibold tracking-[-0.015em] text-foreground">{{ title }}</h1>
          <p v-if="description" class="mt-1.5 text-sm leading-relaxed text-muted">{{ description }}</p>
        </div>
        <slot />
      </div>

      <slot name="footer" />
    </div>
  </div>
</template>
