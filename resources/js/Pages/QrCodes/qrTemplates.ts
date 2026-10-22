import type { SelectOption } from '@/lib/controls';

import { asFlag, type QrEditorFields, type toUpdatePayload } from './qrForm';
import type { QrAppearance, QrCodeTemplate } from './types';

export type TemplateFields = QrAppearance & Pick<QrEditorFields, 'logo' | 'remove_logo' | 'template_id'>;

type UpdatePayload = ReturnType<typeof toUpdatePayload>;

const APPEARANCE_KEYS = [
  'foreground_color',
  'background_color',
  'background_transparent',
  'margin',
  'error_correction',
  'style',
  'eye_style',
] as const;

export function templateFields(template: QrCodeTemplate): TemplateFields {
  return {
    foreground_color: template.foreground_color,
    background_color: template.background_color,
    background_transparent: template.background_transparent,
    margin: template.margin,
    error_correction: template.error_correction,
    style: template.style,
    eye_style: template.eye_style,
    logo: null,
    remove_logo: false,
    template_id: template.id,
  };
}

export function withTemplateDesign(payload: UpdatePayload, template: QrCodeTemplate | null): Partial<UpdatePayload> {
  if (!template || payload.template_id !== template.id) return payload;

  const unchanged = APPEARANCE_KEYS.filter((key) => {
    const value = key === 'background_transparent' ? asFlag(template.background_transparent) : template[key];
    return String(payload[key]) === String(value);
  });

  return Object.fromEntries(
    Object.entries(payload).filter(([key]) => !(unchanged as readonly string[]).includes(key)),
  ) as Partial<UpdatePayload>;
}

export function templateOptions(templates: QrCodeTemplate[]): SelectOption<number>[] {
  return templates.map((template) => ({ value: template.id, label: template.name }));
}
