<script setup lang="ts">
import { ChevronRight } from '@lucide/vue';
import { DropdownMenuPortal, DropdownMenuSub, DropdownMenuSubContent, DropdownMenuSubTrigger } from 'radix-vue';

import { cn } from '@/lib/utils';

withDefaults(
  defineProps<{
    label: string;
    icon?: unknown;
    width?: string;
    contentClass?: string;
  }>(),
  { width: 'w-56' },
);
</script>

<template>
  <DropdownMenuSub>
    <DropdownMenuSubTrigger
      class="flex h-8 cursor-default select-none items-center gap-2.5 rounded-lg px-2.5 text-[13px] text-foreground outline-none transition-colors duration-75 data-[highlighted]:bg-elevated data-[state=open]:bg-elevated"
    >
      <component :is="icon" v-if="icon" class="h-3.5 w-3.5 shrink-0 text-muted" />
      <span class="min-w-0 flex-1 truncate">{{ label }}</span>
      <ChevronRight class="h-3.5 w-3.5 shrink-0 text-faint" />
    </DropdownMenuSubTrigger>
    <DropdownMenuPortal>
      <DropdownMenuSubContent
        :side-offset="6"
        :collision-padding="8"
        :class="
          cn(
            'z-[91] max-h-[min(var(--radix-dropdown-menu-content-available-height),18rem)] overflow-y-auto rounded-xl bg-overlay p-1 shadow-popover outline-none data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0',
            width,
            contentClass,
          )
        "
      >
        <slot />
      </DropdownMenuSubContent>
    </DropdownMenuPortal>
  </DropdownMenuSub>
</template>
