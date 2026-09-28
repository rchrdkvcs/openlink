<script setup lang="ts" generic="T extends string | number | null">
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
import { computed, inject } from 'vue';

import { controlVariants, type ControlSize, type SelectOption } from '@/lib/controls';
import { portalTargetKey } from '@/lib/overlays';
import { cn } from '@/lib/utils';

defineOptions({ inheritAttrs: false });
const props = withDefaults(
  defineProps<{
    modelValue?: string | number | null;
    options: SelectOption<T>[];
    placeholder?: string;
    disabled?: boolean;
    size?: ControlSize;
  }>(),
  { size: 'md' },
);
const emit = defineEmits<{ 'update:modelValue': [value: T] }>();

const portalTarget = inject(portalTargetKey, undefined);

const normalize = (value: unknown) => String(value ?? '');

const selectedKey = computed(() => {
  const index = props.options.findIndex((option) => normalize(option.value) === normalize(props.modelValue));
  return index === -1 ? '' : String(index);
});

function select(key: string) {
  const option = props.options[Number(key)];
  if (option) emit('update:modelValue', option.value);
}
</script>

<template>
  <SelectRoot :model-value="selectedKey" :disabled="disabled" @update:model-value="select">
    <SelectTrigger
      v-bind="$attrs"
      :class="
        cn(
          controlVariants({ size }),
          'flex items-center justify-between gap-2 text-start data-[placeholder]:text-faint [&>span:first-child]:truncate',
          $attrs.class as string,
        )
      "
    >
      <SelectValue :placeholder="placeholder" />
      <SelectIcon as-child><ChevronDown class="h-3.5 w-3.5 shrink-0 text-faint" /></SelectIcon>
    </SelectTrigger>
    <SelectPortal :to="portalTarget ?? 'body'">
      <SelectContent
        position="popper"
        :side-offset="5"
        class="z-[70] max-h-[min(var(--radix-select-content-available-height),20rem)] min-w-[var(--radix-select-trigger-width)] max-w-[min(32rem,calc(100vw-2rem))] overflow-hidden rounded-lg border border-border-strong bg-overlay p-1 shadow-drawer"
      >
        <SelectScrollUpButton class="flex justify-center py-1 text-muted"
          ><ChevronUp class="h-4 w-4"
        /></SelectScrollUpButton>
        <SelectViewport>
          <SelectItem
            v-for="(option, index) in options"
            :key="normalize(option.value)"
            :value="String(index)"
            class="relative flex cursor-default select-none items-center rounded-md py-2 pe-8 ps-2.5 text-[13px] text-foreground outline-none data-[disabled]:pointer-events-none data-[highlighted]:bg-elevated data-[state=checked]:text-accent data-[disabled]:opacity-50"
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
