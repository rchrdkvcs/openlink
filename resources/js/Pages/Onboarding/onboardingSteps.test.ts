import { describe, expect, it } from 'vitest';

import { initialOnboardingStep, onboardingHeading, onboardingSteps } from './onboardingSteps';

describe('onboarding steps', () => {
  it('skips workspace creation when a workspace already exists', () => {
    expect(initialOnboardingStep(true)).toBe(2);
    expect(initialOnboardingStep(false)).toBe(1);
  });

  it('has a heading for every step', () => {
    for (const { number } of onboardingSteps) {
      expect(onboardingHeading(number).title).not.toBe('');
    }
    expect(onboardingHeading(3).title).toBe('Invite your team');
  });
});
