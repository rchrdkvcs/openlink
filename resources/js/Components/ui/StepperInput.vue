<script setup lang="ts">
import { Minus, Plus } from '@lucide/vue';

import Button from '@/Components/ui/Button.vue';
import Input from '@/Components/ui/Input.vue';

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
    <Button
      type="button"
      variant="secondary"
      class="w-9 shrink-0 px-0 text-muted hover:text-foreground"
      aria-label="Decrease"
      @click="stepBy(-step)"
    >
      <Minus class="h-3.5 w-3.5" />
    </Button>
    <Input
      :model-value="modelValue"
      inputmode="numeric"
      class="flex-1 text-center font-mono tabular-nums"
      :placeholder="placeholder"
      @update:model-value="emit('update:modelValue', String($event ?? ''))"
    />
    <Button
      type="button"
      variant="secondary"
      class="w-9 shrink-0 px-0 text-muted hover:text-foreground"
      aria-label="Increase"
      @click="stepBy(step)"
    >
      <Plus class="h-3.5 w-3.5" />
    </Button>
  </div>
</template>
