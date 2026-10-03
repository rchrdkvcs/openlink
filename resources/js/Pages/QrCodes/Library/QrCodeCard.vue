<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { Download, Link2, MoreHorizontal, SlidersHorizontal } from '@lucide/vue';

import Menu from '@/Components/ui/Menu.vue';
import MenuItem from '@/Components/ui/MenuItem.vue';
import MenuSeparator from '@/Components/ui/MenuSeparator.vue';

import { cardSubtitle, compactCount, pluralizeScans } from '../qrLabels';
import { payloadIcon } from '../qrOptions';
import { downloadQrCode, thumbnailUrl } from '../qrUrls';
import type { QrCodeRecord } from '../types';

defineProps<{
  qr: QrCodeRecord;
  payloadTypes: Record<string, string>;
}>();
</script>

<template>
  <li
    class="group relative flex flex-col rounded-xl border bg-surface p-1.5 transition-[border-color] duration-150 focus-within:border-border-strong hover:border-border-strong"
  >
    <div class="grid aspect-square place-items-center rounded-lg bg-white p-1.5">
      <img
        :src="thumbnailUrl(qr.token)"
        :alt="`${qr.name} QR code`"
        class="h-full w-full object-contain"
        loading="lazy"
      />
    </div>
    <div class="flex items-start gap-2 px-2 pb-1.5 pt-2.5">
      <div class="min-w-0 flex-1">
        <Link
          :href="route('qr-codes.show', qr.token)"
          class="block truncate text-sm font-medium text-foreground outline-none after:absolute after:inset-0 after:rounded-xl"
        >
          {{ qr.name }}
        </Link>
        <p class="mt-0.5 flex items-center gap-1.5 text-xs text-faint">
          <Link2 v-if="!qr.is_direct" class="h-3 w-3 shrink-0" />
          <component :is="payloadIcon(qr.payload_type ?? 'raw')" v-else class="h-3 w-3 shrink-0" />
          <span class="truncate">{{ cardSubtitle(qr, payloadTypes) }}</span>
        </p>
        <p v-if="qr.is_direct" class="mt-1.5 text-xs text-faint">Scans not tracked</p>
        <p v-else class="mt-1.5 text-xs tabular-nums text-muted">
          <span class="font-medium text-foreground">{{ compactCount(qr.scans ?? 0) }}</span>
          {{ pluralizeScans(qr.scans) }}
        </p>
      </div>
      <Menu width="w-48">
        <template #trigger>
          <button
            type="button"
            class="relative z-10 -mr-1 grid h-7 w-7 shrink-0 place-items-center rounded-lg text-faint transition-colors hover:bg-elevated hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent/25 data-[state=open]:bg-elevated data-[state=open]:text-foreground"
            :aria-label="`Actions for ${qr.name}`"
          >
            <MoreHorizontal class="h-4 w-4" />
          </button>
        </template>
        <MenuItem :icon="SlidersHorizontal" @select="router.visit(route('qr-codes.show', qr.token))">
          Open studio
        </MenuItem>
        <MenuSeparator />
        <MenuItem :icon="Download" @select="downloadQrCode(qr.token, 'png')">Download PNG</MenuItem>
        <MenuItem :icon="Download" @select="downloadQrCode(qr.token, 'svg')">Download SVG</MenuItem>
      </Menu>
    </div>
  </li>
</template>
