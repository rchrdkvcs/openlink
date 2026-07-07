<script setup lang="ts">
import { X } from '@lucide/vue';

defineProps<{
    label: string;
    icon: unknown;
}>();

const emit = defineEmits<{ remove: [] }>();
</script>

<template>
    <div class="group/opt rounded-xl border bg-surface p-3">
        <div class="mb-2 flex items-center justify-between">
            <span class="flex items-center gap-2 text-[13px] font-medium text-foreground">
                <component :is="icon" class="h-3.5 w-3.5 text-accent" />
                {{ label }}
            </span>
            <button
                type="button"
                class="grid h-6 w-6 place-items-center rounded-md text-faint opacity-0 transition-all hover:bg-elevated hover:text-foreground focus-visible:opacity-100 group-hover/opt:opacity-100"
                :title="`Remove ${label.toLowerCase()}`"
                @click="emit('remove')"
            >
                <X class="h-3.5 w-3.5" />
            </button>
        </div>
        <slot />
    </div>
</template>
