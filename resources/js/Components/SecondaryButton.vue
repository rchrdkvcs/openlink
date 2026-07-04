<script setup lang="ts">
withDefaults(
    defineProps<{
        type?: 'button' | 'submit' | 'reset';
    }>(),
    {
        type: 'button',
    },
);
</script>

<template>
    <button
        :type="type"
        class="inline-flex h-9 select-none items-center justify-center gap-2 rounded-md border bg-surface px-3.5 text-sm font-medium text-foreground transition-colors duration-150 hover:border-border-strong hover:bg-elevated focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent/40 disabled:pointer-events-none disabled:opacity-50"
    >
        <slot />
    </button>
</template>
