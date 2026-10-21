import type { WebhookDelivery, WebhookDeliveryStatus, WebhookEndpoint, WebhookEventOption } from '@/types/payloads';

export type BadgeVariant = 'default' | 'success' | 'warning' | 'danger';

export type EndpointDraft = { url: string; events: string[] };

const statusLabels: Record<WebhookDeliveryStatus, string> = {
  pending: 'Pending',
  retrying: 'Retrying',
  succeeded: 'Delivered',
  failed: 'Failed',
};

const statusVariants: Record<WebhookDeliveryStatus, BadgeVariant> = {
  pending: 'default',
  retrying: 'warning',
  succeeded: 'success',
  failed: 'danger',
};

export function deliveryStatusLabel(status: WebhookDeliveryStatus): string {
  return statusLabels[status] ?? status;
}

export function deliveryStatusVariant(status: WebhookDeliveryStatus): BadgeVariant {
  return statusVariants[status] ?? 'default';
}

export function eventTypeLabel(options: WebhookEventOption[], value: string): string {
  if (value === 'webhook.test') return 'Test event';

  return options.find((option) => option.value === value)?.label ?? value;
}

export function subscriptionSummary(options: WebhookEventOption[], events: string[]): string {
  if (options.length > 0 && options.every((option) => events.includes(option.value))) return 'All short link events';

  return events.map((event) => eventTypeLabel(options, event)).join(', ');
}

export function deliveryOutcome(delivery: WebhookDelivery): string {
  const parts: string[] = [];

  if (delivery.error) {
    parts.push(delivery.error);
  } else if (delivery.response_status !== null) {
    parts.push(`HTTP ${delivery.response_status}`);
  } else {
    parts.push('Waiting for the queue worker');
  }

  if (delivery.duration_ms !== null) parts.push(`${delivery.duration_ms} ms`);
  if (delivery.attempts > 0) parts.push(`${delivery.attempts} ${delivery.attempts === 1 ? 'attempt' : 'attempts'}`);

  return parts.join(' · ');
}

export function canRetry(endpoint: WebhookEndpoint, delivery: WebhookDelivery): boolean {
  return endpoint.is_enabled && delivery.status === 'failed';
}

export function toggleEvent(events: string[], value: string): string[] {
  return events.includes(value) ? events.filter((event) => event !== value) : [...events, value];
}

export function endpointRequest(draft: EndpointDraft) {
  return { url: draft.url.trim(), events: draft.events };
}
