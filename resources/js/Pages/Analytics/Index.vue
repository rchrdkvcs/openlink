<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import {
  ChartLine,
  Download,
  ExternalLink,
  Folder,
  GitBranch,
  Globe,
  ListFilter,
  Plus,
  QrCode,
  Shuffle,
  Table2,
  Tag,
  TrendingUp,
  Waypoints,
} from '@lucide/vue';
import { computed, reactive, ref, watch } from 'vue';

import BarList from '@/Components/analytics/BarList.vue';
import BreakdownCard from '@/Components/analytics/BreakdownCard.vue';
import FilterPill from '@/Components/analytics/FilterPill.vue';
import KpiCard from '@/Components/analytics/KpiCard.vue';
import LinkFilter from '@/Components/analytics/LinkFilter.vue';
import TimeSeriesChart from '@/Components/analytics/TimeSeriesChart.vue';
import Favicon from '@/Components/Links/Favicon.vue';
import Button from '@/Components/ui/Button.vue';
import EmptyState from '@/Components/ui/EmptyState.vue';
import Input from '@/Components/ui/Input.vue';
import Menu from '@/Components/ui/Menu.vue';
import MenuItem from '@/Components/ui/MenuItem.vue';
import MenuSub from '@/Components/ui/MenuSub.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import SectionCard from '@/Components/ui/SectionCard.vue';
import SegmentedControl from '@/Components/ui/SegmentedControl.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import {
  CHANNEL_LABELS,
  DEVICE_LABELS,
  OUTCOME_LABELS,
  countryFlag,
  countryName,
  formatBucket,
  formatNumber,
  languageName,
  type BreakdownTab,
  type LinkOption,
  type RangePreset,
  type Report,
} from '@/lib/analytics';
import type { SelectOption } from '@/lib/controls';
import { displayUrl } from '@/lib/links';

type Option = { id: number; name?: string; hostname?: string };

const props = defineProps<{
  currentWorkspace: { id: number; name: string; slug: string };
  report: Report;
  filters: Record<string, string | number>;
  filterOptions: {
    links: LinkOption[];
    qrCodes?: Option[];
    domains: Option[];
    folders: Option[];
    tags: Option[];
    routingRules: Option[];
    routingVariants: Option[];
  };
}>();

const RANGES: { value: RangePreset; label: string }[] = [
  { value: '24h', label: '24h' },
  { value: '7d', label: '7d' },
  { value: '14d', label: '14d' },
  { value: '30d', label: '30d' },
  { value: '90d', label: '90d' },
  { value: '12m', label: '12m' },
  { value: 'custom', label: 'Custom' },
];

type DimensionKey = 'domain' | 'folder' | 'tag' | 'qr' | 'rule' | 'variant' | 'metric';

type Dimension = { key: DimensionKey; label: string; icon: unknown; options: SelectOption[] };

function toOptions(items: Option[] | undefined, label: (item: Option) => string | undefined): SelectOption[] {
  return (items ?? []).map((item) => ({ value: String(item.id), label: label(item) ?? String(item.id) }));
}

const dimensions = computed<Dimension[]>(() =>
  [
    {
      key: 'metric' as const,
      label: 'Traffic',
      icon: Waypoints,
      options: [
        { value: 'visit', label: 'Visits only' },
        { value: 'scan', label: 'Scans only' },
      ],
    },
    {
      key: 'domain' as const,
      label: 'Domain',
      icon: Globe,
      options: toOptions(props.filterOptions.domains, (domain) => domain.hostname),
    },
    {
      key: 'folder' as const,
      label: 'Folder',
      icon: Folder,
      options: toOptions(props.filterOptions.folders, (folder) => folder.name),
    },
    { key: 'tag' as const, label: 'Tag', icon: Tag, options: toOptions(props.filterOptions.tags, (tag) => tag.name) },
    {
      key: 'qr' as const,
      label: 'QR code',
      icon: QrCode,
      options: toOptions(props.filterOptions.qrCodes, (qr) => qr.name),
    },
    {
      key: 'rule' as const,
      label: 'Routing rule',
      icon: GitBranch,
      options: toOptions(props.filterOptions.routingRules, (rule) => rule.name),
    },
    {
      key: 'variant' as const,
      label: 'Variant',
      icon: Shuffle,
      options: toOptions(props.filterOptions.routingVariants, (variant) => variant.name),
    },
  ].filter((dimension) => dimension.options.length > 0 || Boolean(props.filters[dimension.key])),
);

