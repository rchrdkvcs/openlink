<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';

import {
  SERIES_COLORS,
  formatBucket,
  formatCompact,
  formatNumber,
  type ReportRange,
  type TimePoint,
} from '@/lib/analytics';

const props = defineProps<{
  points: TimePoint[];
  bucket: ReportRange['bucket'];
}>();

const wrapper = ref<HTMLElement | null>(null);
const width = ref(720);
const height = 260;
const pad = { top: 12, right: 12, bottom: 26, left: 44 };

let observer: ResizeObserver | null = null;

onMounted(() => {
  observer = new ResizeObserver((entries) => {
    width.value = Math.max(320, entries[0].contentRect.width);
  });
  if (wrapper.value) observer.observe(wrapper.value);
});

onBeforeUnmount(() => observer?.disconnect());

const plotW = computed(() => width.value - pad.left - pad.right);
const plotH = height - pad.top - pad.bottom;

function niceMax(value: number): number {
  if (value <= 4) return 4;
  const power = 10 ** Math.floor(Math.log10(value));
  for (const step of [1, 2, 2.5, 5, 10]) {
    if (value <= step * power) return step * power;
  }
  return 10 * power;
}

const yMax = computed(() => niceMax(Math.max(...props.points.map((p) => Math.max(p.visits, p.scans)), 1)));

const yTicks = computed(() => [0, 0.25, 0.5, 0.75, 1].map((f) => f * yMax.value).filter((v) => Number.isInteger(v)));

function x(index: number): number {
  const n = props.points.length;
  if (n <= 1) return pad.left + plotW.value / 2;
  return pad.left + (index / (n - 1)) * plotW.value;
}

function y(value: number): number {
  return pad.top + plotH - (value / yMax.value) * plotH;
}

function linePath(key: 'visits' | 'scans'): string {
  return props.points.map((p, i) => `${i === 0 ? 'M' : 'L'}${x(i).toFixed(1)},${y(p[key]).toFixed(1)}`).join('');
}

function areaPath(key: 'visits' | 'scans'): string {
  if (props.points.length === 0) return '';
  const base = y(0).toFixed(1);
  return `${linePath(key)}L${x(props.points.length - 1).toFixed(1)},${base}L${x(0).toFixed(1)},${base}Z`;
}

const xLabelIndexes = computed(() => {
  const n = props.points.length;
  if (n === 0) return [];
  const target = Math.min(6, n);
  const step = Math.max(1, Math.floor((n - 1) / (target - 1 || 1)));
  const indexes: number[] = [];
  for (let i = 0; i < n; i += step) indexes.push(i);
  if (indexes[indexes.length - 1] !== n - 1) indexes.push(n - 1);
  return indexes;
});

const hasScans = computed(() => props.points.some((p) => p.scans > 0));

// Hover / keyboard focus state: index of the highlighted bucket.
const active = ref<number | null>(null);

function onPointerMove(event: PointerEvent) {
  const rect = (event.currentTarget as SVGElement).getBoundingClientRect();
  const px = ((event.clientX - rect.left) / rect.width) * width.value;
  const n = props.points.length;
  if (n === 0) return;
  const ratio = (px - pad.left) / Math.max(plotW.value, 1);
  active.value = Math.min(n - 1, Math.max(0, Math.round(ratio * (n - 1))));
}

function onKeydown(event: KeyboardEvent) {
  const n = props.points.length;
  if (n === 0) return;
  if (event.key === 'ArrowRight') {
    active.value = Math.min(n - 1, (active.value ?? -1) + 1);
    event.preventDefault();
  } else if (event.key === 'ArrowLeft') {
    active.value = Math.max(0, (active.value ?? n) - 1);
    event.preventDefault();
  } else if (event.key === 'Escape') {
    active.value = null;
  }
}

