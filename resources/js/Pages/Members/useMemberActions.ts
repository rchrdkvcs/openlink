import { router } from '@inertiajs/vue3';

import { confirmAction } from '@/lib/confirm';
import { roleLabel } from '@/lib/permissions';
import { toast } from '@/lib/toast';
import type { Member } from '@/types/payloads';

export function useMemberActions(workspaceName: () => string) {
  function changeRole(member: Member, role: string) {
    if (member.role === role) return;
    const label = roleLabel(role);

    router.patch(
      route('members.update', member.id),
      { role },
      {
        preserveScroll: true,
        onSuccess: () => toast({ title: `${member.user.name} is now ${label.toLowerCase()}`, tone: 'success' }),
      },
    );
  }

  async function removeMember(member: Member) {
    const confirmed = await confirmAction({
      title: `Remove ${member.user.name}?`,
      message: `They lose access to ${workspaceName()} immediately. Links they created stay in the workspace.`,
      confirmLabel: 'Remove member',
      destructive: true,
    });

    if (!confirmed) return;

    router.delete(route('members.destroy', member.id), {
      preserveScroll: true,
      onSuccess: () => toast({ title: `${member.user.name} removed` }),
    });
  }

  async function transferOwnership(member: Member) {
    const confirmed = await confirmAction({
      title: `Transfer ownership to ${member.user.name}?`,
      message: `They become the owner of ${workspaceName()} and you become an admin. Only the new owner can undo this.`,
      confirmLabel: 'Transfer ownership',
    });

    if (!confirmed) return;

    router.post(
      route('members.transfer-ownership', member.id),
      {},
      {
        preserveScroll: true,
        onSuccess: () => toast({ title: `${member.user.name} is now the owner`, tone: 'success' }),
      },
    );
  }

  async function leaveWorkspace() {
    const confirmed = await confirmAction({
      title: `Leave ${workspaceName()}?`,
      message: 'You lose access immediately. An owner or admin will have to invite you again.',
      confirmLabel: 'Leave workspace',
      destructive: true,
    });

    if (confirmed) router.post(route('members.leave'));
  }

  return { changeRole, removeMember, transferOwnership, leaveWorkspace };
}
