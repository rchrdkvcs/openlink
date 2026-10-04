<script setup lang="ts">
import { Link } from '@inertiajs/vue3';

import ApplicationLogo from '@/Components/ApplicationLogo.vue';

defineProps<{
  title?: string;
  description?: string;
}>();
</script>

<template>
  <main class="relative flex min-h-screen flex-col overflow-hidden bg-background">
    <div
      class="pointer-events-none absolute inset-x-0 top-0 h-96 bg-[radial-gradient(ellipse_at_top,hsl(var(--accent)/0.08),transparent_65%)]"
    />

    <header class="relative px-5 pt-6 sm:px-10 sm:pt-8">
      <Link
        href="/"
        aria-label="Openlink"
        class="-m-1 inline-flex rounded-lg p-1 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent/25"
      >
        <ApplicationLogo class="h-8 w-auto" />
      </Link>
    </header>

    <div class="relative flex flex-1 items-center px-5 py-12 sm:px-10 sm:pb-24">
      <div class="mx-auto w-full max-w-[380px] animate-slide-up">
        <div v-if="$slots.icon" class="mb-6">
          <slot name="icon" />
        </div>

        <div v-if="title" class="mb-8">
          <h1 class="text-[28px] font-semibold leading-tight tracking-[-0.02em] text-foreground">{{ title }}</h1>
          <p v-if="description || $slots.description" class="mt-2 text-sm leading-relaxed text-muted">
            <slot name="description">{{ description }}</slot>
          </p>
        </div>

        <slot />

        <div v-if="$slots.footer" class="mt-8 border-t pt-6 text-[13px] text-muted">
          <slot name="footer" />
        </div>
      </div>
    </div>
  </main>
</template>
