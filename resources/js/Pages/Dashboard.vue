<script setup lang="ts">
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
            <div class="hidden min-w-0 items-center justify-between gap-4 lg:flex">
                <div class="min-w-0">
                    <p class="text-xs font-medium uppercase text-neutral-500">Overview</p>
                    <h1 class="truncate text-base font-semibold text-neutral-950">{{ currentWorkspace.name }}</h1>
                </div>
                <span class="rounded-md border border-neutral-200 bg-white px-2.5 py-1 text-xs font-medium text-neutral-600">{{ role }}</span>
            </div>
        </template>

        <div class="w-full px-4 py-6 sm:px-6 lg:px-8">
            <div class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <h2 class="text-2xl font-semibold text-neutral-950">Overview</h2>
                    <p class="mt-1 text-sm text-neutral-500">A quiet snapshot of usage across this workspace.</p>
                </div>
                <Link :href="route('links.index')" class="inline-flex h-9 w-fit items-center gap-2 rounded-md bg-neutral-950 px-3 text-sm font-medium text-white hover:bg-neutral-800">
                    <Link2 class="h-4 w-4" /> Manage links
                </Link>
            </div>

            <section class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                <div class="rounded-md border border-neutral-200 bg-[#fafafa] p-4">
                    <div class="flex items-center justify-between text-xs font-medium uppercase text-neutral-500">
                        <span>Active links</span>
                        <Link2 class="h-4 w-4" />
                    </div>
                    <p class="mt-3 text-3xl font-semibold text-neutral-950">{{ activeLinks }}</p>
                    <p class="mt-1 text-sm text-neutral-500">{{ links.length }} total links</p>
                </div>
                <div class="rounded-md border border-neutral-200 bg-[#fafafa] p-4">
                    <div class="flex items-center justify-between text-xs font-medium uppercase text-neutral-500">
                        <span>Visits</span>
                        <MousePointerClick class="h-4 w-4" />
                    </div>
                    <p class="mt-3 text-3xl font-semibold text-neutral-950">{{ successfulVisits }}</p>
                    <p class="mt-1 text-sm text-neutral-500">Successful redirects</p>
                </div>
                <div class="rounded-md border border-neutral-200 bg-[#fafafa] p-4">
                    <div class="flex items-center justify-between text-xs font-medium uppercase text-neutral-500">
                        <span>QR scans</span>
                        <QrCode class="h-4 w-4" />
                    </div>
                    <p class="mt-3 text-3xl font-semibold text-neutral-950">{{ successfulScans }}</p>
                    <p class="mt-1 text-sm text-neutral-500">Tracked scans</p>
                </div>
                <div class="rounded-md border border-neutral-200 bg-[#fafafa] p-4">
                    <div class="flex items-center justify-between text-xs font-medium uppercase text-neutral-500">
                        <span>Domains</span>
                        <Globe class="h-4 w-4" />
                    </div>
                    <p class="mt-3 text-3xl font-semibold text-neutral-950">{{ verifiedDomains }}</p>
                    <p class="mt-1 text-sm text-neutral-500">{{ domains.length }} configured</p>
                </div>
            </section>

            <section class="mt-5 grid gap-5 xl:grid-cols-[minmax(0,1.4fr)_minmax(320px,0.6fr)]">
                <div class="rounded-md border border-neutral-200 bg-white">
                    <div class="border-b border-neutral-200 px-5 py-4">
                        <h3 class="flex items-center gap-2 text-sm font-semibold text-neutral-950"><TrendingUp class="h-4 w-4" /> Last 30 days</h3>
                    </div>
                    <div class="space-y-3 p-5">
                        <div v-for="row in analytics.daily" :key="`${row.date}-${row.metric}-${row.outcome}`" class="grid grid-cols-[118px_76px_1fr_52px] items-center gap-3 text-sm">
                            <span class="truncate text-neutral-500">{{ row.date }}</span>
                            <span class="truncate text-xs font-medium uppercase text-neutral-400">{{ row.metric }}</span>
                            <div class="h-2 overflow-hidden rounded-full bg-neutral-100">
                                <div class="h-full rounded-full bg-neutral-900" :style="{ width: `${Math.max((Number(row.count) / maxDailyCount) * 100, 4)}%` }" />
                            </div>
                            <span class="text-right font-medium text-neutral-950">{{ row.count }}</span>
                        </div>
                        <p v-if="analytics.daily.length === 0" class="py-12 text-center text-sm text-neutral-500">No analytics yet.</p>
                    </div>
                </div>

                <div class="space-y-5">
                    <div class="rounded-md border border-neutral-200 bg-white">
                        <div class="border-b border-neutral-200 px-5 py-4">
                            <h3 class="flex items-center gap-2 text-sm font-semibold text-neutral-950"><BarChart3 class="h-4 w-4" /> Outcomes</h3>
                        </div>
                        <div class="space-y-2 p-5">
                            <div v-for="row in analytics.outcomes" :key="`${row.metric}-${row.outcome}`" class="flex justify-between gap-3 text-sm">
                                <span class="truncate text-neutral-500">{{ row.metric }} / {{ row.outcome }}</span>
                                <span class="font-medium text-neutral-950">{{ row.count }}</span>
                            </div>
                            <p v-if="analytics.outcomes.length === 0" class="text-sm text-neutral-500">No outcomes yet.</p>
                        </div>
                    </div>

                    <div class="rounded-md border border-neutral-200 bg-white">
                        <div class="border-b border-neutral-200 px-5 py-4">
                            <h3 class="text-sm font-semibold text-neutral-950">Top sources</h3>
                        </div>
                        <div class="space-y-2 p-5">
                            <div v-for="row in analytics.referrers" :key="row.referrer_host" class="flex justify-between gap-3 text-sm">
                                <span class="truncate text-neutral-500">{{ row.referrer_host }}</span>
                                <span class="font-medium text-neutral-950">{{ row.count }}</span>
                            </div>
                            <div v-for="row in analytics.countries" :key="row.country" class="flex justify-between gap-3 text-sm">
                                <span class="truncate text-neutral-500">{{ row.country }}</span>
                                <span class="font-medium text-neutral-950">{{ row.count }}</span>
                            </div>
                            <p v-if="analytics.referrers.length === 0 && analytics.countries.length === 0" class="text-sm text-neutral-500">No sources yet.</p>
                        </div>
                    </div>
                </div>
            </section>
        </div>
    </AuthenticatedLayout>
</template>
