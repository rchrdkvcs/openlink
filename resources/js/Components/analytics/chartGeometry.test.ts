import { describe, expect, it } from 'vitest';

import { createChartScale, labelIndexes, niceMax, steppedIndex } from '@/Components/analytics/chartGeometry';

function point(visits: number, scans = 0) {
  return { bucket: '2026-01-01', visits, scans, visitors: 0, blocked: 0 };
}

describe('niceMax', () => {
  it('rounds up to a readable ceiling', () => {
    expect(niceMax(1)).toBe(4);
    expect(niceMax(17)).toBe(20);
    expect(niceMax(230)).toBe(250);
    expect(niceMax(4800)).toBe(5000);
  });
});

describe('labelIndexes', () => {
  it('always includes the first and last index', () => {
    expect(labelIndexes(0)).toEqual([]);
    expect(labelIndexes(1)).toEqual([0]);
    expect(labelIndexes(30)).toEqual([0, 5, 10, 15, 20, 25, 29]);
  });
});

describe('steppedIndex', () => {
  it('moves with arrows, clamps, and clears on escape', () => {
    expect(steppedIndex('ArrowRight', null, 3)).toBe(0);
    expect(steppedIndex('ArrowRight', 2, 3)).toBe(2);
    expect(steppedIndex('ArrowLeft', null, 3)).toBe(2);
    expect(steppedIndex('Escape', 1, 3)).toBeNull();
    expect(steppedIndex('Enter', 1, 3)).toBeUndefined();
    expect(steppedIndex('ArrowRight', 1, 0)).toBeUndefined();
  });
});

describe('createChartScale', () => {
  it('maps points onto the plot area', () => {
    const scale = createChartScale([point(0), point(4, 2)], 400);
    expect(scale.x(0)).toBe(44);
    expect(scale.x(1)).toBe(388);
    expect(scale.yTicks).toEqual([0, 1, 2, 3, 4]);
    expect(scale.linePath('visits')).toBe('M44.0,234.0L388.0,12.0');
    expect(scale.areaPath('visits')).toBe('M44.0,234.0L388.0,12.0L388.0,234.0L44.0,234.0Z');
    expect(scale.indexAt(300)).toBe(1);
  });

  it('centres a single point', () => {
    expect(createChartScale([point(1)], 400).x(0)).toBe(216);
  });
});
