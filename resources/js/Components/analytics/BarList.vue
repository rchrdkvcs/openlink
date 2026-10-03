<script setup lang="ts">
import { formatCompact, formatNumber, type BarListRow } from '@/lib/analytics';

withDefaults(
  defineProps<{
    rows: BarListRow[];
    empty?: string;
  }>(),
  { empty: 'No data for this period yet.' },
);
</script>

<template>
  <div v-if="rows.length > 0" class="space-y-0.5 p-2">
    <div
      v-for="row in rows"
      :key="row.label"
      class="group relative flex h-8 items-center gap-3 rounded-lg px-2.5"
      :title="formatNumber(row.count)"
    >
      <div
        class="absolute inset-y-0.5 start-0 rounded-lg bg-accent/10 transition-colors duration-150 group-hover:bg-accent/15"
        :style="{ width: `${Math.max(row.share, 1.5)}%` }"
      />
      <span class="relative z-10 min-w-0 flex-1 truncate text-[13px] text-foreground">
        <span v-if="row.prefix" class="me-1.5">{{ row.prefix }}</span
        >{{ row.display ?? row.label }}
      </span>
      <span class="relative z-10 hidden w-12 text-end text-xs tabular-nums text-faint sm:block">{{ row.share }}%</span>
      <span class="relative z-10 w-14 text-end text-[13px] font-medium tabular-nums text-foreground">{{
        formatCompact(row.count)
      }}</span>
    </div>
  </div>

  <div v-else class="flex h-full min-h-24 items-center justify-center px-6 py-4">
    <p class="max-w-xs text-balance text-center text-[13px] text-faint">{{ empty }}</p>
  </div>
</template>
