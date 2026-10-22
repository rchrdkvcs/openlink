<script setup lang="ts">
import { computed } from 'vue';

import { stateToneColor, type StateTone } from '@/lib/publicStates';

const props = withDefaults(defineProps<{ tone?: StateTone }>(), { tone: 'accent' });

const glow = computed(() => ({
  background: `radial-gradient(ellipse at top, hsl(${stateToneColor[props.tone]} / 0.1), transparent 65%)`,
}));
</script>

<template>
  <div class="pointer-events-none absolute inset-0 -z-10" aria-hidden="true">
    <div
      class="absolute inset-0 bg-[radial-gradient(hsl(var(--border-strong))_1px,transparent_1px)] opacity-70 [background-size:22px_22px] [mask-image:radial-gradient(ellipse_70%_55%_at_50%_0%,black,transparent)]"
    />
    <div class="absolute inset-x-0 top-0 h-[28rem] transition-[background] duration-500" :style="glow" />
  </div>
</template>
