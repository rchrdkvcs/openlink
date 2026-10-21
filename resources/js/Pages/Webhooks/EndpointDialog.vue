<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { computed, watch } from 'vue';

import Button from '@/Components/ui/Button.vue';
import Checkbox from '@/Components/ui/Checkbox.vue';
import Dialog from '@/Components/ui/Dialog.vue';
import Field from '@/Components/ui/Field.vue';
import Input from '@/Components/ui/Input.vue';
import { toast } from '@/lib/toast';
import type { WebhookEndpoint, WebhookEventOption } from '@/types/payloads';

import { endpointRequest, toggleEvent } from './webhooks';

const props = defineProps<{ endpoint: WebhookEndpoint | null; eventTypes: WebhookEventOption[] }>();
const open = defineModel<boolean>('open', { required: true });

const form = useForm({ url: '', events: [] as string[] });
const editing = computed(() => props.endpoint !== null);
const eventsError = computed(
  () => form.errors.events ?? Object.entries(form.errors).find(([key]) => key.startsWith('events.'))?.[1],
);

watch(open, (value) => {
  if (!value) return;
  form.defaults({
    url: props.endpoint?.url ?? '',
    events: props.endpoint ? [...props.endpoint.events] : props.eventTypes.map((type) => type.value),
  });
  form.reset();
  form.clearErrors();
});

function submit() {
  const options = {
    preserveScroll: true,
    onSuccess: () => {
      open.value = false;
      if (editing.value) toast({ title: 'Endpoint updated', tone: 'success' });
    },
  };

  if (props.endpoint) {
    form
      .transform((data) => ({ ...endpointRequest(data), is_enabled: props.endpoint?.is_enabled ?? true }))
      .patch(route('webhook-endpoints.update', props.endpoint.id), options);
  } else {
    form.transform((data) => endpointRequest(data)).post(route('webhook-endpoints.store'), options);
  }
}
</script>

<template>
  <Dialog
    v-model:open="open"
    size="lg"
    :title="editing ? 'Edit endpoint' : 'Add endpoint'"
    description="Openlink sends a signed POST request to this URL when a selected event happens."
  >
    <form class="space-y-5 px-5 pb-5 pt-4" @submit.prevent="submit">
      <Field label="Endpoint URL" :error="form.errors.url" hint="Must use HTTPS and be reachable from the internet.">
        <Input
          v-model="form.url"
          type="url"
          inputmode="url"
          autocomplete="off"
          spellcheck="false"
          placeholder="https://hooks.example.com/openlink"
          autofocus
        />
      </Field>

      <fieldset>
        <legend class="mb-2 text-[13px] font-medium text-foreground">Events</legend>
        <div class="grid gap-1.5">
          <label
            v-for="type in eventTypes"
            :key="type.value"
            class="flex cursor-pointer items-center gap-3 rounded-lg border border-border px-3 py-2.5 transition-colors hover:border-border-strong hover:bg-elevated/40"
          >
            <Checkbox
              :model-value="form.events.includes(type.value)"
              @update:model-value="form.events = toggleEvent(form.events, type.value)"
            />
            <span class="min-w-0 flex-1">
              <span class="block text-[13px] font-medium text-foreground">{{ type.label }}</span>
              <span class="block font-mono text-xs text-faint">{{ type.value }}</span>
            </span>
          </label>
        </div>
        <p v-if="eventsError" class="mt-1.5 text-xs text-danger">{{ eventsError }}</p>
      </fieldset>

      <div class="flex justify-end gap-2 pt-1">
        <Button variant="ghost" type="button" @click="open = false">Cancel</Button>
        <Button :loading="form.processing">{{ editing ? 'Save endpoint' : 'Add endpoint' }}</Button>
      </div>
    </form>
  </Dialog>
</template>
