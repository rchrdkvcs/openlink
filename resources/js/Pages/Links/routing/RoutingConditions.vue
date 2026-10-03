<script setup lang="ts">
import { Plus } from '@lucide/vue';

import Button from '@/Components/ui/Button.vue';
import SegmentedControl from '@/Components/ui/SegmentedControl.vue';
import { conditionsLead, type Routing, ruleName } from '@/lib/routing';
import type { RoutingRuleDraft } from '@/types/shortLinks';

import RoutingConditionRow from './RoutingConditionRow.vue';

defineProps<{ rule: RoutingRuleDraft; routing: Routing }>();

const matchModeOptions: { value: RoutingRuleDraft['match_mode']; label: string }[] = [
  { value: 'all', label: 'All' },
  { value: 'any', label: 'Any' },
];
</script>

<template>
  <section class="grid gap-2" :aria-label="`Conditions for ${ruleName(rule)}`">
    <div class="flex min-h-7 flex-wrap items-center justify-between gap-x-4 gap-y-2">
      <h4 class="text-[13px] font-medium text-foreground">
        If <span class="font-normal text-muted">{{ conditionsLead(rule) }}</span>
      </h4>
      <div v-if="rule.conditions.length > 1" class="flex items-center gap-2">
        <span class="text-xs text-muted">Match</span>
        <SegmentedControl
          v-model="rule.match_mode"
          size="sm"
          label="Condition matching mode"
          :options="matchModeOptions"
        />
      </div>
    </div>

    <RoutingConditionRow
      v-for="(condition, index) in rule.conditions"
      :key="index"
      :condition="condition"
      :position="index + 1"
      :routing="routing"
      @remove="rule.conditions.splice(index, 1)"
    />

    <p
      v-if="rule.conditions.length === 0"
      class="flex h-8 items-center rounded-lg bg-elevated/40 px-2.5 text-xs text-muted"
    >
      Add a condition to narrow the audience.
    </p>

    <Button
      type="button"
      variant="ghost"
      size="sm"
      class="-ms-2.5 justify-self-start"
      @click="rule.conditions.push(routing.newCondition())"
      ><Plus />Add condition</Button
    >
  </section>
</template>
