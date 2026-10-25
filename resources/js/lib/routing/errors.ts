import type { RoutingRuleDraft } from '@/types/shortLinks';

export type FormErrors = Partial<Record<string, string | undefined>>;

const ROUTING_ERROR_KEY = 'routing_rules';

export function ruleErrors(errors: FormErrors | undefined, index: number): [string, string][] {
  const prefix = `${ROUTING_ERROR_KEY}.${index}.`;

  return Object.entries(errors ?? {}).filter(
    (entry): entry is [string, string] => entry[0].startsWith(prefix) && Boolean(entry[1]),
  );
}

const RULE_ERROR_KEY = /^routing_rules\.(\d+)(\..*)?$/;

export function remapRuleErrors(errors: FormErrors, before: RoutingRuleDraft[], after: RoutingRuleDraft[]): FormErrors {
  const remapped: FormErrors = {};

  for (const [key, message] of Object.entries(errors)) {
    const match = key.match(RULE_ERROR_KEY);
    if (!match) {
      remapped[key] = message;
      continue;
    }

    const target = after.indexOf(before[Number(match[1])]);
    if (target >= 0) remapped[`${ROUTING_ERROR_KEY}.${target}${match[2] ?? ''}`] = message;
  }

  return remapped;
}
