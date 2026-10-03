import type { Member, WorkspaceRole } from '@/types/payloads';

export type AssignableRole = Exclude<WorkspaceRole, 'owner'>;

export type RoleOption = { value: AssignableRole; label: string };

export const roleOptions: RoleOption[] = [
  { value: 'admin', label: 'Admin' },
  { value: 'editor', label: 'Editor' },
  { value: 'viewer', label: 'Viewer' },
];

export const roleDescriptions: Record<AssignableRole, string> = {
  admin: 'Manages members, domains, folders and settings',
  editor: 'Creates and edits links and QR codes',
  viewer: 'Read-only access to links and analytics',
};

export const defaultInviteRole: AssignableRole = 'editor';

const roleRank: Record<string, number> = { owner: 0, admin: 1, editor: 2, viewer: 3 };

export function isOwnerRole(role: string | null | undefined): boolean {
  return role === 'owner';
}

export function roleLabel(role: string): string {
  return roleOptions.find((option) => option.value === role)?.label ?? role;
}

export function roleDescription(role: string): string | undefined {
  return roleDescriptions[role as AssignableRole];
}

export type MemberViewer = { userId: number; canManageMembers: boolean };

export function canEditMember(member: Member, viewer: MemberViewer): boolean {
  return viewer.canManageMembers && !isOwnerRole(member.role) && member.user.id !== viewer.userId;
}

export function canLeaveAs(member: Member, viewer: MemberViewer): boolean {
  return member.user.id === viewer.userId && !isOwnerRole(member.role);
}

export function sortMembersByRole(members: Member[]): Member[] {
  return members.toSorted(
    (a, b) => (roleRank[a.role] ?? 9) - (roleRank[b.role] ?? 9) || a.user.name.localeCompare(b.user.name),
  );
}
