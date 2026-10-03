import type { InviteLink } from '@/types/payloads';

export const expiryOptions = [
  { value: '', label: 'Never' },
  { value: '1', label: '1 day' },
  { value: '7', label: '7 days' },
  { value: '30', label: '30 days' },
];

export const usesOptions = [
  { value: '', label: 'Unlimited' },
  { value: '1', label: '1' },
  { value: '10', label: '10' },
  { value: '100', label: '100' },
];

export type InviteLinkDraft = { role: string; expires_in_days: string; max_uses: string };

export function inviteLinkRequest(draft: InviteLinkDraft) {
  return {
    role: draft.role,
    expires_in_days: draft.expires_in_days === '' ? null : Number(draft.expires_in_days),
    max_uses: draft.max_uses === '' ? null : Number(draft.max_uses),
  };
}

export function formatDate(value: string): string {
  return new Date(value).toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' });
}

export function inviteLinkMeta(link: InviteLink): string {
  const parts: string[] = [];
  parts.push(link.expires_at ? `Expires ${formatDate(link.expires_at)}` : 'Never expires');
  if (link.max_uses !== null) {
    parts.push(`${link.uses}/${link.max_uses} uses`);
  } else if (link.uses > 0) {
    parts.push(`${link.uses} ${link.uses === 1 ? 'use' : 'uses'}`);
  }
  return parts.join(' · ');
}

export function memberCountLabel(count: number): string {
  return `${count} ${count === 1 ? 'person' : 'people'}`;
}
