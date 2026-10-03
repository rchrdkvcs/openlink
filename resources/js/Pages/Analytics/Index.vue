<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Download, TrendingUp } from '@lucide/vue';
import { computed } from 'vue';

import AnalyticsFilterBar from '@/Components/analytics/AnalyticsFilterBar.vue';
import BarList from '@/Components/analytics/BarList.vue';
import BreakdownCard from '@/Components/analytics/BreakdownCard.vue';
import KpiCard from '@/Components/analytics/KpiCard.vue';
import RoutingCard from '@/Components/analytics/RoutingCard.vue';
import TopRankingCard from '@/Components/analytics/TopRankingCard.vue';
import TrafficCard from '@/Components/analytics/TrafficCard.vue';
import Button from '@/Components/ui/Button.vue';
import EmptyState from '@/Components/ui/EmptyState.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import SectionCard from '@/Components/ui/SectionCard.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { formatNumber, type Report } from '@/lib/analytics';
import type { AnalyticsFilterOptions, AppliedFilters } from '@/lib/analyticsFilters';
import {
  breakdownSections,
  bucketDescription,
  outcomeBarRows,
  outcomeTotal,
  reportHasEvents,
} from '@/Pages/Analytics/reportSections';
import { useAnalyticsFilters } from '@/Pages/Analytics/useAnalyticsFilters';
import type { WorkspaceSummary } from '@/types/payloads';

const props = defineProps<{
  currentWorkspace: Pick<WorkspaceSummary, 'id' | 'name' | 'slug'>;
  report: Report;
  filters: AppliedFilters;
  filterOptions: AnalyticsFilterOptions;
}>();

const { state, loading, hasActiveFilters, applyCustomRange, clearFilters, exportCsv } = useAnalyticsFilters(
  props.filters,
);

const summary = computed(() => props.report.summary);
const hasEvents = computed(() => reportHasEvents(props.report));
const topLinkIds = computed(() => props.report.top_links.map((link) => link.id));
const sections = computed(() => breakdownSections(props.report));
const outcomeRows = computed(() => outcomeBarRows(props.report));
const totalAttempts = computed(() => outcomeTotal(props.report));
</script>

<template>
  <Head title="Analytics" />

  <AuthenticatedLayout>
    <div class="mx-auto w-full max-w-6xl px-4 py-6 sm:px-6 lg:px-8 lg:py-8">
      <PageHeader title="Analytics" description="Visits, scans and audience across this workspace. Bots are excluded.">
        <template #actions>
          <Button variant="secondary" size="sm" type="button" @click="exportCsv"> <Download /> Export CSV </Button>
        </template>
      </PageHeader>

      <AnalyticsFilterBar
        v-model:state="state"
        :options="filterOptions"
        :applied="filters"
        :top-link-ids="topLinkIds"
        @clear="clearFilters"
        @apply-custom-range="applyCustomRange"
      />

      <div
        class="mt-5 space-y-3 transition-opacity duration-150"
        :class="loading ? 'pointer-events-none opacity-50' : ''"
        :aria-busy="loading"
      >
        <section class="grid grid-cols-2 gap-3 lg:grid-cols-4">
          <KpiCard
            label="Visits"
            :value="summary.visits"
            :change="summary.visits_change"
            :detail="`${formatNumber(summary.active_links)} active ${summary.active_links === 1 ? 'link' : 'links'}`"
          />
          <KpiCard
            label="Visitors"
            :value="summary.visitors"
            :change="summary.visitors_change"
            detail="Unique per day"
          />
          <KpiCard label="QR scans" :value="summary.scans" :change="summary.scans_change" detail="Successful scans" />
          <KpiCard
            label="Success rate"
            :value="summary.success_rate === null ? '—' : `${summary.success_rate}%`"
            :detail="`${formatNumber(summary.blocked)} blocked ${summary.blocked === 1 ? 'attempt' : 'attempts'}`"
          />
        </section>

        <TrafficCard
          :points="report.timeseries"
          :bucket="report.range.bucket"
          :description="bucketDescription(report)"
        />

        <div v-if="!hasEvents" class="rounded-xl border bg-surface">
          <EmptyState
            title="No traffic in this period"
            :description="
              hasActiveFilters
                ? 'Nothing matches these filters. Widen the range or clear filters.'
                : 'Share a short link or QR code. Visits and scans show up here within seconds.'
            "
          >
            <template #icon><TrendingUp class="h-4 w-4" /></template>
            <template v-if="hasActiveFilters" #action>
              <Button variant="secondary" size="sm" type="button" @click="clearFilters">Clear filters</Button>
            </template>
          </EmptyState>
        </div>

        <section v-if="hasEvents" class="grid gap-3 md:grid-cols-2 lg:grid-cols-6">
          <TopRankingCard
            :links="report.top_links"
            :qr-codes="report.top_qr_codes"
            @select-link="state.link = $event"
            @select-qr="state.qr = $event"
          />

          <BreakdownCard title="Sources" :tabs="sections.sources" class="lg:col-span-3" />
          <BreakdownCard title="Locations" :tabs="sections.locations" class="lg:col-span-3" />
          <BreakdownCard title="Devices" :tabs="sections.devices" class="lg:col-span-2" />
          <BreakdownCard title="Campaigns" :tabs="sections.campaigns" class="lg:col-span-2" />

          <SectionCard title="Outcomes" class="flex flex-col md:col-span-2 lg:col-span-2">
            <template #header>
              <span class="flex h-7 items-center text-xs tabular-nums text-faint">
                {{ formatNumber(totalAttempts) }} {{ totalAttempts === 1 ? 'attempt' : 'attempts' }}
              </span>
            </template>
            <div class="h-72 overflow-y-auto">
              <BarList :rows="outcomeRows" empty="No resolution attempts in this period." />
            </div>
          </SectionCard>
        </section>

        <RoutingCard v-if="hasEvents && report.routing.length > 0" :rows="report.routing" />

        <p v-if="hasEvents && summary.bots > 0" class="px-1 pt-1 text-xs text-faint">
          {{ formatNumber(summary.bots) }} bot and crawler requests were excluded in this period.
        </p>
      </div>
    </div>
  </AuthenticatedLayout>
</template>
