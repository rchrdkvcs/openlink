<script setup lang="ts">
defineProps<{
  title: string;
  description?: string;
}>();
</script>

<template>
  <div class="flex flex-col items-center justify-center px-6 py-16 text-center">
    <div v-if="$slots.icon" class="mb-4 grid h-10 w-10 place-items-center rounded-lg border bg-elevated text-faint">
      <slot name="icon" />
    </div>
    <p class="text-sm font-medium text-foreground">{{ title }}</p>
    <p v-if="description" class="mt-1 max-w-sm text-sm text-muted">{{ description }}</p>
    <div v-if="$slots.action" class="mt-4">
      <slot name="action" />
    </div>
  </div>
</template>
