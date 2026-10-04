<script setup lang="ts">
import { CircleCheck, Info } from '@lucide/vue';

withDefaults(defineProps<{ tone?: 'success' | 'accent' }>(), { tone: 'success' });
</script>

<template>
  <div
    role="status"
    class="flex items-start gap-2.5 rounded-xl border px-3.5 py-3 text-[13px] leading-relaxed"
    :class="
      tone === 'success'
        ? 'border-success/20 bg-success/[0.06] text-foreground'
        : 'border-accent/25 bg-accent/[0.07] text-foreground'
    "
  >
    <CircleCheck v-if="tone === 'success'" class="mt-0.5 h-4 w-4 shrink-0 text-success" />
    <Info v-else class="mt-0.5 h-4 w-4 shrink-0 text-accent" />
    <p class="min-w-0"><slot /></p>
  </div>
</template>
