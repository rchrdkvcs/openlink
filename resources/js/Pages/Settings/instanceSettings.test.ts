import { describe, expect, it } from 'vitest';

import { instanceSettingsFields, registrationDescription, retentionHint } from './instanceSettings';
import { isUpdateInProgress, updateMessage, type UpdateStatus } from './instanceUpdates';

function status(overrides: Partial<UpdateStatus> = {}): UpdateStatus {
  return {
    current: '1.0.0',
    latest: { version: '1.0.0', url: '#' },
    available: false,
    canUpdate: false,
    state: null,
    ...overrides,
  };
}

describe('instance settings', () => {
  it('fills defaults for missing settings', () => {
    const fields = instanceSettingsFields({ reserved_slugs: ['admin', 'login'] });
    expect(fields.registration_mode).toBe('invite_only');
    expect(fields.slug_length).toBe('6');
    expect(fields.reserved_slugs).toBe('admin\nlogin');
    expect(fields.public_unavailable_title).toBe('This link is unavailable');
  });

  it('describes registration modes', () => {
    expect(registrationDescription('closed')).toContain('No one can create an account');
  });

  it('explains retention in months or years', () => {
    expect(retentionHint('10')).toBe('Visit events older than this are pruned. Minimum 30 days.');
    expect(retentionHint('90')).toBe('Visit events are kept for about 3 months, then pruned.');
    expect(retentionHint('365')).toBe('Visit events are kept for about 1 year, then pruned.');
    expect(retentionHint('500')).toBe('Visit events are kept for about 1.4 years, then pruned.');
  });
});

describe('instance updates', () => {
  it('detects updates in progress', () => {
    expect(isUpdateInProgress(status({ state: 'running' }))).toBe(true);
    expect(isUpdateInProgress(status({ state: 'failed' }))).toBe(false);
    expect(isUpdateInProgress(null)).toBe(false);
  });

  it('summarises the update status', () => {
    expect(updateMessage(null)).toBe('Development builds have no release version.');
    expect(updateMessage(status())).toBe('Up to date.');
    expect(updateMessage(status({ available: true }))).toBe(
      'A new release is available. Update through your deployment platform.',
    );
    expect(updateMessage(status({ available: true, canUpdate: true }))).toBe('A new release is available.');
    expect(updateMessage(status({ latest: null }))).toBe('Release information is temporarily unavailable.');
  });
});
