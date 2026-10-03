<script setup lang="ts">
import { ExternalLink, QrCode } from '@lucide/vue';
import { computed, ref, watch } from 'vue';

import Favicon from '@/Components/Links/Favicon.vue';
import SectionCard from '@/Components/ui/SectionCard.vue';
import SegmentedControl from '@/Components/ui/SegmentedControl.vue';
import { formatNumber, type TopLink, type TopQrCode } from '@/lib/analytics';
import { displayUrl } from '@/lib/links';

const props = defineProps<{
  links: TopLink[];
  qrCodes: TopQrCode[];
}>();

const emit = defineEmits<{ selectLink: [id: string]; selectQr: [id: string] }>();

const view = ref<'links' | 'qr'>('links');
const VIEWS = [
  { value: 'links' as const, label: 'Links' },
  { value: 'qr' as const, label: 'QR codes' },
];
const showQrRanking = computed(() => props.qrCodes.length > 0);

watch(showQrRanking, (visible) => {
  if (!visible) view.value = 'links';
});
</script>

<template>
  <SectionCard :title="view === 'qr' ? 'Top QR codes' : 'Top links'" class="flex flex-col md:col-span-2 lg:col-span-6">
    <template #header>
      <SegmentedControl v-if="showQrRanking" v-model="view" :options="VIEWS" size="sm" label="Ranking" />
      <span v-else class="flex h-7 items-center text-xs text-faint">By visits and scans</span>
    </template>

    <div class="h-72 overflow-y-auto">
      <table v-if="view === 'links' && links.length > 0" class="w-full text-[13px]">
        <thead class="sticky top-0 z-10 bg-surface text-xs text-faint">
          <tr>
            <th class="py-2 pe-3 ps-4 text-start font-medium">Link</th>
            <th class="px-2 py-2 text-end font-medium">Visits</th>
            <th class="px-2 py-2 text-end font-medium">Scans</th>
            <th class="py-2 pe-4 ps-2 text-end font-medium">Visitors</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="link in links" :key="link.id" class="group border-t">
            <td class="max-w-0 py-1.5 pe-3 ps-4">
              <div class="flex items-center gap-2.5">
                <Favicon :url="link.destination_url ?? ''" size="sm" />
                <div class="min-w-0 flex-1">
                  <button
                    type="button"
                    class="block max-w-full truncate rounded font-medium text-foreground outline-none hover:text-accent focus-visible:ring-2 focus-visible:ring-accent/40"
                    :title="`Filter by /${link.slug}`"
                    @click="emit('selectLink', String(link.id))"
                  >
                    {{ link.short_url ? displayUrl(link.short_url) : `/${link.slug}` }}
                  </button>
                  <p class="truncate text-xs text-faint">
                    {{ link.destination_url ? displayUrl(link.destination_url) : '' }}
                  </p>
                </div>
                <a
                  v-if="link.short_url"
                  :href="link.short_url"
                  target="_blank"
                  rel="noopener"
                  class="grid h-6 w-6 shrink-0 place-items-center rounded-md text-faint opacity-0 transition-opacity hover:bg-elevated hover:text-foreground focus-visible:opacity-100 group-hover:opacity-100"
                  :aria-label="`Open /${link.slug}`"
                >
                  <ExternalLink class="h-3.5 w-3.5" />
                </a>
              </div>
            </td>
            <td class="px-2 py-1.5 text-end font-medium tabular-nums">{{ formatNumber(link.visits) }}</td>
            <td class="px-2 py-1.5 text-end tabular-nums text-muted">{{ formatNumber(link.scans) }}</td>
            <td class="py-1.5 pe-4 ps-2 text-end tabular-nums text-muted">{{ formatNumber(link.visitors) }}</td>
          </tr>
        </tbody>
      </table>

      <div v-else-if="view === 'qr'" class="space-y-0.5 p-2">
        <button
          v-for="qr in qrCodes"
          :key="qr.id"
          type="button"
          class="flex w-full items-center gap-3 rounded-lg px-2.5 py-1.5 text-start text-[13px] outline-none transition-colors hover:bg-elevated focus-visible:ring-2 focus-visible:ring-accent/40"
          :title="`Filter by ${qr.name}`"
          @click="emit('selectQr', String(qr.id))"
        >
          <QrCode class="h-3.5 w-3.5 shrink-0 text-faint" />
          <span class="min-w-0 flex-1">
            <span class="block truncate font-medium text-foreground">{{ qr.name }}</span>
            <span v-if="qr.link_slug" class="block truncate text-xs text-faint">/{{ qr.link_slug }}</span>
          </span>
          <span class="tabular-nums text-muted">{{ formatNumber(qr.scans) }}</span>
        </button>
      </div>

      <div v-else class="flex h-full items-center justify-center px-6">
        <p class="text-[13px] text-faint">No link traffic in this period.</p>
      </div>
    </div>
  </SectionCard>
</template>
