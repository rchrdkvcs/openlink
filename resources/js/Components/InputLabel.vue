<script setup lang="ts">
defineProps<{
    value?: string;
}>();
</script>

<template>
    <label class="block text-[13px] font-medium text-foreground">
        <span v-if="value">{{ value }}</span>
        <span v-else><slot /></span>
    </label>
</template>
