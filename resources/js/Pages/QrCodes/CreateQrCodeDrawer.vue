<script setup lang="ts">
import type { InertiaForm } from '@inertiajs/vue3';
import { ArrowRight, QrCode } from '@lucide/vue';

import Button from '@/Components/ui/Button.vue';
import Drawer from '@/Components/ui/Drawer.vue';
import Field from '@/Components/ui/Field.vue';

import PayloadFields from './PayloadFields.vue';
import type { PayloadDescriptors } from './types';
import { payloadHint, payloadIcon } from './types';

defineProps<{
  show: boolean;
  form: InertiaForm<{ name: string; payload_type: string; payload: Record<string, any> }>;
  payloadTypes: Record<string, string>;
  payloadDescriptors: PayloadDescriptors;
}>();

const emit = defineEmits<{ close: []; submit: []; setType: [type: string] }>();
</script>

<template>
  <Drawer :show="show" @close="emit('close')">
    <template #header>
      <div class="flex min-w-0 items-center gap-3">
        <span class="grid h-9 w-9 shrink-0 place-items-center rounded-lg border border-accent/30 bg-accent/10">
          <QrCode class="h-4 w-4 text-accent" />
        </span>
        <div class="min-w-0">
          <h3 class="text-[15px] font-semibold text-foreground">New QR code</h3>
          <p class="truncate text-xs text-faint">Pick what the code should do, then fill in its content.</p>
        </div>
      </div>
    </template>

    <form class="flex min-h-full flex-col" @submit.prevent="emit('submit')">
      <div class="flex-1 space-y-6 p-5">
        <div>
          <p class="mb-2 text-[13px] font-medium text-foreground">Type</p>
          <div class="flex flex-wrap gap-1.5" role="radiogroup" aria-label="QR code type">
            <button
              v-for="(label, type) in payloadTypes"
              :key="type"
              type="button"
              role="radio"
              :aria-checked="form.payload_type === type"
              class="inline-flex items-center gap-1.5 rounded-full border px-3 py-1.5 text-[13px] transition-colors"
              :class="
                form.payload_type === type
                  ? 'border-accent/60 bg-accent/15 font-medium text-foreground'
                  : 'text-muted hover:border-accent/40 hover:bg-accent/5 hover:text-foreground'
              "
              @click="emit('setType', type as string)"
            >
              <component
                :is="payloadIcon(type as string)"
                class="h-3.5 w-3.5"
                :class="form.payload_type === type ? 'text-accent' : ''"
              />
              {{ label }}
            </button>
          </div>
          <p class="mt-2 text-xs" :class="form.errors.payload_type ? 'text-danger' : 'text-faint'">
            {{ form.errors.payload_type ?? payloadHint(form.payload_type, payloadDescriptors) }}
          </p>
        </div>

        <PayloadFields
          v-model="form.payload"
          :type="form.payload_type"
          :descriptors="payloadDescriptors"
          :errors="form.errors"
        />

        <Field label="Name" :error="form.errors.name">
          <input v-model="form.name" class="h-9" placeholder="Wi-Fi lobby, business card, event…" />
        </Field>
      </div>

      <footer class="sticky bottom-0 flex shrink-0 items-center justify-end gap-2 border-t bg-overlay px-5 py-4">
        <Button variant="secondary" type="button" @click="emit('close')">Cancel</Button>
        <Button :loading="form.processing" :disabled="form.processing">
          Create QR code
          <ArrowRight class="h-3.5 w-3.5" />
        </Button>
      </footer>
    </form>
  </Drawer>
</template>
