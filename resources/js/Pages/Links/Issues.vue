<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { CircleCheck, QrCode, RefreshCw } from '@lucide/vue';
import { useIntervalFn } from '@vueuse/core';
import { computed, watch } from 'vue';

import Badge from '@/Components/ui/Badge.vue';
import Button from '@/Components/ui/Button.vue';
import EmptyState from '@/Components/ui/EmptyState.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { relativeTime } from '@/lib/datetime';
import {
  type DestinationIssue,
  hasPendingCheck,
  healthLabel,
  healthNote,
  healthVariant,
} from '@/lib/destinationHealth';
import { displayUrl } from '@/lib/links';
import { useShell } from '@/lib/shell';

const props = defineProps<{
  issues: { open: DestinationIssue[]; recovered: DestinationIssue[] };
  monitoringEnabled: boolean;
}>();

const { canEdit } = useShell();

const description = computed(() =>
  props.monitoringEnabled
    ? 'Destinations that failed repeated checks. Redirects keep working as configured.'
    : 'Destinations that failed repeated checks. Background monitoring is off, so only manual checks appear here.',
);

const pending = computed(() => hasPendingCheck([...props.issues.open, ...props.issues.recovered]));
const POLL_LIMIT = 20;
let polls = 0;
const poll = useIntervalFn(
  () => {
    polls += 1;
    if (polls > POLL_LIMIT) return poll.pause();
    router.reload({ only: ['issues', 'navigation'] });
  },
  3000,
  { immediate: false },
);
watch(
  pending,
  (value) => {
    if (value) {
      polls = 0;
      poll.resume();
    } else {
      poll.pause();
    }
  },
  { immediate: true },
);

function retry(issue: DestinationIssue) {
  router.post(
    route('destination-checks.retry', issue.id),
    {},
    { preserveScroll: true, only: ['issues', 'navigation'] },
  );
}

function traffic(count: number): string {
  return count === 1 ? '1 visit this week' : `${count.toLocaleString()} visits this week`;
}
</script>

<template>
  <Head title="Issues" />

  <AuthenticatedLayout>
    <div class="mx-auto w-full max-w-4xl px-4 py-6 sm:px-6 lg:px-8 lg:py-8">
      <PageHeader title="Issues" :description="description" />

      <section class="mt-6 overflow-hidden rounded-xl border bg-surface" aria-labelledby="open-issues">
        <h2 id="open-issues" class="border-b px-5 py-3 text-[13px] font-medium text-foreground">
          Destination incidents
        </h2>
        <ul v-if="issues.open.length" class="divide-y">
          <li v-for="issue in issues.open" :key="issue.id" class="px-5 py-4">
            <div class="flex items-start gap-3">
              <div class="min-w-0 flex-1">
                <a
                  :href="issue.url"
                  target="_blank"
                  rel="noopener noreferrer"
                  class="block truncate text-sm font-medium text-foreground hover:text-accent"
                  :title="issue.url"
                  >{{ displayUrl(issue.url) }}</a
                >
                <p class="mt-0.5 text-xs text-muted">
                  {{ healthNote(issue, relativeTime) }} · {{ traffic(issue.recent_traffic) }}
                </p>
                <p v-if="issue.detail" class="mt-0.5 text-xs text-faint">{{ issue.detail }}</p>
              </div>
              <Badge :variant="healthVariant(issue)" dot class="shrink-0">{{ healthLabel(issue) }}</Badge>
              <Button
                v-if="canEdit"
                variant="secondary"
                size="sm"
                type="button"
                :disabled="issue.pending"
                @click="retry(issue)"
              >
                <RefreshCw /> {{ issue.pending ? 'Checking…' : 'Retry' }}
              </Button>
            </div>
            <ul class="mt-3 space-y-1.5">
              <li
                v-for="reference in issue.references"
                :key="`${reference.short_link.id}-${reference.role}-${reference.label}`"
                class="flex flex-wrap items-center gap-x-2 gap-y-1 text-[13px]"
              >
                <Link
                  :href="route('links.index', { link: reference.short_link.id })"
                  class="font-medium text-foreground hover:text-accent"
                  >{{ displayUrl(reference.short_link.short_url) }}</Link
                >
                <span class="text-faint">{{ reference.label }}</span>
                <Link
                  v-for="qrCode in reference.qr_codes"
                  :key="qrCode.id"
                  :href="route('qr-codes.show', qrCode.token)"
                  class="inline-flex items-center gap-1 text-xs text-muted hover:text-accent"
                >
                  <QrCode class="h-3 w-3" />{{ qrCode.name }}
                </Link>
              </li>
            </ul>
          </li>
        </ul>
        <EmptyState
          v-else
          title="No destination incidents"
          description="Destinations that fail two checks in a row appear here."
        >
          <template #icon><CircleCheck class="h-5 w-5" /></template>
        </EmptyState>
      </section>

      <section
        v-if="issues.recovered.length"
        class="mt-6 overflow-hidden rounded-xl border bg-surface"
        aria-labelledby="recovered-issues"
      >
        <h2 id="recovered-issues" class="border-b px-5 py-3 text-[13px] font-medium text-foreground">
          Recently recovered
        </h2>
        <ul class="divide-y">
          <li v-for="issue in issues.recovered" :key="issue.id" class="flex items-center gap-3 px-5 py-3">
            <div class="min-w-0 flex-1">
              <p class="truncate text-sm text-foreground" :title="issue.url">{{ displayUrl(issue.url) }}</p>
              <p class="mt-0.5 text-xs text-muted">
                {{ healthNote(issue, relativeTime) }} ·
                {{ issue.references.length === 1 ? '1 link' : `${issue.references.length} links` }}
              </p>
            </div>
            <Badge :variant="healthVariant(issue)" dot>{{ healthLabel(issue) }}</Badge>
          </li>
        </ul>
      </section>
    </div>
  </AuthenticatedLayout>
</template>
