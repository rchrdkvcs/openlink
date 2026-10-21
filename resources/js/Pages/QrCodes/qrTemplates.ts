import type { SelectOption } from '@/lib/controls';

import type { QrEditorFields } from './qrForm';
import type { QrAppearance, QrCodeTemplate } from './types';

export type TemplateFields = QrAppearance & Pick<QrEditorFields, 'logo' | 'remove_logo' | 'logo_template_id'>;

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
    remove_logo: !template.has_logo,
    logo_template_id: template.has_logo ? template.id : null,
  };
}

export function templateOptions(templates: QrCodeTemplate[]): SelectOption<number>[] {
  return templates.map((template) => ({
    value: template.id,
    label: template.name,
  }));
}
