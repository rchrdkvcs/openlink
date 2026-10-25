import { hasPendingLogoChange, type QrEditorFields } from './qrForm';
import { EXPORT_SIZE_OPTIONS } from './qrOptions';
import type { ExportFormat, QrAppearance, QrCodeRecord } from './types';

export type DecodeStatus = 'readable' | 'mismatch' | 'unreadable' | 'unsupported' | 'unavailable';

type FormatCheck = {
  status: DecodeStatus;
  decoded: string | null;
  reason?: string;
  derived_from?: ExportFormat;
};

export type ReadabilityWarning = {
  code:
    | 'low_contrast'
    | 'inverted_colors'
    | 'transparent_background'
    | 'quiet_zone'
    | 'small_modules'
    | 'small_print_modules'
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
  min_module_pixels: number;
  print_width_mm: number | null;
  module_mm: number | null;
  min_module_mm: number;
  min_print_width_mm: number;
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

export type ReadabilityRequest = { url: string; body: Record<string, unknown>; version: number };

const READABLE_COLORS = { foreground_color: '#111827', background_color: '#ffffff' } as const;

const READABLE_MARGIN = 4;

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

type FixableFields = Pick<QrAppearance, 'margin' | 'error_correction' | 'foreground_color' | 'background_color'>;

export function blockedReason(form: CheckFields, qr: Pick<QrCodeRecord, 'is_direct' | 'short_link_id'>): string | null {
  if (hasPendingLogoChange(form)) return 'Save the logo change to check it.';
  if (form.target_type !== (qr.is_direct ? 'direct' : 'short_link')) return 'Save the new target to check it.';
  if (form.target_type === 'short_link' && Number(form.short_link_id) !== qr.short_link_id) {
    return 'Save the new short link to check it.';
  }

  return null;
}

export function readabilityBody(
  form: CheckFields,
  exportSize: number,
  printWidthMm: number | null,
): Record<string, unknown> {
  const body: Record<string, unknown> = {
    size: exportSize,
    print_width_mm: printWidthMm,
    foreground_color: form.foreground_color,
    background_color: form.background_color,
    margin: form.margin,
    error_correction: form.error_correction,
    style: form.style,
    eye_style: form.eye_style,
    background_transparent: form.background_transparent,
  };

  if (form.target_type === 'direct') {
    body.payload_type = form.payload_type;
    body.payload = { ...form.payload };
  }

  return body;
}

export function readabilityRequest(
  token: string,
  form: CheckFields,
  exportSize: number,
  printWidthMm: number | null,
  version: number,
): ReadabilityRequest {
  return { url: route('qr-codes.readability', token), body: readabilityBody(form, exportSize, printWidthMm), version };
}

export function readableFixes(
  warnings: ReadabilityWarning[],
  draft: FixableFields,
  exportSize: number,
  summary: Pick<ReadabilitySummary, 'modules' | 'min_module_pixels'>,
): ReadableFixes {
  const codes = new Set(
    warnings
      .filter((warning) => warning.severity === 'warning' && FIXABLE.has(warning.code))
      .map((warning) => warning.code),
  );
  const target: Partial<QrAppearance> = {};
  let margin = draft.margin;

  if (codes.has('low_contrast') || codes.has('inverted_colors')) Object.assign(target, READABLE_COLORS);
  if (codes.has('quiet_zone')) margin = Math.max(margin, READABLE_MARGIN);
  if (codes.has('logo_obstruction')) {
    target.error_correction = 'high';
    margin = Math.min(margin, READABLE_MARGIN);
  }
  target.margin = margin;

  const appearance = Object.fromEntries(
    Object.entries(target).filter(([key, value]) => draft[key as keyof FixableFields] !== value),
  ) as Partial<QrAppearance>;

  if (!codes.has('small_modules')) return { appearance };

  const size = exportSizeFor(summary.modules + 2 * margin, summary.min_module_pixels);

  return size === exportSize ? { appearance } : { appearance, exportSize: size };
}

export function hasFixes(fixes: ReadableFixes): boolean {
  return Object.keys(fixes.appearance).length > 0 || fixes.exportSize !== undefined;
}

export function exportSizeFor(totalModules: number, minModulePixels: number): number {
  const sizes = EXPORT_SIZE_OPTIONS.map((option) => option.value);

  return sizes.find((size) => size / totalModules >= minModulePixels) ?? sizes[sizes.length - 1];
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