const tooltip = computed(() => {
  if (active.value === null || !props.points[active.value]) return null;
  const point = props.points[active.value];
  const anchor = x(active.value);
  const flip = anchor > width.value * 0.62;
  return {
    point,
    anchor,
    flip,
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
      <!-- Gridlines + y ticks -->
      <g v-for="tick in yTicks" :key="tick">
        <line
          :x1="pad.left"
          :x2="width - pad.right"
          :y1="y(tick)"
          :y2="y(tick)"
          class="stroke-border"
          stroke-width="1"
        />
        <text :x="pad.left - 8" :y="y(tick) + 3.5" text-anchor="end" class="fill-faint text-[10px] tabular-nums">
          {{ formatCompact(tick) }}
        </text>
      </g>

      <!-- X labels -->
      <text
        v-for="i in xLabelIndexes"
        :key="`x-${i}`"
        :x="x(i)"
        :y="height - 8"
        :text-anchor="i === 0 ? 'start' : i === points.length - 1 ? 'end' : 'middle'"
        class="fill-faint text-[10px]"
      >
        {{ formatBucket(points[i].bucket, bucket) }}
      </text>

      <!-- Visits: area wash + line -->
      <path :d="areaPath('visits')" :fill="SERIES_COLORS.visits" fill-opacity="0.1" />
      <path
        :d="linePath('visits')"
        fill="none"
        :stroke="SERIES_COLORS.visits"
        stroke-width="2"
        stroke-linejoin="round"
        stroke-linecap="round"
      />

      <!-- Scans line -->
      <path
        v-if="hasScans"
        :d="linePath('scans')"
        fill="none"
        :stroke="SERIES_COLORS.scans"
        stroke-width="2"
        stroke-linejoin="round"
        stroke-linecap="round"
      />

      <!-- Crosshair + markers -->
      <g v-if="active !== null && points[active]">
        <line
          :x1="x(active)"
          :x2="x(active)"
          :y1="pad.top"
          :y2="pad.top + plotH"
          class="stroke-border-strong"
          stroke-width="1"
        />
        <circle
          :cx="x(active)"
          :cy="y(points[active].visits)"
          r="4"
          :fill="SERIES_COLORS.visits"
          class="stroke-surface"
          stroke-width="2"
        />
        <circle
          v-if="hasScans"
          :cx="x(active)"
          :cy="y(points[active].scans)"
          r="4"
          :fill="SERIES_COLORS.scans"
          class="stroke-surface"
          stroke-width="2"
        />
      </g>
    </svg>

    <!-- Tooltip: value leads, label follows -->
    <div
      v-if="tooltip"
      class="pointer-events-none absolute top-3 z-10 min-w-[150px] rounded-md border bg-overlay px-3 py-2 shadow-popover"
      :style="{ left: tooltip.left, right: tooltip.right }"
    >
      <p class="text-[11px] font-medium text-muted">{{ formatBucket(tooltip.point.bucket, bucket, 'long') }}</p>
      <div class="mt-1.5 space-y-1">
        <div class="flex items-center gap-2 text-[13px]">
          <span class="h-0.5 w-3 rounded-full" :style="{ background: SERIES_COLORS.visits }" />
          <span class="font-semibold tabular-nums text-foreground">{{ formatNumber(tooltip.point.visits) }}</span>
          <span class="text-muted">visits</span>
        </div>
        <div v-if="hasScans" class="flex items-center gap-2 text-[13px]">
          <span class="h-0.5 w-3 rounded-full" :style="{ background: SERIES_COLORS.scans }" />
          <span class="font-semibold tabular-nums text-foreground">{{ formatNumber(tooltip.point.scans) }}</span>
          <span class="text-muted">scans</span>
        </div>
        <div class="flex items-center gap-2 text-[13px]">
          <span class="w-3" />
          <span class="font-medium tabular-nums text-muted">{{ formatNumber(tooltip.point.visitors) }}</span>
          <span class="text-faint">visitors</span>
        </div>
        <div v-if="tooltip.point.blocked > 0" class="flex items-center gap-2 text-[13px]">
          <span class="w-3" />
          <span class="font-medium tabular-nums text-muted">{{ formatNumber(tooltip.point.blocked) }}</span>
          <span class="text-faint">blocked</span>
        </div>
      </div>
    </div>

    <!-- Legend -->
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
