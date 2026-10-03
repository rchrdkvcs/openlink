<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ArrowRight, Copy, Link2 } from '@lucide/vue';

import Favicon from '@/Components/Links/Favicon.vue';
import EmptyState from '@/Components/ui/EmptyState.vue';
import { formatCompact } from '@/lib/analytics';
import { relativeTime } from '@/lib/datetime';
import { displayUrl } from '@/lib/links';
import { copyToClipboard } from '@/lib/toast';

import type { RecentLink } from './types';

defineProps<{ links: RecentLink[]; total: number }>();

const emit = defineEmits<{ open: [id: number] }>();
</script>

<template>
  <section class="mt-12">
    <div class="mb-3 flex items-end justify-between px-1">
      <h2 class="text-[15px] font-semibold tracking-[-0.01em]">Recent links</h2>
      <Link
        :href="route('links.index')"
        class="inline-flex items-center gap-1 text-[13px] font-medium text-muted transition-colors hover:text-foreground"
      >
        All {{ total.toLocaleString() }} links <ArrowRight class="h-3.5 w-3.5" />
      </Link>
    </div>

    <div v-if="links.length" class="divide-y overflow-hidden rounded-xl border bg-surface">
      <div
        v-for="link in links"
        :key="link.id"
        class="flex cursor-default items-center gap-3 px-3.5 py-2 transition-colors hover:bg-elevated/40"
        @click="emit('open', link.id)"
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
</template>
