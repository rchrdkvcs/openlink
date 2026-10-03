<script setup lang="ts">
import { cn } from '@/lib/utils';

withDefaults(
  defineProps<{
    variant?: 'default' | 'danger';
    size?: 'sm' | 'md';
    title?: string;
    type?: 'button' | 'submit';
  }>(),
  {
    variant: 'default',
    size: 'md',
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
        'grid shrink-0 place-items-center rounded-lg text-muted transition-[color,background-color,transform] duration-150 ease-out focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent/40 active:scale-95',
        size === 'sm' ? 'h-6 w-6 [&_svg]:h-3.5 [&_svg]:w-3.5' : 'h-7 w-7 [&_svg]:h-4 [&_svg]:w-4',
        variant === 'danger' ? 'hover:bg-danger/15 hover:text-danger' : 'hover:bg-elevated hover:text-foreground',
      )
    "
  >
    <slot />
  </button>
</template>
