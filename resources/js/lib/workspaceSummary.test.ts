import { describe, expect, it } from 'vitest';

import { initialTimezone, statusLines } from '@/lib/workspaceSummary';

const idle = { last_sent_at: null, last_failure: null, pending: 0, next_send_at: null };

describe('statusLines', () => {
  it('reports when nothing was delivered yet and summaries are off', () => {
    expect(statusLines(idle, false).map((line) => line.text)).toEqual([
      'No summary delivered yet.',
      'Future summaries are off.',
    ]);
  });

  it('surfaces the sanitized failure and pending deliveries', () => {
    const lines = statusLines(
      {
        last_sent_at: new Date(Date.now() - 86400000).toISOString(),
        last_failure: { at: new Date().toISOString(), message: 'The mail server did not accept the summary.' },
        pending: 2,
        next_send_at: new Date(Date.now() + 3 * 86400000).toISOString(),
      },
      true,
    );

    expect(lines.map((line) => line.tone)).toEqual(['success', 'danger', 'neutral', 'neutral']);
    expect(lines[1].text).toContain('The mail server did not accept the summary.');
    expect(lines[2].text).toBe('2 deliveries are waiting for the queue worker.');
    expect(lines[3].text).toMatch(/^Next summary in 3 days\.$/);
  });
});

describe('initialTimezone', () => {
  it('prefers the saved timezone', () => {
    expect(initialTimezone('Europe/Paris', ['UTC'], 'Asia/Tokyo')).toBe('Europe/Paris');
  });

  it('falls back to a known browser timezone, then UTC', () => {
    expect(initialTimezone(null, ['UTC', 'Asia/Tokyo'], 'Asia/Tokyo')).toBe('Asia/Tokyo');
    expect(initialTimezone(null, ['UTC'], 'Nowhere/Else')).toBe('UTC');
  });
});
