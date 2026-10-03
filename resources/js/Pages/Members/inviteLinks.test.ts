import { describe, expect, it } from 'vitest';

import type { InviteLink } from '@/types/payloads';

import { inviteLinkMeta, inviteLinkRequest, memberCountLabel } from './inviteLinks';

function link(overrides: Partial<InviteLink> = {}): InviteLink {
  return {
    id: 1,
    role: 'editor',
    token: 'abc',
    url: 'https://example.com/join/abc',
    expires_at: null,
    max_uses: null,
    uses: 0,
    is_usable: true,
    created_at: null,
    ...overrides,
  };
}

describe('invite links', () => {
  it('converts empty draft fields to null', () => {
    expect(inviteLinkRequest({ role: 'viewer', expires_in_days: '', max_uses: '10' })).toEqual({
      role: 'viewer',
      expires_in_days: null,
      max_uses: 10,
    });
  });

  it('describes usage limits', () => {
    expect(inviteLinkMeta(link())).toBe('Never expires');
    expect(inviteLinkMeta(link({ uses: 1 }))).toBe('Never expires · 1 use');
    expect(inviteLinkMeta(link({ uses: 3, max_uses: 10 }))).toBe('Never expires · 3/10 uses');
  });

  it('pluralises member counts', () => {
    expect(memberCountLabel(1)).toBe('1 person');
    expect(memberCountLabel(4)).toBe('4 people');
  });
});
