<script setup lang="ts">
import { SERIES_COLORS, formatBucket, formatNumber, type ReportRange, type TimePoint } from '@/lib/analytics';

defineProps<{
  point: TimePoint;
  bucket: ReportRange['bucket'];
  hasScans: boolean;
  left?: string;
  right?: string;
}>();
</script>

<template>
  <div
    class="pointer-events-none absolute top-3 z-10 min-w-[150px] rounded-lg bg-overlay px-3 py-2 shadow-popover"
    :style="{ left, right }"
  >
    <p class="text-[11px] font-medium text-muted">{{ formatBucket(point.bucket, bucket, 'long') }}</p>
    <div class="mt-1.5 space-y-1">
      <div class="flex items-center gap-2 text-[13px]">
        <span class="h-0.5 w-3 rounded-full" :style="{ background: SERIES_COLORS.visits }" />
        <span class="font-semibold tabular-nums text-foreground">{{ formatNumber(point.visits) }}</span>
        <span class="text-muted">visits</span>
      </div>
      <div v-if="hasScans" class="flex items-center gap-2 text-[13px]">
        <span class="h-0.5 w-3 rounded-full" :style="{ background: SERIES_COLORS.scans }" />
        <span class="font-semibold tabular-nums text-foreground">{{ formatNumber(point.scans) }}</span>
        <span class="text-muted">scans</span>
      </div>
      <div class="flex items-center gap-2 text-[13px]">
        <span class="w-3" />
        <span class="font-medium tabular-nums text-muted">{{ formatNumber(point.visitors) }}</span>
        <span class="text-faint">visitors</span>
      </div>
      <div v-if="point.blocked > 0" class="flex items-center gap-2 text-[13px]">
        <span class="w-3" />
        <span class="font-medium tabular-nums text-muted">{{ formatNumber(point.blocked) }}</span>
        <span class="text-faint">blocked</span>
      </div>
    </div>
  </div>
</template>
