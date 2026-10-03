<script setup lang="ts">
import { ChevronDown, CornerDownRight, Plus } from '@lucide/vue';
import { computed, ref, useId } from 'vue';

import Button from '@/Components/ui/Button.vue';
import Menu from '@/Components/ui/Menu.vue';
import MenuItem from '@/Components/ui/MenuItem.vue';
import { createRouting, firstInvalidRule, type FormErrors, type RuleListChange, ruleErrors } from '@/lib/routing';
import type { RoutingRuleDraft, RoutingSchema } from '@/types/shortLinks';

import { presetIcon } from './presetIcons';
import RoutingPresets from './RoutingPresets.vue';
import RoutingRuleCard from './RoutingRuleCard.vue';

const rules = defineModel<RoutingRuleDraft[]>({ required: true });

const props = defineProps<{ errors?: FormErrors; schema: RoutingSchema; defaultDestination?: string }>();

const routing = computed(() => createRouting(props.schema));
const editorId = useId();
const openIndex = ref<number | null>(firstInvalidRule(rules.value, props.errors) ?? (rules.value.length ? 0 : null));

function apply(change: RuleListChange | null) {
  if (!change) return;
  rules.value = change.rules;
  openIndex.value = change.focus;
}

function toggle(index: number) {
  openIndex.value = openIndex.value === index ? null : index;
}
</script>

<template>
  <div class="@container grid min-w-0 gap-4">
    <p v-if="errors?.routing_rules" role="alert" class="rounded-lg bg-danger/10 px-3 py-2 text-xs text-danger">
      {{ errors.routing_rules }}
    </p>

    <RoutingPresets
      v-if="rules.length === 0"
      :presets="schema.presets"
      @pick="apply(routing.addPreset(rules, $event))"
    />

    <template v-else>
      <ol class="grid gap-2" aria-label="Routing rules">
        <RoutingRuleCard
          v-for="(rule, index) in rules"
          :key="rule.id ?? rule.client_id ?? index"
          :rule="rule"
          :index="index"
          :is-last="index === rules.length - 1"
          :open="openIndex === index"
          :errors="ruleErrors(errors, index)"
          :panel-id="`${editorId}-rule-${index}`"
          :routing="routing"
          @toggle="toggle(index)"
          @move="apply(routing.move(rules, index, $event))"
          @duplicate="apply(routing.duplicate(rules, index))"
          @remove="apply(routing.remove(rules, index))"
        />
      </ol>

      <Menu align="start" width="w-56">
        <template #trigger>
          <Button type="button" variant="secondary" size="sm" class="justify-self-start"
            ><Plus />Add rule<ChevronDown class="text-faint"
          /></Button>
        </template>
        <MenuItem
          v-for="preset in schema.presets"
          :key="preset.kind"
          :icon="presetIcon(preset.kind)"
          @select="apply(routing.addPreset(rules, preset.kind))"
          >{{ preset.label }}</MenuItem
        >
      </Menu>
    </template>

    <div class="flex min-w-0 items-center gap-3 rounded-xl bg-elevated/30 px-3.5 py-2.5">
      <CornerDownRight class="h-3.5 w-3.5 shrink-0 text-faint" />
      <span class="min-w-0 flex-1">
        <span class="block text-[13px] font-medium text-foreground">{{ rules.length ? 'Otherwise' : 'Everyone' }}</span>
        <span class="block truncate text-xs" :class="defaultDestination ? 'text-muted' : 'text-faint'">{{
          defaultDestination || 'Add a destination URL to this link.'
        }}</span>
      </span>
      <span class="shrink-0 text-xs text-faint">Default destination</span>
    </div>
  </div>
</template>
