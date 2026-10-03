import type { RoutingRuleDraft, RoutingSchema } from '@/types/shortLinks';

import { createRouting } from './index';

export const schema: RoutingSchema = {
  conditionTypes: [
    { value: 'country', label: 'Country', operatorGroup: 'scalar' },
    { value: 'device_type', label: 'Device', operatorGroup: 'scalar' },
    { value: 'browser', label: 'Browser', operatorGroup: 'scalar' },
    {
      value: 'date_time',
      label: 'Date/time',
      operatorGroup: 'time',
      zoned: true,
      defaultOperator: 'after',
      defaultValue: '',
    },
    { value: 'day_of_week', label: 'Day', operatorGroup: 'scalar', zoned: true },
    {
      value: 'time_of_day',
      label: 'Time',
      operatorGroup: 'time',
      zoned: true,
      defaultOperator: 'between',
      defaultValue: { from: '09:00', to: '18:00' },
    },
  ],
  operators: {
    scalar: [
      { value: 'is', label: 'is' },
      { value: 'is_not', label: 'is not' },
      { value: 'is_empty', label: 'is empty' },
    ],
    time: [
      { value: 'before', label: 'before' },
      { value: 'after', label: 'after' },
      { value: 'between', label: 'between' },
    ],
  },
  valueOptions: {
    device_type: [
      { value: 'mobile', label: 'Mobile' },
      { value: 'desktop', label: 'Desktop' },
    ],
    day_of_week: [
      { value: 'monday', label: 'Monday' },
      { value: 'tuesday', label: 'Tuesday' },
    ],
  },
  defaults: { country: 'FR', device_type: 'desktop' },
  presets: [
    { kind: 'country', label: 'Country', description: '', conditionType: 'country', ruleType: 'conditional' },
    { kind: 'time', label: 'Time', description: '', conditionType: 'time_of_day', ruleType: 'conditional' },
    { kind: 'split', label: 'Split test', description: '', conditionType: 'custom', ruleType: 'split_test' },
    { kind: 'custom', label: 'Custom', description: '', conditionType: 'custom', ruleType: 'conditional' },
  ],
};

function counter() {
  let next = 0;
  return () => `id-${++next}`;
}

export function routing() {
  return createRouting(schema, { timezone: 'Europe/Paris', createId: counter() });
}

export function conditional(name: string): RoutingRuleDraft {
  return { ...routing().newRule('conditional', 'country'), name };
}
