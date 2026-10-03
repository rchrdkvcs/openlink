import { describe, expect, it } from 'vitest';

import { firstInvalidRule, isRoutingErrorKey, ruleErrors } from './index';
import { conditional, routing } from './testRouting';

describe('rules', () => {
  it('builds rules from presets', () => {
    const model = routing();

    expect(model.ruleFromPreset('country')).toMatchObject({ name: 'Country routing', type: 'conditional' });
    expect(model.ruleFromPreset('custom')).toMatchObject({ name: 'Custom routing', conditions: [] });
    expect(model.ruleFromPreset('missing').name).toBe('Country routing');

    const split = model.ruleFromPreset('split');
    expect(split).toMatchObject({ name: 'Split test', type: 'split_test', conditions: [] });
    expect(split.variants.map((variant) => [variant.name, variant.weight])).toEqual([
      ['A', 50],
      ['B', 50],
    ]);
  });

  it('appends, duplicates, moves and removes rules with the focus to open', () => {
    const model = routing();
    const added = model.addPreset([conditional('One')], 'time');
    expect(added.focus).toBe(1);

    const source = { ...model.ruleFromPreset('split'), id: 7, name: 'Test' };
    source.variants[0].id = 3;
    const duplicated = model.duplicate([source], 0);
    expect(duplicated.focus).toBe(1);
    expect(duplicated.rules[1]).toMatchObject({ id: undefined, name: 'Test copy' });
    expect(duplicated.rules[1].variants[0].id).toBeUndefined();
    expect(duplicated.rules[1].variants[0].client_id).not.toBe(source.variants[0].client_id);
    expect(duplicated.rules[0]).toBe(source);

    const rules = [conditional('One'), conditional('Two'), conditional('Three')];
    expect(model.move(rules, 0, -1)).toBeNull();
    expect(model.move(rules, 2, 1)).toBeNull();
    const moved = model.move(rules, 0, 1)!;
    expect(moved.rules.map((rule) => rule.name)).toEqual(['Two', 'One', 'Three']);
    expect(moved.focus).toBe(1);

    expect(model.remove(rules, 2)).toMatchObject({ focus: 1 });
    expect(model.remove([rules[0]], 0)).toEqual({ rules: [], focus: null });
  });

  it('adds default variants when switching to a split test', () => {
    const model = routing();
    const rule = conditional('One');
    model.setRuleType(rule, 'split_test');
    expect(rule.variants.map((variant) => variant.name)).toEqual(['A', 'B']);

    model.addVariant(rule);
    expect(rule.variants[2].name).toBe('C');
    model.setRuleType(rule, 'conditional');
    model.setRuleType(rule, 'split_test');
    expect(rule.variants).toHaveLength(3);
  });
});

describe('errors', () => {
  const errors = { routing_rules: 'Too many', 'routing_rules.1.destination_url': 'Required', 'routing_rules.10.x': '' };

  it('matches rule errors by index', () => {
    expect(ruleErrors(errors, 1)).toEqual([['routing_rules.1.destination_url', 'Required']]);
    expect(ruleErrors(errors, 10)).toEqual([]);
    expect(ruleErrors(undefined, 0)).toEqual([]);
    expect(firstInvalidRule([conditional('a'), conditional('b')], errors)).toBe(1);
    expect(firstInvalidRule([conditional('a')], errors)).toBeNull();
    expect(isRoutingErrorKey('routing_rules.0.name')).toBe(true);
    expect(isRoutingErrorKey('destination_url')).toBe(false);
  });
});
