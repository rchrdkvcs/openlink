<script setup lang="ts">
import { ListFilter, Plus } from '@lucide/vue';
import { computed } from 'vue';

import FilterPill from '@/Components/analytics/FilterPill.vue';
import LinkFilter from '@/Components/analytics/LinkFilter.vue';
import Button from '@/Components/ui/Button.vue';
import Input from '@/Components/ui/Input.vue';
import Menu from '@/Components/ui/Menu.vue';
import MenuItem from '@/Components/ui/MenuItem.vue';
import MenuSub from '@/Components/ui/MenuSub.vue';
import SegmentedControl from '@/Components/ui/SegmentedControl.vue';
import {
  RANGES,
  filterDimensions,
  hasActiveFilters,
  type AnalyticsFilterOptions,
  type AnalyticsFilterState,
  type AppliedFilters,
} from '@/lib/analyticsFilters';

const state = defineModel<AnalyticsFilterState>('state', { required: true });

const props = defineProps<{
  options: AnalyticsFilterOptions;
  applied: AppliedFilters;
  topLinkIds: number[];
}>();

const emit = defineEmits<{ clear: []; applyCustomRange: [] }>();

const dimensions = computed(() => filterDimensions(props.options, props.applied));
const activeDimensions = computed(() => dimensions.value.filter((dimension) => state.value[dimension.key] !== ''));
const availableDimensions = computed(() => dimensions.value.filter((dimension) => state.value[dimension.key] === ''));
const filtered = computed(() => hasActiveFilters(state.value));

function set(patch: Partial<AnalyticsFilterState>) {
  state.value = { ...state.value, ...patch };
}

function setDate(key: 'from' | 'to', value: string | number | null | undefined) {
  set({ [key]: String(value ?? '') });
}
</script>

<template>
  <div class="mt-6 flex flex-wrap items-center gap-x-4 gap-y-2">
    <div class="flex max-w-full flex-wrap items-center gap-2">
      <SegmentedControl
        :model-value="state.range"
        @update:model-value="set({ range: $event })"
        :options="RANGES"
        size="sm"
        label="Date range"
        class="max-w-full overflow-x-auto"
      />

      <template v-if="state.range === 'custom'">
        <Input
          :model-value="state.from"
          @update:model-value="setDate('from', $event)"
          type="date"
          size="sm"
          class="w-auto"
          aria-label="From"
          @change="emit('applyCustomRange')"
        />
        <span class="text-xs text-faint">to</span>
        <Input
          :model-value="state.to"
          @update:model-value="setDate('to', $event)"
          type="date"
          size="sm"
          class="w-auto"
          aria-label="To"
          @change="emit('applyCustomRange')"
        />
      </template>
    </div>

    <div class="flex min-w-0 max-w-full flex-wrap items-center gap-2">
      <LinkFilter
        :model-value="state.link"
        @update:model-value="set({ link: $event })"
        :links="options.links"
        :top-link-ids="topLinkIds"
      />

      <FilterPill
        v-for="dimension in activeDimensions"
        :key="dimension.key"
        :model-value="state[dimension.key]"
        @update:model-value="set({ [dimension.key]: $event })"
        :label="dimension.label"
        :icon="dimension.icon"
        :options="dimension.options"
        @remove="set({ [dimension.key]: '' })"
      />

      <Menu v-if="availableDimensions.length > 0" align="start" width="w-52">
        <template #trigger>
          <Button variant="ghost" size="sm" type="button">
            <Plus v-if="activeDimensions.length > 0" />
            <ListFilter v-else />
            Add filter
          </Button>
        </template>
        <MenuSub
          v-for="dimension in availableDimensions"
          :key="dimension.key"
          :label="dimension.label"
          :icon="dimension.icon"
        >
          <MenuItem
            v-for="option in dimension.options"
            :key="option.value"
            @select="set({ [dimension.key]: option.value })"
          >
            {{ option.label }}
          </MenuItem>
        </MenuSub>
      </Menu>

      <Button v-if="filtered" variant="ghost" size="sm" type="button" @click="emit('clear')"> Clear </Button>
    </div>
  </div>
</template>
