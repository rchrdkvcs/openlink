<script setup lang="ts">
import Field from '@/Components/ui/Field.vue';

defineProps<{
  label: string;
  error?: string;
  disabled?: boolean;
  emptyLabel?: string;
}>();

const color = defineModel<string>({ required: true });
</script>

<template>
  <Field :label="label" :error="error">
    <span
      class="flex h-8 items-center gap-2.5 rounded-lg border border-transparent bg-elevated/70 pl-1.5 pr-3 transition-[border-color,background-color,opacity] focus-within:border-accent/60 hover:bg-elevated"
      :class="disabled ? 'opacity-50' : ''"
    >
      <input
        v-model="color"
        type="color"
        :aria-label="`${label} color`"
        :disabled="disabled"
        class="h-5 w-5 shrink-0 cursor-pointer rounded-[5px] border-0 bg-transparent p-0 outline-none disabled:cursor-not-allowed [&::-moz-color-swatch]:rounded-[5px] [&::-moz-color-swatch]:border-0 [&::-webkit-color-swatch-wrapper]:p-0 [&::-webkit-color-swatch]:rounded-[5px] [&::-webkit-color-swatch]:border-0"
      />
      <span class="font-mono text-[13px] text-muted">{{
        disabled && emptyLabel ? emptyLabel : color.toUpperCase()
      }}</span>
    </span>
  </Field>
</template>
