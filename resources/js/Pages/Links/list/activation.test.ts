import { describe, expect, it } from 'vitest';

import { activationTime, formatCountdown } from './activation';

describe('activationTime', () => {
  it('only applies to scheduled links with a valid activation date', () => {
    expect(activationTime({ status: 'scheduled', activates_at: '2026-01-01T00:00:00Z' })).toBe(
      Date.parse('2026-01-01T00:00:00Z'),
    );
    expect(activationTime({ status: 'active', activates_at: '2026-01-01T00:00:00Z' })).toBeNull();
    expect(activationTime({ status: 'scheduled', activates_at: null })).toBeNull();
    expect(activationTime({ status: 'scheduled', activates_at: 'nope' })).toBeNull();
  });
});

describe('formatCountdown', () => {
  it.each([
    [-5000, '0s'],
    [1, '1s'],
    [59_000, '59s'],
    [61_000, '1m 1s'],
    [3_600_000 + 120_000, '1h 2m'],
    [2 * 86_400_000 + 3 * 3_600_000, '2d 3h'],
  ])('formats %d ms as %s', (milliseconds, expected) => {
    expect(formatCountdown(milliseconds)).toBe(expected);
  });
});
