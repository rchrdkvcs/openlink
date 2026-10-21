import { asFlag, hasPendingLogoChange, type QrEditorFields } from './qrForm';
import { EXPORT_SIZE_OPTIONS } from './qrOptions';
import { withQuery } from './qrUrls';
import type { ExportFormat, QrAppearance, QrCodeRecord } from './types';

export type DecodeStatus = 'readable' | 'mismatch' | 'unreadable' | 'unsupported' | 'unavailable';

export type FormatCheck = { status: DecodeStatus; decoded: string | null; reason?: string };

export type ReadabilityWarning = {
  code:
    | 'low_contrast'
    | 'inverted_colors'
    | 'transparent_background'
    | 'quiet_zone'
    | 'small_modules'
    | 'logo_obstruction';
  severity: 'warning' | 'notice';
  title: string;
  message: string;
};

export type ReadabilitySummary = {
  encoded_content: string;
  is_direct: boolean;
  payload_type: string | null;
  size: number;
  modules: number;
  quiet_zone: number;
  module_pixels: number;
  error_correction: string;
  contrast: number | null;
  short_link: {
    short_url: string;
    status: string;
    domain: string;
    domain_active: boolean;
    activates_at: string | null;
    expires_at: string | null;
    visit_limit: number | null;
    successful_visits: number;
    has_password: boolean;
  } | null;
};

export type ReadabilityResult = {
  size: number;
  formats: Record<ExportFormat, FormatCheck>;
  warnings: ReadabilityWarning[];
  summary: ReadabilitySummary;
};

export type ReadableFixes = { appearance: Partial<QrAppearance>; exportSize?: number };

export const READABLE_COLORS = { foreground_color: '#111827', background_color: '#ffffff' } as const;

export const READABLE_MARGIN = 4;

export const MIN_MODULE_PIXELS = 4;

const FIXABLE = new Set<ReadabilityWarning['code']>([
  'low_contrast',
  'inverted_colors',
  'quiet_zone',
  'small_modules',
  'logo_obstruction',
]);

type CheckFields = Pick<
  QrEditorFields,
  keyof QrAppearance | 'target_type' | 'short_link_id' | 'payload_type' | 'payload' | 'logo' | 'remove_logo'
>;

export function blockedReason(form: CheckFields, qr: Pick<QrCodeRecord, 'is_direct' | 'short_link_id'>): string | null {
  if (hasPendingLogoChange(form)) return 'Save the logo change to check it.';
  if (form.target_type !== (qr.is_direct ? 'direct' : 'short_link')) return 'Save the new target to check it.';
  if (form.target_type === 'short_link' && Number(form.short_link_id) !== qr.short_link_id) {
    return 'Save the new short link to check it.';
  }

  return null;
}

export function readabilityQuery(
  form: CheckFields,
  exportSize: number,
  version: number,
): Record<string, string | number> {
  const query: Record<string, string | number> = {
    size: exportSize,
    foreground_color: form.foreground_color,
    background_color: form.background_color,
    margin: form.margin,
    error_correction: form.error_correction,
    style: form.style,
    eye_style: form.eye_style,
    background_transparent: asFlag(form.background_transparent),
  };

  if (form.target_type === 'direct') {
    query.payload_type = form.payload_type;
    for (const [key, value] of Object.entries(form.payload)) {
      query[`payload[${key}]`] = typeof value === 'boolean' ? asFlag(value) : (value ?? '');
    }
  }

  query.v = version;

  return query;
}

export function readabilityUrl(token: string, form: CheckFields, exportSize: number, version: number): string {
  return withQuery(route('qr-codes.readability', token), readabilityQuery(form, exportSize, version));
}

export function isFixable(warnings: ReadabilityWarning[]): boolean {
  return warnings.some((warning) => warning.severity === 'warning' && FIXABLE.has(warning.code));
}

export function readableFixes(
  warnings: ReadabilityWarning[],
  form: Pick<QrAppearance, 'margin'>,
  summary: Pick<ReadabilitySummary, 'modules'>,
): ReadableFixes {
  const codes = new Set(
    warnings
      .filter((warning) => warning.severity === 'warning' && FIXABLE.has(warning.code))
      .map((warning) => warning.code),
  );
  const appearance: Partial<QrAppearance> = {};
  let margin = form.margin;

  if (codes.has('low_contrast') || codes.has('inverted_colors')) Object.assign(appearance, READABLE_COLORS);
  if (codes.has('quiet_zone')) margin = Math.max(margin, READABLE_MARGIN);
  if (codes.has('logo_obstruction')) {
    appearance.error_correction = 'high';
    margin = Math.min(margin, READABLE_MARGIN);
  }
  if (margin !== form.margin) appearance.margin = margin;

  if (!codes.has('small_modules')) return { appearance };

  return { appearance, exportSize: exportSizeFor(summary.modules + 2 * margin) };
}

export function exportSizeFor(totalModules: number): number {
  const sizes = EXPORT_SIZE_OPTIONS.map((option) => option.value);

  return sizes.find((size) => size / totalModules >= MIN_MODULE_PIXELS) ?? sizes[sizes.length - 1];
}

export const DECODE_LABELS: Record<DecodeStatus, { label: string; tone: 'success' | 'danger' | 'default' }> = {
  readable: { label: 'Decodes correctly', tone: 'success' },
  mismatch: { label: 'Decodes to other content', tone: 'danger' },
  unreadable: { label: 'Could not decode', tone: 'danger' },
  unsupported: { label: 'Format not checked', tone: 'default' },
  unavailable: { label: 'Check unavailable', tone: 'default' },
};

function formatDate(value: string): string {
  return new Date(value).toLocaleString(undefined, { dateStyle: 'medium', timeStyle: 'short' });
}

export function printRestrictions(summary: Pick<ReadabilitySummary, 'short_link' | 'is_direct'>): string[] {
  const link = summary.short_link;

  if (!link) return summary.is_direct ? ['Native content: changing it later means reprinting.'] : [];

  const restrictions: string[] = [];
  if (!link.domain_active) restrictions.push(`${link.domain} is not active, so scans will not resolve.`);
  if (link.status === 'disabled') restrictions.push('The short link is disabled.');
  if (link.status === 'archived') restrictions.push('The short link is archived.');
  if (link.activates_at) restrictions.push(`Starts resolving ${formatDate(link.activates_at)}.`);
  if (link.expires_at)
    restrictions.push(`${link.status === 'expired' ? 'Expired' : 'Expires'} ${formatDate(link.expires_at)}.`);
  if (link.visit_limit !== null)
    restrictions.push(`Visit limit: ${link.successful_visits} of ${link.visit_limit} used.`);
  if (link.has_password) restrictions.push('Visitors must enter a password.');

  return restrictions;
}
