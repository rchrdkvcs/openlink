<script setup lang="ts">
import { computed } from 'vue';

import Badge from '@/Components/ui/Badge.vue';
import SectionCard from '@/Components/ui/SectionCard.vue';
import { formatNumber, type ConversionReport } from '@/lib/analytics';
import { conversionRate, evidenceNote, hasRoutedConversions, pendingNote, windowNote } from '@/lib/conversions';

const props = defineProps<{ report: ConversionReport }>();

const totals = computed(() => props.report.totals);
const showRouting = computed(() => hasRoutedConversions(props.report));
</script>

<template>
  <SectionCard title="Conversions" :description="windowNote(report)">
    <dl class="grid grid-cols-2 divide-x border-b sm:grid-cols-4">
      <div class="px-5 py-3">
        <dt class="text-xs text-faint">Attributed visits</dt>
        <dd class="mt-0.5 text-[15px] font-semibold tabular-nums">{{ formatNumber(totals.attributed_visits) }}</dd>
      </div>
      <div class="px-5 py-3">
        <dt class="text-xs text-faint">Conversions</dt>
        <dd class="mt-0.5 text-[15px] font-semibold tabular-nums">{{ formatNumber(totals.conversions) }}</dd>
      </div>
      <div class="px-5 py-3">
        <dt class="text-xs text-faint">Conversion rate</dt>
        <dd class="mt-0.5 text-[15px] font-semibold tabular-nums">{{ conversionRate(totals) }}</dd>
      </div>
      <div class="px-5 py-3">
        <dt class="text-xs text-faint">Still in window</dt>
        <dd class="mt-0.5 text-[15px] font-semibold tabular-nums">{{ formatNumber(totals.pending) }}</dd>
      </div>
    </dl>

    <p v-if="report.links.length === 0" class="px-5 py-6 text-center text-[13px] text-muted">
      No attributed visits in this period. Visits to links with a conversion goal appear here.
    </p>

    <div v-else class="overflow-x-auto">
      <table class="w-full text-[13px]">
        <caption class="sr-only">
          Conversions by link
        </caption>
        <thead class="text-xs text-faint">
          <tr>
            <th scope="col" class="px-5 py-2.5 text-start font-medium">Link and goal</th>
            <th scope="col" class="px-3 py-2.5 text-end font-medium">Attributed visits</th>
            <th scope="col" class="px-3 py-2.5 text-end font-medium">Conversions</th>
            <th scope="col" class="px-5 py-2.5 text-end font-medium">Rate</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="row in report.links" :key="row.id" class="border-t">
            <td class="max-w-0 px-5 py-2">
              <span class="block truncate font-medium text-foreground" :title="row.short_url ?? undefined">
                /{{ row.slug ?? 'deleted link' }}
              </span>
              <span class="flex min-w-0 items-center gap-2 text-xs text-faint">
                <span class="truncate" :title="row.conversion_goal ?? undefined">{{
                  row.conversion_goal ?? 'Goal removed'
                }}</span>
                <span v-if="pendingNote(row.pending)" class="shrink-0">· {{ pendingNote(row.pending) }}</span>
              </span>
            </td>
            <td class="px-3 py-2 text-end tabular-nums text-muted">{{ formatNumber(row.attributed_visits) }}</td>
            <td class="px-3 py-2 text-end font-medium tabular-nums">{{ formatNumber(row.conversions) }}</td>
            <td class="px-5 py-2 text-end">
              <span class="block tabular-nums">{{ conversionRate(row) }}</span>
              <Badge v-if="evidenceNote(report, row)" variant="warning" class="ms-auto mt-0.5">
                {{ evidenceNote(report, row) }}
              </Badge>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <div v-if="showRouting" class="overflow-x-auto border-t">
      <table class="w-full text-[13px]">
        <caption class="px-5 pt-3 text-start text-xs font-medium text-muted">
          By routing destination
        </caption>
        <thead class="text-xs text-faint">
          <tr>
            <th scope="col" class="px-5 py-2.5 text-start font-medium">Destination</th>
            <th scope="col" class="px-3 py-2.5 text-end font-medium">Attributed visits</th>
            <th scope="col" class="px-3 py-2.5 text-end font-medium">Conversions</th>
            <th scope="col" class="px-5 py-2.5 text-end font-medium">Rate</th>
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="row in report.routing"
            :key="`${row.short_link_id}-${row.routing_rule_id ?? 'default'}-${row.routing_variant_id ?? 'none'}`"
            class="border-t"
          >
            <td class="max-w-0 px-5 py-2">
              <span
                class="block truncate font-medium text-foreground"
                :title="row.variant_name ? `${row.rule_name} · ${row.variant_name}` : row.rule_name"
              >
                {{ row.variant_name ? `${row.rule_name} · ${row.variant_name}` : row.rule_name }}
              </span>
              <span class="block truncate text-xs text-faint">/{{ row.slug ?? 'deleted link' }}</span>
            </td>
            <td class="px-3 py-2 text-end tabular-nums text-muted">{{ formatNumber(row.attributed_visits) }}</td>
            <td class="px-3 py-2 text-end font-medium tabular-nums">{{ formatNumber(row.conversions) }}</td>
            <td class="px-5 py-2 text-end">
              <span class="block tabular-nums">{{ conversionRate(row) }}</span>
              <Badge v-if="evidenceNote(report, row)" variant="warning" class="ms-auto mt-0.5">
                {{ evidenceNote(report, row) }}
              </Badge>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <p class="border-t px-5 py-3 text-xs text-faint">
      Rates divide conversions by attributed visits. Openlink compares destinations but never declares a winner.
    </p>
  </SectionCard>
</template>
