<script setup lang="ts">
import { Head, usePage } from '@inertiajs/vue3';
import { Copy, Plus, Webhook, X } from '@lucide/vue';
import { computed, ref, watch } from 'vue';

import Button from '@/Components/ui/Button.vue';
import EmptyState from '@/Components/ui/EmptyState.vue';
import IconButton from '@/Components/ui/IconButton.vue';
import SettingsGroup from '@/Components/ui/SettingsGroup.vue';
import SettingsLayout from '@/Layouts/SettingsLayout.vue';
import { useShell } from '@/lib/shell';
import { copyToClipboard } from '@/lib/toast';
import type { WebhookEndpoint, WebhookEventOption } from '@/types/payloads';

import EndpointDialog from './EndpointDialog.vue';
import EndpointGroup from './EndpointGroup.vue';

const props = defineProps<{
  endpoints: WebhookEndpoint[];
  eventTypes: WebhookEventOption[];
  historyLimit: number;
  retentionDays: number;
}>();

type RevealedSecret = { endpoint_id: number; secret: string };

const page = usePage();
const { workspace } = useShell();

const dialogOpen = ref(false);
const editing = ref<WebhookEndpoint | null>(null);
const revealed = ref<RevealedSecret | null>(null);

const revealedUrl = computed(
  () => props.endpoints.find((endpoint) => endpoint.id === revealed.value?.endpoint_id)?.url ?? '',
);

watch(
  () => (page.flash as { webhookSecret?: RevealedSecret }).webhookSecret,
  (secret) => {
    if (secret) revealed.value = secret;
  },
  { immediate: true },
);

function create() {
  editing.value = null;
  dialogOpen.value = true;
}

function edit(endpoint: WebhookEndpoint) {
  editing.value = endpoint;
  dialogOpen.value = true;
}
</script>

<template>
  <Head title="Webhooks" />

  <SettingsLayout
    title="Webhooks"
    :description="`Notify other tools when short links change in ${workspace?.name ?? 'this workspace'}.`"
  >
    <template #actions>
      <Button type="button" @click="create"><Plus class="h-4 w-4" /> Add endpoint</Button>
    </template>

    <div v-if="revealed" role="status" class="rounded-xl border border-success/25 bg-success/10 p-4 sm:p-5">
      <div class="flex items-start gap-3">
        <div class="min-w-0 flex-1">
          <p class="text-sm font-medium text-foreground">Signing secret</p>
          <p class="mt-0.5 text-[13px] text-muted">
            Use it to verify deliveries to <span class="break-all font-mono">{{ revealedUrl }}</span
            >. Copy it now. It won't be shown again.
          </p>
        </div>
        <IconButton title="Dismiss" @click="revealed = null"><X /></IconButton>
      </div>
      <div class="mt-3 flex items-center gap-2">
        <code
          class="min-w-0 flex-1 truncate rounded-lg border bg-background px-3 py-2 font-mono text-[13px] text-foreground"
          :title="revealed.secret"
          >{{ revealed.secret }}</code
        >
        <Button type="button" @click="copyToClipboard(revealed.secret, 'Signing secret copied')">
          <Copy class="h-4 w-4" /> Copy
        </Button>
      </div>
    </div>

    <SettingsGroup v-if="endpoints.length === 0">
      <EmptyState
        title="No webhook endpoints"
        description="Add an endpoint to receive a signed request when a short link is created, updated or archived."
      >
        <template #icon><Webhook class="h-5 w-5" /></template>
        <template #action>
          <Button variant="secondary" type="button" @click="create"><Plus class="h-4 w-4" /> Add endpoint</Button>
        </template>
      </EmptyState>
    </SettingsGroup>

    <div v-else class="space-y-4">
      <EndpointGroup
        v-for="endpoint in endpoints"
        :key="endpoint.id"
        :endpoint="endpoint"
        :event-types="eventTypes"
        @edit="edit"
      />
      <p class="px-1 text-xs leading-relaxed text-faint">
        Each endpoint shows its last {{ historyLimit }} deliveries. Delivery history older than {{ retentionDays }} days
        is deleted. Failed deliveries are retried automatically with increasing delays.
      </p>
    </div>

    <EndpointDialog v-model:open="dialogOpen" :endpoint="editing" :event-types="eventTypes" />
  </SettingsLayout>
</template>
