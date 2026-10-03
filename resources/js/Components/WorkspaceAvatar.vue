<script setup lang="ts">
import { computed } from 'vue';

import { workspaceIconComponent, workspaceInitial } from '@/lib/workspaces';

const props = withDefaults(
  defineProps<{
    name?: string | null;
    icon?: string | null;
    size?: 'sm' | 'md' | 'lg';
  }>(),
  { size: 'md' },
);

const iconComponent = computed(() => workspaceIconComponent(props.icon));

const boxClass = computed(
  () =>
    ({
      sm: 'h-5 w-5 rounded-[5px] text-[10px]',
      md: 'h-6 w-6 rounded-md text-xs',
      lg: 'h-9 w-9 rounded-lg text-sm',
    })[props.size],
);

const iconClass = computed(
  () =>
    ({
      sm: 'h-3 w-3',
      md: 'h-3.5 w-3.5',
      lg: 'h-[18px] w-[18px]',
    })[props.size],
);
</script>

<template>
  <span
    class="grid shrink-0 place-items-center bg-elevated font-semibold text-foreground outline outline-1 -outline-offset-1 outline-white/10"
    :class="boxClass"
  >
    <component :is="iconComponent" v-if="iconComponent" :class="iconClass" />
    <template v-else>{{ workspaceInitial(name) }}</template>
  </span>
</template>
