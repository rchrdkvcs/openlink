<script setup lang="ts">
import { useId } from 'vue';

import type { RoutingPreset } from '@/types/shortLinks';

import { presetIcon } from './presetIcons';

defineProps<{ presets: RoutingPreset[] }>();

const emit = defineEmits<{ pick: [kind: string] }>();

const headingId = useId();
</script>

<template>
  <section class="grid gap-3" :aria-labelledby="headingId">
    <h3 :id="headingId" class="text-[13px] font-medium text-foreground">Start from a preset</h3>
    <div class="@md:grid-cols-2 @2xl:grid-cols-3 grid gap-2">
      <button
        v-for="preset in presets"
        :key="preset.kind"
        type="button"
        class="flex items-start gap-3 rounded-xl border bg-surface p-3 text-start outline-none transition-colors duration-150 hover:bg-elevated/40 focus-visible:ring-2 focus-visible:ring-accent/40"
        @click="emit('pick', preset.kind)"
      >
        <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg bg-elevated text-accent">
          <component :is="presetIcon(preset.kind)" class="h-3.5 w-3.5" />
        </span>
        <span class="min-w-0">
          <span class="block text-[13px] font-medium leading-7 text-foreground">{{ preset.label }}</span>
          <span class="block text-xs leading-[18px] text-muted">{{ preset.description }}</span>
        </span>
      </button>
    </div>
  </section>
</template>
