import type { RoutingRuleDraft } from '@/types/shortLinks';

export type FormErrors = Partial<Record<string, string | undefined>>;

const ROUTING_ERROR_KEY = 'routing_rules';

export function isRoutingErrorKey(key: string): boolean {
  return key.startsWith(ROUTING_ERROR_KEY);
}

export function ruleErrors(errors: FormErrors | undefined, index: number): [string, string][] {
  const prefix = `${ROUTING_ERROR_KEY}.${index}.`;

  return Object.entries(errors ?? {}).filter(
    (entry): entry is [string, string] => entry[0].startsWith(prefix) && Boolean(entry[1]),
  );
}

export function firstInvalidRule(rules: RoutingRuleDraft[], errors: FormErrors | undefined): number | null {
  const index = rules.findIndex((_, position) => ruleErrors(errors, position).length > 0);

  return index >= 0 ? index : null;
}
