<script setup lang="ts">
defineProps<{
  label?: string;
  hint?: string;
  error?: string;
}>();
</script>

<template>
  <label class="grid content-start gap-1.5">
    <span v-if="label" class="text-[13px] font-medium text-foreground">{{ label }}</span>
    <slot />
    <span v-if="error" class="text-xs text-danger">{{ error }}</span>
    <span v-else-if="hint" class="text-xs text-faint">{{ hint }}</span>
  </label>
</template>
