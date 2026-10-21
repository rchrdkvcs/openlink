import { initialTarget, toTargetPayload } from './qrTarget';
import type { PayloadDescriptors, QrAppearance, QrCodeRecord, TargetState } from './types';

export type QrEditorFields = TargetState &
  QrAppearance & {
    name: string;
    size: number;
    logo: File | null;
    remove_logo: boolean;
    logo_template_id: number | null;
  };

export type LogoState = Pick<QrEditorFields, 'logo' | 'remove_logo'> & { logo_template_id?: number | null };

export function asFlag(value: boolean): 0 | 1 {
  return value ? 1 : 0;
}

export function initialEditorFields(qr: QrCodeRecord, descriptors: PayloadDescriptors): QrEditorFields {
  return {
    name: qr.name,
    ...initialTarget(qr, descriptors),
    size: qr.size,
    foreground_color: qr.foreground_color,
    background_color: qr.background_color,
    margin: qr.margin,
    error_correction: qr.error_correction,
    style: qr.style,
    eye_style: qr.eye_style,
    background_transparent: qr.background_transparent,
    logo: null,
    remove_logo: false,
    logo_template_id: null,
  };
}

export function toUpdatePayload(data: QrEditorFields) {
  return {
    ...toTargetPayload(data, 'update'),
    size: data.size,
    foreground_color: data.foreground_color,
    background_color: data.background_color,
    margin: data.margin,
    error_correction: data.error_correction,
    style: data.style,
    eye_style: data.eye_style,
    logo: data.logo,
    logo_template_id: data.logo_template_id,
    _method: 'patch',
    background_transparent: asFlag(data.background_transparent),
    remove_logo: asFlag(data.remove_logo),
  };
}

export function hasPendingLogoChange(data: LogoState): boolean {
  return data.logo !== null || data.remove_logo || (data.logo_template_id ?? null) !== null;
}

export function showsLogo(hasSavedLogo: boolean, data: LogoState): boolean {
  return (hasSavedLogo && !data.remove_logo) || data.logo !== null || (data.logo_template_id ?? null) !== null;
}

export function logoLabel(hasSavedLogo: boolean, data: LogoState): string {
  if (data.logo) return data.logo.name;
  return showsLogo(hasSavedLogo, data) ? 'Replace logo' : 'Upload logo';
}
