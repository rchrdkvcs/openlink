<script setup lang="ts">
import { Plus } from '@lucide/vue';

import Button from '@/Components/ui/Button.vue';
import { activeVariants, type Routing, ruleName, shareDescription, variantShare } from '@/lib/routing';
import type { RoutingRuleDraft, RoutingVariantDraft } from '@/types/shortLinks';

import RoutingVariantRow from './RoutingVariantRow.vue';

const props = defineProps<{ rule: RoutingRuleDraft; routing: Routing }>();

const shareTones = ['bg-accent', 'bg-accent/60', 'bg-accent/35', 'bg-accent/20'];

function tone(variant: RoutingVariantDraft) {
  const position = activeVariants(props.rule).indexOf(variant);
  return position === -1 ? 'bg-border-strong' : shareTones[position % shareTones.length];
}
</script>

<template>
  <section class="grid gap-2" :aria-label="`Variants for ${ruleName(rule)}`">
    <h4 class="flex min-h-7 items-center text-[13px] font-medium text-foreground">
      Then <span class="ms-1 font-normal text-muted">split traffic between variants</span>
    </h4>

    <div
      class="flex h-1.5 gap-0.5 overflow-hidden rounded-full bg-elevated"
      role="img"
      :aria-label="shareDescription(rule)"
    >
      <span
        v-for="variant in activeVariants(rule)"
        :key="variant.id ?? variant.client_id ?? variant.name"
        class="ease-emphasized-out h-full basis-0 transition-[flex-grow] duration-200"
        :class="tone(variant)"
        :style="{ flexGrow: variantShare(rule, variant) }"
      />
    </div>

    <div
      class="@xl:grid hidden grid-cols-[7rem_minmax(0,1fr)_4.5rem_4rem_2.25rem_1.75rem] gap-x-2 pt-1 text-xs text-faint"
      aria-hidden="true"
    >
      <span class="px-2.5">Variant</span>
      <span class="px-2.5">Destination</span>
      <span class="px-2.5">Weight</span>
      <span class="text-end">Share</span>
    </div>

    <RoutingVariantRow
      v-for="(variant, index) in rule.variants"
      :key="variant.id ?? variant.client_id ?? index"
      :variant="variant"
      :position="index + 1"
      :share="variantShare(rule, variant)"
      :tone="tone(variant)"
      @remove="rule.variants.splice(index, 1)"
    />

    <Button type="button" variant="ghost" size="sm" class="-ms-2.5 justify-self-start" @click="routing.addVariant(rule)"
      ><Plus />Add variant</Button
    >
  </section>
</template>
