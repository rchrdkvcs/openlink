<script setup lang="ts">
import {
  CalendarClock,
  ChevronDown,
  ChevronRight,
  Copy,
  Globe2,
  Megaphone,
  MonitorSmartphone,
  Plus,
  Route,
  Shuffle,
  Trash2,
} from '@lucide/vue';
import { ref } from 'vue';

import Button from '@/Components/ui/Button.vue';
import Switch from '@/Components/ui/Switch.vue';

import type { RoutingCondition, RoutingOption, RoutingRuleDraft, RoutingSchema, RoutingVariantDraft } from './types';

const rules = defineModel<RoutingRuleDraft[]>({ required: true });

const props = defineProps<{
  errors?: Record<string, string | undefined>;
  schema: RoutingSchema;
}>();

const timezone = Intl.DateTimeFormat().resolvedOptions().timeZone || 'UTC';
const openIndex = ref<number | null>(null);
const menuOpen = ref(false);

const presetIcons: Record<string, unknown> = {
  country: Globe2,
  device: MonitorSmartphone,
  campaign: Megaphone,
  time: CalendarClock,
  split: Shuffle,
  custom: Plus,
};

function uid() {
  return Math.random().toString(36).slice(2, 10);
}

function newCondition(type = 'country'): RoutingCondition {
  if (type === 'time_of_day') {
    return { type, operator: 'between', value: { from: '09:00', to: '18:00' }, timezone };
  }

  if (type === 'date_time') {
    return { type, operator: 'after', value: '', timezone };
  }

  if (type === 'day_of_week') {
    return { type, operator: 'is', value: 'monday', timezone };
  }

  return { type, operator: 'is', value: props.schema.defaults[type] ?? '' };
}

function newVariant(name: string): RoutingVariantDraft {
  return {
    client_id: uid(),
    name,
    is_enabled: true,
    destination_url: '',
    weight: 50,
  };
}

function newRule(type: RoutingRuleDraft['type'], conditionType = 'country'): RoutingRuleDraft {
  return {
    client_id: uid(),
    name: type === 'split_test' ? 'Split test' : `${labelFor(conditionType)} routing`,
    type,
    is_enabled: true,
    match_mode: 'all',
    conditions: conditionType === 'custom' ? [] : [newCondition(conditionType)],
    destination_url: '',
    variants: type === 'split_test' ? [newVariant('A'), newVariant('B')] : [],
  };
}

function ruleFromKind(kind: string): RoutingRuleDraft {
  const preset = props.schema.presets.find((entry) => entry.kind === kind) ?? props.schema.presets[0];

  return newRule(preset.ruleType, preset.conditionType);
}

function addRule(kind: string) {
  rules.value = [...rules.value, ruleFromKind(kind)];
  openIndex.value = rules.value.length - 1;
  menuOpen.value = false;
}

function toggle(index: number) {
  openIndex.value = openIndex.value === index ? null : index;
}

function duplicateRule(index: number) {
  const copy = JSON.parse(JSON.stringify(rules.value[index])) as RoutingRuleDraft;
  copy.id = undefined;
  copy.client_id = uid();
  copy.name = `${copy.name} copy`;
  copy.variants = copy.variants.map((variant) => ({ ...variant, id: undefined, client_id: uid() }));
  rules.value = [...rules.value.slice(0, index + 1), copy, ...rules.value.slice(index + 1)];
  openIndex.value = index + 1;
}

function removeRule(index: number) {
  rules.value = rules.value.filter((_, current) => current !== index);
  openIndex.value = null;
}

function moveRule(index: number, direction: -1 | 1) {
  const next = index + direction;
  if (next < 0 || next >= rules.value.length) return;
  const clone = [...rules.value];
  [clone[index], clone[next]] = [clone[next], clone[index]];
  rules.value = clone;
  openIndex.value = next;
}

function onRuleTypeChange(rule: RoutingRuleDraft) {
  if (rule.type === 'split_test' && rule.variants.length === 0) {
    rule.variants = [newVariant('A'), newVariant('B')];
  }
}

function onConditionTypeChange(condition: RoutingCondition) {
  const replacement = newCondition(condition.type);
  condition.operator = replacement.operator;
  condition.value = replacement.value;
  condition.timezone = replacement.timezone;
}

