import { describe, expect, it } from 'vitest';
import { createSSRApp, h } from 'vue';
import { renderToString } from 'vue/server-renderer';

import type { DestinationHealth } from '@/lib/destinationHealth';
import type { ShortLink } from '@/types/shortLinks';

import InspectorHealth from './InspectorHealth.vue';

const url = 'https://example.com/page';
const health: DestinationHealth = {
  url,
  status: 'unchecked',
  execution_state: 'queued',
  requested_at: '2026-10-22T10:00:00Z',
  started_at: null,
  http_status: null,
  detail: null,
  consecutive_failures: 0,
  checked_at: null,
  failing_since: null,
  incident_opened_at: null,
  recovered_at: null,
  has_incident: false,
  pending: true,
  can_retry: false,
  stale: false,
};

function render(overrides: Partial<DestinationHealth>) {
  return renderToString(
    createSSRApp({
      render: () =>
        h(InspectorHealth, {
          link: { id: 1, destination_url: url, fallback_url: null, routing_rules: [] } as unknown as ShortLink,
          health: new Map([[url, { ...health, ...overrides }]]),
          canCheck: true,
          unsaved: false,
          open: true,
        }),
    }),
  );
}

describe('InspectorHealth', () => {
  it('shows queued and running checks rather than Not checked', async () => {
    const queued = await render({});
    const running = await render({ execution_state: 'running' });

    expect(queued).toContain('Queued');
    expect(queued).toContain('Waiting for a worker');
    expect(queued).not.toContain('Not checked');
    expect(running).toContain('Running');
    expect(running).not.toContain('Not checked');
  });

  it('offers an explicit safe retry for a delayed queued check', async () => {
    const html = await render({ can_retry: true });

    expect(html).toContain('Queued');
    expect(html).toContain('Retry check');
    expect(html).toContain('Retry replaces the pending request safely');
    expect(html).not.toMatch(/<button[^>]*\sdisabled(?:[=>\s])/);
  });

  it('shows a completed HTTP failure without a reachable success', async () => {
    const html = await render({
      execution_state: 'completed',
      pending: false,
      can_retry: true,
      status: 'http_failure',
      http_status: 404,
      checked_at: '2026-10-22T10:01:00Z',
    });

    expect(html).toContain('Completed');
    expect(html).toContain('HTTP 404');
    expect(html).not.toContain('Reachable');
    expect(html).not.toContain('Not checked');
  });

  it('keeps the latest normalized health visible when a new check fails', async () => {
    const html = await render({
      execution_state: 'failed',
      pending: false,
      status: 'inconclusive',
      http_status: 403,
      checked_at: '2026-10-22T10:01:00Z',
    });

    expect(html).toContain('Check failed');
    expect(html).toContain('Inconclusive');
    expect(html).toContain('Latest result checked');
    expect(html).not.toContain('Completed');
  });
});
