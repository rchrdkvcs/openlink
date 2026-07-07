<script setup lang="ts">
import BarList from '@/Components/analytics/BarList.vue';
import BreakdownCard from '@/Components/analytics/BreakdownCard.vue';
import KpiCard from '@/Components/analytics/KpiCard.vue';
import TimeSeriesChart from '@/Components/analytics/TimeSeriesChart.vue';
import EmptyState from '@/Components/ui/EmptyState.vue';
import SectionCard from '@/Components/ui/SectionCard.vue';
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
    type RangePreset,
    type Report,
} from '@/lib/analytics';
import { Head, router } from '@inertiajs/vue3';
import { Download, ExternalLink, Link2, QrCode, Table2, TrendingUp } from '@lucide/vue';
import { computed, reactive, ref, watch } from 'vue';

type Option = { id: number; name?: string; slug?: string; hostname?: string };

const props = defineProps<{
    currentWorkspace: { id: number; name: string; slug: string };
    report: Report;
    filters: Record<string, string | number>;
    filterOptions: {
        links: { id: number; slug: string; hostname: string | null }[];
        domains: Option[];
        folders: Option[];
        tags: Option[];
        routingRules: Option[];
        routingVariants: Option[];
    };
}>();

const RANGES: { key: RangePreset; label: string }[] = [
    { key: '24h', label: '24h' },
    { key: '7d', label: '7d' },
    { key: '14d', label: '14d' },
    { key: '30d', label: '30d' },
    { key: '90d', label: '90d' },
    { key: '12m', label: '12m' },
    { key: 'custom', label: 'Custom' },
];

const state = reactive({
    range: String(props.filters.range ?? '30d') as RangePreset,
    from: String(props.filters.from ?? ''),
    to: String(props.filters.to ?? ''),
    link: String(props.filters.link ?? ''),
    domain: String(props.filters.domain ?? ''),
    folder: String(props.filters.folder ?? ''),
    tag: String(props.filters.tag ?? ''),
    rule: String(props.filters.rule ?? ''),
    variant: String(props.filters.variant ?? ''),
    metric: String(props.filters.metric ?? ''),
});

const loading = ref(false);