function operatorsFor(condition: RoutingCondition) {
  return ['date_time', 'time_of_day'].includes(condition.type)
    ? props.schema.operators.time
    : props.schema.operators.scalar;
}

function labelFor(type: string) {
  return props.schema.conditionTypes.find((option) => option.value === type)?.label ?? type;
}

function formatValue(condition: RoutingCondition) {
  if (condition.operator === 'is_empty' || condition.operator === 'is_not_empty') return '';
  if (typeof condition.value === 'object' && condition.value && !Array.isArray(condition.value)) {
    return `${condition.value.from ?? ''}-${condition.value.to ?? ''}`;
  }
  return Array.isArray(condition.value) ? condition.value.join(', ') : condition.value || '';
}

function ruleSummary(rule: RoutingRuleDraft) {
  const conditionSummary = rule.conditions.length
    ? rule.conditions
        .map((condition) =>
          `${labelFor(condition.type)} ${condition.operator.replaceAll('_', ' ')} ${formatValue(condition)}`.trim(),
        )
        .join(rule.match_mode === 'all' ? ' + ' : ' / ')
    : 'All visitors';

  if (rule.type === 'split_test') {
    const variants = rule.variants
      .filter((variant) => variant.is_enabled)
      .map((variant) => `${variant.weight}% ${variant.name}`)
      .join(', ');
    return `${conditionSummary} -> ${variants || 'No active variants'}`;
  }

  return `${conditionSummary} -> ${rule.destination_url || 'No destination yet'}`;
}

function hasValueInput(condition: RoutingCondition) {
  return condition.operator !== 'is_empty' && condition.operator !== 'is_not_empty';
}

function valueOptions(condition: RoutingCondition): RoutingOption[] | null {
  return props.schema.valueOptions[condition.type] ?? null;
}

function rangeValue(condition: RoutingCondition): { from?: string; to?: string } {
  if (typeof condition.value !== 'object' || condition.value === null || Array.isArray(condition.value)) {
    condition.value = { from: '', to: '' };
  }

  return condition.value;
}
</script>

