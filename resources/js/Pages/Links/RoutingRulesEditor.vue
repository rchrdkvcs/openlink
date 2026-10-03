<script setup lang="ts">
import {
  ArrowDown,
  ArrowUp,
  CalendarClock,
  ChevronDown,
  ChevronRight,
  Copy,
  CornerDownRight,
  Ellipsis,
  Globe2,
  Megaphone,
  MonitorSmartphone,
  Plus,
  Shuffle,
  Trash2,
  X,
} from '@lucide/vue';
import { ref, useId } from 'vue';

import Button from '@/Components/ui/Button.vue';
import Input from '@/Components/ui/Input.vue';
import Menu from '@/Components/ui/Menu.vue';
import MenuItem from '@/Components/ui/MenuItem.vue';
import MenuSeparator from '@/Components/ui/MenuSeparator.vue';
import SegmentedControl from '@/Components/ui/SegmentedControl.vue';
import Select from '@/Components/ui/Select.vue';
import Switch from '@/Components/ui/Switch.vue';

import { conditionLabel, conditionValueOptions, routingRuleSummary, variantShare as shareOf } from './routing';
import type { RoutingCondition, RoutingOption, RoutingRuleDraft, RoutingSchema, RoutingVariantDraft } from './types';

const rules = defineModel<RoutingRuleDraft[]>({ required: true });

const props = defineProps<{
  errors?: Record<string, string | undefined>;
  schema: RoutingSchema;
  defaultDestination?: string;
}>();

const timezone = Intl.DateTimeFormat().resolvedOptions().timeZone || 'UTC';
const editorId = useId();
const firstInvalidIndex = rules.value.findIndex((_, index) => errorsFor(index).length > 0);
const openIndex = ref<number | null>(firstInvalidIndex >= 0 ? firstInvalidIndex : rules.value.length ? 0 : null);

const presetIcons: Record<string, unknown> = {
  country: Globe2,
  device: MonitorSmartphone,
  campaign: Megaphone,
  time: CalendarClock,
  split: Shuffle,
  custom: Plus,
};

const ruleTypeOptions: { value: RoutingRuleDraft['type']; label: string }[] = [
  { value: 'conditional', label: 'Conditional' },
  { value: 'split_test', label: 'Split test' },
];

const matchModeOptions: { value: RoutingRuleDraft['match_mode']; label: string }[] = [
  { value: 'all', label: 'All' },
  { value: 'any', label: 'Any' },
];

const shareTones = ['bg-accent', 'bg-accent/60', 'bg-accent/35', 'bg-accent/20'];

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
    name:
      type === 'split_test'
        ? 'Split test'
        : conditionType === 'custom'
          ? 'Custom routing'
          : `${labelFor(conditionType)} routing`,
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
  const nextIndex = rules.value.length;
  rules.value = [...rules.value, ruleFromKind(kind)];
  openIndex.value = nextIndex;
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
  const remaining = rules.value.filter((_, current) => current !== index);
  rules.value = remaining;
  openIndex.value = remaining.length ? Math.min(index, remaining.length - 1) : null;
}

function moveRule(index: number, direction: -1 | 1) {
  const next = index + direction;
  if (next < 0 || next >= rules.value.length) return;
  const clone = [...rules.value];
  [clone[index], clone[next]] = [clone[next], clone[index]];
  rules.value = clone;
  openIndex.value = next;
}

function setRuleType(rule: RoutingRuleDraft, type: RoutingRuleDraft['type']) {
  rule.type = type;
  onRuleTypeChange(rule);
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
  return conditionLabel(props.schema, type);
}

function ruleSummary(rule: RoutingRuleDraft) {
  return routingRuleSummary(props.schema, rule);
}

function ruleName(rule: RoutingRuleDraft) {
  return rule.name || 'Untitled routing rule';
}

function conditionsLead(rule: RoutingRuleDraft) {
  if (rule.conditions.length === 0) return 'every visitor';
  if (rule.conditions.length === 1) return 'the visitor matches';
  return rule.match_mode === 'all' ? 'the visitor matches all of these' : 'the visitor matches any of these';
}

function hasValueInput(condition: RoutingCondition) {
  return condition.operator !== 'is_empty' && condition.operator !== 'is_not_empty';
}

function valueOptions(condition: RoutingCondition): RoutingOption[] | null {
  return conditionValueOptions(props.schema, condition);
}

function rangeValue(condition: RoutingCondition): { from?: string; to?: string } {
  if (typeof condition.value !== 'object' || condition.value === null || Array.isArray(condition.value)) {
    condition.value = { from: '', to: '' };
  }

  return condition.value;
}

