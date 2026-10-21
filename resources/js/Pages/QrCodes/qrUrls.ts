import { asFlag, type LogoState } from './qrForm';
import type { ExportFormat, QrAppearance } from './types';

const PREVIEW_SIZE = 512;

export function withQuery(base: string, params: Record<string, string | number>): string {
  const query = new URLSearchParams(Object.entries(params).map(([key, value]) => [key, String(value)])).toString();

  return query ? `${base}?${query}` : base;
}

export function previewLogoQuery(logo: Partial<LogoState>): Record<string, number> {
  if (logo.logo_template_id) return { logo_template_id: logo.logo_template_id };
  return logo.remove_logo ? { remove_logo: 1 } : {};
}

export function previewQuery(
  appearance: QrAppearance & Partial<LogoState>,
  version: number,
): Record<string, string | number> {
  return {
    size: PREVIEW_SIZE,
    foreground_color: appearance.foreground_color,
    background_color: appearance.background_color,
    margin: appearance.margin,
    error_correction: appearance.error_correction,
    style: appearance.style,
    eye_style: appearance.eye_style,
    background_transparent: asFlag(appearance.background_transparent),
    ...previewLogoQuery(appearance),
    v: version,
  };
}

export function thumbnailUrl(token: string): string {
  return route('qr-codes.preview', token);
}

export function previewUrl(token: string, appearance: QrAppearance & Partial<LogoState>, version: number): string {
  return withQuery(thumbnailUrl(token), previewQuery(appearance, version));
}

export function exportUrl(token: string, format: ExportFormat, size?: number): string {
  return withQuery(route('qr-codes.export', [token, format]), size === undefined ? {} : { size });
}

export function downloadQrCode(token: string, format: ExportFormat, size?: number): void {
  window.location.href = exportUrl(token, format, size);
}
