<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ArrowRight, TrendingUp } from '@lucide/vue';
import { computed } from 'vue';

import KpiCard from '@/Components/analytics/KpiCard.vue';
import TimeSeriesChart from '@/Components/analytics/TimeSeriesChart.vue';
import EmptyState from '@/Components/ui/EmptyState.vue';
import SectionCard from '@/Components/ui/SectionCard.vue';
import { formatNumber } from '@/lib/analytics';
import { displayUrl } from '@/lib/links';

import type { DashboardAnalytics, LinkCounts } from './types';

const props = defineProps<{ analytics: DashboardAnalytics; linkCounts: LinkCounts }>();

const emit = defineEmits<{ open: [id: number] }>();

const summary = computed(() => props.analytics.summary);
const hasTraffic = computed(() => summary.value.visits + summary.value.scans > 0);
</script>

<template>
  <section class="mt-12">
    <div class="mb-3 flex items-end justify-between px-1">
      <div>
        <h2 class="text-[15px] font-semibold tracking-[-0.01em]">Last 30 days</h2>
      </div>
      <Link
        :href="route('analytics.index')"
        class="inline-flex items-center gap-1 text-[13px] font-medium text-muted transition-colors hover:text-foreground"
      >
        Analytics <ArrowRight class="h-3.5 w-3.5" />
      </Link>
    </div>

    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
      <KpiCard label="Visits" :value="summary.visits" :change="summary.visits_change" />
      <KpiCard label="Unique visitors" :value="summary.visitors" :change="summary.visitors_change" />
      <KpiCard label="QR scans" :value="summary.scans" :change="summary.scans_change" />
      <KpiCard label="Active links" :value="linkCounts.active" :detail="`of ${linkCounts.total} total`" />
    </div>

    <div class="mt-3 grid gap-3 lg:grid-cols-[minmax(0,1.6fr)_minmax(280px,1fr)]">
      <SectionCard title="Traffic" description="Daily visits and scans">
        <div v-if="hasTraffic" class="px-4 pb-3 pt-4">
          <TimeSeriesChart :points="analytics.timeseries" :bucket="analytics.range.bucket" />
        </div>
        <EmptyState
          v-else
          title="No traffic yet"
          description="Share a short link or QR code — visits show up here within seconds."
        >
          <template #icon><TrendingUp class="h-5 w-5" /></template>
        </EmptyState>
      </SectionCard>

      <SectionCard title="Top links" description="Most clicked this month">
        <div class="p-2">
          <button
            v-for="(link, index) in analytics.top_links"
            :key="link.id"
            type="button"
            class="flex w-full items-center gap-3 rounded-lg px-2.5 py-2 text-left transition-colors hover:bg-elevated/50"
            @click="emit('open', link.id)"
          >
            <span class="w-4 text-xs tabular-nums text-faint">{{ index + 1 }}</span>
            <span class="min-w-0 flex-1">
              <span class="block truncate text-[13px] font-medium text-foreground">/{{ link.slug }}</span>
              <span class="block truncate text-xs text-faint">{{
                link.destination_url ? displayUrl(link.destination_url) : ''
              }}</span>
            </span>
            <span class="shrink-0 text-[13px] tabular-nums text-muted">{{ formatNumber(link.total) }}</span>
          </button>
          <p v-if="analytics.top_links.length === 0" class="px-2.5 py-10 text-center text-[13px] text-faint">
            No link traffic yet.
          </p>
        </div>
      </SectionCard>
    </div>
  </section>
</template>
