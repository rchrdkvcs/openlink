<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ArrowRight, Copy, Link2, TrendingUp } from '@lucide/vue';
import { computed } from 'vue';

import KpiCard from '@/Components/analytics/KpiCard.vue';
import TimeSeriesChart from '@/Components/analytics/TimeSeriesChart.vue';
import Favicon from '@/Components/Links/Favicon.vue';
import type { ComposerLink } from '@/Components/Links/LinkComposer.vue';
import LinkComposer from '@/Components/Links/LinkComposer.vue';
import LogoMark from '@/Components/LogoMark.vue';
import EmptyState from '@/Components/ui/EmptyState.vue';
import SectionCard from '@/Components/ui/SectionCard.vue';
import UserAvatar from '@/Components/UserAvatar.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import {
  formatCompact,
  formatNumber,
  type ReportRange,
  type Summary,
  type TimePoint,
  type TopLink,
} from '@/lib/analytics';
import { relativeTime } from '@/lib/datetime';
import { greetingFor } from '@/lib/greetings';
import { displayUrl } from '@/lib/links';
import { useShell } from '@/lib/shell';
import { copyToClipboard } from '@/lib/toast';

type Workspace = { id: number; name: string; slug: string; preferred_domain_id?: number | null };
type Domain = { id: number; hostname: string; status: string; is_default: boolean };
type RecentLink = {
  id: number;
  slug: string;
  short_url: string;
  destination_url: string;
  visits: number;
  scans: number;
  status: string;
  created_at?: string | null;
};

const props = defineProps<{
  currentWorkspace: Workspace;
  canEditWorkspace: boolean;
  domains: Domain[];
  folders: { id: number; name: string }[];
  linkCounts: { total: number; active: number };
  recentLinks: RecentLink[];
  analytics: {
    range: { preset: string; bucket: ReportRange['bucket'] };
    summary: Summary;
    timeseries: TimePoint[];
    top_links: TopLink[];
  };
}>();

const { user } = useShell();

const summary = computed(() => props.analytics.summary);
const hasTraffic = computed(() => summary.value.visits + summary.value.scans > 0);
const firstName = computed(() => user.value.name.split(' ')[0]);
const greeting = greetingFor(firstName.value);

function openLink(id: number) {
  router.visit(route('links.index', { link: id }));
}

function onEdit(link: ComposerLink) {
  openLink(link.id);
}
</script>

<template>
  <Head title="Home" />

  <AuthenticatedLayout>
    <div class="mx-auto w-full max-w-5xl px-4 py-8 sm:px-6 lg:px-8 lg:py-12">
      <section class="mx-auto max-w-3xl">
        <div class="mb-5 flex justify-center" aria-hidden="true">
          <span class="grid h-10 w-10 place-items-center rounded-xl bg-foreground text-background">
            <LogoMark class="h-[22px] w-auto" />
          </span>
          <UserAvatar
            :name="user.name"
            :src="user.profile_avatar_url"
            size="lg"
            class="-ml-2 !h-10 !w-10 border-0 ring-[3px] ring-canvas"
          />
        </div>
        <h1 class="text-center text-2xl font-semibold tracking-[-0.02em] text-foreground">{{ greeting }}</h1>
        <p class="mt-1 text-center text-sm text-muted">
          Paste a link, get a short one. It’s copied to your clipboard the moment it’s ready.
        </p>

        <LinkComposer
          v-if="canEditWorkspace"
          class="mt-7"
          size="lg"
          autofocus
          :domains="domains"
          :folders="folders"
          :preferred-domain-id="currentWorkspace.preferred_domain_id ?? null"
          @edit="onEdit"
        />
      </section>

      <section class="mt-12">
        <div class="mb-3 flex items-end justify-between px-1">
          <h2 class="text-[15px] font-semibold tracking-[-0.01em]">Recent links</h2>
          <Link
            :href="route('links.index')"
            class="inline-flex items-center gap-1 text-[13px] font-medium text-muted transition-colors hover:text-foreground"
          >
            All {{ linkCounts.total.toLocaleString() }} links <ArrowRight class="h-3.5 w-3.5" />
          </Link>
        </div>

        <div v-if="recentLinks.length" class="divide-y overflow-hidden rounded-xl border bg-surface">
          <div
            v-for="link in recentLinks"
            :key="link.id"
            class="flex cursor-default items-center gap-3 px-3.5 py-2 transition-colors hover:bg-elevated/40"
            @click="openLink(link.id)"
          >
            <Favicon :url="link.destination_url" />
            <div class="min-w-0 flex-1">
              <button
                type="button"
                class="group/copy -mx-1 inline-flex max-w-full items-center gap-1.5 rounded-md px-1 text-left text-[13px] font-medium text-foreground transition-colors hover:bg-border-strong/60 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent/40"
                title="Copy short link"
                @click.stop="copyToClipboard(link.short_url)"
              >
                <span class="truncate">{{ displayUrl(link.short_url) }}</span>
                <Copy
                  class="h-3 w-3 shrink-0 text-faint opacity-0 transition-opacity group-hover/copy:opacity-100 group-focus-visible/copy:opacity-100"
                />
              </button>
              <p class="truncate text-xs text-faint">{{ displayUrl(link.destination_url) }}</p>
            </div>
            <span class="hidden whitespace-nowrap text-right text-xs text-faint sm:block">{{
              relativeTime(link.created_at)
            }}</span>
            <span class="w-16 text-right text-xs tabular-nums text-muted">
              <span class="font-medium text-foreground">{{ formatCompact(link.visits + link.scans) }}</span> clicks
            </span>
          </div>
        </div>
        <div v-else class="rounded-xl border border-dashed">
          <EmptyState title="Your links will appear here" description="Shorten your first URL above to get started.">
            <template #icon><Link2 class="h-5 w-5" /></template>
          </EmptyState>
        </div>
      </section>

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
                @click="openLink(link.id)"
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
    </div>
  </AuthenticatedLayout>
</template>
