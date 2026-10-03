<script setup lang="ts">
import { ChartLine, Table2 } from '@lucide/vue';
import { ref } from 'vue';

import TimeSeriesChart from '@/Components/analytics/TimeSeriesChart.vue';
import SectionCard from '@/Components/ui/SectionCard.vue';
import SegmentedControl from '@/Components/ui/SegmentedControl.vue';
import { formatBucket, formatNumber, type ReportRange, type TimePoint } from '@/lib/analytics';

defineProps<{
  points: TimePoint[];
  bucket: ReportRange['bucket'];
  description: string;
}>();

const view = ref<'chart' | 'table'>('chart');
const VIEWS = [
  { value: 'chart' as const, label: 'Chart', icon: ChartLine },
  { value: 'table' as const, label: 'Table', icon: Table2 },
];
</script>

<template>
  <SectionCard title="Traffic" :description="description">
    <template #header>
      <SegmentedControl v-model="view" :options="VIEWS" size="sm" label="Traffic view" />
    </template>

    <div v-if="view === 'chart'" class="px-4 pb-3 pt-4">
      <TimeSeriesChart :points="points" :bucket="bucket" />
    </div>

    <div v-else class="h-[19.5rem] overflow-y-auto">
      <table class="w-full text-[13px]">
        <thead class="sticky top-0 bg-surface text-start text-xs text-faint">
          <tr>
            <th class="px-5 py-2 text-start font-medium">Period</th>
            <th class="px-5 py-2 text-end font-medium">Visits</th>
            <th class="px-5 py-2 text-end font-medium">Scans</th>
            <th class="px-5 py-2 text-end font-medium">Visitors</th>
            <th class="px-5 py-2 text-end font-medium">Blocked</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="point in points" :key="point.bucket" class="border-t">
            <td class="px-5 py-1.5 text-muted">
              {{ formatBucket(point.bucket, bucket, 'long') }}
            </td>
            <td class="px-5 py-1.5 text-end tabular-nums">{{ formatNumber(point.visits) }}</td>
            <td class="px-5 py-1.5 text-end tabular-nums">{{ formatNumber(point.scans) }}</td>
            <td class="px-5 py-1.5 text-end tabular-nums text-muted">{{ formatNumber(point.visitors) }}</td>
            <td class="px-5 py-1.5 text-end tabular-nums text-muted">{{ formatNumber(point.blocked) }}</td>
          </tr>
        </tbody>
      </table>
    </div>
  </SectionCard>
</template>
