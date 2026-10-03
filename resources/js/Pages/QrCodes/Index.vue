<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Download, Link2, MoreHorizontal, Plus, QrCode, Search, SlidersHorizontal } from '@lucide/vue';
import { computed, onUnmounted, ref, watch } from 'vue';

import Button from '@/Components/ui/Button.vue';
import EmptyState from '@/Components/ui/EmptyState.vue';
import Menu from '@/Components/ui/Menu.vue';
import MenuItem from '@/Components/ui/MenuItem.vue';
import MenuSeparator from '@/Components/ui/MenuSeparator.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { displayUrl } from '@/lib/links';

import CreateQrCodeDialog from './CreateQrCodeDialog.vue';
import type { PayloadDescriptors, QrCodeRecord, ShortLinkOption } from './types';
import { payloadIcon } from './types';

const props = defineProps<{
  qrCodes: QrCodeRecord[];
  qrPagination: { currentPage: number; lastPage: number; total: number };
  qrFilters: { search: string };
  payloadTypes: Record<string, string>;
  payloadDescriptors: PayloadDescriptors;
  shortLinks: ShortLinkOption[];
  canEditWorkspace: boolean;
}>();

const createOpen = ref(false);
const search = ref(props.qrFilters.search);
const searchInput = ref<HTMLInputElement | null>(null);
let searchTimer: ReturnType<typeof setTimeout> | undefined;
onUnmounted(() => clearTimeout(searchTimer));

watch(search, () => {
  clearTimeout(searchTimer);
  searchTimer = setTimeout(() => {
    router.get(
      route('qr-codes.index'),
      { search: search.value },
      {
        preserveState: true,
        preserveScroll: true,
        replace: true,
        only: ['qrCodes', 'qrPagination', 'qrFilters'],
      },
    );
  }, 300);
});

function goToPage(page: number) {
  router.get(
    route('qr-codes.index'),
    { search: search.value, page },
    {
      preserveState: true,
      preserveScroll: true,
      only: ['qrCodes', 'qrPagination', 'qrFilters'],
    },
  );
}

const description = computed(() => {
  const total = props.qrPagination.total;
  return `${total.toLocaleString()} QR code${total === 1 ? '' : 's'} · Codes for links, Wi-Fi, contact cards and more`;
});

const formatter = new Intl.NumberFormat('en-US', { notation: 'compact', maximumFractionDigits: 1 });

function typeLabel(qr: QrCodeRecord) {
  return qr.is_direct ? (props.payloadTypes[qr.payload_type ?? ''] ?? qr.payload_type ?? 'Content') : 'Short link';
}

function target(qr: QrCodeRecord) {
  return qr.short_link ? displayUrl(qr.short_link.short_url) : (qr.content ?? '');
}

function download(qr: QrCodeRecord, format: 'png' | 'svg') {
  window.location.href = route('qr-codes.export', [qr.token, format]);
}
</script>

<template>
  <Head title="QR codes" />

  <AuthenticatedLayout>
    <div class="mx-auto w-full max-w-5xl px-4 py-6 sm:px-6 lg:px-8 lg:py-8">
      <PageHeader title="QR codes" :description="description">
        <template v-if="canEditWorkspace" #actions>
          <Button size="sm" type="button" @click="createOpen = true"> <Plus class="h-4 w-4" /> New QR code </Button>
        </template>
      </PageHeader>

      <div class="mt-6 flex items-center gap-2">
        <div class="relative min-w-0 flex-1 sm:max-w-xs">
          <Search class="pointer-events-none absolute left-2.5 top-1/2 h-4 w-4 -translate-y-1/2 text-faint" />
          <input
            ref="searchInput"
            v-model="search"
            type="search"
            aria-label="Search QR codes"
            placeholder="Search QR codes"
            class="h-8 w-full rounded-lg border border-transparent bg-elevated/70 pl-8 pr-3 text-[13px] text-foreground outline-none transition-[border-color,box-shadow] placeholder:text-faint hover:bg-elevated focus-visible:border-accent/70 focus-visible:ring-2 focus-visible:ring-accent/15"
            @keydown.escape="
              search = '';
              searchInput?.blur();
            "
          />
        </div>
      </div>

      <div v-if="qrCodes.length === 0" class="mt-4 rounded-xl border border-dashed">
        <EmptyState
          :title="search ? 'No QR codes match' : 'No QR codes yet'"
          :description="
            search
              ? 'Try another name.'
              : 'Point a code at a short link to track scans, or encode Wi-Fi, a contact card and more.'
          "
        >
          <template #icon><QrCode class="h-5 w-5" /></template>
          <template v-if="canEditWorkspace && !search" #action>
            <Button size="sm" type="button" @click="createOpen = true"> <Plus class="h-4 w-4" /> New QR code </Button>
          </template>
        </EmptyState>
      </div>

      <ul v-else class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5">
        <li
          v-for="qr in qrCodes"
          :key="qr.id"
          class="group relative flex flex-col rounded-xl border bg-surface p-1.5 transition-[border-color] duration-150 focus-within:border-border-strong hover:border-border-strong"
        >
          <div class="grid aspect-square place-items-center rounded-lg bg-white p-1.5">
            <img
              :src="route('qr-codes.preview', qr.token)"
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
                <span class="truncate">{{ qr.is_direct ? typeLabel(qr) : target(qr) }}</span>
              </p>
              <p v-if="qr.is_direct" class="mt-1.5 text-xs text-faint">Scans not tracked</p>
              <p v-else class="mt-1.5 text-xs tabular-nums text-muted">
                <span class="font-medium text-foreground">{{ formatter.format(qr.scans ?? 0) }}</span>
                scan{{ qr.scans === 1 ? '' : 's' }}
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
              <MenuItem :icon="Download" @select="download(qr, 'png')">Download PNG</MenuItem>
              <MenuItem :icon="Download" @select="download(qr, 'svg')">Download SVG</MenuItem>
            </Menu>
          </div>
        </li>
      </ul>

      <nav
        v-if="qrPagination.lastPage > 1"
        class="mt-6 flex items-center justify-between gap-3 text-[13px]"
        aria-label="Pagination"
      >
        <span class="text-faint">Page {{ qrPagination.currentPage }} of {{ qrPagination.lastPage }}</span>
        <div class="flex gap-2">
          <Button
            variant="secondary"
            size="sm"
            type="button"
            :disabled="qrPagination.currentPage <= 1"
            @click="goToPage(qrPagination.currentPage - 1)"
            >Previous</Button
          >
          <Button
            variant="secondary"
            size="sm"
            type="button"
            :disabled="qrPagination.currentPage >= qrPagination.lastPage"
            @click="goToPage(qrPagination.currentPage + 1)"
            >Next</Button
          >
        </div>
      </nav>
    </div>

    <CreateQrCodeDialog
      v-if="canEditWorkspace"
      v-model:open="createOpen"
      :payload-types="payloadTypes"
      :payload-descriptors="payloadDescriptors"
      :short-links="shortLinks"
    />
  </AuthenticatedLayout>
</template>
