<script setup lang="ts">
import { Link } from '@inertiajs/vue3';

import { SidebarMenuButton, SidebarMenuItem } from '@/Components/ui/sidebar';

withDefaults(
  defineProps<{
    href: string;
    icon?: unknown;
    label: string;
    active?: boolean;
    count?: number | null;
    dropActive?: boolean;
  }>(),
  { active: false, count: null, dropActive: false },
);
</script>

<template>
  <SidebarMenuItem
    class="group/item relative flex h-8 items-center rounded-lg transition-colors duration-100 group-data-[collapsible=icon]:size-8"
    :class="[
      active ? 'bg-elevated text-foreground' : 'text-muted hover:bg-elevated/50 hover:text-foreground',
      dropActive ? 'bg-accent/15 text-foreground ring-1 ring-inset ring-accent/60' : '',
    ]"
  >
    <SidebarMenuButton
      as-child
      :is-active="active"
      :tooltip="label"
      class="p-0 text-[13px] group-data-[collapsible=icon]:justify-center"
    >
      <Link
        :href="href"
        :aria-label="label"
        class="flex h-full min-w-0 flex-1 items-center gap-2.5 rounded-lg px-2 text-[13px] font-medium focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent/40"
        :aria-current="active ? 'page' : undefined"
      >
        <component
          :is="icon"
          v-if="icon"
          class="h-[15px] w-[15px] shrink-0 transition-colors"
          :class="active ? 'text-foreground' : 'text-faint group-hover/item:text-muted'"
        />
        <slot name="leading" />
        <span class="min-w-0 flex-1 truncate group-data-[collapsible=icon]:hidden">{{ label }}</span>
        <span
          v-if="count !== null"
          class="text-xs tabular-nums text-faint transition-opacity group-data-[collapsible=icon]:hidden"
          :class="$slots.actions ? 'group-focus-within/item:opacity-0 group-hover/item:opacity-0' : ''"
          >{{ count }}</span
        >
      </Link>
    </SidebarMenuButton>
    <div
      v-if="$slots.actions"
      class="absolute right-1 opacity-0 transition-opacity focus-within:opacity-100 group-hover/item:opacity-100 has-[[data-state=open]]:opacity-100 group-data-[collapsible=icon]:hidden"
    >
      <slot name="actions" />
    </div>
  </SidebarMenuItem>
</template>