function onOperatorChange(condition: RoutingCondition, operator: string) {
  condition.operator = operator;
  if (operator === 'between') {
    rangeValue(condition);
  } else if (typeof condition.value === 'object' && condition.value && !Array.isArray(condition.value)) {
    condition.value = condition.value.from ?? '';
  }
}

function scalarValue(condition: RoutingCondition) {
  return Array.isArray(condition.value)
    ? condition.value.join(', ')
    : typeof condition.value === 'string'
      ? condition.value
      : '';
}

function errorsFor(index: number) {
  return Object.entries(props.errors ?? {}).filter(
    ([key, value]) => key.startsWith(`routing_rules.${index}.`) && value,
  );
}

function variantShare(rule: RoutingRuleDraft, variant: RoutingVariantDraft) {
  return shareOf(rule, variant);
}

function activeVariants(rule: RoutingRuleDraft) {
  return rule.variants.filter((variant) => variant.is_enabled && variantShare(rule, variant) > 0);
}

function variantTone(rule: RoutingRuleDraft, variant: RoutingVariantDraft) {
  const position = activeVariants(rule).indexOf(variant);
  return position === -1 ? 'bg-border-strong' : shareTones[position % shareTones.length];
}

function shareDescription(rule: RoutingRuleDraft) {
  const active = activeVariants(rule);
  return active.length
    ? `Traffic split: ${active.map((variant) => `${variant.name} ${variantShare(rule, variant)}%`).join(', ')}`
    : 'No active variants';
}
</script>

