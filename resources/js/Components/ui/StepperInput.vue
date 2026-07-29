<script setup lang="ts">
import { Minus, Plus } from '@lucide/vue';

const props = withDefaults(
  defineProps<{
    modelValue: string;
    step?: number;
    min?: number;
    placeholder?: string;
  }>(),
  { step: 1, min: 1 },
);

const emit = defineEmits<{ 'update:modelValue': [value: string] }>();

function stepBy(delta: number) {
  const next = Math.max(props.min, (Number(props.modelValue) || 0) + delta);
  emit('update:modelValue', String(next));
}
</script>

<template>
  <div class="flex items-center gap-1.5">
    <button
      type="button"
      class="grid h-9 w-9 shrink-0 place-items-center rounded-md border text-muted transition-colors hover:border-border-strong hover:text-foreground"
      @click="stepBy(-step)"
    >
      <Minus class="h-3.5 w-3.5" />
    </button>
    <input
      :value="modelValue"
      inputmode="numeric"
      class="h-9 min-w-0 flex-1 text-center font-mono !text-sm tabular-nums"
      :placeholder="placeholder"
      @input="emit('update:modelValue', ($event.target as HTMLInputElement).value)"
    />
    <button
      type="button"
      class="grid h-9 w-9 shrink-0 place-items-center rounded-md border text-muted transition-colors hover:border-border-strong hover:text-foreground"
      @click="stepBy(step)"
    >
      <Plus class="h-3.5 w-3.5" />
    </button>
  </div>
</template>
