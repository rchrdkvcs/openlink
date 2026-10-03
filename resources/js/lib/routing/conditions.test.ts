import { describe, expect, it } from 'vitest';

import {
  changeOperator,
  createRouting,
  hasValueInput,
  scalarValue,
  shareDescription,
  usesTimeOperators,
  valueInputType,
  valuePlaceholder,
  variantShare,
} from './index';
import { routing, schema } from './testRouting';

describe('conditions', () => {
  it('derives scalar defaults from the schema defaults, then the first value option', () => {
    const model = routing();

    expect(model.newCondition()).toEqual({ type: 'country', operator: 'is', value: 'FR' });
    expect(model.newCondition('device_type')).toEqual({ type: 'device_type', operator: 'is', value: 'desktop' });
    expect(model.newCondition('browser')).toEqual({ type: 'browser', operator: 'is', value: '' });
    expect(model.newCondition('day_of_week')).toEqual({
      type: 'day_of_week',
      operator: 'is',
      value: 'monday',
      timezone: 'Europe/Paris',
    });
  });

  it('uses time operators and windows for temporal conditions', () => {
    const model = routing();

    expect(model.newCondition('time_of_day')).toEqual({
      type: 'time_of_day',
      operator: 'between',
      value: { from: '09:00', to: '18:00' },
      timezone: 'Europe/Paris',
    });
    expect(model.newCondition('date_time')).toEqual({
      type: 'date_time',
      operator: 'after',
      value: '',
      timezone: 'Europe/Paris',
    });
    expect(model.operators(model.newCondition('date_time'))).toBe(schema.operators.time);
    expect(model.operators(model.newCondition('day_of_week'))).toBe(schema.operators.scalar);
  });

  it('reads operator groups and default windows from the schema', () => {
    const custom = {
      ...schema,
      conditionTypes: [
        ...schema.conditionTypes,
        {
          value: 'utm_campaign',
          label: 'Campaign',
          operatorGroup: 'time' as const,
          defaultOperator: 'between',
          defaultValue: { from: '07:00', to: '08:00' },
        },
      ],
    };
    const model = createRouting(custom, { timezone: 'UTC' });
    const first = model.newCondition('utm_campaign');
    const second = model.newCondition('utm_campaign');

    expect(first).toEqual({ type: 'utm_campaign', operator: 'between', value: { from: '07:00', to: '08:00' } });
    expect(first.value).not.toBe(second.value);
    expect(model.operators(first)).toBe(custom.operators.time);
    expect(usesTimeOperators(custom, 'utm_campaign')).toBe(true);
    expect(usesTimeOperators(custom, 'unknown')).toBe(false);
  });

  it('resets operator, value and timezone when the condition type changes', () => {
    const model = routing();
    const condition = model.newCondition('time_of_day');
    model.changeConditionType(condition, 'country');

    expect(condition).toEqual({ type: 'country', operator: 'is', value: 'FR', timezone: undefined });
  });

  it('switches between scalar and range values with the operator', () => {
    const condition = routing().newCondition('date_time');
    condition.value = '2026-01-01T10:00';
    changeOperator(condition, 'between');
    expect(condition.value).toEqual({ from: '', to: '' });

    condition.value = { from: '2026-01-01T10:00', to: '2026-01-02T10:00' };
    changeOperator(condition, 'before');
    expect(condition).toMatchObject({ operator: 'before', value: '2026-01-01T10:00' });
  });

  it('describes value inputs', () => {
    expect(hasValueInput({ type: 'country', operator: 'is_empty' })).toBe(false);
    expect(hasValueInput({ type: 'country', operator: 'is' })).toBe(true);
    expect(scalarValue({ type: 'country', operator: 'is', value: ['FR', 'BE'] })).toBe('FR, BE');
    expect(scalarValue({ type: 'country', operator: 'is', value: { from: 'a' } })).toBe('');
    expect(valueInputType('date_time')).toBe('datetime-local');
    expect(valueInputType('time_of_day')).toBe('time');
    expect(valueInputType('country')).toBe('text');
    expect(valuePlaceholder('language')).toBe('e.g. fr');
    expect(valuePlaceholder('browser')).toBe('Enter a value');
  });
});

describe('summaries', () => {
  it('summarises conditional and split rules', () => {
    const model = routing();
    const rule = model.ruleFromPreset('country');
    rule.conditions.push({ type: 'device_type', operator: 'is', value: 'mobile' });
    expect(model.summary(rule)).toBe('Country is FR + Device is Mobile → Choose a destination');

    rule.match_mode = 'any';
    rule.destination_url = 'https://example.fr';
    expect(model.summary(rule)).toBe('Country is FR / Device is Mobile → https://example.fr');

    const split = model.ruleFromPreset('split');
    split.variants[0].weight = 3;
    split.variants[1].weight = '1';
    expect(model.summary(split)).toBe('All visitors → 75% A, 25% B');
    expect(shareDescription(split)).toBe('Traffic split: A 75%, B 25%');

    split.variants.forEach((variant) => (variant.is_enabled = false));
    expect(model.summary(split)).toBe('All visitors → No active variants');
    expect(variantShare(split, split.variants[0])).toBe(0);
  });
});
