import type { ComputedRef, InjectionKey } from 'vue';

export type SelectValue = string | number | boolean | null | undefined;

// Radix reserves the empty string. Encode values so optional choices and numeric
// IDs round-trip without conflating 0, "0", false, or the empty choice.
export function encodeSelectValue(value: SelectValue): string {
  return JSON.stringify(value ?? null);
}

export function decodeSelectValue(value: string): SelectValue {
  return JSON.parse(value) as SelectValue;
}

export const fieldContextKey: InjectionKey<
  ComputedRef<{
    labelId?: string;
    descriptionId?: string;
    invalid: boolean;
  }>
> = Symbol('field');
