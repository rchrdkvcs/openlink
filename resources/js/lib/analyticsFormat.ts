import type { ReportRange } from '@/lib/analytics';

const compact = new Intl.NumberFormat('en', { notation: 'compact', maximumFractionDigits: 1 });
const full = new Intl.NumberFormat('en');
const regionNames = new Intl.DisplayNames(['en'], { type: 'region' });
const languageNames = new Intl.DisplayNames(['en'], { type: 'language' });

export function formatCompact(value: number): string {
  return compact.format(value);
}

export function formatNumber(value: number): string {
  return full.format(value);
}

export function countryName(code: string): string {
  try {
    return regionNames.of(code.toUpperCase()) ?? code;
  } catch {
    return code;
  }
}

export function countryFlag(code: string): string {
  const upper = code.toUpperCase();
  if (!/^[A-Z]{2}$/.test(upper)) return '';
  return String.fromCodePoint(...[...upper].map((c) => 0x1f1e6 + c.charCodeAt(0) - 65));
}

export function languageName(code: string): string {
  try {
    return languageNames.of(code.toLowerCase()) ?? code;
  } catch {
    return code;
  }
}

export const OUTCOME_LABELS: Record<string, string> = {
  success: 'Successful',
  password_required: 'Password prompt',
  password_failed: 'Wrong password',
  expired: 'Expired link',
  disabled: 'Disabled link',
  scheduled: 'Not yet active',
  not_found: 'Not found',
  domain_unavailable: 'Domain unavailable',
  visit_limit_reached: 'Visit limit reached',
  archived: 'Archived link',
};

export const CHANNEL_LABELS: Record<string, string> = {
  direct: 'Direct / none',
  search: 'Search engines',
  social: 'Social networks',
  video: 'Video platforms',
  email: 'Email',
  messaging: 'Messaging apps',
  ai: 'AI assistants',
  referral: 'Other websites',
};

export const DEVICE_LABELS: Record<string, string> = {
  desktop: 'Desktop',
  mobile: 'Mobile',
  tablet: 'Tablet',
  bot: 'Bot',
  unknown: 'Unknown',
};

export function formatBucket(bucket: string, unit: ReportRange['bucket'], style: 'short' | 'long' = 'short'): string {
  if (unit === 'hour') {
    const [date, time] = bucket.split(' ');
    const day = new Date(`${date}T00:00:00`);
    const dayLabel = day.toLocaleDateString('en', { month: 'short', day: 'numeric' });
    return style === 'short' ? time : `${dayLabel}, ${time}`;
  }

  if (unit === 'month') {
    const day = new Date(`${bucket}-01T00:00:00`);
    return day.toLocaleDateString('en', { month: 'short', year: style === 'short' ? '2-digit' : 'numeric' });
  }

  const day = new Date(`${bucket}T00:00:00`);
  return day.toLocaleDateString('en', {
    month: 'short',
    day: 'numeric',
    ...(style === 'long' ? { year: 'numeric', weekday: 'short' } : {}),
  });
}
