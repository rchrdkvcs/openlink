<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';

import {
  CHART_HEIGHT as height,
  CHART_PADDING as pad,
  PLOT_HEIGHT as plotH,
  createChartScale,
  steppedIndex,
} from '@/Components/analytics/chartGeometry';
import ChartTooltip from '@/Components/analytics/ChartTooltip.vue';
import { SERIES_COLORS, formatBucket, formatCompact, type ReportRange, type TimePoint } from '@/lib/analytics';

const props = defineProps<{
  points: TimePoint[];
  bucket: ReportRange['bucket'];
}>();

const wrapper = ref<HTMLElement | null>(null);
const width = ref(720);

let observer: ResizeObserver | null = null;

onMounted(() => {
  observer = new ResizeObserver((entries) => {
    width.value = Math.max(320, entries[0].contentRect.width);
  });
  if (wrapper.value) observer.observe(wrapper.value);
});

onBeforeUnmount(() => observer?.disconnect());

const scale = computed(() => createChartScale(props.points, width.value));
const hasScans = computed(() => props.points.some((p) => p.scans > 0));
const active = ref<number | null>(null);

function onPointerMove(event: PointerEvent) {
  const rect = (event.currentTarget as SVGElement).getBoundingClientRect();
  if (props.points.length === 0) return;
  active.value = scale.value.indexAt(((event.clientX - rect.left) / rect.width) * width.value);
}

function onKeydown(event: KeyboardEvent) {
  const next = steppedIndex(event.key, active.value, props.points.length);
  if (next === undefined) return;
  active.value = next;
  if (event.key !== 'Escape') event.preventDefault();
}

const tooltip = computed(() => {
  if (active.value === null || !props.points[active.value]) return null;
  const anchor = scale.value.x(active.value);
  const flip = anchor > width.value * 0.62;
  return {
    point: props.points[active.value],
    left: flip ? undefined : `${anchor + 12}px`,
    right: flip ? `${width.value - anchor + 12}px` : undefined,
  };
});
</script>

<template>
  <div ref="wrapper" class="relative">
    <svg
      :viewBox="`0 0 ${width} ${height}`"
      :width="width"
      :height="height"
      class="block max-w-full touch-none select-none"
      role="img"
      aria-label="Traffic over time"
      tabindex="0"
      @pointermove="onPointerMove"
      @pointerleave="active = null"
      @keydown="onKeydown"
      @blur="active = null"
    >
      <g v-for="tick in scale.yTicks" :key="tick">
        <line
          :x1="pad.left"
          :x2="width - pad.right"
          :y1="scale.y(tick)"
          :y2="scale.y(tick)"
          class="stroke-border"
          stroke-width="1"
        />
        <text :x="pad.left - 8" :y="scale.y(tick) + 3.5" text-anchor="end" class="fill-faint text-[10px] tabular-nums">
          {{ formatCompact(tick) }}
        </text>
      </g>

      <text
        v-for="i in scale.xLabels"
        :key="`x-${i}`"
        :x="scale.x(i)"
        :y="height - 8"
        :text-anchor="i === 0 ? 'start' : i === points.length - 1 ? 'end' : 'middle'"
        class="fill-faint text-[10px]"
      >
        {{ formatBucket(points[i].bucket, bucket) }}
      </text>

      <path :d="scale.areaPath('visits')" :fill="SERIES_COLORS.visits" fill-opacity="0.1" />
      <path
        :d="scale.linePath('visits')"
        fill="none"
        :stroke="SERIES_COLORS.visits"
        stroke-width="2"
        stroke-linejoin="round"
        stroke-linecap="round"
      />

      <path
        v-if="hasScans"
        :d="scale.linePath('scans')"
        fill="none"
        :stroke="SERIES_COLORS.scans"
        stroke-width="2"
        stroke-linejoin="round"
        stroke-linecap="round"
      />

      <g v-if="active !== null && points[active]">
        <line
          :x1="scale.x(active)"
          :x2="scale.x(active)"
          :y1="pad.top"
          :y2="pad.top + plotH"
          class="stroke-border-strong"
          stroke-width="1"
        />
        <circle
          :cx="scale.x(active)"
          :cy="scale.y(points[active].visits)"
          r="4"
          :fill="SERIES_COLORS.visits"
          class="stroke-surface"
          stroke-width="2"
        />
        <circle
          v-if="hasScans"
          :cx="scale.x(active)"
          :cy="scale.y(points[active].scans)"
          r="4"
          :fill="SERIES_COLORS.scans"
          class="stroke-surface"
          stroke-width="2"
        />
      </g>
    </svg>

    <ChartTooltip
      v-if="tooltip"
      :point="tooltip.point"
      :bucket="bucket"
      :has-scans="hasScans"
      :left="tooltip.left"
      :right="tooltip.right"
    />

    <div class="flex items-center gap-4 px-1 pt-2">
      <span class="inline-flex items-center gap-1.5 text-xs text-muted">
        <span class="h-0.5 w-4 rounded-full" :style="{ background: SERIES_COLORS.visits }" /> Visits
      </span>
      <span v-if="hasScans" class="inline-flex items-center gap-1.5 text-xs text-muted">
        <span class="h-0.5 w-4 rounded-full" :style="{ background: SERIES_COLORS.scans }" /> QR scans
      </span>
    </div>
  </div>
</template>
