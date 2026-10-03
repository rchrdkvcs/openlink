<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Plus, QrCode, Search } from '@lucide/vue';
import { computed, ref } from 'vue';

import Button from '@/Components/ui/Button.vue';
import EmptyState from '@/Components/ui/EmptyState.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { useShell } from '@/lib/shell';
import type { Pagination } from '@/types/payloads';

import CreateQrCodeDialog from './CreateQrCodeDialog.vue';
import QrCodeCard from './Library/QrCodeCard.vue';
import QrPagination from './Library/QrPagination.vue';
import { useQrCodeQuery } from './Library/useQrCodeQuery';
import { indexDescription } from './qrLabels';
import type { PayloadDescriptors, QrCodeRecord, ShortLinkOption } from './types';

const props = defineProps<{
  qrCodes: QrCodeRecord[];
  qrPagination: Pagination;
  qrFilters: { search: string };
  payloadTypes: Record<string, string>;
  payloadDescriptors: PayloadDescriptors;
  shortLinks: ShortLinkOption[];
}>();

const { canEdit } = useShell();

const createOpen = ref(false);
const searchInput = ref<HTMLInputElement | null>(null);
const { search, goToPage } = useQrCodeQuery(() => props.qrFilters);

const description = computed(() => indexDescription(props.qrPagination.total));

function clearSearch() {
  search.value = '';
  searchInput.value?.blur();
}
</script>

<template>
  <Head title="QR codes" />

  <AuthenticatedLayout>
    <div class="mx-auto w-full max-w-5xl px-4 py-6 sm:px-6 lg:px-8 lg:py-8">
      <PageHeader title="QR codes" :description="description">
        <template v-if="canEdit" #actions>
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
            @keydown.escape="clearSearch"
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
          <template v-if="canEdit && !search" #action>
            <Button size="sm" type="button" @click="createOpen = true"> <Plus class="h-4 w-4" /> New QR code </Button>
          </template>
        </EmptyState>
      </div>

      <ul v-else class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5">
        <QrCodeCard v-for="qr in qrCodes" :key="qr.id" :qr="qr" :payload-types="payloadTypes" />
      </ul>

      <QrPagination :pagination="qrPagination" @go="goToPage" />
    </div>

    <CreateQrCodeDialog
      v-if="canEdit"
      v-model:open="createOpen"
      :payload-types="payloadTypes"
      :payload-descriptors="payloadDescriptors"
      :short-links="shortLinks"
    />
  </AuthenticatedLayout>
</template>
