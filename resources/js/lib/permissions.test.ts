import { describe, expect, it } from 'vitest';

import type { Member } from '@/types/payloads';

import { canEditMember, canLeaveAs, isOwnerRole, roleDescription, roleLabel, sortMembersByRole } from './permissions';

function member(id: number, role: string, name = `User ${id}`): Member {
  return { id, role, created_at: '2026-01-01', user: { id, name, email: `${id}@example.com` } };
}

describe('permissions', () => {
  it('recognises the owner role', () => {
    expect(isOwnerRole('owner')).toBe(true);
    expect(isOwnerRole('admin')).toBe(false);
    expect(isOwnerRole(null)).toBe(false);
  });

  it('labels and describes assignable roles', () => {
    expect(roleLabel('editor')).toBe('Editor');
    expect(roleLabel('owner')).toBe('owner');
    expect(roleDescription('viewer')).toBe('Read-only access to links and analytics');
    expect(roleDescription('owner')).toBeUndefined();
  });

  it('lets managers edit other non-owner members only', () => {
    const viewer = { userId: 1, canManageMembers: true };
    expect(canEditMember(member(2, 'editor'), viewer)).toBe(true);
    expect(canEditMember(member(2, 'owner'), viewer)).toBe(false);
    expect(canEditMember(member(1, 'admin'), viewer)).toBe(false);
    expect(canEditMember(member(2, 'editor'), { userId: 1, canManageMembers: false })).toBe(false);
  });

  it('lets non-owners leave as themselves', () => {
    const viewer = { userId: 1, canManageMembers: false };
    expect(canLeaveAs(member(1, 'viewer'), viewer)).toBe(true);
    expect(canLeaveAs(member(1, 'owner'), viewer)).toBe(false);
    expect(canLeaveAs(member(2, 'viewer'), viewer)).toBe(false);
  });

  it('sorts members by role rank then name', () => {
    const sorted = sortMembersByRole([
      member(1, 'viewer', 'Ann'),
      member(2, 'admin', 'Zed'),
      member(3, 'owner', 'Bob'),
      member(4, 'admin', 'Amy'),
      member(5, 'guest', 'Al'),
    ]);
    expect(sorted.map((entry) => entry.id)).toEqual([3, 4, 2, 1, 5]);
  });
});
