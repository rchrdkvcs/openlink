<script setup lang="ts">
import { cn } from '@/lib/utils';

withDefaults(
  defineProps<{
    variant?: 'default' | 'danger';
    title?: string;
    type?: 'button' | 'submit';
  }>(),
  {
    variant: 'default',
    type: 'button',
  },
);
</script>

<template>
  <button
    :type="type"
    :title="title"
    :aria-label="title"
    :class="
      cn(
        'ui-icon-button',
        variant === 'danger' ? 'hover:bg-danger/15 hover:text-danger' : 'hover:bg-elevated hover:text-foreground',
      )
    "
  >
    <slot />
  </button>
</template>
