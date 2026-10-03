<script setup lang="ts">
import { Check, Copy } from '@lucide/vue';

import Badge from '@/Components/ui/Badge.vue';
import type { DnsRecordRequirement } from '@/lib/domains';
import { copyToClipboard } from '@/lib/toast';

const props = defineProps<{ record: DnsRecordRequirement }>();

const copyable = [
  { label: 'Name', field: 'name', copied: 'Name copied' },
  { label: 'Value', field: 'value', copied: 'Value copied' },
] as const;

function copy(field: 'name' | 'value', message: string) {
  copyToClipboard(props.record[field], message);
}
</script>

<template>
  <section
    class="overflow-hidden rounded-xl border bg-surface transition-colors duration-200"
    :class="record.done ? 'border-success/30' : ''"
  >
    <header class="flex items-center justify-between gap-3 px-4 py-3.5 sm:px-5">
      <div class="flex min-w-0 items-center gap-3">
        <span
          class="grid h-5 w-5 shrink-0 place-items-center rounded-full transition-colors duration-200"
          :class="record.done ? 'bg-success text-white' : 'border border-border-strong'"
        >
          <Check v-if="record.done" class="h-3 w-3" />
        </span>
        <div class="min-w-0">
          <h2 class="text-sm font-medium text-foreground">{{ record.type }} record</h2>
          <p class="text-[13px] text-muted">{{ record.purpose }}</p>
        </div>
      </div>
      <Badge :variant="record.done ? 'success' : 'warning'" dot class="shrink-0">
        {{ record.done ? 'Found' : 'Waiting' }}
      </Badge>
    </header>

    <dl class="divide-y divide-border border-t">
      <div class="grid grid-cols-[64px_minmax(0,1fr)_32px] items-center gap-3 px-4 py-2 sm:px-5">
        <dt class="text-[13px] text-muted">Type</dt>
        <dd class="truncate font-mono text-[13px] text-foreground">{{ record.type }}</dd>
        <span />
      </div>
      <div
        v-for="row in copyable"
        :key="row.field"
        class="grid grid-cols-[64px_minmax(0,1fr)_32px] items-center gap-3 px-4 py-2 sm:px-5"
      >
        <dt class="text-[13px] text-muted">{{ row.label }}</dt>
        <dd class="truncate font-mono text-[13px] text-foreground" :title="record[row.field]">
          {{ record[row.field] }}
        </dd>
        <button
          type="button"
          class="grid h-8 w-8 place-items-center rounded-lg text-faint transition-colors duration-150 hover:bg-elevated hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent/25"
          :aria-label="`Copy ${record.type} record ${row.field}`"
          @click="copy(row.field, row.copied)"
        >
          <Copy class="h-3.5 w-3.5" />
        </button>
      </div>
    </dl>

    <p v-if="record.error && !record.done" class="border-t px-4 py-2.5 text-xs text-warning sm:px-5">
      {{ record.error }}
    </p>
  </section>
</template>
