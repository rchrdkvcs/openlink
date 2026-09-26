<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { Download, Plus, QrCode } from '@lucide/vue';
import { ref } from 'vue';

import Badge from '@/Components/ui/Badge.vue';
import Button from '@/Components/ui/Button.vue';
import EmptyState from '@/Components/ui/EmptyState.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';

import CreateQrCodeDrawer from './CreateQrCodeDrawer.vue';
import type { PayloadDescriptors, QrCodeRecord, ShortLinkOption } from './types';
import { payloadDefaults, payloadIcon } from './types';

const props = defineProps<{
  qrCodes: QrCodeRecord[];
  payloadTypes: Record<string, string>;
  payloadDescriptors: PayloadDescriptors;
  shortLinks: ShortLinkOption[];
  canEditWorkspace: boolean;
}>();

const createOpen = ref(false);

const form = useForm({
  name: '',
  target_type: 'short_link',
  short_link_id: '' as string | number,
  payload_type: 'url',
  payload: payloadDefaults('url', props.payloadDescriptors),
});

function setPayloadType(type: string) {
  if (type === 'short_link') {
    form.target_type = 'short_link';
    form.clearErrors();
    return;
  }
  form.target_type = 'direct';
  if (type === form.payload_type) {
    return;
  }
  form.payload_type = type;
  form.payload = payloadDefaults(type, props.payloadDescriptors);
  form.clearErrors();
}

function submit() {
  form
    .transform((data) =>
      data.target_type === 'short_link'
        ? { name: data.name, short_link_id: data.short_link_id }
        : { name: data.name, payload_type: data.payload_type, payload: data.payload },
    )
    .post(route('qr-codes.store'), {
      preserveScroll: true,
      onSuccess: () => {
        form.reset();
        form.payload = payloadDefaults('url', props.payloadDescriptors);
        form.target_type = 'short_link';
        createOpen.value = false;
      },
    });
}
</script>

<template>
  <Head title="QR codes" />

  <AuthenticatedLayout>
    <div class="ui-page">
      <div class="mb-6 flex flex-wrap items-end justify-between gap-3">
        <div>
          <h1 class="text-xl font-semibold tracking-tight">QR Codes</h1>
          <p class="mt-1 max-w-2xl text-sm text-muted">
            Scannable codes for web pages, Wi-Fi, contact cards, events and more.
          </p>
        </div>
        <Button v-if="canEditWorkspace" @click="createOpen = true">
          <Plus class="h-4 w-4" />
          New QR code
        </Button>
      </div>

      <EmptyState
        v-if="qrCodes.length === 0"
        title="No QR Codes yet"
        description="Create a tracked QR Code for a Short Link or encode a native payload such as Wi-Fi or a contact card."
      >
        <template #icon><QrCode class="h-5 w-5 text-faint" /></template>
        <template v-if="canEditWorkspace" #action>
          <Button @click="createOpen = true">
            <Plus class="h-4 w-4" />
            New QR code
          </Button>
        </template>
      </EmptyState>

      <div v-else class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 2xl:grid-cols-4">
        <article
          v-for="qr in qrCodes"
          :key="qr.id"
          class="ui-panel group relative grid overflow-hidden transition-colors hover:border-border-strong"
        >
          <div class="relative grid place-items-center border-b bg-white p-6">
            <img
              :src="route('qr-codes.preview', qr.token)"
              :alt="qr.name"
              class="h-32 w-32 object-contain"
              loading="lazy"
            />
            <div
              class="absolute inset-x-0 bottom-0 z-10 flex justify-center gap-1.5 bg-gradient-to-t from-black/40 to-transparent p-2 transition-opacity focus-within:opacity-100 group-hover:opacity-100 [@media(hover:hover)]:opacity-0"
            >
              <a
                v-for="format in ['svg', 'png']"
                :key="format"
                :href="route('qr-codes.export', [qr.token, format])"
                class="inline-flex min-h-10 items-center gap-1 rounded-md bg-white/90 px-2 text-xs font-medium text-zinc-900 shadow hover:bg-white"
                :aria-label="`Download ${qr.name} as ${format.toUpperCase()}`"
              >
                <Download class="h-3 w-3" /> {{ format.toUpperCase() }}
              </a>
            </div>
          </div>
          <div class="grid min-w-0 gap-2 p-5">
            <div class="flex min-w-0 flex-wrap items-center justify-between gap-2">
              <h2 class="min-w-0 text-sm font-semibold text-foreground">
                <Link
                  :href="route('qr-codes.show', qr.token)"
                  class="block truncate after:absolute after:inset-0 after:rounded-2xl focus-visible:outline-none focus-visible:after:ring-2 focus-visible:after:ring-accent"
                  >{{ qr.name }}</Link
                >
              </h2>
              <Badge class="inline-flex shrink-0 items-center gap-1">
                <QrCode v-if="!qr.is_direct" class="h-3 w-3" />
                <component v-else :is="payloadIcon(qr.payload_type ?? 'raw')" class="h-3 w-3" />
                {{ qr.is_direct ? (payloadTypes[qr.payload_type ?? ''] ?? qr.payload_type) : 'Short Link' }}
              </Badge>
            </div>
            <p class="truncate font-mono text-xs text-faint">{{ qr.short_link?.short_url ?? qr.content }}</p>
          </div>
        </article>
      </div>
    </div>

    <CreateQrCodeDrawer
      :show="createOpen"
      :form="form"
      :payload-types="payloadTypes"
      :payload-descriptors="payloadDescriptors"
      :short-links="shortLinks"
      @close="createOpen = false"
      @set-type="setPayloadType"
      @submit="submit"
    />
  </AuthenticatedLayout>
</template>
