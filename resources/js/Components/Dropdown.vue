<script setup lang="ts">
import { PopoverContent, PopoverPortal, PopoverRoot, PopoverTrigger } from 'radix-vue';
import { computed, ref } from 'vue';

const props = withDefaults(
  defineProps<{
    align?: 'left' | 'right';
    width?: '48' | '64' | '72';
    placement?: 'bottom' | 'top';
    contentClasses?: string;
  }>(),
  { align: 'right', width: '48', placement: 'bottom', contentClasses: 'p-1' },
);
const open = ref(false);
const widthClass = computed(() => ({ 48: 'w-48', 64: 'w-64', 72: 'w-72' })[props.width]);
</script>

<template>
  <PopoverRoot v-model:open="open">
    <PopoverTrigger as-child><slot name="trigger" /></PopoverTrigger>
    <PopoverPortal>
      <PopoverContent
        :side="placement"
        :align="align === 'left' ? 'start' : 'end'"
        :side-offset="8"
        :collision-padding="12"
        class="ui-popover z-[70] max-h-[var(--radix-popover-content-available-height)] max-w-[calc(100vw-1.5rem)] overflow-y-auto"
        :class="[widthClass, contentClasses]"
        @click="open = false"
      >
        <slot name="content" />
      </PopoverContent>
    </PopoverPortal>
  </PopoverRoot>
</template>
