import { router, useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

import { defaultInviteRole } from '@/lib/permissions';
import { copyToClipboard } from '@/lib/toast';
import type { Domain, InviteLink, WorkspaceSummary } from '@/types/payloads';

import { initialOnboardingStep, onboardingHeading, type OnboardingStep } from './onboardingSteps';

export type OnboardingProps = {
  workspace: WorkspaceSummary | null;
  domains: Domain[];
  inviteLinks: InviteLink[];
  hasLink: boolean;
};

export function useOnboardingFlow(props: OnboardingProps) {
  const step = ref<OnboardingStep>(initialOnboardingStep(Boolean(props.workspace)));

  watch(
    () => props.workspace,
    (workspace) => {
      if (workspace && step.value === 1) step.value = 2;
    },
  );

  const firstDomainId = () => props.domains[0]?.id ?? null;

  const workspaceForm = useForm({ name: '' });
  const linkForm = useForm({ domain_id: firstDomainId(), destination_url: '', slug: '' });
  const inviteForm = useForm({ role: defaultInviteRole as string, expires_in_days: null, max_uses: null });

  watch(
    () => props.domains,
    () => {
      if (!linkForm.domain_id) linkForm.domain_id = firstDomainId();
    },
    { immediate: true },
  );

  const domainOptions = computed(() => props.domains.map((domain) => ({ value: domain.id, label: domain.hostname })));
  const teamInviteLink = computed(() => props.inviteLinks[0] ?? null);
  const heading = computed(() => onboardingHeading(step.value));

  return {
    step,
    heading,
    workspaceForm,
    linkForm,
    inviteForm,
    domainOptions,
    teamInviteLink,
    createWorkspace: () => workspaceForm.post(route('onboarding.workspace')),
    createFirstLink: () =>
      linkForm.post(route('short-links.store'), { preserveScroll: true, onSuccess: () => (step.value = 3) }),
    createInviteLink: () => inviteForm.post(route('invite-links.store'), { preserveScroll: true }),
    copyInviteLink: () => {
      if (teamInviteLink.value) copyToClipboard(teamInviteLink.value.url, 'Invite link copied');
    },
    finish: () => router.post(route('onboarding.complete')),
  };
}
