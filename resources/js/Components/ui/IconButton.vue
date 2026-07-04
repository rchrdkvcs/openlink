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
        :class="
            cn(
                'grid h-8 w-8 shrink-0 place-items-center rounded-md text-muted transition-colors duration-150 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent/40',
                variant === 'danger' ? 'hover:bg-danger/15 hover:text-danger' : 'hover:bg-elevated hover:text-foreground',
            )
        "
    >
        <slot />
    </button>
</template>
