<script setup lang="ts">
import { ArrowDownRight, ArrowUpRight } from '@lucide/vue';
import { computed } from 'vue';

import { formatCompact, formatNumber } from '@/lib/analytics';

const props = withDefaults(
  defineProps<{
    label: string;
    value: number | string;

    change?: number | null;

    upIsGood?: boolean;
    detail?: string;
  }>(),
  { change: null, upIsGood: true },
);

const display = computed(() => (typeof props.value === 'number' ? formatCompact(props.value) : props.value));
const exact = computed(() => (typeof props.value === 'number' ? formatNumber(props.value) : undefined));

const deltaClass = computed(() => {
  if (props.change === null || props.change === 0) return 'text-faint';
  const good = props.change > 0 ? props.upIsGood : !props.upIsGood;
  return good ? 'text-success' : 'text-danger';
});
</script>

<template>
  <div class="rounded-xl border bg-surface p-4 transition-colors duration-150 hover:border-border-strong">
    <div class="flex items-center justify-between gap-2 text-faint">
      <span class="truncate text-[13px] font-medium text-muted">{{ label }}</span>
      <span
        v-if="change !== null"
        class="inline-flex shrink-0 items-center gap-0.5 text-xs font-medium tabular-nums"
        :class="deltaClass"
      >
        <ArrowUpRight v-if="change > 0" class="h-3.5 w-3.5" />
        <ArrowDownRight v-else-if="change < 0" class="h-3.5 w-3.5" />
        {{ Math.abs(change) }}%
      </span>
    </div>
    <p class="mt-1.5 text-[22px] font-semibold tabular-nums tracking-[-0.02em] text-foreground" :title="exact">
      {{ display }}
    </p>
    <p v-if="detail" class="mt-1 truncate text-[13px] text-muted">{{ detail }}</p>
  </div>
</template>
