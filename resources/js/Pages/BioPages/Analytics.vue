<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ArrowLeft, BarChart3, Eye, MousePointerClick, QrCode } from '@lucide/vue';

import KpiCard from '@/Components/analytics/KpiCard.vue';
import EmptyState from '@/Components/ui/EmptyState.vue';
import SectionCard from '@/Components/ui/SectionCard.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { formatNumber } from '@/lib/analytics';

type BioSummary = {
  bio_views: number;
  bio_activations: number;
  scans: number;
  visitors: number;
  bio_views_change: number | null;
  bio_activations_change: number | null;
  scans_change: number | null;
};

type DestinationRow = {
  id: number;
  label: string;
  type: string | null;
  activations: number;
  visitors: number;
};

const props = defineProps<{
  bioPage: { id: number; displayName: string; status: string };
  report: {
    summary: BioSummary;
    top_bio_elements: DestinationRow[];
  };
  filters: { range: string };
}>();

function setRange(range: string) {
  router.get(route('bio-pages.analytics', props.bioPage.id), { range }, { preserveState: true, replace: true });
}
</script>

<template>
  <Head :title="`${bioPage.displayName} Analytics`" />

  <AuthenticatedLayout>
    <div class="w-full px-4 py-8 sm:px-6 lg:px-8">
      <Link
        :href="route('bio-pages.show', bioPage.id)"
        class="mb-4 inline-flex items-center gap-1.5 text-sm text-muted hover:text-foreground"
      >
        <ArrowLeft class="h-4 w-4" /> Back to editor
      </Link>

      <div class="mb-6 flex flex-wrap items-end justify-between gap-3">
        <div>
          <div class="flex items-center gap-2">
            <h1 class="text-xl font-semibold tracking-tight">{{ bioPage.displayName }}</h1>
            <span class="rounded-full border px-2 py-0.5 text-xs capitalize text-muted">{{ bioPage.status }}</span>
          </div>
          <p class="mt-1 text-sm text-muted">Bio Views, Bio Activations, QR Scans, and destination performance.</p>
        </div>

        <select
          :value="filters.range"
          class="h-9 rounded-md border bg-surface px-3 text-sm"
          aria-label="Analytics range"
          @change="setRange(($event.target as HTMLSelectElement).value)"
        >
          <option value="24h">Last 24 hours</option>
          <option value="7d">Last 7 days</option>
          <option value="30d">Last 30 days</option>
          <option value="90d">Last 90 days</option>
          <option value="12m">Last 12 months</option>
        </select>
      </div>

      <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <KpiCard label="Bio Views" :value="report.summary.bio_views" :change="report.summary.bio_views_change" />
        <KpiCard
          label="Bio Activations"
          :value="report.summary.bio_activations"
          :change="report.summary.bio_activations_change"
        />
        <KpiCard label="QR Scans" :value="report.summary.scans" :change="report.summary.scans_change" />
        <KpiCard label="Visitors" :value="report.summary.visitors" />
      </div>

      <SectionCard class="mt-6" title="Destinations" description="Bio Activations in the selected period">
        <template #icon><MousePointerClick class="h-4 w-4 text-faint" /></template>

        <EmptyState
          v-if="report.top_bio_elements.length === 0"
          title="No Bio Activations yet"
          description="Destination activity will appear here after visitors start using this Bio Page."
        >
          <template #icon><BarChart3 class="h-5 w-5" /></template>
        </EmptyState>

        <div v-else class="overflow-x-auto">
          <table class="w-full text-[13px]">
            <thead class="text-left text-xs uppercase tracking-wide text-faint">
              <tr>
                <th class="px-5 py-2.5 font-medium">Destination</th>
                <th class="px-3 py-2.5 text-right font-medium">Activations</th>
                <th class="px-5 py-2.5 text-right font-medium">Visitors</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="destination in report.top_bio_elements" :key="destination.id" class="border-t">
                <td class="px-5 py-3 font-medium text-foreground">{{ destination.label }}</td>
                <td class="px-3 py-3 text-right font-medium tabular-nums">
                  {{ formatNumber(destination.activations) }}
                </td>
                <td class="px-5 py-3 text-right tabular-nums text-muted">
                  {{ formatNumber(destination.visitors) }}
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </SectionCard>

      <p class="mt-4 flex items-center gap-2 text-xs text-faint">
        <Eye class="h-3.5 w-3.5" /> Bio Views and Bio Activations are distinct. <QrCode class="ml-2 h-3.5 w-3.5" /> QR
        Scans are counted separately.
      </p>
    </div>
  </AuthenticatedLayout>
</template>
