<script setup lang="ts" generic="T extends string">
withDefaults(
  defineProps<{
    options: { value: T; label: string; icon?: unknown }[];
    size?: 'sm' | 'md';
    label?: string;
  }>(),
  { size: 'md' },
);

const model = defineModel<T>({ required: true });
</script>

<template>
  <div
    role="radiogroup"
    :aria-label="label"
    class="inline-flex rounded-lg bg-elevated/60 p-0.5"
    :class="size === 'sm' ? 'h-7' : 'h-8'"
  >
    <button
      v-for="option in options"
      :key="option.value"
      type="button"
      role="radio"
      :aria-checked="model === option.value"
      class="inline-flex flex-1 items-center justify-center gap-1.5 whitespace-nowrap rounded-md px-2.5 text-[13px] font-medium transition-[color,background-color,box-shadow] duration-150 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent/40"
      :class="model === option.value ? 'bg-border-strong text-foreground' : 'text-muted hover:text-foreground'"
      @click="model = option.value"
    >
      <component :is="option.icon" v-if="option.icon" class="h-3.5 w-3.5" />
      {{ option.label }}
    </button>
  </div>
</template>
