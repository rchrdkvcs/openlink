<script setup lang="ts">
import { Check } from '@lucide/vue';

import { WORKSPACE_COLORS, workspaceInitial } from '@/lib/workspaces';

defineProps<{ name?: string }>();

const color = defineModel<string>('color', { default: '' });
</script>

<template>
  <div class="grid gap-1.5">
    <span class="text-[13px] font-medium text-foreground">Color</span>
    <div class="flex flex-wrap items-center gap-1.5">
      <button
        type="button"
        title="No color"
        class="grid h-7 w-7 place-items-center rounded-full border bg-elevated text-[10px] font-semibold text-muted transition-transform duration-100 hover:scale-105"
        :class="color === '' ? 'ring-2 ring-accent ring-offset-2 ring-offset-overlay' : ''"
        @click="color = ''"
      >
        {{ workspaceInitial(name) }}
      </button>
      <button
        v-for="(hex, key) in WORKSPACE_COLORS"
        :key="key"
        type="button"
        :title="key"
        class="grid h-7 w-7 place-items-center rounded-full text-white transition-transform duration-100 hover:scale-105"
        :class="color === key ? 'ring-2 ring-accent ring-offset-2 ring-offset-overlay' : ''"
        :style="{ backgroundColor: hex }"
        @click="color = color === key ? '' : key"
      >
        <Check v-if="color === key" class="h-3.5 w-3.5" />
      </button>
    </div>
  </div>
</template>
