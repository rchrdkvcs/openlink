<script setup lang="ts">
import { computed, ref } from 'vue';

import BarList from '@/Components/analytics/BarList.vue';
import SectionCard from '@/Components/ui/SectionCard.vue';
import SegmentedControl from '@/Components/ui/SegmentedControl.vue';
import { type BreakdownTab } from '@/lib/analytics';

const props = defineProps<{
  title: string;
  tabs: BreakdownTab[];
}>();

const activeKey = ref(props.tabs[0]?.key ?? '');

const options = computed(() => props.tabs.map((tab) => ({ value: tab.key, label: tab.label })));
const activeTab = computed(() => props.tabs.find((tab) => tab.key === activeKey.value) ?? props.tabs[0]);
</script>

<template>
  <SectionCard :title="title" class="flex flex-col">
    <template #header>
      <SegmentedControl v-if="tabs.length > 1" v-model="activeKey" :options="options" size="sm" :label="title" />
    </template>

    <div class="h-72 overflow-y-auto">
      <BarList v-if="activeTab" :rows="activeTab.rows" :empty="activeTab.empty" />
    </div>
  </SectionCard>
</template>
