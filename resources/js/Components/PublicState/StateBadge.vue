<script setup lang="ts">
import { stateToneColor, type StateTone } from '@/lib/publicStates';

withDefaults(defineProps<{ tone?: StateTone; live?: boolean }>(), { tone: 'neutral', live: false });
</script>

<template>
  <span
    class="inline-flex h-6 items-center gap-1.5 rounded-full border border-border-strong px-2.5 text-xs font-medium text-muted"
  >
    <span
      class="h-1.5 w-1.5 rounded-full"
      :class="{ 'badge-live': live }"
      :style="{ backgroundColor: `hsl(${stateToneColor[tone]})` }"
    />
    <slot />
  </span>
</template>

<style scoped>
.badge-live {
  animation: badge-live 2.4s ease-in-out infinite;
}

@keyframes badge-live {
  50% {
    opacity: 0.35;
  }
}

@media (prefers-reduced-motion: reduce) {
  .badge-live {
    animation: none !important;
  }
}
</style>
