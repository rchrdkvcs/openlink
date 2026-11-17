import type { Domain, DomainEdgeMode, DomainSetup, WorkspaceSummary } from '@/types/payloads';

export type DomainBadgeVariant = 'outline' | 'success' | 'danger' | 'default' | 'warning';

type DomainState = Pick<Domain, 'status' | 'is_default' | 'edge_status' | 'needs_dns_update'>;

export type DomainEdgeTraits = {
  pointingPurpose: string;
  readyMessage: string;
  administratorAddsDomains: boolean;
  managesDnsTarget: boolean;
  issuesHttps: boolean;
};

export const domainEdges: Record<DomainEdgeMode, DomainEdgeTraits> = {
  direct: {
    pointingPurpose: 'Sends visitors to this server',
    readyMessage:
      'You can now create short links on it. If it does not open over HTTPS yet, ask your instance administrator to add it to the server.',
    administratorAddsDomains: true,
    managesDnsTarget: false,
    issuesHttps: false,
  },
  cloudflare: {
    pointingPurpose: 'Sends visitors to Openlink and enables HTTPS automatically',
    readyMessage: 'You can now create short links on it. HTTPS is set up automatically.',
    administratorAddsDomains: false,
    managesDnsTarget: true,
    issuesHttps: true,
  },
};

export function domainEdge(mode: DomainEdgeMode | null | undefined): DomainEdgeTraits {
  return domainEdges[mode ?? 'direct'] ?? domainEdges.direct;
}

export const issuingHttpsMessage = 'CNAME found, issuing HTTPS…';

export const dnsUpdateMessage = 'Replace the current record for this domain with this CNAME to keep it working.';

const statusLabels: Record<string, string> = {
  active: 'Active',
  ownership_verified: 'Almost ready',
  pending_verification: 'Setup needed',
  failed_verification: 'Setup needed',
  disabled: 'Disabled',
};

export function domainStatusVariant(domain: DomainState): DomainBadgeVariant {
  if (domain.is_default) return 'outline';
  if (domain.needs_dns_update) return 'warning';
  if (domain.status === 'active') return 'success';
  if (domain.status === 'failed_verification') return 'danger';
  if (domain.status === 'disabled') return 'default';
  return 'warning';
}

export function domainStatusLabel(domain: DomainState): string {
  if (domain.is_default) return 'Default';
  if (domain.needs_dns_update) return 'Update DNS';
  return statusLabels[domain.status] ?? domain.status;
}

export function domainSubtitle(domain: DomainState): string {
  if (domain.is_default) return 'Instance default, available to every workspace';
  if (domain.needs_dns_update) return 'Its DNS record needs an update to keep working';
  if (domain.status === 'active') return 'DNS configured';
  if (domain.status === 'disabled') return 'Not serving links';
  if (domain.edge_status === 'issuing') return issuingHttpsMessage;
  if (domain.status === 'ownership_verified') return 'Ownership verified, waiting for DNS to point here';
  return 'Waiting for DNS records';
}

export function domainNeedsSetup(domain: DomainState): boolean {
  if (domain.is_default) return false;
  if (domain.needs_dns_update) return true;
  return domain.status !== 'active' && domain.status !== 'disabled';
}

export function setupActionLabel(domain: DomainState): string {
  return domain.needs_dns_update ? 'Update DNS' : 'Continue setup';
}

export type WorkspaceOption = { value: number; label: string };

export function transferTargetOptions(workspaces: WorkspaceSummary[], currentWorkspaceId: number): WorkspaceOption[] {
  return workspaces
    .filter((workspace) => workspace.id !== currentWorkspaceId)
    .map((workspace) => ({ value: workspace.id, label: workspace.name }));
}

export type SetupStep = 1 | 2 | 3;

export const setupSteps: { number: SetupStep; label: string }[] = [
  { number: 1, label: 'Hostname' },
  { number: 2, label: 'DNS records' },
  { number: 3, label: 'Ready' },
];

export function setupStep(domain: Pick<Domain, 'status' | 'needs_dns_update'> | null): SetupStep {
  if (!domain) return 1;
  return domain.status === 'active' && !domain.needs_dns_update ? 3 : 2;
}

export function setupDescription(step: SetupStep): string {
  if (step === 1) return 'Use a domain or subdomain you own for branded short links.';
  if (step === 2) return 'Add two DNS records where you manage this domain. You only do this once.';
  return 'Verified and serving short links.';
}

export type DnsRecordProgress = 'waiting' | 'issuing' | 'done';

export type DnsRecordRequirement = {
  key: 'txt' | 'pointing';
  purpose: string;
  type: string;
  name: string;
  value: string;
  done: boolean;
  progress: DnsRecordProgress;
  statusLabel: string;
  note: string | null;
  error: string | null;
};

const progressLabels: Record<DnsRecordProgress, string> = {
  waiting: 'Waiting',
  issuing: 'Issuing HTTPS',
  done: 'Found',
};

function requirement(fields: Omit<DnsRecordRequirement, 'done' | 'statusLabel'>): DnsRecordRequirement {
  return { ...fields, done: fields.progress === 'done', statusLabel: progressLabels[fields.progress] };
}

function pointingProgress(domain: DomainSetup, edge: DomainEdgeTraits): DnsRecordProgress {
  if (!edge.issuesHttps) return domain.dns_pointed || domain.status === 'active' ? 'done' : 'waiting';
  if (domain.edge_status === 'connected') return 'done';
  return domain.edge_status === 'issuing' ? 'issuing' : 'waiting';
}

export function dnsRecordRequirements(domain: DomainSetup, mode: DomainEdgeMode): DnsRecordRequirement[] {
  const edge = domainEdge(mode);
  const pointing = pointingProgress(domain, edge);

  return [
    requirement({
      key: 'txt',
      purpose: 'Proves you own the domain',
      type: 'TXT',
      name: domain.expected_txt_name,
      value: domain.expected_txt,
      progress: domain.ownership_verified ? 'done' : 'waiting',
      note: null,
      error: domain.ownership_verified ? null : domain.failure_reason,
    }),
    requirement({
      key: 'pointing',
      purpose: edge.pointingPurpose,
      type: domain.dns_record.type,
      name: domain.hostname,
      value: domain.dns_record.value,
      progress: pointing,
      note: pointing === 'issuing' ? issuingHttpsMessage : null,
      error: domain.dns_check_error ?? (domain.needs_dns_update && pointing === 'waiting' ? dnsUpdateMessage : null),
    }),
  ];
}
