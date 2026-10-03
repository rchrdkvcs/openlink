<script setup lang="ts">
import SectionCard from '@/Components/ui/SectionCard.vue';
import { formatNumber, type RoutingPerformanceRow } from '@/lib/analytics';

defineProps<{ rows: RoutingPerformanceRow[] }>();
</script>

<template>
  <SectionCard title="Routing" description="How traffic splits across the default destination, rules and variants">
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
            v-for="row in rows"
            :key="`${row.routing_rule_id ?? 'default'}-${row.routing_variant_id ?? 'none'}`"
            class="border-t"
          >
            <td class="max-w-0 px-5 py-2">
              <span class="block truncate font-medium text-foreground">{{ row.rule_name }}</span>
              <span v-if="row.variant_name" class="block truncate text-xs text-faint">{{ row.variant_name }}</span>
            </td>
            <td class="px-3 py-2 text-end font-medium tabular-nums">{{ formatNumber(row.visits) }}</td>
            <td class="px-3 py-2 text-end tabular-nums text-muted">{{ formatNumber(row.scans) }}</td>
            <td class="px-5 py-2 text-end tabular-nums text-muted">{{ formatNumber(row.visitors) }}</td>
          </tr>
        </tbody>
      </table>
    </div>
  </SectionCard>
</template>
