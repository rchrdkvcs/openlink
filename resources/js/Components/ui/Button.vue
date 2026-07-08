<script setup lang="ts">
import { cn } from '@/lib/utils';
import { cva, type VariantProps } from 'class-variance-authority';
import { Loader2 } from '@lucide/vue';

const buttonVariants = cva(
    'inline-flex select-none items-center justify-center gap-2 whitespace-nowrap rounded-md font-medium transition-[color,background-color,border-color,transform] duration-150 ease-out focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent/40 active:scale-[0.97] disabled:pointer-events-none disabled:opacity-50',
    {
        variants: {
            variant: {
                primary: 'bg-foreground text-background hover:bg-foreground/85',
                secondary: 'border border-border bg-surface text-foreground hover:border-border-strong hover:bg-elevated',
                ghost: 'text-muted hover:bg-elevated hover:text-foreground',
                danger: 'border border-danger/30 bg-danger/10 text-danger hover:border-danger/50 hover:bg-danger/20',
            },
            size: {
                sm: 'h-8 px-2.5 text-[13px]',
                md: 'h-9 px-3.5 text-sm',
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
    <button :type="type" :disabled="disabled || loading" :class="cn(buttonVariants({ variant, size }), $attrs.class as string)">
        <Loader2 v-if="loading" class="h-4 w-4 animate-spin" />
        <slot />
    </button>
</template>
