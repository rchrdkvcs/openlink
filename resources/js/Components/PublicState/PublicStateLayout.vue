<script setup lang="ts">
import { ref } from 'vue';

import type { StateTone } from '@/lib/publicStates';

import PoweredBy from './PoweredBy.vue';
import PublicBackdrop from './PublicBackdrop.vue';

withDefaults(
  defineProps<{
    title: string;
    description?: string;
    tone?: StateTone;
    wide?: boolean;
    showBranding?: boolean;
  }>(),
  { tone: 'accent', wide: false, showBranding: false },
);

const art = ref<HTMLElement | null>(null);
</script>

<template>
  <main class="relative isolate flex min-h-dvh flex-col overflow-hidden bg-background text-foreground">
    <PublicBackdrop :tone="tone" :anchor="art" />

    <div class="flex flex-1 items-center justify-center px-6 py-16 sm:py-24">
      <section class="w-full text-center" :class="wide ? 'max-w-2xl' : 'max-w-[400px]'">
        <div ref="art">
          <slot name="art" />
        </div>

        <div class="reveal mt-10" style="--delay: 80ms">
          <div v-if="$slots.badge" class="mb-4 flex justify-center">
            <slot name="badge" />
          </div>
          <h1 class="text-[32px] font-semibold leading-[1.1] tracking-[-0.025em] text-foreground">{{ title }}</h1>
          <p
            v-if="description || $slots.description"
            class="mx-auto mt-3 max-w-[360px] text-pretty text-[15px] leading-relaxed text-muted"
          >
            <slot name="description">{{ description }}</slot>
          </p>
        </div>

        <div v-if="$slots.default" class="reveal mt-10" style="--delay: 140ms">
          <slot />
        </div>

        <div v-if="$slots.footer" class="reveal mt-10 text-[13px] text-faint" style="--delay: 200ms">
          <slot name="footer" />
        </div>
      </section>
    </div>
    <footer v-if="showBranding" class="relative px-6 pb-6 pt-2">
      <PoweredBy />
    </footer>
  </main>
</template>

<style scoped>
.reveal {
  animation: reveal 0.4s cubic-bezier(0.16, 1, 0.3, 1) var(--delay, 0ms) both;
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