<template>
  <div class="grid gap-3">
    <div
      v-if="props.errors?.routing_rules"
      class="rounded-md border border-danger/30 bg-danger/10 px-3 py-2 text-xs text-danger"
    >
      {{ props.errors.routing_rules }}
    </div>

    <div v-if="rules.length === 0" class="rounded-lg border border-dashed bg-surface p-4">
      <div class="flex items-start gap-3">
        <span class="grid h-9 w-9 shrink-0 place-items-center rounded-lg border border-accent/30 bg-accent/10">
          <Route class="h-4 w-4 text-accent" />
        </span>
        <div class="min-w-0">
          <p class="text-sm font-medium text-foreground">Smart Routing</p>
          <p class="mt-1 text-xs leading-5 text-faint">
            Choose destinations by country, device, campaign, time, or split traffic between variants.
          </p>
        </div>
      </div>

      <div class="mt-4 grid grid-cols-2 gap-2">
        <button
          v-for="preset in schema.presets"
          :key="preset.kind"
          type="button"
          class="flex items-center gap-2 rounded-md border bg-elevated/40 px-3 py-2 text-left transition-colors hover:border-border-strong hover:bg-elevated"
          @click="addRule(preset.kind)"
        >
          <component :is="presetIcons[preset.kind] ?? Plus" class="h-3.5 w-3.5 shrink-0 text-accent" />
          <span class="text-[13px] font-medium text-foreground">{{ preset.label }}</span>
        </button>
      </div>
    </div>

    <div v-else class="flex items-center justify-between gap-3">
      <p class="text-xs text-faint">
        {{ rules.length }} rule{{ rules.length > 1 ? 's' : '' }} · evaluated top to bottom, first match wins.
      </p>
      <div class="relative">
        <Button type="button" variant="secondary" size="sm" @click="menuOpen = !menuOpen">
          <Plus class="h-3.5 w-3.5" />Add rule<ChevronDown class="h-3 w-3 text-faint" />
        </Button>
        <div v-if="menuOpen" class="fixed inset-0 z-10" @click="menuOpen = false" />
        <Transition
          enter-active-class="transition ease-emphasized-out duration-150"
          enter-from-class="opacity-0 scale-[0.97] -translate-y-0.5"
          enter-to-class="opacity-100 scale-100 translate-y-0"
          leave-active-class="transition ease-out duration-100"
          leave-from-class="opacity-100 scale-100"
          leave-to-class="opacity-0 scale-[0.97] -translate-y-0.5"
        >
          <div
            v-if="menuOpen"
            class="absolute right-0 z-20 mt-1.5 w-64 origin-top-right rounded-lg border border-border-strong bg-overlay p-1 shadow-drawer"
          >
            <button
              v-for="preset in schema.presets"
              :key="preset.kind"
              type="button"
              class="flex w-full items-start gap-2.5 rounded-md px-2.5 py-2 text-left transition-colors hover:bg-elevated"
              @click="addRule(preset.kind)"
            >
              <component :is="presetIcons[preset.kind] ?? Plus" class="mt-0.5 h-3.5 w-3.5 shrink-0 text-accent" />
              <span class="min-w-0">
                <span class="block text-[13px] font-medium text-foreground">{{ preset.label }}</span>
                <span class="block text-xs text-faint">{{ preset.description }}</span>
              </span>
            </button>
          </div>
        </Transition>
      </div>
    </div>

    <article
      v-for="(rule, index) in rules"
      :key="rule.id ?? rule.client_id ?? index"
      class="rounded-lg border bg-surface transition-colors"
      :class="openIndex === index ? 'border-accent/50' : 'hover:border-border-strong'"
    >
      <div class="flex items-center gap-2.5 p-3">
        <button type="button" class="flex min-w-0 flex-1 items-center gap-2.5 text-left" @click="toggle(index)">
          <ChevronRight
            class="h-3.5 w-3.5 shrink-0 text-faint transition-transform"
            :class="openIndex === index && 'rotate-90'"
          />
          <span class="min-w-0">
            <span
              class="block truncate text-[13px] font-medium"
              :class="rule.is_enabled ? 'text-foreground' : 'text-faint line-through'"
              >{{ rule.name }}</span
            >
            <span class="mt-0.5 block truncate text-xs text-faint">{{ ruleSummary(rule) }}</span>
          </span>
        </button>
        <Switch v-model="rule.is_enabled" />
      </div>

      <div v-if="openIndex === index" class="grid gap-4 border-t bg-elevated/30 p-3">
        <div class="grid gap-3 sm:grid-cols-[1fr_140px]">
          <input v-model="rule.name" class="h-9" placeholder="Rule name" />
          <select v-model="rule.type" class="h-9" @change="onRuleTypeChange(rule)">
            <option value="conditional">Destination</option>
            <option value="split_test">Split test</option>
          </select>
        </div>

        <div class="grid gap-2">
          <div class="flex items-center justify-between gap-3">
            <p class="text-xs font-medium uppercase tracking-wide text-faint">When</p>
            <div class="grid grid-cols-2 rounded-md border bg-surface p-0.5 text-xs">
              <button
                v-for="mode in ['all', 'any'] as const"
                :key="mode"
                type="button"
                class="rounded px-2.5 py-1 font-medium transition-colors"
                :class="rule.match_mode === mode ? 'bg-elevated text-foreground' : 'text-faint hover:text-foreground'"
                @click="rule.match_mode = mode"
              >
                {{ mode === 'all' ? 'All match' : 'Any match' }}
              </button>
            </div>
          </div>

          <p v-if="rule.conditions.length === 0" class="rounded-md border border-dashed px-3 py-2 text-xs text-faint">
            No condition - applies to every visitor.
          </p>

          <div
            v-for="(condition, conditionIndex) in rule.conditions"
            :key="conditionIndex"
            class="grid gap-2 rounded-md border bg-surface p-2 sm:grid-cols-[140px_140px_1fr_auto]"
          >
            <select v-model="condition.type" class="h-9" @change="onConditionTypeChange(condition)">
              <option v-for="option in schema.conditionTypes" :key="option.value" :value="option.value">
                {{ option.label }}
              </option>
            </select>
            <select v-model="condition.operator" class="h-9">
              <option v-for="option in operatorsFor(condition)" :key="option.value" :value="option.value">
                {{ option.label }}
              </option>
            </select>

            <template v-if="hasValueInput(condition)">
              <div v-if="condition.operator === 'between'" class="grid grid-cols-2 gap-2">
                <input
                  v-model="rangeValue(condition).from"
                  class="h-9"
                  :type="condition.type === 'date_time' ? 'datetime-local' : 'time'"
                />
                <input
                  v-model="rangeValue(condition).to"
                  class="h-9"
                  :type="condition.type === 'date_time' ? 'datetime-local' : 'time'"
                />
              </div>
              <select v-else-if="valueOptions(condition)" v-model="condition.value" class="h-9">
                <option v-for="option in valueOptions(condition)" :key="option.value" :value="option.value">
                  {{ option.label }}
                </option>
              </select>
              <input
                v-else
                v-model="condition.value"
                class="h-9"
                :type="condition.type === 'date_time' ? 'datetime-local' : 'text'"
                placeholder="Value"
              />
            </template>
            <div v-else class="h-9 rounded-md border bg-elevated px-3 py-2 text-xs text-faint">No value needed</div>

            <Button
              type="button"
              variant="ghost"
              size="sm"
              title="Remove condition"
              @click="rule.conditions.splice(conditionIndex, 1)"
            >
              <Trash2 class="h-3.5 w-3.5" />
            </Button>

            <input
              v-if="['date_time', 'day_of_week', 'time_of_day'].includes(condition.type)"
              v-model="condition.timezone"
              class="h-8 sm:col-span-4"
              placeholder="Timezone"
            />
          </div>

          <Button
            type="button"
            variant="ghost"
            size="sm"
            class="justify-self-start"
            @click="rule.conditions.push(newCondition())"
          >
            <Plus class="h-3.5 w-3.5" />Condition
          </Button>
        </div>

        <div class="grid gap-2">
          <p class="text-xs font-medium uppercase tracking-wide text-faint">Then</p>
          <template v-if="rule.type === 'conditional'">
            <input v-model="rule.destination_url" class="h-9" placeholder="https://example.com/landing" />
            <p v-if="props.errors?.[`routing_rules.${index}.destination_url`]" class="text-xs text-danger">
              {{ props.errors[`routing_rules.${index}.destination_url`] }}
            </p>
          </template>
          <template v-else>
            <div
              v-for="(variant, variantIndex) in rule.variants"
              :key="variant.id ?? variant.client_id ?? variantIndex"
              class="grid gap-2 rounded-md border bg-surface p-2 sm:grid-cols-[80px_1fr_72px_auto]"
            >
              <input v-model="variant.name" class="h-9" />
              <input v-model="variant.destination_url" class="h-9" placeholder="https://example.com/variant" />
              <input v-model="variant.weight" class="h-9" type="number" min="1" />
              <div class="flex items-center gap-2">
                <Switch v-model="variant.is_enabled" />
                <Button
                  type="button"
                  variant="ghost"
                  size="sm"
                  title="Remove variant"
                  @click="rule.variants.splice(variantIndex, 1)"
                >
                  <Trash2 class="h-3.5 w-3.5" />
                </Button>
              </div>
            </div>
            <Button
              type="button"
              variant="ghost"
              size="sm"
              class="justify-self-start"
              @click="rule.variants.push(newVariant(String.fromCharCode(65 + rule.variants.length)))"
            >
              <Plus class="h-3.5 w-3.5" />Variant
            </Button>
            <p v-if="props.errors?.[`routing_rules.${index}.variants`]" class="text-xs text-danger">
              {{ props.errors[`routing_rules.${index}.variants`] }}
            </p>
          </template>
        </div>

        <div class="flex items-center justify-between border-t pt-3">
          <div class="flex gap-1.5">
            <Button
              type="button"
              variant="ghost"
              size="sm"
              title="Move up"
              :disabled="index === 0"
              @click="moveRule(index, -1)"
            >
              <ChevronDown class="h-3.5 w-3.5 rotate-180" />
            </Button>
            <Button
              type="button"
              variant="ghost"
              size="sm"
              title="Move down"
              :disabled="index === rules.length - 1"
              @click="moveRule(index, 1)"
            >
              <ChevronDown class="h-3.5 w-3.5" />
            </Button>
            <Button type="button" variant="ghost" size="sm" title="Duplicate" @click="duplicateRule(index)">
              <Copy class="h-3.5 w-3.5" />
            </Button>
            <Button type="button" variant="danger" size="sm" title="Delete" @click="removeRule(index)">
              <Trash2 class="h-3.5 w-3.5" />
            </Button>
          </div>
          <Button type="button" variant="secondary" size="sm" @click="openIndex = null">Done</Button>
        </div>
      </div>
    </article>
  </div>
</template>
