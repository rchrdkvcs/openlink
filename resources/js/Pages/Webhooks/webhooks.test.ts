import { describe, expect, it } from 'vitest';

import type { WebhookDelivery, WebhookEndpoint } from '@/types/payloads';

import {
  canRetry,
  deliveryOutcome,
  deliveryStatusLabel,
  deliveryStatusVariant,
  endpointRequest,
  eventTypeLabel,
  subscriptionSummary,
  toggleEvent,
} from './webhooks';

const options = [
  { value: 'short_link.created', label: 'Short link created' },
  { value: 'short_link.updated', label: 'Short link updated' },
  { value: 'short_link.archived', label: 'Short link archived' },
];

function delivery(overrides: Partial<WebhookDelivery> = {}): WebhookDelivery {
  return {
    id: 1,
    event_id: '0199f0a4-7c1d-7b3a-9e55-2f3b8d1c4a10',
    event_type: 'short_link.created',
    status: 'pending',
    attempts: 0,
    response_status: null,
    error: null,
    duration_ms: null,
    last_attempted_at: null,
    next_attempt_at: null,
    created_at: '2026-10-21T10:00:00Z',
    ...overrides,
  };
}

function endpoint(overrides: Partial<WebhookEndpoint> = {}): WebhookEndpoint {
  return {
    id: 1,
    url: 'https://hooks.example.com/openlink',
    events: ['short_link.created'],
    is_enabled: true,
    created_at: '2026-10-21T10:00:00Z',
    deliveries: [],
    ...overrides,
  };
}

describe('webhooks', () => {
  it('labels delivery statuses with text and a matching tone', () => {
    expect(deliveryStatusLabel('succeeded')).toBe('Delivered');
    expect(deliveryStatusVariant('succeeded')).toBe('success');
    expect(deliveryStatusLabel('retrying')).toBe('Retrying');
    expect(deliveryStatusVariant('retrying')).toBe('warning');
    expect(deliveryStatusLabel('failed')).toBe('Failed');
    expect(deliveryStatusVariant('failed')).toBe('danger');
    expect(deliveryStatusVariant('pending')).toBe('default');
  });

  it('names event types, including the test event', () => {
    expect(eventTypeLabel(options, 'short_link.archived')).toBe('Short link archived');
    expect(eventTypeLabel(options, 'webhook.test')).toBe('Test event');
    expect(eventTypeLabel(options, 'unknown.event')).toBe('unknown.event');
  });

  it('summarises subscriptions', () => {
    expect(subscriptionSummary(options, ['short_link.created', 'short_link.updated', 'short_link.archived'])).toBe(
      'All short link events',
    );
    expect(subscriptionSummary(options, ['short_link.archived', 'short_link.created'])).toBe(
      'Short link archived, Short link created',
    );
  });

  it('describes delivery outcomes', () => {
    expect(deliveryOutcome(delivery())).toBe('Waiting for the queue worker');
    expect(
      deliveryOutcome(delivery({ status: 'succeeded', response_status: 204, duration_ms: 182, attempts: 1 })),
    ).toBe('HTTP 204 · 182 ms · 1 attempt');
    expect(
      deliveryOutcome(
        delivery({
          status: 'failed',
          response_status: 500,
          error: 'The endpoint responded with HTTP 500.',
          duration_ms: 40,
          attempts: 6,
        }),
      ),
    ).toBe('The endpoint responded with HTTP 500. · 40 ms · 6 attempts');
  });

  it('allows retrying only failed deliveries of enabled endpoints', () => {
    expect(canRetry(endpoint(), delivery({ status: 'failed' }))).toBe(true);
    expect(canRetry(endpoint(), delivery({ status: 'succeeded' }))).toBe(false);
    expect(canRetry(endpoint({ is_enabled: false }), delivery({ status: 'failed' }))).toBe(false);
  });

  it('toggles subscribed events and trims the URL', () => {
    expect(toggleEvent(['short_link.created'], 'short_link.archived')).toEqual([
      'short_link.created',
      'short_link.archived',
    ]);
    expect(toggleEvent(['short_link.created', 'short_link.archived'], 'short_link.created')).toEqual([
      'short_link.archived',
    ]);
    expect(endpointRequest({ url: '  https://hooks.example.com/x  ', events: ['short_link.created'] })).toEqual({
      url: 'https://hooks.example.com/x',
      events: ['short_link.created'],
    });
  });
});
