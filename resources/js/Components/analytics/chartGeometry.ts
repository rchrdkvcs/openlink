import type { TimePoint } from '@/lib/analytics';

export type SeriesKey = 'visits' | 'scans';

export const CHART_HEIGHT = 260;
export const CHART_PADDING = { top: 12, right: 12, bottom: 26, left: 44 } as const;
export const PLOT_HEIGHT = CHART_HEIGHT - CHART_PADDING.top - CHART_PADDING.bottom;

export function niceMax(value: number): number {
  if (value <= 4) return 4;
  const power = 10 ** Math.floor(Math.log10(value));
  for (const step of [1, 2, 2.5, 5, 10]) {
    if (value <= step * power) return step * power;
  }
  return 10 * power;
}

export function labelIndexes(count: number): number[] {
  if (count === 0) return [];
  const target = Math.min(6, count);
  const step = Math.max(1, Math.floor((count - 1) / (target - 1 || 1)));
  const indexes: number[] = [];
  for (let i = 0; i < count; i += step) indexes.push(i);
  if (indexes[indexes.length - 1] !== count - 1) indexes.push(count - 1);
  return indexes;
}

export function steppedIndex(key: string, active: number | null, count: number): number | null | undefined {
  if (count === 0) return undefined;
  if (key === 'ArrowRight') return Math.min(count - 1, (active ?? -1) + 1);
  if (key === 'ArrowLeft') return Math.max(0, (active ?? count) - 1);
  if (key === 'Escape') return null;
  return undefined;
}

export function createChartScale(points: TimePoint[], width: number) {
  const plotWidth = width - CHART_PADDING.left - CHART_PADDING.right;
  const yMax = niceMax(Math.max(...points.map((p) => Math.max(p.visits, p.scans)), 1));
  const count = points.length;

  function x(index: number): number {
    if (count <= 1) return CHART_PADDING.left + plotWidth / 2;
    return CHART_PADDING.left + (index / (count - 1)) * plotWidth;
  }

  function y(value: number): number {
    return CHART_PADDING.top + PLOT_HEIGHT - (value / yMax) * PLOT_HEIGHT;
  }

  function linePath(key: SeriesKey): string {
    return points.map((p, i) => `${i === 0 ? 'M' : 'L'}${x(i).toFixed(1)},${y(p[key]).toFixed(1)}`).join('');
  }

  function areaPath(key: SeriesKey): string {
    if (count === 0) return '';
    const base = y(0).toFixed(1);
    return `${linePath(key)}L${x(count - 1).toFixed(1)},${base}L${x(0).toFixed(1)},${base}Z`;
  }

  function indexAt(px: number): number {
    const ratio = (px - CHART_PADDING.left) / Math.max(plotWidth, 1);
    return Math.min(count - 1, Math.max(0, Math.round(ratio * (count - 1))));
  }

  const yTicks = [0, 0.25, 0.5, 0.75, 1].map((f) => f * yMax).filter((v) => Number.isInteger(v));

  return { x, y, linePath, areaPath, indexAt, yTicks, xLabels: labelIndexes(count) };
}

export type ChartScale = ReturnType<typeof createChartScale>;
