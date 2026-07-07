<script setup lang="ts">
defineProps<{
    modelValue: boolean;
}>();

const emit = defineEmits<{ 'update:modelValue': [value: boolean] }>();
</script>

<template>
    <button
        type="button"
        role="switch"
        :aria-checked="modelValue"
        class="relative inline-flex h-5 w-9 shrink-0 items-center rounded-full transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent/40"
        :class="modelValue ? 'bg-accent' : 'bg-border-strong'"
        @click="emit('update:modelValue', !modelValue)"
    >
        <span
            class="inline-block h-4 w-4 rounded-full bg-white shadow transition-transform"
            :class="modelValue ? 'translate-x-[18px]' : 'translate-x-0.5'"
        />
    </button>
</template>
