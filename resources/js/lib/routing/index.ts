import type { RoutingCondition, RoutingRuleDraft, RoutingSchema } from '@/types/shortLinks';

import { changeConditionType, conditionValueOptions, newCondition, operatorsFor } from './conditions';
import {
  addVariant,
  appendRule,
  duplicateRule,
  type IdFactory,
  moveRule,
  newVariant,
  randomId,
  removeRule,
  type RuleListChange,
  setRuleType,
} from './rules';
import { conditionLabel, routingRuleSummary } from './summary';

export * from './conditions';
export * from './errors';
export * from './rules';
export * from './summary';

export type RoutingOptions = { timezone?: string; createId?: IdFactory };

function browserTimezone(): string {
  return Intl.DateTimeFormat().resolvedOptions().timeZone || 'UTC';
}

export function createRouting(schema: RoutingSchema, options: RoutingOptions = {}) {
  const timezone = options.timezone ?? browserTimezone();
  const createId = options.createId ?? randomId;
  const defaultConditionType = schema.conditionTypes[0]?.value ?? 'country';

  function condition(type = defaultConditionType): RoutingCondition {
    return newCondition(schema, type, timezone);
  }

  function rule(type: RoutingRuleDraft['type'], conditionType: string): RoutingRuleDraft {
    const custom = conditionType === 'custom';
    const name =
      type === 'split_test'
        ? 'Split test'
        : custom
          ? 'Custom routing'
          : `${conditionLabel(schema, conditionType)} routing`;

    return {
      client_id: createId(),
      name,
      type,
      is_enabled: true,
      match_mode: 'all',
      conditions: custom ? [] : [condition(conditionType)],
      destination_url: '',
      variants: type === 'split_test' ? [newVariant('A', createId), newVariant('B', createId)] : [],
    };
  }

  function ruleFromPreset(kind: string): RoutingRuleDraft {
    const preset = schema.presets.find((entry) => entry.kind === kind) ?? schema.presets[0];

    return rule(preset.ruleType, preset.conditionType);
  }

  return {
    schema,
    label: (type: string) => conditionLabel(schema, type),
    summary: (target: RoutingRuleDraft) => routingRuleSummary(schema, target),
    operators: (target: RoutingCondition) => operatorsFor(schema, target),
    valueOptions: (target: RoutingCondition) => conditionValueOptions(schema, target),
    newCondition: condition,
    newRule: rule,
    ruleFromPreset,
    changeConditionType: (target: RoutingCondition, type: string) =>
      changeConditionType(schema, target, type, timezone),
    addPreset: (rules: RoutingRuleDraft[], kind: string): RuleListChange => appendRule(rules, ruleFromPreset(kind)),
    duplicate: (rules: RoutingRuleDraft[], index: number) => duplicateRule(rules, index, createId),
    remove: removeRule,
    move: moveRule,
    setRuleType: (target: RoutingRuleDraft, type: RoutingRuleDraft['type']) => setRuleType(target, type, createId),
    addVariant: (target: RoutingRuleDraft) => addVariant(target, createId),
  };
}

export type Routing = ReturnType<typeof createRouting>;
