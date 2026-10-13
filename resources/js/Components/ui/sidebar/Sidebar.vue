<script setup lang="ts">
import {
  DialogRoot as Sheet,
  DialogContent as SheetContent,
  DialogOverlay,
  DialogPortal,
  DialogTitle,
  DialogDescription,
} from 'radix-vue';

import { cn } from '@/lib/utils';

import type { SidebarProps } from '.';
import { SIDEBAR_WIDTH_MOBILE, useSidebar } from './utils';

defineOptions({
  inheritAttrs: false,
});

const props = withDefaults(defineProps<SidebarProps>(), {
  side: 'left',
  variant: 'sidebar',
  collapsible: 'offcanvas',
});

const { isMobile, state, openMobile, setOpenMobile } = useSidebar();
</script>

<template>
  <div
    v-if="collapsible === 'none'"
    :class="cn('flex h-full w-[var(--sidebar-width)] flex-col bg-sidebar text-sidebar-foreground', props.class)"
    v-bind="$attrs"
  >
    <slot />
  </div>

  <Sheet v-else-if="isMobile" :open="openMobile" v-bind="$attrs" @update:open="setOpenMobile">
    <DialogPortal>
      <DialogOverlay
        class="fixed inset-0 z-40 bg-black/60 backdrop-blur-[2px] data-[state=closed]:duration-150 data-[state=open]:duration-200 data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0"
      />
      <SheetContent
        data-sidebar="sidebar"
        data-mobile="true"
        :class="
          side === 'left'
            ? 'left-2 data-[state=closed]:slide-out-to-left-[calc(100%+0.5rem)] data-[state=open]:slide-in-from-left-[calc(100%+0.5rem)]'
            : 'right-2 data-[state=closed]:slide-out-to-right-[calc(100%+0.5rem)] data-[state=open]:slide-in-from-right-[calc(100%+0.5rem)]'
        "
        class="fixed inset-y-2 z-50 w-[var(--sidebar-width)] max-w-[calc(100vw-1rem)] overflow-hidden rounded-xl bg-sidebar p-0 text-sidebar-foreground shadow-dialog outline-none data-[state=closed]:duration-200 data-[state=open]:duration-300 data-[state=open]:animate-in data-[state=closed]:animate-out data-[state=open]:[animation-timing-function:cubic-bezier(0.16,1,0.3,1)] data-[state=closed]:[animation-timing-function:cubic-bezier(0.32,0.72,0,1)] [&>button]:hidden"
        :style="{
          '--sidebar-width': SIDEBAR_WIDTH_MOBILE,
        }"
      >
        <DialogTitle class="sr-only">Navigation</DialogTitle>
        <DialogDescription class="sr-only">Workspace navigation and account menu</DialogDescription>
        <div class="flex h-full w-full flex-col">
          <slot />
        </div>
      </SheetContent>
    </DialogPortal>
  </Sheet>

  <div
    v-else
    class="group peer hidden lg:block"
    :data-state="state"
    :data-collapsible="state === 'collapsed' ? collapsible : ''"
    :data-variant="variant"
    :data-side="side"
  >
    <div
      :class="
        cn(
          'relative h-svh w-[var(--sidebar-width)] bg-transparent transition-[width] duration-200 ease-linear group-data-[resizing=true]/sidebar-wrapper:transition-none',
          'group-data-[collapsible=offcanvas]:w-0',
          'group-data-[side=right]:rotate-180',
          variant === 'floating' || variant === 'inset'
            ? 'group-data-[collapsible=icon]:w-[calc(var(--sidebar-width-icon)_+_theme(spacing.4))]'
            : 'group-data-[collapsible=icon]:w-[var(--sidebar-width-icon)]',
        )
      "
    />
    <div
      :class="
        cn(
          'fixed inset-y-0 z-10 hidden h-svh w-[var(--sidebar-width)] transition-[left,right,width] duration-200 ease-linear group-data-[resizing=true]/sidebar-wrapper:transition-none lg:flex',
          side === 'left'
            ? 'left-0 group-data-[collapsible=offcanvas]:left-[calc(var(--sidebar-width)*-1)]'
            : 'right-0 group-data-[collapsible=offcanvas]:right-[calc(var(--sidebar-width)*-1)]',

          variant === 'floating' || variant === 'inset'
            ? 'p-2 group-data-[collapsible=icon]:w-[calc(var(--sidebar-width-icon)_+_theme(spacing.4)_+_2px)]'
            : 'group-data-[collapsible=icon]:w-[var(--sidebar-width-icon)] group-data-[side=left]:border-r group-data-[side=right]:border-l',
          props.class,
        )
      "
      v-bind="$attrs"
    >
      <div
        data-sidebar="sidebar"
        class="flex h-full w-full flex-col bg-sidebar text-sidebar-foreground group-data-[variant=floating]:rounded-lg group-data-[variant=floating]:border group-data-[variant=floating]:border-sidebar-border group-data-[variant=floating]:shadow"
      >
        <slot />
      </div>
    </div>
  </div>
</template>
