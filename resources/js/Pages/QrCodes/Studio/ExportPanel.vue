<script setup lang="ts">
import { Copy, Download } from '@lucide/vue';

import Button from '@/Components/ui/Button.vue';
import Field from '@/Components/ui/Field.vue';
import Select from '@/Components/ui/Select.vue';
import { displayUrl } from '@/lib/links';
import { copyToClipboard } from '@/lib/toast';

import { EXPORT_SIZE_OPTIONS } from '../qrOptions';
import { downloadQrCode } from '../qrUrls';
import type { ExportFormat, TargetType } from '../types';

const props = defineProps<{
  qr: { token: string; public_url: string };
  targetType: TargetType;
  unsaved: boolean;
}>();

const exportSize = defineModel<number>('exportSize', { required: true });

function download(format: ExportFormat) {
  downloadQrCode(props.qr.token, format, exportSize.value);
}
</script>

<template>
  <section class="grid min-w-0 gap-4" aria-labelledby="export-heading">
    <h2 id="export-heading" class="text-sm font-medium text-foreground">Export and sharing</h2>
    <button
      type="button"
      class="group flex min-w-0 items-center gap-3 rounded-lg border bg-surface px-3 py-2 text-left transition-colors hover:bg-elevated/60 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent/40"
      :aria-label="`Copy ${targetType === 'short_link' ? 'tracked' : 'public'} URL`"
      @click="copyToClipboard(qr.public_url, 'Public URL copied')"
    >
      <span class="min-w-0 flex-1">
        <span class="block text-xs text-faint">{{ targetType === 'short_link' ? 'Tracked URL' : 'Public URL' }}</span>
        <span class="block break-all text-[13px] text-foreground">{{ displayUrl(qr.public_url) }}</span>
      </span>
      <Copy class="h-3.5 w-3.5 shrink-0 text-faint transition-colors group-hover:text-foreground" />
    </button>
    <Field label="Export size" hint="Applies to downloads and the readability check.">
      <Select v-model="exportSize" :options="EXPORT_SIZE_OPTIONS" size="sm" aria-label="Export size" />
    </Field>
    <p v-if="unsaved" class="text-xs text-warning">
      Downloads use the saved QR code. Save your changes before exporting.
    </p>
    <div class="flex flex-wrap gap-2">
      <Button variant="secondary" size="sm" type="button" @click="download('svg')"><Download /> SVG</Button>
      <Button size="sm" type="button" @click="download('png')"><Download /> PNG</Button>
    </div>
  </section>
</template>
