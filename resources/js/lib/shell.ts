import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

import { isOwnerRole } from '@/lib/permissions';
import type { PageProps } from '@/types';

export function useShell() {
  const page = usePage<PageProps>();

  const query = computed(() => new URL(page.url, window.location.origin).searchParams);
  const role = computed(() => page.props.role ?? null);

  return {
    page,
    user: computed(() => page.props.auth.user),
    workspace: computed(() => page.props.currentWorkspace),
    workspaces: computed(() => page.props.workspaces ?? []),
    navigation: computed(() => page.props.navigation),
    role,
    isOwner: computed(() => isOwnerRole(role.value)),
    canManage: computed(() => Boolean(page.props.canManageWorkspace)),
    canEdit: computed(() => Boolean(page.props.canEditWorkspace)),
    query,
  };
}

export function isMac() {
  return typeof navigator !== 'undefined' && /Mac|iPhone|iPad/.test(navigator.platform);
}
