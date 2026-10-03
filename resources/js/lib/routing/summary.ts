import type { RoutingCondition, RoutingRuleDraft, RoutingSchema, RoutingVariantDraft } from '@/types/shortLinks';

import { conditionValueOptions, hasValueInput, isRange } from './conditions';

export function conditionLabel(schema: RoutingSchema, type: string): string {
  return schema.conditionTypes.find((option) => option.value === type)?.label ?? type;
}

export function formatConditionValue(schema: RoutingSchema, condition: RoutingCondition): string {
  if (!hasValueInput(condition)) return '';
  if (isRange(condition.value)) return `${condition.value.from ?? ''}-${condition.value.to ?? ''}`;

  const value = Array.isArray(condition.value) ? condition.value.join(', ') : condition.value || '';

  return conditionValueOptions(schema, condition)?.find((option) => option.value === value)?.label ?? value;
}

function weightOf(variant: RoutingVariantDraft): number {
  return variant.is_enabled ? Math.max(0, Number(variant.weight) || 0) : 0;
}

export function variantShare(rule: RoutingRuleDraft, variant: RoutingVariantDraft): number {
  const total = rule.variants.reduce((sum, entry) => sum + weightOf(entry), 0);

  return total ? Math.round((weightOf(variant) / total) * 100) : 0;
}

export function activeVariants(rule: RoutingRuleDraft): RoutingVariantDraft[] {
  return rule.variants.filter((variant) => variant.is_enabled && variantShare(rule, variant) > 0);
}

export function shareDescription(rule: RoutingRuleDraft): string {
  const active = activeVariants(rule);

  return active.length
    ? `Traffic split: ${active.map((variant) => `${variant.name} ${variantShare(rule, variant)}%`).join(', ')}`
    : 'No active variants';
}

export function ruleName(rule: RoutingRuleDraft): string {
  return rule.name || 'Untitled routing rule';
}

export function conditionsLead(rule: RoutingRuleDraft): string {
  if (rule.conditions.length === 0) return 'every visitor';
  if (rule.conditions.length === 1) return 'the visitor matches';

  return rule.match_mode === 'all' ? 'the visitor matches all of these' : 'the visitor matches any of these';
}

function conditionSummary(schema: RoutingSchema, condition: RoutingCondition): string {
  const operator = condition.operator.replaceAll('_', ' ');

  return `${conditionLabel(schema, condition.type)} ${operator} ${formatConditionValue(schema, condition)}`.trim();
}

export function routingRuleSummary(schema: RoutingSchema, rule: RoutingRuleDraft): string {
  const audience = rule.conditions.length
    ? rule.conditions
        .map((condition) => conditionSummary(schema, condition))
        .join(rule.match_mode === 'all' ? ' + ' : ' / ')
    : 'All visitors';

  if (rule.type === 'split_test') {
    const variants = rule.variants
      .filter((variant) => variant.is_enabled)
      .map((variant) => `${variantShare(rule, variant)}% ${variant.name}`)
      .join(', ');

    return `${audience} → ${variants || 'No active variants'}`;
  }

  return `${audience} → ${rule.destination_url || 'Choose a destination'}`;
}