<template>
  <div class="@container grid min-w-0 gap-4">
    <p v-if="props.errors?.routing_rules" role="alert" class="rounded-lg bg-danger/10 px-3 py-2 text-xs text-danger">
      {{ props.errors.routing_rules }}
    </p>

    <section v-if="rules.length === 0" class="grid gap-3" :aria-labelledby="`${editorId}-presets`">
      <h3 :id="`${editorId}-presets`" class="text-[13px] font-medium text-foreground">Start from a preset</h3>
      <div class="@md:grid-cols-2 @2xl:grid-cols-3 grid gap-2">
        <button
          v-for="preset in schema.presets"
          :key="preset.kind"
          type="button"
          class="flex items-start gap-3 rounded-xl border bg-surface p-3 text-start outline-none transition-colors duration-150 hover:bg-elevated/40 focus-visible:ring-2 focus-visible:ring-accent/40"
          @click="addRule(preset.kind)"
        >
          <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg bg-elevated text-accent">
            <component :is="presetIcons[preset.kind] ?? Plus" class="h-3.5 w-3.5" />
          </span>
          <span class="min-w-0">
            <span class="block text-[13px] font-medium leading-7 text-foreground">{{ preset.label }}</span>
            <span class="block text-xs leading-[18px] text-muted">{{ preset.description }}</span>
          </span>
        </button>
      </div>
    </section>

    <template v-else>
      <ol class="grid gap-2" aria-label="Routing rules">
        <li
          v-for="(rule, index) in rules"
          :key="rule.id ?? rule.client_id ?? index"
          class="min-w-0 rounded-xl border bg-surface"
          :class="errorsFor(index).length > 0 && 'border-danger/50'"
        >
          <div class="flex min-h-12 items-center gap-2 py-2 pe-2 ps-2">
            <button
              type="button"
              class="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg text-faint transition-colors duration-150 hover:bg-elevated hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent/40"
              :aria-expanded="openIndex === index"
              :aria-controls="`${editorId}-rule-${index}`"
              :aria-label="`${openIndex === index ? 'Collapse' : 'Expand'} ${ruleName(rule)}`"
              @click="toggle(index)"
            >
              <ChevronRight
                class="ease-emphasized-out h-3.5 w-3.5 transition-transform duration-200"
                :class="openIndex === index && 'rotate-90'"
              />
            </button>
            <span
              class="flex h-5 min-w-5 shrink-0 items-center justify-center rounded-md bg-elevated px-1 font-mono text-[11px] tabular-nums text-muted"
              :title="`Priority ${index + 1}`"
              >{{ index + 1 }}</span
            >
            <div class="min-w-0 flex-1 ps-1">
              <input
                v-if="openIndex === index"
                v-model="rule.name"
                type="text"
                aria-label="Rule name"
                placeholder="Untitled routing rule"
                class="-ms-1.5 h-7 w-full min-w-0 rounded-md border border-transparent bg-transparent px-1.5 text-[13px] font-medium text-foreground outline-none transition-[background-color,border-color] duration-150 placeholder:text-faint hover:bg-elevated/60 focus-visible:border-accent/60 focus-visible:bg-elevated"
              />
              <button
                v-else
                type="button"
                class="block w-full min-w-0 rounded-md text-start outline-none focus-visible:ring-2 focus-visible:ring-accent/40"
                :class="!rule.is_enabled && 'opacity-60'"
                :aria-expanded="false"
                :aria-controls="`${editorId}-rule-${index}`"
                @click="toggle(index)"
              >
                <span class="block truncate text-[13px] font-medium text-foreground">{{ ruleName(rule) }}</span>
                <span class="block truncate text-xs text-muted">{{
                  rule.is_enabled ? ruleSummary(rule) : 'Disabled · skipped when routing visitors'
                }}</span>
              </button>
            </div>
            <Switch v-model="rule.is_enabled" :aria-label="`Enable ${ruleName(rule)}`" />
            <Menu align="end" width="w-44">
              <template #trigger>
                <Button
                  type="button"
                  variant="ghost"
                  size="sm"
                  class="w-7 shrink-0 px-0"
                  :aria-label="`Actions for ${ruleName(rule)}`"
                  ><Ellipsis
                /></Button>
              </template>
              <MenuItem :icon="ArrowUp" :disabled="index === 0" @select="moveRule(index, -1)">Move up</MenuItem>
              <MenuItem :icon="ArrowDown" :disabled="index === rules.length - 1" @select="moveRule(index, 1)"
                >Move down</MenuItem
              >
              <MenuItem :icon="Copy" @select="duplicateRule(index)">Duplicate</MenuItem>
              <MenuSeparator />
              <MenuItem :icon="Trash2" destructive @select="removeRule(index)">Delete</MenuItem>
            </Menu>
          </div>

          <div v-if="openIndex === index" :id="`${editorId}-rule-${index}`" class="grid gap-5 border-t p-4">
            <div class="flex flex-wrap items-center justify-between gap-x-4 gap-y-2">
              <span class="text-[13px] font-medium text-foreground">Rule type</span>
              <SegmentedControl
                size="sm"
                label="Rule type"
                :options="ruleTypeOptions"
                :model-value="rule.type"
                @update:model-value="setRuleType(rule, $event)"
              />
            </div>

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

              <div
                v-for="(condition, conditionIndex) in rule.conditions"
                :key="conditionIndex"
                class="@xl:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_minmax(0,1.4fr)_1.75rem] grid grid-cols-[minmax(0,1fr)_minmax(0,1fr)_1.75rem] items-center gap-2"
              >
                <Select
                  :model-value="condition.type"
                  :options="schema.conditionTypes"
                  :aria-label="`Condition ${conditionIndex + 1} field`"
                  @update:model-value="
                    condition.type = $event;
                    onConditionTypeChange(condition);
                  "
                />
                <Select
                  :model-value="condition.operator"
                  :options="operatorsFor(condition)"
                  :aria-label="`Condition ${conditionIndex + 1} operator`"
                  @update:model-value="onOperatorChange(condition, $event)"
                />
                <template v-if="hasValueInput(condition)">
                  <div
                    v-if="condition.operator === 'between'"
                    class="@xl:col-span-3 col-span-2 grid min-w-0 grid-cols-2 gap-2"
                  >
                    <Input
                      v-model="rangeValue(condition).from"
                      :type="condition.type === 'date_time' ? 'datetime-local' : 'time'"
                      :aria-label="`Condition ${conditionIndex + 1} start`"
                    />
                    <Input
                      v-model="rangeValue(condition).to"
                      :type="condition.type === 'date_time' ? 'datetime-local' : 'time'"
                      :aria-label="`Condition ${conditionIndex + 1} end`"
                    />
                  </div>
                  <Select
                    v-else-if="valueOptions(condition)"
                    class="@xl:col-span-1 col-span-2"
                    :model-value="scalarValue(condition)"
                    :options="valueOptions(condition)!"
                    :aria-label="`Condition ${conditionIndex + 1} value`"
                    @update:model-value="condition.value = $event"
                  />
                  <Input
                    v-else
                    :model-value="scalarValue(condition)"
                    class="@xl:col-span-1 col-span-2"
                    :type="
                      condition.type === 'date_time'
                        ? 'datetime-local'
                        : condition.type === 'time_of_day'
                          ? 'time'
                          : 'text'
                    "
                    :placeholder="
                      condition.type === 'country'
                        ? 'e.g. FR'
                        : condition.type === 'language'
                          ? 'e.g. fr'
                          : 'Enter a value'
                    "
                    :aria-label="`Condition ${conditionIndex + 1} value`"
                    @update:model-value="condition.value = String($event ?? '')"
                  />
                </template>
                <span v-else class="@xl:col-span-1 col-span-2 px-2.5 text-xs text-faint">No value needed</span>
                <label
                  v-if="['date_time', 'day_of_week', 'time_of_day'].includes(condition.type)"
                  class="@xl:col-span-3 col-span-2 flex min-w-0 items-center gap-2 text-xs text-muted"
                >
                  <CalendarClock class="h-3.5 w-3.5 shrink-0 text-faint" />
                  Timezone
                  <Input v-model="condition.timezone" size="sm" class="max-w-56 flex-1" placeholder="Europe/Paris" />
                </label>
                <Button
                  type="button"
                  variant="ghost"
                  size="sm"
                  class="@xl:col-start-4 col-start-3 row-start-1 w-7 px-0 text-faint hover:text-danger"
                  :aria-label="`Remove condition ${conditionIndex + 1}`"
                  @click="rule.conditions.splice(conditionIndex, 1)"
                  ><X
                /></Button>
              </div>

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
                @click="rule.conditions.push(newCondition())"
                ><Plus />Add condition</Button
              >
            </section>

            <section v-if="rule.type === 'conditional'" class="grid gap-2">
              <label
                :for="`${editorId}-destination-${index}`"
                class="flex min-h-7 items-center text-[13px] font-medium"
              >
                Then <span class="ms-1 font-normal text-muted">redirect to</span>
              </label>
              <Input
                :id="`${editorId}-destination-${index}`"
                v-model="rule.destination_url"
                type="url"
                spellcheck="false"
                placeholder="https://example.com/landing"
              />
            </section>

            <section v-else class="grid gap-2" :aria-label="`Variants for ${ruleName(rule)}`">
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
                  :class="variantTone(rule, variant)"
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

              <div
                v-for="(variant, variantIndex) in rule.variants"
                :key="variant.id ?? variant.client_id ?? variantIndex"
                class="@xl:grid-cols-[7rem_minmax(0,1fr)_4.5rem_4rem_2.25rem_1.75rem] grid grid-cols-[minmax(0,1fr)_4.5rem_4rem_2.25rem_1.75rem] items-center gap-x-2 gap-y-1.5"
              >
                <Input
                  v-model="variant.name"
                  :class="!variant.is_enabled && 'opacity-50'"
                  :aria-label="`Variant ${variantIndex + 1} name`"
                />
                <Input
                  v-model="variant.destination_url"
                  type="url"
                  spellcheck="false"
                  class="@xl:col-span-1 @xl:col-start-2 @xl:row-start-1 col-span-4 row-start-2"
                  :class="!variant.is_enabled && 'opacity-50'"
                  placeholder="https://example.com/variant"
                  :aria-label="`Variant ${variant.name} destination URL`"
                />
                <Input
                  v-model="variant.weight"
                  type="number"
                  min="1"
                  step="1"
                  class="tabular-nums"
                  :class="!variant.is_enabled && 'opacity-50'"
                  :aria-label="`Variant ${variant.name} weight`"
                />
                <span
                  class="inline-flex h-6 items-center gap-1.5 justify-self-end rounded-full bg-elevated px-2 font-mono text-[11px] tabular-nums text-muted"
                  :class="!variant.is_enabled && 'opacity-50'"
                  :aria-label="`${variantShare(rule, variant)}% of matched traffic`"
                >
                  <span class="h-1.5 w-1.5 rounded-full" :class="variantTone(rule, variant)" aria-hidden="true" />
                  {{ variantShare(rule, variant) }}%
                </span>
                <Switch
                  v-model="variant.is_enabled"
                  class="justify-self-center"
                  :aria-label="`Enable variant ${variant.name}`"
                />
                <Button
                  type="button"
                  variant="ghost"
                  size="sm"
                  class="w-7 px-0 text-faint hover:text-danger"
                  :aria-label="`Remove variant ${variant.name}`"
                  @click="rule.variants.splice(variantIndex, 1)"
                  ><X
                /></Button>
              </div>

              <Button
                type="button"
                variant="ghost"
                size="sm"
                class="-ms-2.5 justify-self-start"
                @click="rule.variants.push(newVariant(String.fromCharCode(65 + rule.variants.length)))"
                ><Plus />Add variant</Button
              >
            </section>

            <div v-if="errorsFor(index).length" role="alert" class="grid gap-1 rounded-lg bg-danger/10 px-3 py-2">
              <p v-for="[key, error] in errorsFor(index)" :key="key" class="text-xs text-danger">{{ error }}</p>
            </div>

            <p class="flex items-center gap-2 text-xs text-faint">
              <ArrowDown class="h-3.5 w-3.5 shrink-0" />
              Otherwise, continue to {{ index < rules.length - 1 ? 'the next rule.' : 'the default destination.' }}
            </p>
          </div>
          <p v-else-if="errorsFor(index).length" role="alert" class="border-t px-4 py-2 text-xs text-danger">
            This routing rule needs attention. Expand it to review the errors.
          </p>
        </li>
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
          :icon="presetIcons[preset.kind] ?? Plus"
          @select="addRule(preset.kind)"
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