const state = reactive({
  range: String(props.filters.range ?? '30d') as RangePreset,
  from: String(props.filters.from ?? ''),
  to: String(props.filters.to ?? ''),
  link: String(props.filters.link ?? ''),
  domain: String(props.filters.domain ?? ''),
  folder: String(props.filters.folder ?? ''),
  tag: String(props.filters.tag ?? ''),
  qr: String(props.filters.qr ?? ''),
  rule: String(props.filters.rule ?? ''),
  variant: String(props.filters.variant ?? ''),
  metric: String(props.filters.metric ?? ''),
});

const FILTER_KEYS = ['link', 'domain', 'folder', 'tag', 'qr', 'rule', 'variant', 'metric'] as const;

const activeDimensions = computed(() => dimensions.value.filter((dimension) => state[dimension.key] !== ''));
const availableDimensions = computed(() => dimensions.value.filter((dimension) => state[dimension.key] === ''));
const hasActiveFilters = computed(() => FILTER_KEYS.some((key) => state[key] !== ''));

function clearFilters() {
  for (const key of FILTER_KEYS) state[key] = '';
}

const loading = ref(false);

function query(): Record<string, string> {
  const params: Record<string, string> = { range: state.range };
  if (state.range === 'custom') {
    if (state.from) params.from = state.from;
    if (state.to) params.to = state.to;
  }
  for (const key of FILTER_KEYS) {
    if (state[key]) params[key] = state[key];
  }
  return params;
}

function reload() {
  loading.value = true;
  router.get(route('analytics.index'), query(), {
    preserveState: true,
    preserveScroll: true,
    only: ['report', 'filters'],
    onFinish: () => (loading.value = false),
  });
}

watch(
  () => [state.range, ...FILTER_KEYS.map((key) => state[key])],
  () => {
    if (state.range !== 'custom' || (state.from && state.to)) reload();
  },
);

function applyCustomRange() {
  if (state.from && state.to) reload();
}

const exportUrl = computed(() => route('analytics.export') + '?' + new URLSearchParams(query()).toString());

function exportCsv() {
  window.location.href = exportUrl.value;
}

const summary = computed(() => props.report.summary);
const hasEvents = computed(
  () => summary.value.visits + summary.value.scans + summary.value.blocked + summary.value.bots > 0,
);

const topLinkIds = computed(() => props.report.top_links.map((link) => link.id));

const trafficView = ref<'chart' | 'table'>('chart');
const TRAFFIC_VIEWS = [
  { value: 'chart' as const, label: 'Chart', icon: ChartLine },
  { value: 'table' as const, label: 'Table', icon: Table2 },
];

const rankingView = ref<'links' | 'qr'>('links');
const RANKING_VIEWS = [
  { value: 'links' as const, label: 'Links' },
  { value: 'qr' as const, label: 'QR codes' },
];
const showQrRanking = computed(() => props.report.top_qr_codes.length > 0);

watch(showQrRanking, (visible) => {
  if (!visible) rankingView.value = 'links';
});

const sourceTabs = computed<BreakdownTab[]>(() => [
  {
    key: 'referrers',
    label: 'Referrers',
    rows: props.report.breakdowns.referrers,
    empty: 'No referrers yet. Direct visits carry none.',
  },
  {
    key: 'channels',
    label: 'Channels',
    rows: props.report.breakdowns.channels.map((row) => ({
      ...row,
      display: CHANNEL_LABELS[row.label] ?? row.label,
    })),
  },
]);

const locationTabs = computed<BreakdownTab[]>(() => [
  {
    key: 'countries',
    label: 'Countries',
    rows: props.report.breakdowns.countries.map((row) => ({
      ...row,
      display: countryName(row.label),
      prefix: countryFlag(row.label),
    })),
    empty: 'No country data yet. Detection needs a geo header from your proxy or CDN.',
  },
  {
    key: 'languages',
    label: 'Languages',
    rows: props.report.breakdowns.languages.map((row) => ({ ...row, display: languageName(row.label) })),
  },
]);

const deviceTabs = computed<BreakdownTab[]>(() => [
  {
    key: 'devices',
    label: 'Devices',
    rows: props.report.breakdowns.devices.map((row) => ({
      ...row,
      display: DEVICE_LABELS[row.label] ?? row.label,
    })),
  },
  { key: 'browsers', label: 'Browsers', rows: props.report.breakdowns.browsers },
  { key: 'os', label: 'OS', rows: props.report.breakdowns.os },
]);

