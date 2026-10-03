<script setup lang="ts">
import { ChevronDown, Link2, X } from '@lucide/vue';
import { PopoverAnchor, PopoverTrigger } from 'radix-vue';

import Favicon from '@/Components/Links/Favicon.vue';
import { shortLabel, type LinkOption } from '@/lib/analytics';

defineProps<{ selected: LinkOption | null }>();

const emit = defineEmits<{ clear: [] }>();
</script>

<template>
  <PopoverAnchor as-child>
    <div
      class="inline-flex h-7 max-w-full items-center rounded-lg text-[13px] transition-colors duration-150"
      :class="selected ? 'bg-elevated' : 'bg-elevated/70 hover:bg-elevated'"
    >
      <PopoverTrigger as-child>
        <button
          type="button"
          class="inline-flex h-full min-w-0 items-center gap-1.5 rounded-lg px-2.5 outline-none focus-visible:ring-2 focus-visible:ring-accent/40"
          :class="selected ? 'pe-1.5' : ''"
          :aria-label="selected ? `Link filter: ${shortLabel(selected)}` : 'Filter by link'"
        >
          <Favicon v-if="selected && selected.destination_url" :url="selected.destination_url" size="sm" />
          <Link2 v-else class="h-3.5 w-3.5 shrink-0 text-muted" />
          <span v-if="selected" class="min-w-0 truncate font-medium text-foreground">{{ shortLabel(selected) }}</span>
          <span v-else class="text-muted">All links</span>
          <ChevronDown v-if="!selected" class="h-3.5 w-3.5 shrink-0 text-faint" />
        </button>
      </PopoverTrigger>
      <button
        v-if="selected"
        type="button"
        class="me-0.5 grid h-6 w-6 shrink-0 place-items-center rounded-md text-faint outline-none transition-colors hover:bg-border-strong hover:text-foreground focus-visible:ring-2 focus-visible:ring-accent/40"
        aria-label="Clear link filter"
        @click="emit('clear')"
      >
        <X class="h-3.5 w-3.5" />
      </button>
    </div>
  </PopoverAnchor>
</template>
