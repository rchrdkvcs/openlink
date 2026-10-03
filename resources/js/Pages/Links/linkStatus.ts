import type { LinkStatus } from '@/types/shortLinks';

type StatusPresentation = { label: string; dot: string };

const STATUSES: Record<LinkStatus, StatusPresentation> = {
  active: { label: 'Active', dot: 'bg-success' },
  scheduled: { label: 'Scheduled', dot: 'bg-accent' },
  expired: { label: 'Expired', dot: 'bg-warning' },
  disabled: { label: 'Disabled', dot: 'bg-danger' },
  archived: { label: 'Archived', dot: 'bg-faint' },
};

export const LINK_STATUSES = Object.keys(STATUSES) as LinkStatus[];

export function linkStatusLabel(status: LinkStatus): string {
  return STATUSES[status]?.label ?? status;
}

export function linkStatusDot(status: LinkStatus): string {
  return STATUSES[status]?.dot ?? 'bg-faint';
}

export function linkStatusFilterOptions(): { value: '' | LinkStatus; label: string }[] {
  return [
    { value: '', label: 'Any status' },
    ...LINK_STATUSES.map((status) => ({ value: status, label: STATUSES[status].label })),
  ];
}