const campaignTabs = computed<BreakdownTab[]>(() => [
  {
    key: 'utm_campaigns',
    label: 'Campaigns',
    rows: props.report.breakdowns.utm_campaigns,
    empty: 'No UTM campaigns yet. Add ?utm_campaign=… to shared links.',
  },
  {
    key: 'utm_sources',
    label: 'Sources',
    rows: props.report.breakdowns.utm_sources,
    empty: 'No utm_source values yet.',
  },
  {
    key: 'utm_mediums',
    label: 'Mediums',
    rows: props.report.breakdowns.utm_mediums,
    empty: 'No utm_medium values yet.',
  },
]);

const outcomeRows = computed(() =>
  props.report.outcomes.map((row) => ({
    label: row.outcome,
    display: OUTCOME_LABELS[row.outcome] ?? row.outcome,
    count: row.count,
    share: row.share,
  })),
);

const totalAttempts = computed(() => props.report.outcomes.reduce((sum, row) => sum + row.count, 0));
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

      <div class="mt-6 flex flex-wrap items-center gap-x-4 gap-y-2">
        <div class="flex max-w-full flex-wrap items-center gap-2">
          <SegmentedControl
            v-model="state.range"
            :options="RANGES"
            size="sm"
            label="Date range"
            class="max-w-full overflow-x-auto"
          />

          <template v-if="state.range === 'custom'">
            <Input
              v-model="state.from"
              type="date"
              size="sm"
              class="w-auto"
              aria-label="From"
              @change="applyCustomRange"
            />
            <span class="text-xs text-faint">to</span>
            <Input v-model="state.to" type="date" size="sm" class="w-auto" aria-label="To" @change="applyCustomRange" />
          </template>
        </div>

        <div class="flex min-w-0 max-w-full flex-wrap items-center gap-2">
          <LinkFilter v-model="state.link" :links="filterOptions.links" :top-link-ids="topLinkIds" />

          <FilterPill
            v-for="dimension in activeDimensions"
            :key="dimension.key"
            v-model="state[dimension.key]"
            :label="dimension.label"
            :icon="dimension.icon"
            :options="dimension.options"
            @remove="state[dimension.key] = ''"
          />

          <Menu v-if="availableDimensions.length > 0" align="start" width="w-52">
            <template #trigger>
              <Button variant="ghost" size="sm" type="button">
                <Plus v-if="activeDimensions.length > 0" />
                <ListFilter v-else />
                Add filter
              </Button>
            </template>
            <MenuSub
              v-for="dimension in availableDimensions"
              :key="dimension.key"
              :label="dimension.label"
              :icon="dimension.icon"
            >
              <MenuItem
                v-for="option in dimension.options"
                :key="option.value"
                @select="state[dimension.key] = option.value"
              >
                {{ option.label }}
              </MenuItem>
            </MenuSub>
          </Menu>

          <Button v-if="hasActiveFilters" variant="ghost" size="sm" type="button" @click="clearFilters"> Clear </Button>
        </div>
      </div>

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

        <SectionCard
          title="Traffic"
          :description="
            report.range.bucket === 'hour' ? 'Hourly' : report.range.bucket === 'month' ? 'Monthly' : 'Daily'
          "
        >
          <template #header>
            <SegmentedControl v-model="trafficView" :options="TRAFFIC_VIEWS" size="sm" label="Traffic view" />
          </template>

          <div v-if="trafficView === 'chart'" class="px-4 pb-3 pt-4">
            <TimeSeriesChart :points="report.timeseries" :bucket="report.range.bucket" />
          </div>

          <div v-else class="h-[19.5rem] overflow-y-auto">
            <table class="w-full text-[13px]">
              <thead class="sticky top-0 bg-surface text-start text-xs text-faint">
                <tr>
                  <th class="px-5 py-2 text-start font-medium">Period</th>
                  <th class="px-5 py-2 text-end font-medium">Visits</th>
                  <th class="px-5 py-2 text-end font-medium">Scans</th>
                  <th class="px-5 py-2 text-end font-medium">Visitors</th>
                  <th class="px-5 py-2 text-end font-medium">Blocked</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="point in report.timeseries" :key="point.bucket" class="border-t">
                  <td class="px-5 py-1.5 text-muted">
                    {{ formatBucket(point.bucket, report.range.bucket, 'long') }}
                  </td>
                  <td class="px-5 py-1.5 text-end tabular-nums">{{ formatNumber(point.visits) }}</td>
                  <td class="px-5 py-1.5 text-end tabular-nums">{{ formatNumber(point.scans) }}</td>
                  <td class="px-5 py-1.5 text-end tabular-nums text-muted">{{ formatNumber(point.visitors) }}</td>
                  <td class="px-5 py-1.5 text-end tabular-nums text-muted">{{ formatNumber(point.blocked) }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </SectionCard>

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
          <SectionCard
            :title="rankingView === 'qr' ? 'Top QR codes' : 'Top links'"
            class="flex flex-col md:col-span-2 lg:col-span-6"
          >
            <template #header>
              <SegmentedControl
                v-if="showQrRanking"
                v-model="rankingView"
                :options="RANKING_VIEWS"
                size="sm"
                label="Ranking"
              />
              <span v-else class="flex h-7 items-center text-xs text-faint">By visits and scans</span>
            </template>

            <div class="h-72 overflow-y-auto">
              <table v-if="rankingView === 'links' && report.top_links.length > 0" class="w-full text-[13px]">
                <thead class="sticky top-0 z-10 bg-surface text-xs text-faint">
                  <tr>
                    <th class="py-2 pe-3 ps-4 text-start font-medium">Link</th>
                    <th class="px-2 py-2 text-end font-medium">Visits</th>
                    <th class="px-2 py-2 text-end font-medium">Scans</th>
                    <th class="py-2 pe-4 ps-2 text-end font-medium">Visitors</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="link in report.top_links" :key="link.id" class="group border-t">
                    <td class="max-w-0 py-1.5 pe-3 ps-4">
                      <div class="flex items-center gap-2.5">
                        <Favicon :url="link.destination_url ?? ''" size="sm" />
                        <div class="min-w-0 flex-1">
                          <button
                            type="button"
                            class="block max-w-full truncate rounded font-medium text-foreground outline-none hover:text-accent focus-visible:ring-2 focus-visible:ring-accent/40"
                            :title="`Filter by /${link.slug}`"
                            @click="state.link = String(link.id)"
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

              <div v-else-if="rankingView === 'qr'" class="space-y-0.5 p-2">
                <button
                  v-for="qr in report.top_qr_codes"
                  :key="qr.id"
                  type="button"
                  class="flex w-full items-center gap-3 rounded-lg px-2.5 py-1.5 text-start text-[13px] outline-none transition-colors hover:bg-elevated focus-visible:ring-2 focus-visible:ring-accent/40"
                  :title="`Filter by ${qr.name}`"
                  @click="state.qr = String(qr.id)"
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

          <BreakdownCard title="Sources" :tabs="sourceTabs" class="lg:col-span-3" />
          <BreakdownCard title="Locations" :tabs="locationTabs" class="lg:col-span-3" />
          <BreakdownCard title="Devices" :tabs="deviceTabs" class="lg:col-span-2" />
          <BreakdownCard title="Campaigns" :tabs="campaignTabs" class="lg:col-span-2" />

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

        <SectionCard
          v-if="hasEvents && report.routing.length > 0"
          title="Routing"
          description="How traffic splits across the default destination, rules and variants"
        >
          <div class="overflow-x-auto">
            <table class="w-full text-[13px]">
              <thead class="text-xs text-faint">
                <tr>
                  <th class="px-5 py-2.5 text-start font-medium">Destination</th>
                  <th class="px-3 py-2.5 text-end font-medium">Visits</th>
                  <th class="px-3 py-2.5 text-end font-medium">Scans</th>
                  <th class="px-5 py-2.5 text-end font-medium">Visitors</th>
                </tr>
              </thead>
              <tbody>
                <tr
                  v-for="row in report.routing"
                  :key="`${row.routing_rule_id ?? 'default'}-${row.routing_variant_id ?? 'none'}`"
                  class="border-t"
                >
                  <td class="max-w-0 px-5 py-2">
                    <span class="block truncate font-medium text-foreground">{{ row.rule_name }}</span>
                    <span v-if="row.variant_name" class="block truncate text-xs text-faint">{{
                      row.variant_name
                    }}</span>
                  </td>
                  <td class="px-3 py-2 text-end font-medium tabular-nums">{{ formatNumber(row.visits) }}</td>
                  <td class="px-3 py-2 text-end tabular-nums text-muted">{{ formatNumber(row.scans) }}</td>
                  <td class="px-5 py-2 text-end tabular-nums text-muted">{{ formatNumber(row.visitors) }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </SectionCard>

        <p v-if="hasEvents && summary.bots > 0" class="px-1 pt-1 text-xs text-faint">
          {{ formatNumber(summary.bots) }} bot and crawler requests were excluded in this period.
        </p>
      </div>
    </div>
  </AuthenticatedLayout>
</template>
