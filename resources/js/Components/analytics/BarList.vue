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
    <div class="space-y-1 p-3">
        <div v-for="row in rows" :key="row.label" class="group relative flex h-8 items-center gap-3 rounded-[5px] px-2.5" :title="formatNumber(row.count)">
            <!-- Share bar behind the label, in the series accent wash -->
            <div class="absolute inset-y-0.5 left-0 rounded-[5px] bg-accent/10" :style="{ width: `${Math.max(row.share, 1.5)}%` }" />
            <span class="relative z-10 min-w-0 flex-1 truncate text-[13px] text-foreground">
                <span v-if="row.prefix" class="mr-1.5">{{ row.prefix }}</span>{{ row.display ?? row.label }}
            </span>
            <span class="relative z-10 hidden w-12 text-right text-xs tabular-nums text-faint sm:block">{{ row.share }}%</span>
            <span class="relative z-10 w-14 text-right text-[13px] font-medium tabular-nums text-foreground">{{ formatCompact(row.count) }}</span>
        </div>

        <p v-if="rows.length === 0" class="px-2.5 py-6 text-center text-[13px] text-faint">{{ empty }}</p>
    </div>
</template>
