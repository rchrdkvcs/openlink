<script setup lang="ts">
import { Loader2 } from '@lucide/vue';
import { cva, type VariantProps } from 'class-variance-authority';

import { cn } from '@/lib/utils';

const buttonVariants = cva(
  'inline-flex select-none items-center justify-center gap-1.5 whitespace-nowrap rounded-lg font-medium transition-[color,background-color,transform] duration-150 ease-out focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent/40 active:scale-[0.96] disabled:pointer-events-none disabled:opacity-50 [&_svg]:h-3.5 [&_svg]:w-3.5',
  {
    variants: {
      variant: {
        primary: 'bg-foreground text-background hover:bg-foreground/90',
        secondary: 'bg-elevated text-foreground hover:bg-border-strong',
        ghost: 'text-muted hover:bg-elevated hover:text-foreground',
        danger: 'bg-danger/12 text-danger hover:bg-danger/20',
      },
      size: {
        sm: 'h-7 px-2.5 text-[13px]',
        md: 'h-8 px-3 text-[13px]',
        lg: 'h-10 px-4 text-sm',
      },
    },
    defaultVariants: {
      variant: 'primary',
      size: 'md',
    },
  },
);

withDefaults(
  defineProps<{
    variant?: VariantProps<typeof buttonVariants>['variant'];
    size?: VariantProps<typeof buttonVariants>['size'];
    type?: 'button' | 'submit' | 'reset';
    loading?: boolean;
    disabled?: boolean;
  }>(),
  {
    type: 'submit',
    loading: false,
    disabled: false,
  },
);
</script>

<template>
  <button
    :type="type"
    :disabled="disabled || loading"
    :class="cn(buttonVariants({ variant, size }), $attrs.class as string)"
  >
    <Loader2 v-if="loading" class="h-4 w-4 animate-spin" />
    <slot />
  </button>
</template>
