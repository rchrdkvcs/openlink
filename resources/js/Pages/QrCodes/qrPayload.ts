import type { PayloadDescriptors, PayloadField, PayloadValue, PayloadValues } from './types';

export const DEFAULT_PAYLOAD_TYPE = 'url';

export function payloadDefaults(type: string, descriptors: PayloadDescriptors): PayloadValues {
  return { ...(descriptors[type]?.defaults ?? descriptors.raw?.defaults ?? { content: '' }) };
}

export function payloadHint(type: string, descriptors: PayloadDescriptors): string {
  return descriptors[type]?.hint ?? descriptors.raw?.hint ?? '';
}

export function payloadFields(type: string, descriptors: PayloadDescriptors): PayloadField[] {
  return descriptors[type]?.fields ?? descriptors.raw?.fields ?? [];
}

export function isFieldDisabled(field: PayloadField, payload: PayloadValues): boolean {
  return field.disabledWhen ? payload[field.disabledWhen.key] === field.disabledWhen.value : false;
}

export function payloadTypeOptions(payloadTypes: Record<string, string>): { value: string; label: string }[] {
  return Object.entries(payloadTypes).map(([value, label]) => ({ value, label }));
}

export function flagValue(value: PayloadValue | undefined): boolean {
  return value === true || value === 1 || value === '1';
}

export function inputValue(value: PayloadValue | undefined): string | number | null {
  return typeof value === 'boolean' ? String(value) : (value ?? null);
}

export function textValue(value: PayloadValue | undefined): string | null {
  return value === null || value === undefined ? null : String(value);
}
