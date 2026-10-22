export type CheckState = 'healthy' | 'stale' | 'missing' | 'unavailable' | 'disabled' | 'not_configured';

export type DiagnosticCheck = {
  label: string;
  state: CheckState;
  summary: string;
  hint: string | null;
  observedAt: string | null;
  checkedAt: string | null;
  mode?: 'after_response' | 'queue';
};

export type MailTest = {
  state: 'healthy' | 'unavailable';
  summary: string;
  checkedAt: string | null;
};

export type BadgeVariant = 'success' | 'warning' | 'danger' | 'default' | 'outline';

const states: Record<CheckState, { label: string; variant: BadgeVariant }> = {
  healthy: { label: 'Healthy', variant: 'success' },
  stale: { label: 'Stale', variant: 'warning' },
  missing: { label: 'Missing', variant: 'danger' },
  unavailable: { label: 'Unavailable', variant: 'danger' },
  disabled: { label: 'Disabled', variant: 'outline' },
  not_configured: { label: 'Not configured', variant: 'warning' },
};

export function stateLabel(state: CheckState): string {
  return states[state]?.label ?? 'Unknown';
}

export function stateVariant(state: CheckState): BadgeVariant {
  return states[state]?.variant ?? 'default';
}

export function needsAttention(check: Pick<DiagnosticCheck, 'state'>): boolean {
  return !['healthy', 'disabled'].includes(check.state);
}

export function attentionSummary(checks: Pick<DiagnosticCheck, 'state'>[]): string {
  const count = checks.filter(needsAttention).length;
  if (count === 0) return 'All services are healthy or not in use.';
  return count === 1 ? '1 service needs attention.' : `${count} services need attention.`;
}

export function heartbeatFootnote(cadenceMinutes: number, staleAfterMinutes: number): string {
  const cadence = cadenceMinutes === 1 ? 'every minute' : `every ${cadenceMinutes} minutes`;
  return `The scheduler and queue worker record a heartbeat ${cadence}. A heartbeat older than ${staleAfterMinutes} minutes is stale.`;
}
