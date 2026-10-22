<script setup lang="ts">
import { ArrowDown, ArrowUp, Copy, Trash2 } from '@lucide/vue';
import { computed } from 'vue';

import Button from '@/Components/ui/Button.vue';
import Field from '@/Components/ui/Field.vue';
import Input from '@/Components/ui/Input.vue';
import SegmentedControl from '@/Components/ui/SegmentedControl.vue';
import Switch from '@/Components/ui/Switch.vue';
import { type Routing, variantShare } from '@/lib/routing';
import type { GraphNode } from '@/lib/routing/graph';
import type { RoutingRuleDraft } from '@/types/shortLinks';

import RoutingConditions from './RoutingConditions.vue';
import RoutingVariants from './RoutingVariants.vue';

const props = defineProps<{
  node: GraphNode;
  rule: RoutingRuleDraft | null;
  variantIndex: number;
  isLast: boolean;
  routing: Routing;
  canEdit: boolean;
  errors: [string, string][];
}>();

const defaultDestination = defineModel<string>('defaultDestination', { required: true });
const emit = defineEmits<{
  close: [];
  move: [direction: -1 | 1];
  duplicate: [];
  remove: [];
  destination: [];
  rule: [index: number];
}>();
const variant = computed(() => props.rule?.variants[props.variantIndex] ?? null);
const ruleTypes = [
  { value: 'conditional' as const, label: 'Destination' },
  { value: 'split_test' as const, label: 'Split test' },
];

function removeVariant() {
  const index = props.node.type === 'variant' ? props.node.data.index : null;
  props.rule?.variants.splice(props.variantIndex, 1);
  if (index !== null) emit('rule', index);
}
</script>

<template>
  <div class="@container min-h-0 overflow-y-auto overscroll-contain px-5 py-4">
    <fieldset :disabled="!canEdit" class="grid min-w-0 gap-5">
      <template v-if="node.type === 'default'">
        <p class="text-[13px] text-muted">Visitors who match no enabled routing rule go here.</p>
        <Field label="Destination URL"
          ><Input v-model="defaultDestination" type="url" spellcheck="false" placeholder="https://example.com/page"
        /></Field>
      </template>
      <template v-else-if="rule && node.type === 'rule'">
        <div class="flex items-end gap-4">
          <Field label="Rule name" class="min-w-0 flex-1"
            ><Input v-model="rule.name" placeholder="Untitled routing rule"
          /></Field>
          <label class="flex h-8 items-center gap-2 text-[13px] text-muted"
            ><Switch v-model="rule.is_enabled" aria-label="Enable routing rule" />Enabled</label
          >
        </div>
        <div class="flex flex-wrap items-center justify-between gap-2">
          <span class="text-[13px] font-medium">When matched, send visitors to</span>
          <SegmentedControl
            :model-value="rule.type"
            :options="ruleTypes"
            label="Rule type"
            size="sm"
            @update:model-value="routing.setRuleType(rule!, $event)"
          />
        </div>
        <RoutingConditions :rule="rule!" :routing="routing" />
        <RoutingVariants v-if="rule.type === 'split_test'" :rule="rule!" :routing="routing" />
        <Button v-else type="button" variant="secondary" class="justify-self-start" @click="emit('destination')"
          >Configure destination</Button
        >
        <div class="flex flex-wrap items-center gap-2 border-t pt-4">
          <Button
            type="button"
            variant="secondary"
            size="sm"
            :disabled="node.data.index === 0"
            @click="emit('move', -1)"
            ><ArrowUp />Move up</Button
          >
          <Button type="button" variant="secondary" size="sm" :disabled="isLast" @click="emit('move', 1)"
            ><ArrowDown />Move down</Button
          >
          <Button type="button" variant="ghost" size="sm" @click="emit('duplicate')"><Copy />Duplicate</Button>
          <Button type="button" variant="danger" size="sm" @click="emit('remove')"><Trash2 />Delete rule</Button>
        </div>
      </template>
      <template v-else-if="rule && node.type === 'destination' && rule.type === 'conditional'">
        <p class="text-[13px] text-muted">Destination for {{ rule.name || 'this routing rule' }}.</p>
        <Field label="Destination URL"
          ><Input
            v-model="rule!.destination_url"
            type="url"
            spellcheck="false"
            placeholder="https://example.com/landing"
        /></Field>
      </template>
      <template v-else-if="rule && variant && node.type === 'variant'">
        <Field label="Variant name"><Input v-model="variant!.name" /></Field>
        <Field label="Destination URL"
          ><Input
            v-model="variant!.destination_url"
            type="url"
            spellcheck="false"
            placeholder="https://example.com/variant"
        /></Field>
        <div class="flex items-end gap-4">
          <Field label="Weight" class="min-w-0 flex-1"
            ><Input v-model="variant!.weight" type="number" min="1" step="1"
          /></Field>
          <label class="flex h-8 items-center gap-2 text-[13px] text-muted"
            ><Switch v-model="variant!.is_enabled" aria-label="Enable variant" />Enabled</label
          >
        </div>
        <p class="text-xs text-muted">
          {{ variantShare(rule!, variant!) }}% of matched traffic. Open the split rule to manage all variants.
        </p>
        <Button type="button" variant="danger" class="justify-self-start" @click="removeVariant"
          ><Trash2 />Remove variant</Button
        >
      </template>
      <RoutingVariants v-else-if="rule && rule.type === 'split_test'" :rule="rule!" :routing="routing" />
    </fieldset>
    <div v-if="errors.length" role="alert" class="mt-4 grid gap-1 rounded-lg bg-danger/10 px-3 py-2">
      <p v-for="[key, error] in errors" :key="key" class="text-xs text-danger">{{ error }}</p>
    </div>
  </div>
  <footer class="flex shrink-0 items-center justify-between gap-3 border-t px-5 py-3">
    <p class="text-xs text-muted">
      {{ canEdit ? 'Changes stay in your draft until saved.' : 'Read-only routing details.' }}
    </p>
    <Button type="button" @click="emit('close')">Done</Button>
  </footer>
</template>
