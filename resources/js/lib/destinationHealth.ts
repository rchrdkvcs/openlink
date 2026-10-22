import type { ShortLink } from '@/types/shortLinks';

export type DestinationHealthStatus =
  | 'unchecked'
  | 'reachable'
  | 'http_failure'
  | 'dns_failure'
  | 'tls_failure'
  | 'connection_failure'
  | 'timeout'
  | 'inconclusive'
  | 'blocked';

export type DestinationHealth = {
  url: string;
  status: DestinationHealthStatus;
  http_status: number | null;
  detail: string | null;
  consecutive_failures: number;
  checked_at: string | null;
  failing_since: string | null;
  incident_opened_at: string | null;
  recovered_at: string | null;
  has_incident: boolean;
  pending: boolean;
  stale: boolean;
};

export type DestinationRole = 'destination' | 'fallback' | 'routing_rule' | 'routing_variant';

export type LinkDestination = { url: string; role: DestinationRole; label: string };

export type DestinationIssue = DestinationHealth & {
  id: number;
  recent_traffic: number;
  references: (LinkDestination & {
    short_link: { id: number; slug: string; short_url: string };
    qr_codes: { id: number; name: string; token: string }[];
  })[];
};

export type HealthVariant = 'default' | 'outline' | 'success' | 'warning' | 'danger';

export type LinkHealthSummary = 'incident' | 'confirming' | 'healthy' | 'unchecked';

const STATUS_LABELS: Record<DestinationHealthStatus, string> = {
  unchecked: 'Not checked',
  reachable: 'Reachable',
  http_failure: 'HTTP error',
  dns_failure: 'DNS failure',
  tls_failure: 'TLS failure',
  connection_failure: 'Connection failed',
  timeout: 'Timed out',
  inconclusive: 'Inconclusive',
  blocked: 'Blocked',
};

const FAILURES: DestinationHealthStatus[] = [
  'http_failure',
  'dns_failure',
  'tls_failure',
  'connection_failure',
  'timeout',
];

export function isFailure(status: DestinationHealthStatus): boolean {
  return FAILURES.includes(status);
}

export function healthLabel(health: DestinationHealth | undefined): string {
  if (!health) return STATUS_LABELS.unchecked;
  if (health.status === 'http_failure' && health.http_status) return `HTTP ${health.http_status}`;
  return STATUS_LABELS[health.status];
}

export function healthVariant(health: DestinationHealth | undefined): HealthVariant {
  if (!health || health.status === 'unchecked') return 'default';
  if (health.status === 'reachable') return 'success';
  if (health.has_incident) return 'danger';
  if (isFailure(health.status)) return 'warning';
  return 'outline';
}

export function linkDestinations(
  link: Pick<ShortLink, 'destination_url' | 'fallback_url' | 'routing_rules'>,
): LinkDestination[] {
  const destinations: LinkDestination[] = [
    { url: link.destination_url, role: 'destination', label: 'Default destination' },
  ];

  if (link.fallback_url) destinations.push({ url: link.fallback_url, role: 'fallback', label: 'Fallback URL' });

  for (const rule of link.routing_rules) {
    if (!rule.is_enabled) continue;
    if (rule.type === 'split_test') {
      for (const variant of rule.variants) {
        if (variant.is_enabled && variant.destination_url) {
          destinations.push({
            url: variant.destination_url,
            role: 'routing_variant',
            label: `${rule.name} · ${variant.name}`,
          });
        }
      }
    } else if (rule.destination_url) {
      destinations.push({ url: rule.destination_url, role: 'routing_rule', label: rule.name });
    }
  }

  return destinations.filter((destination) => destination.url);
}

export function healthByUrl(entries: DestinationHealth[]): Map<string, DestinationHealth> {
  return new Map(entries.map((entry) => [entry.url, entry]));
}

export function linkHealthSummary(
  link: Pick<ShortLink, 'destination_url' | 'fallback_url' | 'routing_rules'>,
  health: Map<string, DestinationHealth>,
): LinkHealthSummary {
  const results = linkDestinations(link).map((destination) => health.get(destination.url));

  if (results.some((result) => result?.has_incident)) return 'incident';
  if (results.some((result) => result && isFailure(result.status))) return 'confirming';
  if (results.length > 0 && results.every((result) => result?.status === 'reachable')) return 'healthy';
  return 'unchecked';
}

export function healthNote(health: DestinationHealth | undefined, relative: (value: string) => string): string {
  if (!health) return 'Never checked';
  if (health.pending) return 'Checking…';
  if (health.has_incident && health.failing_since) return `Failing since ${relative(health.failing_since)}`;
  if (isFailure(health.status)) return 'Failed once, confirming';
  if (health.recovered_at && health.status === 'reachable') return `Recovered ${relative(health.recovered_at)}`;
  if (!health.checked_at) return 'Never checked';
  return health.stale
    ? `Checked ${relative(health.checked_at)} · may be outdated`
    : `Checked ${relative(health.checked_at)}`;
}

export function hasPendingCheck(entries: DestinationHealth[]): boolean {
  return entries.some((entry) => entry.pending);
}
