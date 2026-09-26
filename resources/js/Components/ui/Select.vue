<script setup lang="ts" generic="T extends SelectValue">
import { ChevronDown, ChevronUp } from '@lucide/vue';
import {
  SelectContent,
  SelectIcon,
  SelectPortal,
  SelectRoot,
  SelectScrollDownButton,
  SelectScrollUpButton,
  SelectTrigger,
  SelectValue as RadixSelectValue,
  SelectViewport,
} from 'radix-vue';
import { computed, inject } from 'vue';

import { decodeSelectValue, encodeSelectValue, fieldContextKey, type SelectValue } from '@/lib/select';
import { cn } from '@/lib/utils';

defineOptions({ inheritAttrs: false });
const props = withDefaults(
  defineProps<{
    modelValue?: T;
    disabled?: boolean;
    placeholder?: string;
    name?: string;
  }>(),
  { placeholder: 'Choose an option…' },
);
const emit = defineEmits<{ 'update:modelValue': [value: T] }>();
const field = inject(fieldContextKey, undefined);
const value = computed(() => (props.modelValue === undefined ? undefined : encodeSelectValue(props.modelValue)));
</script>

<template>
  <input v-if="name" type="hidden" :name="name" :value="modelValue" :disabled="disabled" />
  <SelectRoot
    :model-value="value"
    :disabled="disabled"
    @update:model-value="emit('update:modelValue', decodeSelectValue($event) as T)"
  >
    <SelectTrigger
      v-bind="$attrs"
      :class="
        cn(
          'ui-control inline-flex h-9 w-full min-w-0 items-center justify-between gap-2 px-3 text-start',
          $attrs.class as string,
        )
      "
      :aria-labelledby="($attrs['aria-labelledby'] as string) ?? ($attrs['aria-label'] ? undefined : field?.labelId)"
      :aria-describedby="($attrs['aria-describedby'] as string) ?? field?.descriptionId"
      :aria-invalid="field?.invalid || undefined"
    >
      <RadixSelectValue :placeholder="placeholder" class="pointer-events-none min-w-0 flex-1 truncate" />
      <SelectIcon as-child><ChevronDown class="h-3.5 w-3.5 shrink-0 text-faint" :stroke-width="1.5" /></SelectIcon>
    </SelectTrigger>
    <SelectPortal>
      <SelectContent
        position="popper"
        :side-offset="5"
        :collision-padding="12"
        class="ui-popover z-[70] max-h-[min(20rem,var(--radix-select-content-available-height))] min-w-[var(--radix-select-trigger-width)] max-w-[min(28rem,calc(100vw-1.5rem))] overflow-hidden"
      >
        <SelectScrollUpButton class="flex h-6 items-center justify-center text-muted"
          ><ChevronUp class="h-3.5 w-3.5"
        /></SelectScrollUpButton>
        <SelectViewport class="p-1"><slot /></SelectViewport>
        <SelectScrollDownButton class="flex h-6 items-center justify-center text-muted"
          ><ChevronDown class="h-3.5 w-3.5"
        /></SelectScrollDownButton>
      </SelectContent>
    </SelectPortal>
  </SelectRoot>
</template>
