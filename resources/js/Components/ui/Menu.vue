<script setup lang="ts">
import { DropdownMenuContent, DropdownMenuPortal, DropdownMenuRoot, DropdownMenuTrigger } from 'radix-vue';

import { cn } from '@/lib/utils';

withDefaults(
  defineProps<{
    align?: 'start' | 'center' | 'end';
    side?: 'top' | 'right' | 'bottom' | 'left';
    width?: string;
    contentClass?: string;
  }>(),
  { align: 'end', side: 'bottom', width: 'w-52' },
);

const open = defineModel<boolean>('open', { default: false });
</script>

<template>
  <DropdownMenuRoot v-model:open="open" :modal="false">
    <DropdownMenuTrigger as-child>
      <slot name="trigger" />
    </DropdownMenuTrigger>
    <DropdownMenuPortal>
      <DropdownMenuContent
        :align="align"
        :side="side"
        :side-offset="6"
        :collision-padding="8"
        :class="
          cn(
            'z-[90] origin-[var(--radix-dropdown-menu-content-transform-origin)] rounded-xl bg-overlay p-1 shadow-popover outline-none data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0 data-[state=closed]:zoom-out-[0.97] data-[state=open]:zoom-in-[0.97]',
            width,
            contentClass,
          )
        "
      >
        <slot />
      </DropdownMenuContent>
    </DropdownMenuPortal>
  </DropdownMenuRoot>
</template>
