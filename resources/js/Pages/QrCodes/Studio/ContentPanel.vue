<script setup lang="ts">
import { AlertTriangle } from '@lucide/vue';
import { computed } from 'vue';

import Field from '@/Components/ui/Field.vue';
import Input from '@/Components/ui/Input.vue';
import SegmentedControl from '@/Components/ui/SegmentedControl.vue';
import { displayUrl } from '@/lib/links';

import PayloadEditor from '../PayloadEditor.vue';
import { TARGET_OPTIONS } from '../qrOptions';
import ShortLinkPicker from '../ShortLinkPicker.vue';
import type { PayloadDescriptors, ShortLinkOption } from '../types';
import { useShortLinkSearch } from '../useShortLinkSearch';
import type { QrEditorForm } from './useQrCodeEditor';

const props = defineProps<{
  form: QrEditorForm;
  qr: { is_direct: boolean; content: string | null };
  payloadTypes: Record<string, string>;
  payloadDescriptors: PayloadDescriptors;
  shortLinks: ShortLinkOption[];
}>();

const originalWasDirect = props.qr.is_direct;

const { search, links } = useShortLinkSearch(
  props.shortLinks,
  computed(() => props.form.short_link_id),
);

const selectedShortLink = computed(() => links.value.find((link) => link.id === Number(props.form.short_link_id)));

const changesTarget = computed(() => originalWasDirect !== (props.form.target_type === 'direct'));
</script>

<template>
  <div class="grid gap-5">
    <Field label="Name" :error="form.errors.name">
      <Input v-model="form.name" />
    </Field>

    <div class="grid gap-1.5">
      <span class="text-[13px] font-medium text-foreground">Opens</span>
      <SegmentedControl v-model="form.target_type" :options="TARGET_OPTIONS" label="Target" class="w-full" />
    </div>

    <template v-if="form.target_type === 'short_link'">
      <ShortLinkPicker
        v-model="form.short_link_id"
        v-model:search="search"
        :links="links"
        :error="form.errors.short_link_id"
      />
      <p v-if="selectedShortLink" class="-mt-2 truncate text-xs text-faint">
        Scans redirect to {{ displayUrl(selectedShortLink.destination_url) }}
      </p>
    </template>

    <template v-else>
      <PayloadEditor :form="form" :payload-types="payloadTypes" :payload-descriptors="payloadDescriptors" />
      <details v-if="qr.is_direct && qr.content" class="group rounded-lg bg-surface">
        <summary class="cursor-pointer select-none px-3 py-2 text-[13px] text-muted hover:text-foreground">
          Encoded content
        </summary>
        <pre
          class="max-h-60 overflow-auto whitespace-pre-wrap break-words border-t px-3 py-2.5 font-mono text-xs text-muted"
          >{{ qr.content }}</pre>
      </details>
    </template>

    <div
      v-if="changesTarget"
      class="flex gap-2.5 rounded-lg bg-warning/10 px-3 py-2.5 text-[13px] leading-relaxed text-warning"
    >
      <AlertTriangle class="mt-0.5 h-4 w-4 shrink-0" />
      <p>Codes you’ve already exported won’t update. Export and reprint this QR code after saving.</p>
    </div>
  </div>
</template>
