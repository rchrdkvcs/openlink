<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { KeyRound, MoreHorizontal, Pencil, RotateCcw, Send, Trash2 } from '@lucide/vue';

import Badge from '@/Components/ui/Badge.vue';
import Button from '@/Components/ui/Button.vue';
import Menu from '@/Components/ui/Menu.vue';
import MenuItem from '@/Components/ui/MenuItem.vue';
import MenuSeparator from '@/Components/ui/MenuSeparator.vue';
import Switch from '@/Components/ui/Switch.vue';
import { confirmAction } from '@/lib/confirm';
import { relativeTime } from '@/lib/datetime';
import { toast } from '@/lib/toast';
import type { WebhookDelivery, WebhookEndpoint, WebhookEventOption } from '@/types/payloads';

import {
  canRetry,
  deliveryOutcome,
  deliveryStatusLabel,
  deliveryStatusVariant,
  eventTypeLabel,
  subscriptionSummary,
} from './webhooks';

const props = defineProps<{ endpoint: WebhookEndpoint; eventTypes: WebhookEventOption[] }>();
const emit = defineEmits<{ edit: [endpoint: WebhookEndpoint] }>();

function setEnabled(enabled: boolean) {
  router.patch(
    route('webhook-endpoints.update', props.endpoint.id),
    { url: props.endpoint.url, events: props.endpoint.events, is_enabled: enabled },
    {
      preserveScroll: true,
      onSuccess: () => toast({ title: enabled ? 'Endpoint enabled' : 'Endpoint disabled' }),
    },
  );
}

function sendTest() {
  router.post(
    route('webhook-endpoints.test', props.endpoint.id),
    {},
    {
      preserveScroll: true,
      onSuccess: () => toast({ title: 'Test event queued', tone: 'success' }),
      onError: (errors) => toast({ title: 'Test event not sent', description: errors.delivery, tone: 'danger' }),
    },
  );
}

async function rotateSecret() {
  const confirmed = await confirmAction({
    title: 'Rotate signing secret?',
    message:
      'Deliveries are signed with both the new and the current secret for 24 hours. Update your receiver with the new secret within that time.',
    confirmLabel: 'Rotate secret',
  });

  if (!confirmed) return;

  router.post(route('webhook-endpoints.rotate-secret', props.endpoint.id), {}, { preserveScroll: true });
}

async function destroy() {
  const confirmed = await confirmAction({
    title: 'Delete this endpoint?',
    message: 'Openlink stops sending events to it and deletes its delivery history. This cannot be undone.',
    confirmLabel: 'Delete endpoint',
    destructive: true,
  });

  if (!confirmed) return;

  router.delete(route('webhook-endpoints.destroy', props.endpoint.id), {
    preserveScroll: true,
    onSuccess: () => toast({ title: 'Endpoint deleted' }),
  });
}

function retry(delivery: WebhookDelivery) {
  router.post(
    route('webhook-deliveries.retry', delivery.id),
    {},
    {
      preserveScroll: true,
      onSuccess: () => toast({ title: 'Delivery queued again', tone: 'success' }),
      onError: (errors) => toast({ title: 'Delivery not retried', description: errors.delivery, tone: 'danger' }),
    },
  );
}
</script>

<template>
  <section class="overflow-hidden rounded-xl border bg-surface">
    <div class="flex items-center gap-3 px-4 py-3.5 sm:px-5">
      <div class="min-w-0 flex-1">
        <p class="truncate font-mono text-[13px] text-foreground" :title="endpoint.url">{{ endpoint.url }}</p>
        <p class="mt-0.5 truncate text-xs text-faint">
          {{ endpoint.is_enabled ? subscriptionSummary(eventTypes, endpoint.events) : 'Disabled · no new deliveries' }}
        </p>
      </div>
      <Switch
        :model-value="endpoint.is_enabled"
        :aria-label="endpoint.is_enabled ? 'Disable endpoint' : 'Enable endpoint'"
        @update:model-value="setEnabled"
      />
      <Menu width="w-56">
        <template #trigger>
          <button
            type="button"
            class="grid h-8 w-8 shrink-0 place-items-center rounded-lg text-muted transition-colors duration-150 hover:bg-elevated hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent/25 data-[state=open]:bg-elevated data-[state=open]:text-foreground"
            :aria-label="`Actions for ${endpoint.url}`"
          >
            <MoreHorizontal class="h-4 w-4" />
          </button>
        </template>
        <MenuItem :icon="Send" :disabled="!endpoint.is_enabled" @select="sendTest">Send test event</MenuItem>
        <MenuItem :icon="Pencil" @select="emit('edit', endpoint)">Edit</MenuItem>
        <MenuItem :icon="KeyRound" @select="rotateSecret">Rotate signing secret</MenuItem>
        <MenuSeparator />
        <MenuItem :icon="Trash2" destructive @select="destroy">Delete</MenuItem>
      </Menu>
    </div>

    <ul v-if="endpoint.deliveries.length > 0" class="divide-y divide-border border-t" aria-label="Recent deliveries">
      <li
        v-for="delivery in endpoint.deliveries"
        :key="delivery.id"
        class="flex items-center gap-3 px-4 py-2.5 sm:px-5"
      >
        <Badge :variant="deliveryStatusVariant(delivery.status)" class="w-[5.5rem] shrink-0 justify-center">
          {{ deliveryStatusLabel(delivery.status) }}
        </Badge>
        <div class="min-w-0 flex-1">
          <p class="truncate text-[13px] text-foreground">{{ eventTypeLabel(eventTypes, delivery.event_type) }}</p>
          <p class="truncate text-xs text-faint" :title="`${deliveryOutcome(delivery)} · Event ${delivery.event_id}`">
            {{ deliveryOutcome(delivery) }}
          </p>
        </div>
        <time class="hidden shrink-0 text-xs tabular-nums text-faint sm:block" :datetime="delivery.created_at">
          {{ relativeTime(delivery.created_at) }}
        </time>
        <Button
          v-if="canRetry(endpoint, delivery)"
          variant="secondary"
          size="sm"
          type="button"
          @click="retry(delivery)"
        >
          <RotateCcw class="h-3.5 w-3.5" /> Retry
        </Button>
      </li>
    </ul>
    <p v-else class="border-t px-4 py-3.5 text-[13px] text-muted sm:px-5">
      No deliveries yet. Send a test event to check the endpoint.
    </p>
  </section>
</template>
