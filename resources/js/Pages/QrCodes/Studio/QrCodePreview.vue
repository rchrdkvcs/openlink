<script setup lang="ts">
import { Copy, Download } from '@lucide/vue';
import { computed } from 'vue';

import Button from '@/Components/ui/Button.vue';
import Select from '@/Components/ui/Select.vue';
import { displayUrl } from '@/lib/links';
import { copyToClipboard } from '@/lib/toast';

import { EXPORT_SIZE_OPTIONS } from '../qrOptions';
import { downloadQrCode } from '../qrUrls';
import type { ExportFormat, TargetType } from '../types';

const props = defineProps<{
  qr: { name: string; token: string; public_url: string };
  src: string;
  targetType: TargetType;
  backgroundColor: string;
  transparent: boolean;
  pendingLogo: boolean;
}>();

const exportSize = defineModel<number>('exportSize', { required: true });

const backdrop = computed(() =>
  props.transparent
    ? {
        backgroundImage: 'repeating-conic-gradient(rgba(128,128,128,0.18) 0% 25%, transparent 0% 50%)',
        backgroundSize: '20px 20px',
      }
    : { backgroundColor: props.backgroundColor },
);

function download(format: ExportFormat) {
  downloadQrCode(props.qr.token, format, exportSize.value);
}
</script>

<template>
  <div class="w-full max-w-[380px]">
    <div class="rounded-2xl border bg-surface p-2">
      <div class="grid place-items-center rounded-xl p-2" :style="backdrop">
        <img :src="src" :alt="`${qr.name} QR code preview`" class="aspect-square w-full" />
      </div>
    </div>
    <p v-if="pendingLogo" class="pt-2.5 text-center text-xs text-faint">Save to see the new logo.</p>

    <button
      type="button"
      class="group mt-4 flex w-full items-center gap-3 rounded-lg px-3 py-2 text-left transition-colors hover:bg-elevated/60"
      @click="copyToClipboard(qr.public_url, 'Public URL copied')"
    >
      <span class="min-w-0 flex-1">
        <span class="block text-xs text-faint">{{ targetType === 'short_link' ? 'Tracked URL' : 'Public URL' }}</span>
        <span class="block truncate text-[13px] text-foreground">{{ displayUrl(qr.public_url) }}</span>
      </span>
      <Copy class="h-3.5 w-3.5 shrink-0 text-faint transition-colors group-hover:text-foreground" />
    </button>

    <div class="mt-3 flex items-center gap-2">
      <Select v-model="exportSize" :options="EXPORT_SIZE_OPTIONS" size="sm" aria-label="Export size" class="w-28" />
      <div class="ml-auto flex gap-1.5">
        <Button variant="secondary" size="sm" type="button" @click="download('svg')"> <Download /> SVG </Button>
        <Button size="sm" type="button" @click="download('png')"><Download /> PNG</Button>
      </div>
    </div>
  </div>
</template>