function query(): Record<string, string> {
    const params: Record<string, string> = { range: state.range };
    if (state.range === 'custom') {
        if (state.from) params.from = state.from;
        if (state.to) params.to = state.to;
    }
    for (const key of ['link', 'domain', 'folder', 'tag', 'rule', 'variant', 'metric'] as const) {
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
    () => [state.range, state.link, state.domain, state.folder, state.tag, state.rule, state.variant, state.metric],
    () => {
        if (state.range !== 'custom' || (state.from && state.to)) reload();
    },
);

function applyCustomRange() {
    if (state.from && state.to) reload();
}

const exportUrl = computed(() => route('analytics.export') + '?' + new URLSearchParams(query()).toString());

const summary = computed(() => props.report.summary);
const hasEvents = computed(
    () => summary.value.visits + summary.value.scans + summary.value.blocked + summary.value.bots > 0,
);

const showTable = ref(false);

const sourceTabs = computed<BreakdownTab[]>(() => [
    {
        key: 'referrers',
        label: 'Referrers',
        rows: props.report.breakdowns.referrers,
        empty: 'No referrer data yet — direct visits carry no referrer.',
    },
    {
        key: 'channels',
        label: 'Channels',
        rows: props.report.breakdowns.channels.map((row) => ({ ...row, display: CHANNEL_LABELS[row.label] ?? row.label })),
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
        empty: 'No country data yet. Country detection needs a geo header from your proxy or CDN (e.g. Cloudflare).',
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
        rows: props.report.breakdowns.devices.map((row) => ({ ...row, display: DEVICE_LABELS[row.label] ?? row.label })),
    },
    { key: 'browsers', label: 'Browsers', rows: props.report.breakdowns.browsers },
    { key: 'os', label: 'OS', rows: props.report.breakdowns.os },
]);

const campaignTabs = computed<BreakdownTab[]>(() => [
    {
        key: 'utm_campaigns',
        label: 'Campaigns',
        rows: props.report.breakdowns.utm_campaigns,
        empty: 'No UTM parameters seen yet. Share links with ?utm_campaign=… to segment traffic here.',
    },
    { key: 'utm_sources', label: 'Sources', rows: props.report.breakdowns.utm_sources, empty: 'No utm_source values yet.' },
    { key: 'utm_mediums', label: 'Mediums', rows: props.report.breakdowns.utm_mediums, empty: 'No utm_medium values yet.' },
]);

const outcomeRows = computed(() =>
    props.report.outcomes.map((row) => ({
        label: row.outcome,
        display: OUTCOME_LABELS[row.outcome] ?? row.outcome,
        count: row.count,
        share: row.share,
    })),
);
</script>

<template>
    <Head title="Analytics" />

    <AuthenticatedLayout>
        <div class="w-full px-4 py-8 sm:px-6 lg:px-8">
            <div class="mb-6 flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                <div>
                    <h1 class="text-xl font-semibold tracking-tight">Analytics</h1>
                    <p class="mt-1 text-sm text-muted">Visits, scans, and audience across this workspace. Bots are excluded from every figure.</p>
                </div>
                <a
                    :href="exportUrl"
                    class="inline-flex h-9 w-fit items-center gap-2 rounded-md border border-border bg-surface px-3.5 text-sm font-medium text-foreground transition-colors duration-150 hover:border-border-strong hover:bg-elevated"
                >
                    <Download class="h-4 w-4" /> Export CSV
                </a>
            </div>

            <!-- Filter row: scopes everything below it -->
            <div class="mb-6 flex flex-wrap items-center gap-2">
                <div class="flex items-center gap-0.5 rounded-md border bg-surface p-0.5">
                    <button
                        v-for="range in RANGES"
                        :key="range.key"
                        type="button"
                        class="rounded-[5px] px-2.5 py-1.5 text-[13px] font-medium transition-colors duration-100"
                        :class="state.range === range.key ? 'bg-elevated text-foreground' : 'text-muted hover:text-foreground'"
                        @click="state.range = range.key"
                    >
                        {{ range.label }}
                    </button>
                </div>

                <template v-if="state.range === 'custom'">
                    <input v-model="state.from" type="date" class="h-9 w-auto" @change="applyCustomRange" />
                    <span class="text-xs text-faint">to</span>
                    <input v-model="state.to" type="date" class="h-9 w-auto" @change="applyCustomRange" />
                </template>

                <select v-model="state.link" class="h-9 w-auto min-w-36 max-w-56">
                    <option value="">All links</option>
                    <option v-for="link in filterOptions.links" :key="link.id" :value="String(link.id)">
                        {{ link.hostname ? `${link.hostname}/` : '/' }}{{ link.slug }}
                    </option>
                </select>

                <select v-if="filterOptions.domains.length > 0" v-model="state.domain" class="h-9 w-auto min-w-32">
                    <option value="">All domains</option>
                    <option v-for="domain in filterOptions.domains" :key="domain.id" :value="String(domain.id)">{{ domain.hostname }}</option>
                </select>

                <select v-if="filterOptions.folders.length > 0" v-model="state.folder" class="h-9 w-auto min-w-32">
                    <option value="">All folders</option>
                    <option v-for="folder in filterOptions.folders" :key="folder.id" :value="String(folder.id)">{{ folder.name }}</option>
                </select>

                <select v-if="filterOptions.tags.length > 0" v-model="state.tag" class="h-9 w-auto min-w-28">
                    <option value="">All tags</option>
                    <option v-for="tag in filterOptions.tags" :key="tag.id" :value="String(tag.id)">{{ tag.name }}</option>
                </select>

                <select v-if="filterOptions.routingRules.length > 0" v-model="state.rule" class="h-9 w-auto min-w-32">
                    <option value="">All rules</option>
                    <option v-for="rule in filterOptions.routingRules" :key="rule.id" :value="String(rule.id)">{{ rule.name }}</option>
                </select>

                <select v-if="filterOptions.routingVariants.length > 0" v-model="state.variant" class="h-9 w-auto min-w-32">
                    <option value="">All variants</option>
                    <option v-for="variant in filterOptions.routingVariants" :key="variant.id" :value="String(variant.id)">{{ variant.name }}</option>
                </select>

                <select v-model="state.metric" class="h-9 w-auto min-w-28">
                    <option value="">Visits + scans</option>
                    <option value="visit">Visits only</option>
                    <option value="scan">Scans only</option>
                </select>
            </div>

            <!-- Refetch keeps the frame: previous render held at reduced opacity -->
            <div class="space-y-6 transition-opacity duration-150" :class="loading ? 'pointer-events-none opacity-50' : ''">
                <section class="grid gap-3 sm:grid-cols-2 xl:grid-cols-6">
                    <KpiCard label="Visits" :value="summary.visits" :change="summary.visits_change" detail="Successful redirects" />
                    <KpiCard label="Visitors" :value="summary.visitors" :change="summary.visitors_change" detail="Unique per day" />
                    <KpiCard label="QR scans" :value="summary.scans" :change="summary.scans_change" detail="Successful scans" />
                    <KpiCard
                        label="Blocked"
                        :value="summary.blocked"
                        :change="summary.blocked_change"
                        :up-is-good="false"
                        detail="Failed attempts"
                    />
                    <KpiCard
                        label="Success rate"
                        :value="summary.success_rate === null ? '—' : `${summary.success_rate}%`"
                        detail="Of human attempts"
                    />
                    <KpiCard label="Active links" :value="summary.active_links" detail="With traffic in period" />
                </section>

                <SectionCard title="Traffic over time" :description="report.range.bucket === 'hour' ? 'Hourly' : report.range.bucket === 'month' ? 'Monthly' : 'Daily'">
                    <template #icon><TrendingUp class="h-4 w-4 text-faint" /></template>
                    <template #header>
                        <button
                            type="button"
                            class="inline-flex items-center gap-1.5 rounded-md px-2 py-1 text-xs font-medium text-muted transition-colors hover:bg-elevated hover:text-foreground"
                            @click="showTable = !showTable"
                        >
                            <Table2 class="h-3.5 w-3.5" /> {{ showTable ? 'Chart' : 'Table' }}
                        </button>
                    </template>

                    <div v-if="!showTable" class="px-4 pb-3 pt-4">
                        <TimeSeriesChart :points="report.timeseries" :bucket="report.range.bucket" />
                    </div>

                    <div v-else class="max-h-80 overflow-y-auto">
                        <table class="w-full text-[13px]">
                            <thead class="sticky top-0 bg-surface text-left text-xs uppercase tracking-wide text-faint">
                                <tr>
                                    <th class="px-5 py-2 font-medium">Period</th>
                                    <th class="px-5 py-2 text-right font-medium">Visits</th>
                                    <th class="px-5 py-2 text-right font-medium">Scans</th>
                                    <th class="px-5 py-2 text-right font-medium">Visitors</th>
                                    <th class="px-5 py-2 text-right font-medium">Blocked</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="point in report.timeseries" :key="point.bucket" class="border-t">
                                    <td class="px-5 py-1.5 text-muted">{{ formatBucket(point.bucket, report.range.bucket, 'long') }}</td>
                                    <td class="px-5 py-1.5 text-right tabular-nums">{{ formatNumber(point.visits) }}</td>
                                    <td class="px-5 py-1.5 text-right tabular-nums">{{ formatNumber(point.scans) }}</td>
                                    <td class="px-5 py-1.5 text-right tabular-nums text-muted">{{ formatNumber(point.visitors) }}</td>
                                    <td class="px-5 py-1.5 text-right tabular-nums text-muted">{{ formatNumber(point.blocked) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </SectionCard>

                <EmptyState
                    v-if="!hasEvents"
                    title="No traffic in this period"
                    description="Share a short link or QR code and analytics will appear here within seconds — no extra setup needed."
                >
                    <template #icon><TrendingUp class="h-5 w-5" /></template>
                </EmptyState>

                <section v-if="hasEvents" class="grid gap-6 lg:grid-cols-2">
                    <BreakdownCard title="Sources" :tabs="sourceTabs" />
                    <BreakdownCard title="Locations" :tabs="locationTabs" />
                    <BreakdownCard title="Devices" :tabs="deviceTabs" />
                    <BreakdownCard title="Campaigns (UTM)" :tabs="campaignTabs" />
                </section>

                <SectionCard v-if="hasEvents && report.routing.length > 0" title="Routing performance" description="Traffic distribution across default destination, rules, and variants">
                    <template #icon><TrendingUp class="h-4 w-4 text-faint" /></template>
                    <div class="overflow-x-auto">
                        <table class="w-full text-[13px]">
                            <thead class="text-left text-xs uppercase tracking-wide text-faint">
                                <tr>
                                    <th class="px-5 py-2.5 font-medium">Destination path</th>
                                    <th class="px-3 py-2.5 text-right font-medium">Visits</th>
                                    <th class="px-3 py-2.5 text-right font-medium">Scans</th>
                                    <th class="px-5 py-2.5 text-right font-medium">Visitors</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="row in report.routing" :key="`${row.routing_rule_id ?? 'default'}-${row.routing_variant_id ?? 'none'}`" class="border-t">
                                    <td class="max-w-0 px-5 py-2.5">
                                        <span class="block truncate font-medium text-foreground">{{ row.rule_name }}</span>
                                        <span v-if="row.variant_name" class="block truncate text-xs text-faint">{{ row.variant_name }}</span>
                                    </td>
                                    <td class="px-3 py-2.5 text-right font-medium tabular-nums">{{ formatNumber(row.visits) }}</td>
                                    <td class="px-3 py-2.5 text-right tabular-nums text-muted">{{ formatNumber(row.scans) }}</td>
                                    <td class="px-5 py-2.5 text-right tabular-nums text-muted">{{ formatNumber(row.visitors) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </SectionCard>

                <section v-if="hasEvents" class="grid gap-6 xl:grid-cols-[minmax(0,1.4fr)_minmax(320px,0.6fr)]">
                    <SectionCard title="Top links" description="By successful visits and scans in the period">
                        <template #icon><Link2 class="h-4 w-4 text-faint" /></template>

                        <div class="overflow-x-auto">
                            <table class="w-full text-[13px]">
                                <thead class="text-left text-xs uppercase tracking-wide text-faint">
                                    <tr>
                                        <th class="px-5 py-2.5 font-medium">Link</th>
                                        <th class="px-3 py-2.5 text-right font-medium">Visits</th>
                                        <th class="px-3 py-2.5 text-right font-medium">Scans</th>
                                        <th class="px-5 py-2.5 text-right font-medium">Visitors</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="link in report.top_links" :key="link.id" class="border-t">
                                        <td class="max-w-0 px-5 py-2.5">
                                            <a
                                                v-if="link.short_url"
                                                :href="link.short_url"
                                                target="_blank"
                                                rel="noopener"
                                                class="group inline-flex max-w-full items-center gap-1.5 font-medium text-foreground hover:text-accent"
                                            >
                                                <span class="truncate">/{{ link.slug }}</span>
                                                <ExternalLink class="h-3 w-3 shrink-0 text-faint group-hover:text-accent" />
                                            </a>
                                            <span v-else class="font-medium text-muted">{{ link.slug }}</span>
                                            <p class="truncate text-xs text-faint">{{ link.destination_url }}</p>
                                        </td>
                                        <td class="px-3 py-2.5 text-right font-medium tabular-nums">{{ formatNumber(link.visits) }}</td>
                                        <td class="px-3 py-2.5 text-right tabular-nums text-muted">{{ formatNumber(link.scans) }}</td>
                                        <td class="px-5 py-2.5 text-right tabular-nums text-muted">{{ formatNumber(link.visitors) }}</td>
                                    </tr>
                                    <tr v-if="report.top_links.length === 0">
                                        <td colspan="4" class="px-5 py-8 text-center text-faint">No link traffic in this period.</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </SectionCard>

                    <div class="space-y-6">
                        <SectionCard title="Outcomes" description="How resolution attempts ended">
                            <BarList :rows="outcomeRows" />
                        </SectionCard>

                        <SectionCard v-if="report.top_qr_codes.length > 0" title="Top QR codes">
                            <template #icon><QrCode class="h-4 w-4 text-faint" /></template>
                            <div class="space-y-1 p-3">
                                <div v-for="qr in report.top_qr_codes" :key="qr.id" class="flex items-center gap-3 rounded-[5px] px-2.5 py-1.5 text-[13px]">
                                    <span class="min-w-0 flex-1">
                                        <span class="block truncate font-medium text-foreground">{{ qr.name }}</span>
                                        <span v-if="qr.link_slug" class="block truncate text-xs text-faint">/{{ qr.link_slug }}</span>
                                    </span>
                                    <span class="tabular-nums text-muted">{{ formatNumber(qr.scans) }} scans</span>
                                </div>
                            </div>
                        </SectionCard>
                    </div>
                </section>

                <p v-if="hasEvents && summary.bots > 0" class="text-xs text-faint">
                    {{ formatNumber(summary.bots) }} bot and crawler requests were excluded from these figures in this period.
                </p>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
