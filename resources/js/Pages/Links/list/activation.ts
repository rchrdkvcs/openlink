import type { ShortLink } from '@/types/shortLinks';

type Schedulable = Pick<ShortLink, 'status' | 'activates_at'>;

export function activationTime(link: Schedulable): number | null {
  if (link.status !== 'scheduled' || !link.activates_at) return null;

  const time = new Date(link.activates_at).getTime();

  return Number.isNaN(time) ? null : time;
}

export function formatCountdown(milliseconds: number): string {
  const seconds = Math.max(0, Math.ceil(milliseconds / 1000));
  const days = Math.floor(seconds / 86400);
  const hours = Math.floor((seconds % 86400) / 3600);
  const minutes = Math.floor((seconds % 3600) / 60);
  const secs = seconds % 60;

  if (days > 0) return `${days}d ${hours}h`;
  if (hours > 0) return `${hours}h ${minutes}m`;
  if (minutes > 0) return `${minutes}m ${secs}s`;

  return `${secs}s`;
}
