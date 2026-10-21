import { relativeTime } from '@/lib/datetime';

export type SummaryStatus = {
  last_sent_at: string | null;
  last_failure: { at: string; message: string } | null;
  pending: number;
  next_send_at: string | null;
};

export type StatusLine = { tone: 'neutral' | 'success' | 'danger'; text: string };

export function statusLines(status: SummaryStatus, enabled: boolean): StatusLine[] {
  const lines: StatusLine[] = [];

  if (status.last_sent_at) {
    lines.push({ tone: 'success', text: `Last delivered ${relativeTime(status.last_sent_at)}.` });
  } else {
    lines.push({ tone: 'neutral', text: 'No summary delivered yet.' });
  }

  if (status.last_failure) {
    lines.push({
      tone: 'danger',
      text: `Last failure ${relativeTime(status.last_failure.at)}: ${status.last_failure.message}`,
    });
  }

  if (status.pending > 0) {
    lines.push({
      tone: 'neutral',
      text: `${status.pending} ${status.pending === 1 ? 'delivery is' : 'deliveries are'} waiting for the queue worker.`,
    });
  }

  if (enabled && status.next_send_at) {
    lines.push({ tone: 'neutral', text: `Next summary ${relativeTime(status.next_send_at)}.` });
  }

  if (!enabled) lines.push({ tone: 'neutral', text: 'Future summaries are off.' });

  return lines;
}

export function initialTimezone(saved: string | null, timezones: string[], browser?: string): string {
  if (saved) return saved;
  if (browser && timezones.includes(browser)) return browser;
  return 'UTC';
}
