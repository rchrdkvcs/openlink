import { toInputValue } from '@/lib/datetime';
import { normalizeUrl } from '@/lib/links';
import type { LinkStatus, RoutingCondition, RoutingRuleDraft } from '@/types/shortLinks';

export type PreviewContext = {
  country: string;
  language: string;
  device_type: string;
  browser: string;
  operating_system: string;
  referrer: string;
  utm_source: string;
  utm_medium: string;
  utm_campaign: string;
  utm_term: string;
  utm_content: string;
  occurred_at: string;
  timezone: string;
  seed: string;
};

export type PreviewDraft = { destination_url: string; routing_rules: RoutingRuleDraft[] };

export type PreviewRuleStatus = 'disabled' | 'not_matched' | 'matched' | 'skipped' | 'not_reached';

export type PreviewCondition = {
  type: string;
  operator: string;
  value: RoutingCondition['value'];
  timezone: string | null;
  matched: boolean;
};

export type PreviewVariant = {
  id: number | null;
  name: string;
  destination_url: string;
  weight: number;
  is_enabled: boolean;
  eligible: boolean;
  share: number;
  selected: boolean;
};

export type PreviewRule = {
  index: number;
  id: number | null;
  name: string;
  type: RoutingRuleDraft['type'];
  match_mode: RoutingRuleDraft['match_mode'];
  status: PreviewRuleStatus;
  destination_url: string | null;
  conditions: PreviewCondition[];
  slot: number | null;
  total_weight: number | null;
  variants: PreviewVariant[];
};

export type PreviewAvailability = {
  status: LinkStatus;
  domain: string | null;
  domain_usable: boolean;
  password_protected: boolean;
  available: boolean;
};

export type RoutingPreview = {
  source: 'saved' | 'draft';
  context: {
    occurred_at: string;
    local_time: string;
    timezone: string;
    visitor_hash: string;
    dimensions: Record<string, string | boolean | null>;
  };
  rules: PreviewRule[];
  decision: {
    rule_index: number | null;
    rule_id: number | null;
    rule_name: string | null;
    variant_id: number | null;
    variant_name: string | null;
    destination_url: string;
    is_default: boolean;
  };
  availability: PreviewAvailability;
};

type StatusPresentation = { label: string; tone: 'success' | 'warning' | 'default' | 'outline' };

const RULE_STATUSES: Record<PreviewRuleStatus, StatusPresentation> = {
  matched: { label: 'Matched', tone: 'success' },
  not_matched: { label: 'No match', tone: 'default' },
  disabled: { label: 'Disabled', tone: 'outline' },
  skipped: { label: 'Skipped', tone: 'warning' },
  not_reached: { label: 'Not checked', tone: 'outline' },
};

const STATUS_REASONS: Record<LinkStatus, string> = {
  active: '',
  scheduled: 'The link is scheduled and not active yet.',
  expired: 'The link has expired or reached its visit limit.',
  disabled: 'The link is disabled.',
  archived: 'The link is archived.',
};

export function newPreviewContext(timezone: string, now: Date = new Date()): PreviewContext {
  return {
    country: '',
    language: '',
    device_type: '',
    browser: '',
    operating_system: '',
    referrer: '',
    utm_source: '',
    utm_medium: '',
    utm_campaign: '',
    utm_term: '',
    utm_content: '',
    occurred_at: toInputValue(now),
    timezone,
    seed: 'visitor-1',
  };
}

export function randomSeed(random: () => number = Math.random): string {
  return `visitor-${Math.floor(random() * 1_000_000)}`;
}

export function previewRequest(context: PreviewContext, draft: PreviewDraft | null) {
  if (!draft) return { source: 'saved' as const, context };

  return {
    source: 'draft' as const,
    destination_url: normalizeUrl(draft.destination_url),
    routing_rules: draft.routing_rules,
    context,
  };
}

export function timeWasShifted(requested: string, localTime: string): boolean {
  return requested !== '' && localTime.slice(0, 16) !== requested;
}

export function ruleStatus(status: PreviewRuleStatus): StatusPresentation {
  return RULE_STATUSES[status];
}

export function skippedReason(rule: PreviewRule): string {
  return rule.type === 'split_test'
    ? 'Conditions matched, but no variant is enabled with a weight.'
    : 'No destination URL.';
}

export function availabilityReasons(availability: PreviewAvailability): string[] {
  const reasons = [];

  if (availability.status !== 'active') reasons.push(STATUS_REASONS[availability.status]);
  if (!availability.domain_usable) reasons.push(`${availability.domain ?? 'The domain'} is not active yet.`);

  return reasons;
}

export function previewErrors(body: unknown): string[] {
  const errors = (body as { errors?: Record<string, string[] | string> } | null)?.errors;
  if (!errors) return [];

  return Object.values(errors).flat();
}
