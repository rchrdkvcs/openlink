import type { Domain, DomainSetup, WorkspaceSummary } from '@/types/payloads';

export type DomainBadgeVariant = 'outline' | 'success' | 'danger' | 'default' | 'warning';

type DomainState = Pick<Domain, 'status' | 'is_default'>;

const statusLabels: Record<string, string> = {
  active: 'Active',
  ownership_verified: 'Almost ready',
  pending_verification: 'Setup needed',
  failed_verification: 'Setup needed',
  disabled: 'Disabled',
};

export function domainStatusVariant(domain: DomainState): DomainBadgeVariant {
  if (domain.is_default) return 'outline';
  if (domain.status === 'active') return 'success';
  if (domain.status === 'failed_verification') return 'danger';
  if (domain.status === 'disabled') return 'default';
  return 'warning';
}

export function domainStatusLabel(domain: DomainState): string {
  if (domain.is_default) return 'Default';
  return statusLabels[domain.status] ?? domain.status;
}

export function domainSubtitle(domain: DomainState): string {
  if (domain.is_default) return 'Instance default, available to every workspace';
  if (domain.status === 'active') return 'DNS configured';
  if (domain.status === 'disabled') return 'Not serving links';
  if (domain.status === 'ownership_verified') return 'Ownership verified, waiting for DNS to point here';
  return 'Waiting for DNS records';
}

export function domainNeedsSetup(domain: DomainState): boolean {
  return !domain.is_default && domain.status !== 'active' && domain.status !== 'disabled';
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

export function setupStep(domain: Pick<Domain, 'status'> | null): SetupStep {
  if (!domain) return 1;
  return domain.status === 'active' ? 3 : 2;
}

export function setupDescription(step: SetupStep): string {
  if (step === 1) return 'Use a domain or subdomain you own for branded short links.';
  if (step === 2) return 'Add two DNS records where you manage this domain. You only do this once.';
  return 'Verified and serving short links.';
}

export type DnsRecordRequirement = {
  key: 'txt' | 'pointing';
  purpose: string;
  type: string;
  name: string;
  value: string;
  done: boolean;
  error: string | null;
};

export function dnsRecordRequirements(domain: DomainSetup): DnsRecordRequirement[] {
  return [
    {
      key: 'txt',
      purpose: 'Proves you own the domain',
      type: 'TXT',
      name: domain.expected_txt_name,
      value: domain.expected_txt,
      done: domain.ownership_verified,
      error: domain.ownership_verified ? null : domain.failure_reason,
    },
    {
      key: 'pointing',
      purpose: 'Sends visitors to this server',
      type: domain.dns_record.type,
      name: domain.hostname,
      value: domain.dns_record.value,
      done: domain.dns_pointed || domain.status === 'active',
      error: domain.dns_check_error,
    },
  ];
}
