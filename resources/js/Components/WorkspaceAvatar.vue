<script setup lang="ts">
import { computed } from 'vue';

import { workspaceColorHex, workspaceIconComponent, workspaceInitial } from '@/lib/workspaces';

const props = withDefaults(
  defineProps<{
    name?: string | null;
    icon?: string | null;
    color?: string | null;
    size?: 'sm' | 'md' | 'lg';
  }>(),
  { size: 'md' },
);

const iconComponent = computed(() => workspaceIconComponent(props.icon));
const colorHex = computed(() => workspaceColorHex(props.color));

const boxClass = computed(
  () =>
    ({
      sm: 'h-5 w-5 rounded text-[10px]',
      md: 'h-6 w-6 rounded-[6px] text-xs',
      lg: 'h-9 w-9 rounded-lg text-sm',
    })[props.size],
);

const iconClass = computed(
  () =>
    ({
      sm: 'h-3 w-3',
      md: 'h-3.5 w-3.5',
      lg: 'h-4.5 w-4.5',
    })[props.size],
);
</script>

<template>
  <span
    class="grid shrink-0 place-items-center font-semibold"
    :class="[boxClass, colorHex ? 'text-white' : 'border bg-elevated text-foreground']"
    :style="colorHex ? { backgroundColor: colorHex } : undefined"
  >
    <component :is="iconComponent" v-if="iconComponent" :class="iconClass" />
    <template v-else>{{ workspaceInitial(name) }}</template>
  </span>
</template>
