<script setup lang="ts">
import { ChevronDown, X } from '@lucide/vue';
import { computed } from 'vue';

import Menu from '@/Components/ui/Menu.vue';
import MenuItem from '@/Components/ui/MenuItem.vue';
import MenuLabel from '@/Components/ui/MenuLabel.vue';
import type { SelectOption } from '@/lib/controls';

const props = defineProps<{
  label: string;
  icon?: unknown;
  options: SelectOption[];
}>();

const model = defineModel<string>({ required: true });
const emit = defineEmits<{ remove: [] }>();

const current = computed(() => props.options.find((option) => option.value === model.value)?.label ?? 'Unknown');
</script>

<template>
  <div class="inline-flex h-7 max-w-full items-center rounded-lg bg-elevated text-[13px]">
    <Menu align="start" width="w-60" content-class="max-h-72 overflow-y-auto">
      <template #trigger>
        <button
          type="button"
          class="inline-flex h-full min-w-0 items-center gap-1.5 rounded-lg pe-1.5 ps-2.5 outline-none focus-visible:ring-2 focus-visible:ring-accent/40"
          :aria-label="`${label} filter: ${current}`"
        >
          <component :is="icon" v-if="icon" class="h-3.5 w-3.5 shrink-0 text-muted" />
          <span class="text-muted">{{ label }}</span>
          <span class="min-w-0 max-w-48 truncate font-medium text-foreground">{{ current }}</span>
          <ChevronDown class="h-3 w-3 shrink-0 text-faint" />
        </button>
      </template>
      <MenuLabel>{{ label }}</MenuLabel>
      <MenuItem
        v-for="option in options"
        :key="option.value"
        :checked="option.value === model"
        @select="model = option.value"
      >
        {{ option.label }}
      </MenuItem>
    </Menu>
    <button
      type="button"
      class="me-0.5 grid h-6 w-6 shrink-0 place-items-center rounded-md text-faint outline-none transition-colors hover:bg-border-strong hover:text-foreground focus-visible:ring-2 focus-visible:ring-accent/40"
      :aria-label="`Remove ${label.toLowerCase()} filter`"
      @click="emit('remove')"
    >
      <X class="h-3.5 w-3.5" />
    </button>
  </div>
</template>
