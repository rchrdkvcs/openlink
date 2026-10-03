import type { RoutingCondition, RoutingOption, RoutingRuleDraft, RoutingSchema, RoutingVariantDraft } from './types';

export function conditionLabel(schema: RoutingSchema, type: string) {
  return schema.conditionTypes.find((option) => option.value === type)?.label ?? type;
}

export function conditionValueOptions(schema: RoutingSchema, condition: RoutingCondition): RoutingOption[] | null {
  return schema.valueOptions[condition.type] ?? null;
}

export function formatConditionValue(schema: RoutingSchema, condition: RoutingCondition) {
  if (condition.operator === 'is_empty' || condition.operator === 'is_not_empty') return '';
  if (typeof condition.value === 'object' && condition.value && !Array.isArray(condition.value)) {
    return `${condition.value.from ?? ''}-${condition.value.to ?? ''}`;
  }
  const value = Array.isArray(condition.value) ? condition.value.join(', ') : condition.value || '';
  return conditionValueOptions(schema, condition)?.find((option) => option.value === value)?.label ?? value;
}

export function variantShare(rule: RoutingRuleDraft, variant: RoutingVariantDraft) {
  const total = rule.variants.reduce(
    (sum, entry) => sum + (entry.is_enabled ? Math.max(0, Number(entry.weight) || 0) : 0),
    0,
  );
  return variant.is_enabled && total ? Math.round((Math.max(0, Number(variant.weight) || 0) / total) * 100) : 0;
}

export function routingRuleSummary(schema: RoutingSchema, rule: RoutingRuleDraft) {
  const conditionSummary = rule.conditions.length
    ? rule.conditions
        .map((condition) =>
          `${conditionLabel(schema, condition.type)} ${condition.operator.replaceAll('_', ' ')} ${formatConditionValue(schema, condition)}`.trim(),
        )
        .join(rule.match_mode === 'all' ? ' + ' : ' / ')
    : 'All visitors';

  if (rule.type === 'split_test') {
    const variants = rule.variants
      .filter((variant) => variant.is_enabled)
      .map((variant) => `${variantShare(rule, variant)}% ${variant.name}`)
      .join(', ');
    return `${conditionSummary} → ${variants || 'No active variants'}`;
  }

  return `${conditionSummary} → ${rule.destination_url || 'Choose a destination'}`;
}
