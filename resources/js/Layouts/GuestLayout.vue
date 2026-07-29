<script setup lang="ts">
import { Link } from '@inertiajs/vue3';

import ApplicationLogo from '@/Components/ApplicationLogo.vue';
</script>

<template>
  <div class="relative flex min-h-screen flex-col items-center justify-center overflow-hidden bg-background px-4 py-12">
    <!-- Ambient glow -->
    <div
      class="pointer-events-none absolute inset-x-0 top-0 h-96 bg-[radial-gradient(ellipse_at_top,hsl(var(--accent)/0.12),transparent_65%)]"
    />

    <div class="relative w-full max-w-sm animate-slide-up">
      <div class="mb-8 flex justify-center">
        <Link
          href="/"
          aria-label="Openlink"
          class="rounded-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent/40"
        >
          <ApplicationLogo class="h-10 w-auto" />
        </Link>
      </div>

      <div class="card-sheen rounded-xl border bg-surface p-6 shadow-2xl shadow-black/30">
        <slot />
      </div>

      <slot name="footer" />
    </div>
  </div>
</template>
