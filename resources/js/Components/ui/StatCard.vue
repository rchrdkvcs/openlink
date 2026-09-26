<script setup lang="ts">
defineProps<{
  label: string;
  value: string | number;
  detail?: string;
}>();
</script>

<template>
  <div class="ui-panel p-4">
    <div class="flex items-center justify-between text-faint">
      <span class="text-sm font-medium">{{ label }}</span>
      <slot name="icon" />
    </div>
    <p class="mt-3 text-3xl font-medium tabular-nums tracking-tight text-foreground">{{ value }}</p>
    <p v-if="detail" class="mt-1 text-[13px] text-muted">{{ detail }}</p>
  </div>
</template>
