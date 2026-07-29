<script setup lang="ts">
import { cva, type VariantProps } from 'class-variance-authority';

import { cn } from '@/lib/utils';

const badgeVariants = cva(
  'inline-flex w-fit items-center gap-1.5 whitespace-nowrap rounded-full px-2 py-0.5 text-xs font-medium capitalize',
  {
    variants: {
      variant: {
        default: 'bg-elevated text-muted',
        outline: 'border border-border bg-transparent text-muted',
        accent: 'bg-accent/15 text-accent',
        success: 'bg-success/15 text-success',
        warning: 'bg-warning/15 text-warning',
        danger: 'bg-danger/15 text-danger',
      },
    },
    defaultVariants: {
      variant: 'default',
    },
  },
);

withDefaults(
  defineProps<{
    variant?: VariantProps<typeof badgeVariants>['variant'];
    dot?: boolean;
  }>(),
  { dot: false },
);
</script>

<template>
  <span :class="cn(badgeVariants({ variant }), $attrs.class as string)">
    <span v-if="dot" class="h-1.5 w-1.5 rounded-full bg-current" />
    <slot />
  </span>
</template>
