<script setup lang="ts">
import { PopoverContent, PopoverPortal, PopoverRoot, PopoverTrigger } from 'radix-vue';

import { cn } from '@/lib/utils';

defineOptions({ inheritAttrs: false });
withDefaults(defineProps<{ align?: 'start' | 'center' | 'end'; side?: 'top' | 'bottom' | 'left' | 'right' }>(), {
  align: 'start',
  side: 'bottom',
});
const open = defineModel<boolean>('open', { default: false });
</script>
<template>
  <PopoverRoot v-model:open="open">
    <PopoverTrigger as-child><slot name="trigger" /></PopoverTrigger>
    <PopoverPortal>
      <PopoverContent
        v-bind="$attrs"
        :side="side"
        :align="align"
        :side-offset="6"
        :collision-padding="12"
        :class="
          cn(
            'ui-popover z-[70] max-h-[var(--radix-popover-content-available-height)] max-w-[calc(100vw-1.5rem)] overflow-y-auto',
            $attrs.class as string,
          )
        "
      >
        <slot />
      </PopoverContent>
    </PopoverPortal>
  </PopoverRoot>
</template>
