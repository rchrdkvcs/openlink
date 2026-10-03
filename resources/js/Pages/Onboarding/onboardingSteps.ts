export type OnboardingStep = 1 | 2 | 3;

export type OnboardingHeading = { title: string; description: string };

export const onboardingSteps: { number: OnboardingStep; label: string }[] = [
  { number: 1, label: 'Workspace' },
  { number: 2, label: 'First link' },
  { number: 3, label: 'Team' },
];

const headings: Record<OnboardingStep, OnboardingHeading> = {
  1: { title: 'Create your workspace', description: 'A workspace holds your links, domains and team.' },
  2: { title: 'Shorten your first link', description: 'Paste a long URL and we’ll create the short link.' },
  3: { title: 'Invite your team', description: 'Anyone with the link joins with the role you choose.' },
};

export function onboardingHeading(step: OnboardingStep): OnboardingHeading {
  return headings[step];
}

export function initialOnboardingStep(hasWorkspace: boolean): OnboardingStep {
  return hasWorkspace ? 2 : 1;
}
