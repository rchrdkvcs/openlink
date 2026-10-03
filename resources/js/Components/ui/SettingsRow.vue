<script setup lang="ts">
withDefaults(
  defineProps<{
    label: string;
    description?: string;
    error?: string;
    stacked?: boolean;
    for?: string;
  }>(),
  { stacked: false },
);
</script>

<template>
  <div
    class="flex gap-x-6 gap-y-3 px-4 py-3.5 sm:px-5"
    :class="stacked ? 'flex-col' : 'flex-col sm:flex-row sm:items-center sm:justify-between'"
  >
    <div class="min-w-0" :class="stacked ? '' : 'sm:max-w-[55%]'">
      <label :for="$props.for" class="block text-sm font-medium text-foreground">{{ label }}</label>
      <p v-if="description" class="mt-0.5 text-[13px] leading-relaxed text-muted">{{ description }}</p>
      <p v-if="error" class="mt-1 text-xs text-danger">{{ error }}</p>
    </div>
    <div class="min-w-0" :class="stacked ? '' : 'sm:w-72 sm:shrink-0'">
      <slot />
    </div>
  </div>
</template>
