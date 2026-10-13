<script setup lang="ts">
import type { HTMLAttributes } from 'vue';

import { cn } from '@/lib/utils';

const props = defineProps<{
  as?: string;
  class?: HTMLAttributes['class'];
}>();
</script>

<template>
  <component :is="as ?? 'li'" data-sidebar="menu-item" :class="cn('group/menu-item relative', props.class)">
    <slot />
  </component>
</template>
