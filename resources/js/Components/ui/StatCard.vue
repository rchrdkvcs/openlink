<script setup lang="ts">
defineProps<{
  label: string;
  value: string | number;
  detail?: string;
}>();
</script>

<template>
  <div class="card-sheen rounded-lg border bg-surface p-4 transition-colors duration-150 hover:border-border-strong">
    <div class="flex items-center justify-between text-faint">
      <span class="text-xs font-medium uppercase tracking-wide">{{ label }}</span>
      <slot name="icon" />
    </div>
    <p class="mt-3 text-2xl font-semibold tabular-nums tracking-tight text-foreground">{{ value }}</p>
    <p v-if="detail" class="mt-1 text-[13px] text-muted">{{ detail }}</p>
  </div>
</template>
