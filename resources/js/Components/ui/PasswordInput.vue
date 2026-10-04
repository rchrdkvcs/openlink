<script setup lang="ts">
import { Eye, EyeOff } from '@lucide/vue';
import { ref } from 'vue';

import Input from '@/Components/ui/Input.vue';
import type { ControlSize } from '@/lib/controls';

defineOptions({ inheritAttrs: false });

withDefaults(
  defineProps<{
    modelValue: string;
    placeholder?: string;
    autocomplete?: string;
    size?: ControlSize;
  }>(),
  { autocomplete: 'new-password', size: 'md' },
);

const emit = defineEmits<{ 'update:modelValue': [value: string] }>();

const show = ref(false);
</script>

<template>
  <div class="relative">
    <Input
      v-bind="$attrs"
      :model-value="modelValue"
      :type="show ? 'text' : 'password'"
      :size="size"
      :class="size === 'lg' ? 'pr-11' : 'pr-10'"
      :placeholder="placeholder"
      :autocomplete="autocomplete"
      @update:model-value="emit('update:modelValue', String($event ?? ''))"
    />
    <button
      type="button"
      class="absolute right-1 top-1/2 grid -translate-y-1/2 place-items-center rounded-md text-faint transition-colors hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent/25"
      :class="size === 'lg' ? 'h-8 w-8' : 'h-7 w-7'"
      :aria-label="show ? 'Hide password' : 'Show password'"
      :aria-pressed="show"
      :title="show ? 'Hide password' : 'Show password'"
      @click="show = !show"
    >
      <component :is="show ? EyeOff : Eye" class="h-3.5 w-3.5" />
    </button>
  </div>
</template>
