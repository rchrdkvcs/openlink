<script setup lang="ts">
import { Search, X } from '@lucide/vue';
import { computed, ref } from 'vue';

import Button from '@/Components/ui/Button.vue';
import Kbd from '@/Components/ui/Kbd.vue';
import Select from '@/Components/ui/Select.vue';
import type { LinkFilters } from '@/Pages/Links/types';
import type { Tag } from '@/types/payloads';

import { linkStatusFilterOptions } from '../linkStatus';

const props = defineProps<{ tags: Tag[]; showStatus: boolean; searching: boolean; total: number }>();

const filters = defineModel<LinkFilters>('filters', { required: true });

const emit = defineEmits<{ clear: [] }>();

const statusOptions = linkStatusFilterOptions();

const tagOptions = computed(() => [
  { value: '', label: 'Any tag' },
  ...props.tags.map((tag) => ({ value: tag.name, label: `#${tag.name}` })),
]);

const searchInput = ref<HTMLInputElement | null>(null);

function clearSearch() {
  filters.value.search = '';
  searchInput.value?.blur();
}

defineExpose({ focusSearch: () => searchInput.value?.focus() });
</script>

<template>
  <div class="mt-6 flex flex-wrap items-center gap-2">
    <div class="relative min-w-0 flex-1 sm:max-w-xs">
      <Search class="pointer-events-none absolute left-2.5 top-1/2 h-4 w-4 -translate-y-1/2 text-faint" />
      <input
        ref="searchInput"
        v-model="filters.search"
        type="search"
        aria-label="Search links"
        placeholder="Search links"
        class="h-8 w-full rounded-lg border border-transparent bg-elevated/70 pl-8 pr-8 text-[13px] text-foreground outline-none transition-[border-color,box-shadow] placeholder:text-faint hover:bg-elevated focus-visible:border-accent/70 focus-visible:ring-2 focus-visible:ring-accent/15"
        @keydown.escape="clearSearch"
      />
      <Kbd v-if="!filters.search" class="pointer-events-none absolute right-1.5 top-1/2 -translate-y-1/2">/</Kbd>
    </div>
    <Select
      v-if="showStatus"
      v-model="filters.status"
      :options="statusOptions"
      size="sm"
      aria-label="Status"
      class="w-auto min-w-32"
    />
    <Select
      v-if="tags.length"
      v-model="filters.tag"
      :options="tagOptions"
      size="sm"
      aria-label="Tag"
      class="w-auto min-w-28"
    />
    <Button v-if="searching" variant="ghost" size="sm" type="button" @click="emit('clear')">
      <X class="h-3.5 w-3.5" /> Clear
    </Button>
    <span v-if="searching" class="ml-auto text-[13px] tabular-nums text-faint">
      {{ total }} result{{ total === 1 ? '' : 's' }}
    </span>
  </div>
</template>
