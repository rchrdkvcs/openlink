import type { ErrorEvent } from '@sentry/vue';
import { describe, expect, it, vi } from 'vitest';
import type { App } from 'vue';

import { scrubEvent, startErrorReporting } from '@/lib/errorReporting';

const app = {} as App;

function fakeSentry() {
  return {
    init: vi.fn(),
    browserTracingIntegration: vi.fn(() => ({ name: 'BrowserTracing' })),
  };
}

describe('startErrorReporting', () => {
  it('never loads the SDK without a DSN', async () => {
    const load = vi.fn();

    await startErrorReporting(app, null, load);
    await startErrorReporting(app, undefined, load);
    await startErrorReporting(app, { dsn: '', environment: 'production', release: null, tracesSampleRate: 0 }, load);

    expect(load).not.toHaveBeenCalled();
  });

  it('initialises without tracing or personal data by default', async () => {
    const sentry = fakeSentry();

    await startErrorReporting(
      app,
      { dsn: 'https://key@errors.example.test/1', environment: 'production', release: 'v1.2.0', tracesSampleRate: 0 },
      async () => sentry as never,
    );

    expect(sentry.init).toHaveBeenCalledWith(
      expect.objectContaining({
        app,
        dsn: 'https://key@errors.example.test/1',
        environment: 'production',
        release: 'v1.2.0',
        dataCollection: { userInfo: false, cookies: false, httpHeaders: false, httpBodies: [], urlQueryParams: false },
        tracesSampleRate: undefined,
        integrations: [],
        beforeSend: scrubEvent,
      }),
    );
    expect(sentry.browserTracingIntegration).not.toHaveBeenCalled();
  });

  it('enables browser tracing only with a positive sample rate', async () => {
    const sentry = fakeSentry();

    await startErrorReporting(
      app,
      { dsn: 'https://key@errors.example.test/1', environment: 'production', release: null, tracesSampleRate: 0.2 },
      async () => sentry as never,
    );

    expect(sentry.init).toHaveBeenCalledWith(
      expect.objectContaining({
        tracesSampleRate: 0.2,
        release: undefined,
        integrations: [{ name: 'BrowserTracing' }],
      }),
    );
  });
});

describe('scrubEvent', () => {
  it('drops headers, cookies, query strings, bodies, and user details', () => {
    const event = scrubEvent({
      type: undefined,
      request: {
        url: 'https://app.example.test/links',
        headers: { 'User-Agent': 'Agent', Referer: 'https://elsewhere.test' },
        cookies: { session: 'secret' },
        query_string: 'token=secret',
        data: { password: 'secret' },
      },
      user: { id: '1', email: 'someone@example.test', ip_address: '203.0.113.9' },
    } as ErrorEvent);

    expect(event.request).toEqual({ url: 'https://app.example.test/links' });
    expect(event.user).toBeUndefined();
  });
});
