<script setup lang="ts">
import type { StateTone } from '@/lib/publicStates';

import PublicBackdrop from './PublicBackdrop.vue';

withDefaults(
  defineProps<{
    title: string;
    description?: string;
    tone?: StateTone;
    wide?: boolean;
  }>(),
  { tone: 'accent', wide: false },
);
</script>

<template>
  <main class="relative isolate flex min-h-dvh flex-col overflow-hidden bg-background text-foreground">
    <PublicBackdrop :tone="tone" />

    <div class="flex flex-1 items-center justify-center px-5 py-14 sm:py-20">
      <section class="w-full text-center" :class="wide ? 'max-w-2xl' : 'max-w-[380px]'">
        <slot name="art" />

        <div class="reveal mt-5" style="--delay: 80ms">
          <div v-if="$slots.badge" class="mb-3 flex justify-center">
            <slot name="badge" />
          </div>
          <h1 class="text-[28px] font-semibold leading-tight tracking-[-0.02em] text-foreground">{{ title }}</h1>
          <p
            v-if="description || $slots.description"
            class="mx-auto mt-2 max-w-[340px] text-pretty text-sm leading-relaxed text-muted"
          >
            <slot name="description">{{ description }}</slot>
          </p>
        </div>

        <div v-if="$slots.default" class="reveal mt-8" style="--delay: 140ms">
          <slot />
        </div>

        <div v-if="$slots.footer" class="reveal mt-8 text-[13px] text-faint" style="--delay: 200ms">
          <slot name="footer" />
        </div>
      </section>
    </div>
  </main>
</template>

<style scoped>
.reveal {
  animation: reveal 0.35s cubic-bezier(0.16, 1, 0.3, 1) var(--delay, 0ms) both;
}

@keyframes reveal {
  from {
    opacity: 0;
    transform: translateY(6px);
  }
}

@keyframes reveal-fade {
  from {
    opacity: 0;
  }
}

@media (prefers-reduced-motion: reduce) {
  .reveal {
    animation: reveal-fade 0.2s ease var(--delay, 0ms) both !important;
  }
}
</style>
