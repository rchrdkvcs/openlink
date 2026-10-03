<script setup lang="ts">
import { ArrowRight, Check, Copy, PencilLine, QrCode, X } from '@lucide/vue';

import Button from '@/Components/ui/Button.vue';
import { createQrCodeFor } from '@/lib/linkActions';
import { displayUrl } from '@/lib/links';

import type { ComposerLink } from './composerLink';

defineProps<{ link: ComposerLink; copied: boolean }>();

const emit = defineEmits<{ copy: []; edit: []; dismiss: [] }>();
</script>

<template>
  <div class="flex flex-wrap items-center gap-2.5 border-t px-3.5 py-2.5">
    <span class="grid h-5 w-5 shrink-0 place-items-center rounded-full bg-success/15 text-success">
      <Check class="h-3.5 w-3.5" />
    </span>
    <div class="min-w-0 flex-1">
      <a
        :href="link.short_url"
        target="_blank"
        rel="noopener"
        class="block truncate text-[13px] font-semibold text-foreground hover:text-accent"
        >{{ displayUrl(link.short_url) }}</a
      >
      <p class="truncate text-xs text-faint">
        <ArrowRight class="mr-1 inline h-3 w-3" />{{ displayUrl(link.destination_url) }}
      </p>
    </div>
    <div class="flex shrink-0 items-center gap-1">
      <Button variant="secondary" size="sm" type="button" @click="emit('copy')">
        <component :is="copied ? Check : Copy" class="h-3.5 w-3.5" />
        {{ copied ? 'Copied' : 'Copy' }}
      </Button>
      <Button variant="ghost" size="sm" type="button" @click="emit('edit')">
        <PencilLine class="h-3.5 w-3.5" /> Details
      </Button>
      <Button variant="ghost" size="sm" type="button" class="hidden sm:inline-flex" @click="createQrCodeFor(link)">
        <QrCode class="h-3.5 w-3.5" /> QR code
      </Button>
      <button
        type="button"
        class="grid h-8 w-8 place-items-center rounded-lg text-faint transition-colors hover:bg-elevated hover:text-foreground"
        aria-label="Dismiss"
        @click="emit('dismiss')"
      >
        <X class="h-3.5 w-3.5" />
      </button>
    </div>
  </div>
</template>
