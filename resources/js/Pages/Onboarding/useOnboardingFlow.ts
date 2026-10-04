import { router, useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

import { type AssignableRole, defaultInviteRole } from '@/lib/permissions';
import { copyToClipboard } from '@/lib/toast';
import type { Domain, InviteLink, WorkspaceSummary } from '@/types/payloads';

import {
  initialOnboardingStep,
  onboardingActions,
  onboardingStep,
  type OnboardingDirection,
  type OnboardingStep,
  stepDirection,
} from './onboardingSteps';

export type OnboardingFirstLink = { short_url: string; destination_url: string };

export type OnboardingProps = {
  workspace: WorkspaceSummary | null;
  domains: Domain[];
  inviteLinks: InviteLink[];
  firstLink: OnboardingFirstLink | null;
};

export function useOnboardingFlow(props: OnboardingProps) {
  const step = ref<OnboardingStep>(initialOnboardingStep(Boolean(props.workspace)));
  const direction = ref<OnboardingDirection>('forward');
  const finishing = ref(false);

  function goTo(target: OnboardingStep) {
    direction.value = stepDirection(step.value, target);
    step.value = target;
  }

  watch(
    () => props.workspace,
    (workspace) => {
      if (workspace && step.value === 1) goTo(2);
    },
  );

  const firstDomainId = () => props.domains.find((domain) => domain.is_default)?.id ?? props.domains[0]?.id ?? null;

  const workspaceForm = useForm({ name: '' });
  const linkForm = useForm({ domain_id: firstDomainId(), destination_url: '', slug: '' });
  const inviteForm = useForm({
    role: defaultInviteRole as AssignableRole,
    expires_in_days: null,
    max_uses: null,
  });

  watch(
    () => props.domains,
    () => {
      if (!linkForm.domain_id) linkForm.domain_id = firstDomainId();
    },
    { immediate: true },
  );

  const domainOptions = computed(() => props.domains.map((domain) => ({ value: domain.id, label: domain.hostname })));
  const selectedHostname = computed(
    () => props.domains.find((domain) => domain.id === linkForm.domain_id)?.hostname ?? props.domains[0]?.hostname,
  );
  const teamInviteLink = computed(() => props.inviteLinks[0] ?? null);

  const completed = computed(() => {
    if (step.value === 1) return Boolean(props.workspace);
    if (step.value === 2) return Boolean(props.firstLink);
    return Boolean(teamInviteLink.value);
  });

  const definition = computed(() => onboardingStep(step.value));
  const actions = computed(() => onboardingActions(step.value, completed.value));

  const processing = computed(
    () => workspaceForm.processing || linkForm.processing || inviteForm.processing || finishing.value,
  );

  function finish() {
    router.post(
      route('onboarding.complete'),
      {},
      {
        onStart: () => (finishing.value = true),
        onFinish: () => (finishing.value = false),
      },
    );
  }

  function advance() {
    if (step.value === 3) finish();
    else goTo((step.value + 1) as OnboardingStep);
  }

  function submit() {
    if (completed.value) return advance();

    if (step.value === 1) return workspaceForm.post(route('onboarding.workspace'));
    if (step.value === 2) return linkForm.post(route('short-links.store'), { preserveScroll: true });
    return inviteForm.post(route('invite-links.store'), { preserveScroll: true });
  }

  return {
    step,
    direction,
    definition,
    actions,
    completed,
    processing,
    workspaceForm,
    linkForm,
    inviteForm,
    domainOptions,
    selectedHostname,
    teamInviteLink,
    submit,
    skip: advance,
    back: () => goTo((step.value - 1) as OnboardingStep),
    copy: (value: string, title: string) => copyToClipboard(value, title),
  };
}
