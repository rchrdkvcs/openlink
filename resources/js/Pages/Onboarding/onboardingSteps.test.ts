import { describe, expect, it } from 'vitest';

import {
  initialOnboardingStep,
  onboardingActions,
  onboardingStep,
  onboardingSteps,
  stepDirection,
} from './onboardingSteps';

describe('onboarding steps', () => {
  it('skips workspace creation when a workspace already exists', () => {
    expect(initialOnboardingStep(true)).toBe(2);
    expect(initialOnboardingStep(false)).toBe(1);
  });

  it('has a title for every step', () => {
    for (const { number } of onboardingSteps) {
      expect(onboardingStep(number).title).not.toBe('');
    }
  });

  it('never offers to skip the workspace step', () => {
    expect(onboardingActions(1, false)).toEqual({ back: false, skip: false, primary: 'Create workspace' });
  });

  it('offers skip only while an optional step is incomplete', () => {
    expect(onboardingActions(2, false)).toEqual({ back: false, skip: true, primary: 'Create link' });
    expect(onboardingActions(2, true)).toEqual({ back: false, skip: false, primary: 'Continue' });
  });

  it('finishes from the last step and allows going back', () => {
    expect(onboardingActions(3, false)).toEqual({ back: true, skip: true, primary: 'Create invite link' });
    expect(onboardingActions(3, true)).toEqual({ back: true, skip: false, primary: 'Finish setup' });
  });

  it('derives the transition direction from the step order', () => {
    expect(stepDirection(2, 3)).toBe('forward');
    expect(stepDirection(3, 2)).toBe('backward');
  });
});
