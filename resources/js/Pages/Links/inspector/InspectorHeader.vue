<script setup lang="ts">
import { Copy, ExternalLink, QrCode, X } from '@lucide/vue';

import Favicon from '@/Components/Links/Favicon.vue';
import Button from '@/Components/ui/Button.vue';
import IconButton from '@/Components/ui/IconButton.vue';
import { createQrCodeFor } from '@/lib/linkActions';
import { displayUrl } from '@/lib/links';
import { useShell } from '@/lib/shell';
import { copyToClipboard } from '@/lib/toast';
import type { ShortLink } from '@/types/shortLinks';

defineProps<{ link: ShortLink; previewUrl: string }>();

const emit = defineEmits<{ close: [] }>();

const { canEdit } = useShell();
</script>

<template>
  <header class="flex items-center gap-3 px-5 pb-4 pt-5">
    <Favicon :url="previewUrl || link.destination_url" />
    <div class="min-w-0 flex-1">
      <a
        :href="link.short_url"
        target="_blank"
        rel="noopener"
        class="block truncate text-sm font-semibold text-foreground hover:text-accent"
        >{{ displayUrl(link.short_url) }}</a
      >
      <p class="truncate text-xs text-faint">
        {{ displayUrl(link.destination_url) }}
      </p>
    </div>
    <IconButton title="Close" @click="emit('close')"><X class="h-4 w-4" /></IconButton>
  </header>

  <div class="flex items-center gap-1.5 border-b px-5 pb-3">
    <Button variant="secondary" size="sm" type="button" class="flex-1" @click="copyToClipboard(link.short_url)">
      <Copy class="h-3.5 w-3.5" /> Copy
    </Button>
    <a
      :href="link.short_url"
      target="_blank"
      rel="noopener"
      class="inline-flex h-7 flex-1 items-center justify-center gap-1.5 rounded-lg bg-elevated px-2.5 text-[13px] font-medium text-foreground transition-colors hover:bg-border-strong"
    >
      <ExternalLink class="h-3.5 w-3.5" /> Open
    </a>
    <Button v-if="canEdit" variant="secondary" size="sm" type="button" class="flex-1" @click="createQrCodeFor(link)">
      <QrCode class="h-3.5 w-3.5" /> QR code
    </Button>
  </div>
</template>
