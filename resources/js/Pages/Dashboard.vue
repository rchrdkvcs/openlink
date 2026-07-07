<script setup lang="ts">
import KpiCard from '@/Components/analytics/KpiCard.vue';
import TimeSeriesChart from '@/Components/analytics/TimeSeriesChart.vue';
import EmptyState from '@/Components/ui/EmptyState.vue';
import SectionCard from '@/Components/ui/SectionCard.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { formatNumber, type ReportRange, type Summary, type TimePoint, type TopLink } from '@/lib/analytics';
import { Head, Link } from '@inertiajs/vue3';
import { ArrowRight, BarChart3, ExternalLink, Globe, Link2, TrendingUp } from '@lucide/vue';
import { computed } from 'vue';

type Workspace = { id: number; name: string; slug: string };
type Domain = { id: number; hostname: string; status: string; is_default: boolean };
type ShortLink = { id: number; status: string; is_enabled: boolean };

const props = defineProps<{
    currentWorkspace: Workspace;
    domains: Domain[];
    links: ShortLink[];
    analytics: {
        range: { preset: string; bucket: ReportRange['bucket'] };
        summary: Summary;
        timeseries: TimePoint[];
        top_links: TopLink[];
    };
}>();

const summary = computed(() => props.analytics.summary);
const activeLinks = computed(() => props.links.filter((link) => link.status === 'active' && link.is_enabled).length);
const verifiedDomains = computed(() => props.domains.filter((domain) => domain.status === 'active').length);
const hasTraffic = computed(() => summary.value.visits + summary.value.scans > 0);
</script>

<template>
    <Head title="Overview" />

    <AuthenticatedLayout>
        <div class="w-full px-4 py-8 sm:px-6 lg:px-8">
            <div class="mb-8 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <h1 class="text-xl font-semibold tracking-tight">Overview</h1>
                    <p class="mt-1 text-sm text-muted">Traffic across this workspace over the last 30 days.</p>
                </div>
                <Link
                    :href="route('analytics.index')"
                    class="inline-flex h-9 w-fit items-center gap-2 rounded-md bg-foreground px-3.5 text-sm font-medium text-background transition-colors duration-150 hover:bg-foreground/85"
                >
                    <BarChart3 class="h-4 w-4" /> Full analytics
                </Link>
            </div>

            <section class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                <KpiCard label="Visits" :value="summary.visits" :change="summary.visits_change" detail="Successful redirects, 30 days" />
                <KpiCard label="Visitors" :value="summary.visitors" :change="summary.visitors_change" detail="Unique per day" />
                <KpiCard label="QR scans" :value="summary.scans" :change="summary.scans_change" detail="Successful scans, 30 days" />
                <KpiCard label="Active links" :value="activeLinks" :detail="`${links.length} links · ${verifiedDomains} verified domains`" />
            </section>

            <section class="mt-6 grid gap-6 xl:grid-cols-[minmax(0,1.4fr)_minmax(320px,0.6fr)]">
                <SectionCard title="Last 30 days" description="Daily visits and scans">
                    <template #icon><TrendingUp class="h-4 w-4 text-faint" /></template>

                    <div v-if="hasTraffic" class="px-4 pb-3 pt-4">
                        <TimeSeriesChart :points="analytics.timeseries" :bucket="analytics.range.bucket" />
                    </div>

                    <EmptyState
                        v-else
                        title="No traffic yet"
                        description="Share a short link or QR code and analytics will appear here within seconds."
                    >
                        <template #icon><TrendingUp class="h-5 w-5" /></template>
                    </EmptyState>
                </SectionCard>

                <SectionCard title="Top links" description="Most visited this month">
                    <template #icon><Link2 class="h-4 w-4 text-faint" /></template>

                    <div class="space-y-1 p-3">
                        <div
                            v-for="link in analytics.top_links"
                            :key="link.id"
                            class="flex items-center gap-3 rounded-[5px] px-2.5 py-1.5 text-[13px]"
                        >
                            <span class="min-w-0 flex-1">
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
                                <span class="block truncate text-xs text-faint">{{ link.destination_url }}</span>
                            </span>
                            <span class="shrink-0 tabular-nums text-muted">{{ formatNumber(link.total) }}</span>
                        </div>

                        <p v-if="analytics.top_links.length === 0" class="px-2.5 py-8 text-center text-[13px] text-faint">
                            No link traffic yet.
                        </p>

                        <Link
                            v-if="analytics.top_links.length > 0"
                            :href="route('analytics.index')"
                            class="mt-1 flex items-center justify-center gap-1.5 rounded-[5px] px-2.5 py-2 text-[13px] font-medium text-muted transition-colors hover:bg-elevated hover:text-foreground"
                        >
                            View all analytics <ArrowRight class="h-3.5 w-3.5" />
                        </Link>
                    </div>
                </SectionCard>
            </section>

            <section v-if="domains.length === 0" class="mt-6">
                <EmptyState title="No domains yet" description="Add and verify a domain to publish short links.">
                    <template #icon><Globe class="h-5 w-5" /></template>
                </EmptyState>
            </section>
        </div>
    </AuthenticatedLayout>
</template>
