<script setup lang="ts">
defineProps<{
    title?: string;
    description?: string;
}>();
</script>

<template>
    <section class="card-sheen overflow-hidden rounded-lg border bg-surface">
        <header v-if="title || $slots.header" class="flex items-center justify-between gap-4 border-b px-5 py-3.5">
            <div class="flex min-w-0 items-center gap-2 text-foreground">
                <slot name="icon" />
                <div class="min-w-0">
                    <h3 class="truncate text-sm font-semibold">{{ title }}</h3>
                    <p v-if="description" class="mt-0.5 truncate text-[13px] font-normal text-muted">{{ description }}</p>
                </div>
            </div>
            <slot name="header" />
        </header>
        <slot />
    </section>
</template>
