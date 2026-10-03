<script setup lang="ts">
import { ChevronRight } from '@lucide/vue';
import { useId } from 'vue';

defineProps<{
  icon: unknown;
  label: string;
  summary?: string;
  active?: boolean;
}>();

const open = defineModel<boolean>('open', { default: false });
const panelId = useId();
</script>

<template>
  <div>
    <button
      type="button"
      class="flex h-10 w-full items-center gap-2.5 px-3.5 text-left transition-colors hover:bg-elevated/40 focus-visible:bg-elevated/40 focus-visible:outline-none"
      :aria-expanded="open"
      :aria-controls="panelId"
      @click="open = !open"
    >
      <component :is="icon" class="h-4 w-4 shrink-0" :class="active ? 'text-accent' : 'text-faint'" />
      <span class="flex-1 text-[13px] font-medium text-foreground">{{ label }}</span>
      <span class="max-w-[55%] truncate text-[13px]" :class="active ? 'text-muted' : 'text-faint'">{{ summary }}</span>
      <ChevronRight
        class="ease-emphasized-out h-3.5 w-3.5 shrink-0 text-faint transition-transform duration-200"
        :class="open ? 'rotate-90' : ''"
      />
    </button>
    <div
      :id="panelId"
      class="ease-emphasized-out grid transition-[grid-template-rows] duration-300"
      :class="open ? 'grid-rows-[1fr]' : 'grid-rows-[0fr]'"
    >
      <div class="min-h-0 overflow-hidden">
        <div class="space-y-3 px-3.5 pb-3.5 pt-1">
          <slot />
        </div>
      </div>
    </div>
  </div>
</template>
