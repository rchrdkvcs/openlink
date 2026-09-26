<script setup lang="ts">
import { Check, ChevronDown, ChevronUp } from '@lucide/vue';
import {
  SelectContent,
  SelectIcon,
  SelectItem,
  SelectItemIndicator,
  SelectItemText,
  SelectPortal,
  SelectRoot,
  SelectScrollDownButton,
  SelectScrollUpButton,
  SelectTrigger,
  SelectValue,
  SelectViewport,
} from 'radix-vue';

import { cn } from '@/lib/utils';

defineOptions({ inheritAttrs: false });
defineProps<{
  modelValue?: string;
  options: { value: string; label: string }[];
  placeholder?: string;
  disabled?: boolean;
}>();
const emit = defineEmits<{ 'update:modelValue': [value: string] }>();
</script>

<template>
  <SelectRoot :model-value="modelValue" :disabled="disabled" @update:model-value="emit('update:modelValue', $event)">
    <SelectTrigger
      v-bind="$attrs"
      :class="
        cn(
          'flex h-9 w-full min-w-0 items-center justify-between gap-2 rounded-md border bg-surface px-3 text-[13px] text-foreground outline-none transition-colors hover:border-border-strong focus-visible:border-accent/60 focus-visible:ring-2 focus-visible:ring-accent/25 disabled:opacity-50 [&>span:first-child]:truncate',
          $attrs.class as string,
        )
      "
    >
      <SelectValue :placeholder="placeholder" />
      <SelectIcon><ChevronDown class="h-3.5 w-3.5 shrink-0 text-faint" /></SelectIcon>
    </SelectTrigger>
    <SelectPortal>
      <SelectContent
        position="popper"
        :side-offset="5"
        class="z-[70] max-h-[var(--radix-select-content-available-height)] min-w-[var(--radix-select-trigger-width)] overflow-hidden rounded-lg border border-border-strong bg-overlay p-1 shadow-drawer"
        @escape-key-down.stop
      >
        <SelectScrollUpButton class="flex justify-center py-1 text-muted"
          ><ChevronUp class="h-4 w-4"
        /></SelectScrollUpButton>
        <SelectViewport>
          <SelectItem
            v-for="option in options"
            :key="option.value"
            :value="option.value"
            class="relative flex cursor-default select-none items-center rounded-md py-2 pe-8 ps-2.5 text-[13px] outline-none data-[highlighted]:bg-elevated data-[state=checked]:text-accent"
          >
            <SelectItemText>{{ option.label }}</SelectItemText>
            <SelectItemIndicator class="absolute end-2"><Check class="h-3.5 w-3.5" /></SelectItemIndicator>
          </SelectItem>
        </SelectViewport>
        <SelectScrollDownButton class="flex justify-center py-1 text-muted"
          ><ChevronDown class="h-4 w-4"
        /></SelectScrollDownButton>
      </SelectContent>
    </SelectPortal>
  </SelectRoot>
</template>
