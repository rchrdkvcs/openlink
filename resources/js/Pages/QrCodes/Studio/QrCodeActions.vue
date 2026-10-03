<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Copy, Download, MoreHorizontal, Trash2 } from '@lucide/vue';

import Button from '@/Components/ui/Button.vue';
import Menu from '@/Components/ui/Menu.vue';
import MenuItem from '@/Components/ui/MenuItem.vue';
import MenuSeparator from '@/Components/ui/MenuSeparator.vue';
import { confirmAction } from '@/lib/confirm';
import { useShell } from '@/lib/shell';
import { copyToClipboard } from '@/lib/toast';

import { downloadQrCode } from '../qrUrls';
import type { ExportFormat } from '../types';

const props = defineProps<{
  qr: { name: string; token: string; public_url: string };
  exportSize: number;
}>();

const { canEdit } = useShell();

function download(format: ExportFormat) {
  downloadQrCode(props.qr.token, format, props.exportSize);
}

async function destroy() {
  const confirmed = await confirmAction({
    title: `Delete “${props.qr.name}”?`,
    message: 'Exported and printed copies that point to this code will stop resolving.',
    confirmLabel: 'Delete QR code',
    destructive: true,
  });
  if (confirmed) router.delete(route('qr-codes.destroy', props.qr.token));
}
</script>

<template>
  <Menu width="w-52">
    <template #trigger>
      <Button variant="secondary" size="sm" type="button" class="w-7 px-0" aria-label="More actions">
        <MoreHorizontal />
      </Button>
    </template>
    <MenuItem :icon="Copy" @select="copyToClipboard(qr.public_url, 'Public URL copied')">Copy public URL</MenuItem>
    <MenuItem :icon="Download" @select="download('png')">Download PNG</MenuItem>
    <MenuItem :icon="Download" @select="download('svg')">Download SVG</MenuItem>
    <template v-if="canEdit">
      <MenuSeparator />
      <MenuItem :icon="Trash2" destructive @select="destroy">Delete QR code</MenuItem>
    </template>
  </Menu>
</template>
