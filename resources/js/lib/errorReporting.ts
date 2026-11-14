import type { ErrorEvent } from '@sentry/vue';
import type { App } from 'vue';

export type ErrorReportingConfig = {
  dsn: string;
  environment: string;
  release: string | null;
  tracesSampleRate: number;
};

type SentryModule = typeof import('@sentry/vue');

export function scrubEvent(event: ErrorEvent): ErrorEvent {
  if (event.request) {
    delete event.request.headers;
    delete event.request.cookies;
    delete event.request.query_string;
    delete event.request.data;
  }

  delete event.user;

  return event;
}

export async function startErrorReporting(
  app: App,
  config: ErrorReportingConfig | null | undefined,
  load: () => Promise<SentryModule> = () => import('@sentry/vue'),
): Promise<void> {
  if (!config?.dsn) {
    return;
  }

  const Sentry = await load();
  const tracing = config.tracesSampleRate > 0;

  Sentry.init({
    app,
    dsn: config.dsn,
    environment: config.environment,
    release: config.release ?? undefined,
    dataCollection: {
      userInfo: false,
      cookies: false,
      httpHeaders: false,
      httpBodies: [],
      urlQueryParams: false,
    },
    tracesSampleRate: tracing ? config.tracesSampleRate : undefined,
    integrations: tracing ? [Sentry.browserTracingIntegration()] : [],
    beforeSend: scrubEvent,
  });
}
