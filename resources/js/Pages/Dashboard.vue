<script setup lang="ts">
import Badge from '@/Components/ui/Badge.vue';
import EmptyState from '@/Components/ui/EmptyState.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import SectionCard from '@/Components/ui/SectionCard.vue';
import StatCard from '@/Components/ui/StatCard.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { BarChart3, Globe, Link2, MousePointerClick, QrCode, TrendingUp } from '@lucide/vue';
import { computed } from 'vue';

type Workspace = { id: number; name: string; slug: string };
type Domain = { id: number; hostname: string; status: string; is_default: boolean };
type ShortLink = { id: number; status: string; visits: number; scans: number; is_enabled: boolean };

const props = defineProps<{
    currentWorkspace: Workspace;
    role: string;
    domains: Domain[];
    links: ShortLink[];
    analytics: {
        daily: { date: string; metric: string; outcome: string; count: number }[];
        outcomes: { metric: string; outcome: string; count: number }[];
        devices: { device_type: string; count: number }[];
        countries: { country: string; count: number }[];
        referrers: { referrer_host: string; count: number }[];
    };
}>();

function total(rows: { count: number }[]) {
    return rows.reduce((sum, row) => sum + Number(row.count), 0);
}

const successfulVisits = computed(() => total(props.analytics.daily.filter((row) => row.metric === 'visit' && row.outcome === 'success')));
const successfulScans = computed(() => total(props.analytics.daily.filter((row) => row.metric === 'scan' && row.outcome === 'success')));
const activeLinks = computed(() => props.links.filter((link) => link.status === 'active' && link.is_enabled).length);
const verifiedDomains = computed(() => props.domains.filter((domain) => domain.status === 'verified').length);
const maxDailyCount = computed(() => Math.max(...props.analytics.daily.map((row) => Number(row.count)), 1));
</script>

<template>
    <Head title="Overview" />

    <AuthenticatedLayout>
        <template #header>
            <PageHeader section="Overview">
                <Badge variant="outline">{{ role }}</Badge>
            </PageHeader>
        </template>

        <div class="w-full px-4 py-8 sm:px-6 lg:px-8">
            <div class="mb-8 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <h1 class="text-xl font-semibold tracking-tight">Overview</h1>
                    <p class="mt-1 text-sm text-muted">A quiet snapshot of usage across this workspace.</p>
                </div>
                <Link
                    :href="route('links.index')"
                    class="inline-flex h-9 w-fit items-center gap-2 rounded-md bg-foreground px-3.5 text-sm font-medium text-background transition-colors duration-150 hover:bg-foreground/85"
                >
                    <Link2 class="h-4 w-4" /> Manage links
                </Link>
            </div>

            <section class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                <StatCard label="Active links" :value="activeLinks" :detail="`${links.length} total links`">
                    <template #icon><Link2 class="h-4 w-4" /></template>
                </StatCard>
                <StatCard label="Visits" :value="successfulVisits" detail="Successful redirects">
                    <template #icon><MousePointerClick class="h-4 w-4" /></template>
                </StatCard>
                <StatCard label="QR scans" :value="successfulScans" detail="Tracked scans">
                    <template #icon><QrCode class="h-4 w-4" /></template>
                </StatCard>
                <StatCard label="Domains" :value="verifiedDomains" :detail="`${domains.length} configured`">
                    <template #icon><Globe class="h-4 w-4" /></template>
                </StatCard>
            </section>

            <section class="mt-6 grid gap-6 xl:grid-cols-[minmax(0,1.4fr)_minmax(320px,0.6fr)]">
                <SectionCard title="Last 30 days">
                    <template #icon><TrendingUp class="h-4 w-4 text-faint" /></template>

                    <div class="space-y-2.5 p-5">
                        <div
                            v-for="row in analytics.daily"
                            :key="`${row.date}-${row.metric}-${row.outcome}`"
                            class="grid grid-cols-[110px_64px_1fr_48px] items-center gap-3 text-[13px]"
                        >
                            <span class="truncate tabular-nums text-muted">{{ row.date }}</span>
                            <span class="truncate text-[11px] font-medium uppercase tracking-wide text-faint">{{ row.metric }}</span>
                            <div class="h-1.5 overflow-hidden rounded-full bg-elevated">
                                <div
                                    class="h-full rounded-full bg-accent/80"
                                    :style="{ width: `${Math.max((Number(row.count) / maxDailyCount) * 100, 3)}%` }"
                                />
                            </div>
                            <span class="text-right font-medium tabular-nums text-foreground">{{ row.count }}</span>
                        </div>

                        <EmptyState v-if="analytics.daily.length === 0" title="No analytics yet" description="Activity will appear here once your links get visits.">
                            <template #icon><TrendingUp class="h-5 w-5" /></template>
                        </EmptyState>
                    </div>
                </SectionCard>

                <div class="space-y-6">
                    <SectionCard title="Outcomes">
                        <template #icon><BarChart3 class="h-4 w-4 text-faint" /></template>

                        <div class="space-y-2 p-5">
                            <div v-for="row in analytics.outcomes" :key="`${row.metric}-${row.outcome}`" class="flex justify-between gap-3 text-[13px]">
                                <span class="truncate capitalize text-muted">{{ row.metric }} · {{ row.outcome }}</span>
                                <span class="font-medium tabular-nums text-foreground">{{ row.count }}</span>
                            </div>
                            <p v-if="analytics.outcomes.length === 0" class="text-[13px] text-faint">No outcomes yet.</p>
                        </div>
                    </SectionCard>

                    <SectionCard title="Top sources">
                        <template #icon><Globe class="h-4 w-4 text-faint" /></template>

                        <div class="space-y-2 p-5">
                            <div v-for="row in analytics.referrers" :key="row.referrer_host" class="flex justify-between gap-3 text-[13px]">
                                <span class="truncate text-muted">{{ row.referrer_host }}</span>
                                <span class="font-medium tabular-nums text-foreground">{{ row.count }}</span>
                            </div>
                            <div v-for="row in analytics.countries" :key="row.country" class="flex justify-between gap-3 text-[13px]">
                                <span class="truncate text-muted">{{ row.country }}</span>
                                <span class="font-medium tabular-nums text-foreground">{{ row.count }}</span>
                            </div>
                            <p v-if="analytics.referrers.length === 0 && analytics.countries.length === 0" class="text-[13px] text-faint">No sources yet.</p>
                        </div>
                    </SectionCard>
                </div>
            </section>
        </div>
    </AuthenticatedLayout>
</template>
