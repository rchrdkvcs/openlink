<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { ref } from 'vue';

import PublicStateLayout from '@/Components/PublicState/PublicStateLayout.vue';
import StateArt from '@/Components/PublicState/StateArt.vue';
import Badge from '@/Components/ui/Badge.vue';
import CopyCheckIcon from '@/Components/ui/CopyCheckIcon.vue';
import type { PayloadType } from '@/Pages/QrCodes/types';

const props = defineProps<{
  name: string;
  payloadType: PayloadType;
  payloadTypeLabel: string;
  content: string;
}>();

const copied = ref(false);

async function copyContent() {
  try {
    await navigator.clipboard.writeText(props.content);
    copied.value = true;
    setTimeout(() => (copied.value = false), 1500);
  } catch {}
}
</script>

<template>
  <Head :title="name" />

  <PublicStateLayout :title="name" tone="accent" wide>
    <template #art>
      <StateArt scene="payload" tone="accent" />
    </template>

    <template #badge>
      <Badge variant="accent">{{ payloadTypeLabel }}</Badge>
    </template>

    <div class="grid gap-4 text-left">
      <section class="overflow-hidden rounded-xl border bg-surface/90 backdrop-blur">
        <div class="flex items-center justify-between gap-3 border-b px-4 py-3">
          <p class="truncate text-[13px] font-medium text-foreground">Current payload</p>
          <button
            type="button"
            class="inline-flex h-8 shrink-0 items-center gap-1.5 rounded-lg border px-2.5 text-[13px] font-medium text-muted transition-colors hover:border-border-strong hover:text-foreground"
            @click="copyContent"
          >
            <CopyCheckIcon :copied="copied" />
            {{ copied ? 'Copied' : 'Copy' }}
          </button>
        </div>

        <pre
          class="max-h-[60vh] overflow-auto whitespace-pre-wrap break-words p-4 font-mono text-sm leading-relaxed text-muted"
          >{{ content }}</pre>
      </section>

      <p v-if="payloadType === 'wifi'" class="text-center text-[13px] leading-relaxed text-faint">
        Copy this Wi-Fi payload into a QR scanner or network setup tool if your device does not apply it automatically.
      </p>
    </div>
  </PublicStateLayout>
</template>
