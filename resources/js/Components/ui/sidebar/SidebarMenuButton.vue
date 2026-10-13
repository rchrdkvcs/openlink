<script setup lang="ts">
import { reactiveOmit } from '@vueuse/core';
import { TooltipRoot as Tooltip, TooltipContent, TooltipTrigger, TooltipPortal } from 'radix-vue';
import type { Component } from 'vue';

import type { SidebarMenuButtonProps } from './SidebarMenuButtonChild.vue';
import SidebarMenuButtonChild from './SidebarMenuButtonChild.vue';
import { useSidebar } from './utils';

defineOptions({
  inheritAttrs: false,
});

const props = withDefaults(
  defineProps<
    SidebarMenuButtonProps & {
      tooltip?: string | Component;
    }
  >(),
  {
    as: 'button',
    variant: 'default',
    size: 'default',
  },
);

const { isMobile, state } = useSidebar();

const delegatedProps = reactiveOmit(props, 'tooltip');
</script>

<template>
  <SidebarMenuButtonChild v-if="!tooltip" v-bind="{ ...delegatedProps, ...$attrs }">
    <slot />
  </SidebarMenuButtonChild>

  <Tooltip v-else>
    <TooltipTrigger as-child>
      <SidebarMenuButtonChild v-bind="{ ...delegatedProps, ...$attrs }">
        <slot />
      </SidebarMenuButtonChild>
    </TooltipTrigger>
    <TooltipPortal>
      <TooltipContent
        class="z-50 rounded-md border bg-overlay px-2 py-1 text-xs text-foreground shadow-popover"
        :side-offset="8"
        side="right"
        align="center"
        :hidden="state !== 'collapsed' || isMobile"
      >
        <template v-if="typeof tooltip === 'string'">
          {{ tooltip }}
        </template>
        <component :is="tooltip" v-else />
      </TooltipContent>
    </TooltipPortal>
  </Tooltip>
</template>
