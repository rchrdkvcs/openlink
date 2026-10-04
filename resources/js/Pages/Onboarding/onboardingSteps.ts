export type OnboardingStep = 1 | 2 | 3;

export type OnboardingDirection = 'forward' | 'backward';

export type OnboardingStepDefinition = {
  number: OnboardingStep;
  label: string;
  title: string;
  description: string;
  optional: boolean;
};

export type OnboardingActions = {
  back: boolean;
  skip: boolean;
  primary: string;
};

export const onboardingSteps: OnboardingStepDefinition[] = [
  {
    number: 1,
    label: 'Workspace',
    title: 'Name your workspace',
    description: 'A workspace holds your links, domains and team. You can rename it anytime.',
    optional: false,
  },
  {
    number: 2,
    label: 'First link',
    title: 'Shorten your first link',
    description: 'Paste any long URL. We’ll turn it into a short link you can share right away.',
    optional: true,
  },
  {
    number: 3,
    label: 'Team',
    title: 'Bring your team along',
    description: 'Create an invite link. Anyone who opens it joins with the role you pick.',
    optional: true,
  },
];

export const onboardingStepCount = onboardingSteps.length;

export function onboardingStep(step: OnboardingStep): OnboardingStepDefinition {
  return onboardingSteps[step - 1];
}

export function initialOnboardingStep(hasWorkspace: boolean): OnboardingStep {
  return hasWorkspace ? 2 : 1;
}

export function onboardingActions(step: OnboardingStep, completed: boolean): OnboardingActions {
  const definition = onboardingStep(step);
  const isLast = step === onboardingStepCount;

  return {
    back: step > 2,
    skip: definition.optional && !completed,
    primary: completed ? (isLast ? 'Finish setup' : 'Continue') : stepAction[step],
  };
}

const stepAction: Record<OnboardingStep, string> = {
  1: 'Create workspace',
  2: 'Create link',
  3: 'Create invite link',
};

export function stepDirection(from: OnboardingStep, to: OnboardingStep): OnboardingDirection {
  return to >= from ? 'forward' : 'backward';
}
