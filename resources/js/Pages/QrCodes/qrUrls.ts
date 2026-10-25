import { asFlag, type QrEditorFields } from './qrForm';
import type { ExportFormat, QrAppearance } from './types';

const PREVIEW_SIZE = 512;

export function withQuery(base: string, params: Record<string, string | number>): string {
  const query = new URLSearchParams(Object.entries(params).map(([key, value]) => [key, String(value)])).toString();

  return query ? `${base}?${query}` : base;
}

type PreviewDesign = QrAppearance & Partial<Pick<QrEditorFields, 'remove_logo' | 'template_id'>>;

function previewDesignQuery(design: Partial<PreviewDesign>): Record<string, number> {
  return {
    ...(design.template_id ? { template_id: design.template_id } : {}),
    ...(design.remove_logo ? { remove_logo: 1 } : {}),
  };
}

export function previewQuery(appearance: PreviewDesign, version: number): Record<string, string | number> {
  return {
    size: PREVIEW_SIZE,
    foreground_color: appearance.foreground_color,
    background_color: appearance.background_color,
    margin: appearance.margin,
    error_correction: appearance.error_correction,
    style: appearance.style,
    eye_style: appearance.eye_style,
    background_transparent: asFlag(appearance.background_transparent),
    ...previewDesignQuery(appearance),
    v: version,
  };
}

export function thumbnailUrl(token: string): string {
  return route('qr-codes.preview', token);
}

export function previewUrl(token: string, appearance: PreviewDesign, version: number): string {
  return withQuery(thumbnailUrl(token), previewQuery(appearance, version));
}

function exportUrl(token: string, format: ExportFormat, size?: number): string {
  return withQuery(route('qr-codes.export', [token, format]), size === undefined ? {} : { size });
}

export function downloadQrCode(token: string, format: ExportFormat, size?: number): void {
  window.location.href = exportUrl(token, format, size);
}
