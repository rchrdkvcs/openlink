import type { RoutingRuleDraft, RoutingVariantDraft } from '@/types/shortLinks';

export type RuleListChange = { rules: RoutingRuleDraft[]; focus: number | null };

export type IdFactory = () => string;

function jsonClone<T>(value: T): T {
  return JSON.parse(JSON.stringify(value)) as T;
}

export function randomId(): string {
  return Math.random().toString(36).slice(2, 10);
}

export function newVariant(name: string, createId: IdFactory): RoutingVariantDraft {
  return { client_id: createId(), name, is_enabled: true, destination_url: '', weight: 50 };
}

export function addVariant(rule: RoutingRuleDraft, createId: IdFactory): void {
  rule.variants.push(newVariant(String.fromCharCode(65 + rule.variants.length), createId));
}

export function setRuleType(rule: RoutingRuleDraft, type: RoutingRuleDraft['type'], createId: IdFactory): void {
  rule.type = type;
  if (type === 'split_test' && rule.variants.length === 0) {
    rule.variants = [newVariant('A', createId), newVariant('B', createId)];
  }
}

export function appendRule(rules: RoutingRuleDraft[], rule: RoutingRuleDraft): RuleListChange {
  return { rules: [...rules, rule], focus: rules.length };
}

export function duplicateRule(rules: RoutingRuleDraft[], index: number, createId: IdFactory): RuleListChange {
  const copy = jsonClone(rules[index]);
  copy.id = undefined;
  copy.client_id = createId();
  copy.name = `${copy.name} copy`;
  copy.variants = copy.variants.map((variant) => ({ ...variant, id: undefined, client_id: createId() }));

  return { rules: [...rules.slice(0, index + 1), copy, ...rules.slice(index + 1)], focus: index + 1 };
}

export function removeRule(rules: RoutingRuleDraft[], index: number): RuleListChange {
  const remaining = rules.filter((_, position) => position !== index);

  return { rules: remaining, focus: remaining.length ? Math.min(index, remaining.length - 1) : null };
}

export function moveRule(rules: RoutingRuleDraft[], index: number, direction: -1 | 1): RuleListChange | null {
  const target = index + direction;
  if (target < 0 || target >= rules.length) return null;

  const reordered = [...rules];
  [reordered[index], reordered[target]] = [reordered[target], reordered[index]];

  return { rules: reordered, focus: target };
}

export function cloneRules(rules: RoutingRuleDraft[]): RoutingRuleDraft[] {
  return rules.map((rule) => ({
    ...rule,
    conditions: jsonClone(rule.conditions ?? []),
    variants: (rule.variants ?? []).map((variant) => ({ ...variant })),
  }));
}
