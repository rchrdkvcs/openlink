import { describe, expect, it } from 'vitest';

import { errorState } from './publicStates';

describe('errorState', () => {
  it('describes each rendered status with its own scene', () => {
    expect(errorState(403)).toMatchObject({ scene: 'locked', title: 'Access denied', canRetry: false });
    expect(errorState(404)).toMatchObject({ scene: 'unlinked', title: 'Page not found', canRetry: false });
    expect(errorState(419)).toMatchObject({ scene: 'expired', title: 'Page expired', canRetry: true });
    expect(errorState(429)).toMatchObject({ scene: 'expired', title: 'Too many requests', canRetry: true });
    expect(errorState(500)).toMatchObject({ scene: 'offline', title: 'Something went wrong', canRetry: true });
    expect(errorState(503)).toMatchObject({ scene: 'offline', title: 'Back shortly', canRetry: true });
  });

  it('keeps the original status and falls back to the server error copy', () => {
    expect(errorState(502)).toMatchObject({ status: 502, scene: 'offline', title: 'Something went wrong' });
  });
});
