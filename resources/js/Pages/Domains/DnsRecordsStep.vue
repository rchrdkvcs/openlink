<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Info, RefreshCw } from '@lucide/vue';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';

import Button from '@/Components/ui/Button.vue';
import { dnsRecordRequirements } from '@/lib/domains';
import type { DomainEdge, DomainSetup } from '@/types/payloads';

import DnsRecordCard from './DnsRecordCard.vue';

const props = defineProps<{ domain: DomainSetup; edge: DomainEdge }>();

const pollIntervalMs = 15000;

const records = computed(() => dnsRecordRequirements(props.domain, props.edge));
const checking = ref(false);

function checkNow() {
  if (checking.value) return;
  checking.value = true;
  router.post(
    route('domains.verify', props.domain.id),
    {},
    { preserveScroll: true, onFinish: () => (checking.value = false) },
  );
}

let pollTimer: ReturnType<typeof setInterval> | null = null;

onMounted(() => {
  pollTimer = setInterval(() => {
    if (!document.hidden) checkNow();
  }, pollIntervalMs);
});

onBeforeUnmount(() => {
  if (pollTimer) clearInterval(pollTimer);
});
</script>

<template>
  <p class="text-sm text-muted">
    Sign in where you bought the domain (Cloudflare, OVH, GoDaddy, Namecheap…), open its DNS settings and add both
    records below.
  </p>

  <DnsRecordCard v-for="record in records" :key="record.key" :record="record" />

  <div
    v-if="edge === 'direct'"
    role="note"
    class="flex items-start gap-2.5 rounded-xl border bg-surface px-4 py-3 text-[13px] leading-relaxed text-muted"
  >
    <Info class="mt-0.5 h-4 w-4 shrink-0 text-faint" />
    <p>
      DNS records alone do not enable HTTPS on this instance. Your instance administrator also has to add
      <span class="font-medium text-foreground">{{ domain.hostname }}</span> to the server so it can serve your short
      links securely.
    </p>
  </div>

  <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
    <p class="text-xs leading-relaxed text-faint">
      Checked automatically every 15 seconds. DNS changes usually apply within minutes, but can take up to 24 hours.
    </p>
    <Button variant="secondary" size="sm" type="button" class="shrink-0" :loading="checking" @click="checkNow">
      <RefreshCw v-if="!checking" class="h-3.5 w-3.5" /> Check now
    </Button>
  </div>
</template>
