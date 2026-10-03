import { DEFAULT_PAYLOAD_TYPE, payloadDefaults } from './qrPayload';
import type { PayloadDescriptors, PayloadValues, TargetState, TargetType } from './types';

export type PayloadMode = 'create' | 'update';

export type TargetForm = TargetState & {
  errors: Partial<Record<string, string>>;
  clearErrors: (...fields: never[]) => unknown;
};

type TargetPayload =
  | { name: string; short_link_id: string | number }
  | { name: string; short_link_id?: null; payload_type: string; payload: PayloadValues };

export function initialTarget(
  record: { is_direct: boolean; short_link_id: number | null; payload_type: string | null; payload: object | null },
  descriptors: PayloadDescriptors,
): TargetState {
  const payloadType = record.payload_type ?? DEFAULT_PAYLOAD_TYPE;

  return {
    target_type: record.is_direct ? 'direct' : 'short_link',
    short_link_id: record.short_link_id ?? '',
    payload_type: payloadType,
    payload: { ...payloadDefaults(payloadType, descriptors), ...(record.payload as PayloadValues | null) },
  };
}

export function blankTarget(descriptors: PayloadDescriptors, targetType: TargetType = 'short_link'): TargetState {
  return {
    target_type: targetType,
    short_link_id: '',
    payload_type: DEFAULT_PAYLOAD_TYPE,
    payload: payloadDefaults(DEFAULT_PAYLOAD_TYPE, descriptors),
  };
}

export function isPayloadErrorKey(key: string): boolean {
  return key === 'payload_type' || key === 'short_link_id' || key === 'payload' || key.startsWith('payload.');
}

export function applyPayloadType(state: TargetState, type: unknown, descriptors: PayloadDescriptors): boolean {
  const next = String(type ?? DEFAULT_PAYLOAD_TYPE);
  if (state.target_type === 'direct' && next === state.payload_type) return false;

  state.target_type = 'direct';
  if (next !== state.payload_type) {
    state.payload_type = next;
    state.payload = payloadDefaults(next, descriptors);
  }

  return true;
}

export function setPayloadType(form: TargetForm, type: unknown, descriptors: PayloadDescriptors): void {
  if (!applyPayloadType(form, type, descriptors)) return;

  const keys = Object.keys(form.errors).filter(isPayloadErrorKey);
  if (keys.length > 0) form.clearErrors(...(keys as never[]));
}

export function toTargetPayload(data: TargetState & { name: string }, mode: PayloadMode): TargetPayload {
  if (data.target_type === 'short_link') return { name: data.name, short_link_id: data.short_link_id };

  const direct = { payload_type: data.payload_type, payload: data.payload };

  return mode === 'update' ? { name: data.name, short_link_id: null, ...direct } : { name: data.name, ...direct };
}
