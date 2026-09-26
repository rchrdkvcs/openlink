<script setup lang="ts">
import {
  ArrowDown,
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
import {
  DropdownMenuRoot,
  DropdownMenuTrigger,
  DropdownMenuPortal,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuSeparator,
} from 'radix-vue';
import { ref, useId } from 'vue';

import Button from '@/Components/ui/Button.vue';
import Field from '@/Components/ui/Field.vue';
import Select from '@/Components/ui/Select.vue';
import Switch from '@/Components/ui/Switch.vue';

import type { RoutingCondition, RoutingOption, RoutingRuleDraft, RoutingSchema, RoutingVariantDraft } from './types';

const rules = defineModel<RoutingRuleDraft[]>({ required: true });

const props = defineProps<{
  errors?: Record<string, string | undefined>;
  schema: RoutingSchema;
  defaultDestination?: string;
}>();

const timezone = Intl.DateTimeFormat().resolvedOptions().timeZone || 'UTC';
const editorId = useId();
const openIndex = ref<number | null>(rules.value.length ? 0 : null);

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
  const value = Array.isArray(condition.value) ? condition.value.join(', ') : condition.value || '';
  return valueOptions(condition)?.find((option) => option.value === value)?.label ?? value;
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
      .map((variant) => `${variantShare(rule, variant)}% ${variant.name}`)
      .join(', ');
    return `${conditionSummary} → ${variants || 'No active variants'}`;
  }

  return `${conditionSummary} → ${rule.destination_url || 'Choose a destination'}`;
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
  const total = rule.variants.reduce(
    (sum, entry) => sum + (entry.is_enabled ? Math.max(0, Number(entry.weight) || 0) : 0),
    0,
  );
  return variant.is_enabled && total ? Math.round((Math.max(0, Number(variant.weight) || 0) / total) * 100) : 0;
}
</script>

<template>
  <div class="routing-builder grid min-w-0 gap-4">
    <p
      v-if="props.errors?.routing_rules"
      role="alert"
      class="rounded-lg border border-danger/30 bg-danger/10 px-3 py-2 text-xs text-danger"
    >
      {{ props.errors.routing_rules }}
    </p>

    <!-- Empty state: presets are the first step, no wrapper card -->
    <template v-if="rules.length === 0">
      <div>
        <p class="text-[13px] font-medium text-foreground">Route visitors to different destinations</p>
        <p class="mt-1 text-xs leading-5 text-muted">
          Pick a starting point. You can tailor every condition afterwards.
        </p>
      </div>
      <div class="grid gap-2 min-[420px]:grid-cols-2">
        <button
          v-for="preset in schema.presets"
          :key="preset.kind"
          type="button"
          class="flex items-start gap-3 rounded-lg border p-3 text-start outline-none transition-colors hover:border-accent/40 hover:bg-accent/5 focus-visible:ring-2 focus-visible:ring-accent/40"
          @click="addRule(preset.kind)"
        >
          <component :is="presetIcons[preset.kind] ?? Plus" class="mt-0.5 h-4 w-4 shrink-0 text-accent" />
          <span
            ><span class="block text-[13px] font-medium">{{ preset.label }}</span
            ><span class="mt-0.5 block text-xs leading-5 text-muted">{{ preset.description }}</span></span
          >
        </button>
      </div>
    </template>

    <template v-else>
      <div class="flex items-center justify-between gap-3">
        <p class="text-xs text-muted">Checked top to bottom. The first enabled match wins.</p>
        <DropdownMenuRoot>
          <DropdownMenuTrigger as-child
            ><Button type="button" variant="secondary" size="sm" class="shrink-0"
              ><Plus class="h-3.5 w-3.5" />Add rule<ChevronDown class="h-3 w-3 text-faint" /></Button
          ></DropdownMenuTrigger>
          <DropdownMenuPortal
            ><DropdownMenuContent align="end" :side-offset="6" class="routing-menu w-72" @escape-key-down.stop>
              <DropdownMenuItem
                v-for="preset in schema.presets"
                :key="preset.kind"
                class="routing-menu-item items-start"
                @select="addRule(preset.kind)"
                ><component :is="presetIcons[preset.kind] ?? Plus" class="mt-0.5 h-4 w-4 shrink-0 text-accent" /><span
                  ><span class="block font-medium">{{ preset.label }}</span
                  ><span class="mt-0.5 block text-xs leading-5 text-muted">{{ preset.description }}</span></span
                ></DropdownMenuItem
              >
            </DropdownMenuContent></DropdownMenuPortal
          >
        </DropdownMenuRoot>
      </div>

      <ol class="grid gap-2">
        <li
          v-for="(rule, index) in rules"
          :key="rule.id ?? rule.client_id ?? index"
          class="min-w-0 rounded-xl border bg-surface transition-colors"
          :class="[
            errorsFor(index).length
              ? 'border-danger/50'
              : openIndex === index
                ? 'border-border-strong'
                : 'border-border',
            !rule.is_enabled && openIndex !== index && 'opacity-60',
          ]"
        >
          <!-- Rule header -->
          <div class="flex items-center gap-2 py-2.5 pe-2 ps-3">
            <button
              type="button"
              class="flex min-w-0 flex-1 items-center gap-3 rounded-md py-0.5 text-start outline-none focus-visible:ring-2 focus-visible:ring-accent/40"
              :aria-expanded="openIndex === index"
              :aria-controls="`${editorId}-rule-${index}`"
              @click="toggle(index)"
            >
              <ChevronRight
                class="h-4 w-4 shrink-0 text-faint transition-transform duration-150"
                :class="openIndex === index && 'rotate-90'"
              />
              <span class="min-w-0 flex-1"
                ><span class="block truncate text-[13px] font-medium"
                  ><span class="me-1.5 font-mono text-xs font-normal text-faint">{{ index + 1 }}.</span
                  >{{ rule.name || 'Untitled routing rule' }}</span
                ><span v-if="openIndex !== index" class="mt-0.5 block truncate text-xs text-muted">{{
                  rule.is_enabled ? ruleSummary(rule) : 'Disabled · skipped when routing visitors'
                }}</span></span
              >
            </button>
            <Switch v-model="rule.is_enabled" :aria-label="`Enable ${rule.name || 'routing rule'}`" />
            <DropdownMenuRoot>
              <DropdownMenuTrigger as-child
                ><Button
                  type="button"
                  variant="ghost"
                  size="sm"
                  class="w-8 shrink-0 px-0"
                  :aria-label="`Actions for ${rule.name || 'routing rule'}`"
                  ><Ellipsis class="h-4 w-4" /></Button
              ></DropdownMenuTrigger>
              <DropdownMenuPortal
                ><DropdownMenuContent align="end" :side-offset="5" class="routing-menu" @escape-key-down.stop>
                  <DropdownMenuItem class="routing-menu-item" :disabled="index === 0" @select="moveRule(index, -1)"
                    ><ArrowDown class="h-3.5 w-3.5 rotate-180" />Move up</DropdownMenuItem
                  >
                  <DropdownMenuItem
                    class="routing-menu-item"
                    :disabled="index === rules.length - 1"
                    @select="moveRule(index, 1)"
                    ><ArrowDown class="h-3.5 w-3.5" />Move down</DropdownMenuItem
                  >
                  <DropdownMenuItem class="routing-menu-item" @select="duplicateRule(index)"
                    ><Copy class="h-3.5 w-3.5" />Duplicate</DropdownMenuItem
                  >
                  <DropdownMenuSeparator class="my-1 h-px bg-border" />
                  <DropdownMenuItem class="routing-menu-item text-danger" @select="removeRule(index)"
                    ><Trash2 class="h-3.5 w-3.5" />Delete routing rule</DropdownMenuItem
                  >
                </DropdownMenuContent></DropdownMenuPortal
              >
            </DropdownMenuRoot>
          </div>

          <!-- Rule body: one flat surface, sentence-like rows sharing a single content edge -->
          <div v-if="openIndex === index" :id="`${editorId}-rule-${index}`" class="grid gap-5 border-t p-4">
            <div class="grid gap-3 min-[480px]:grid-cols-[minmax(0,1fr)_160px]">
              <Field label="Rule name"
                ><input v-model="rule.name" class="h-9" placeholder="e.g. French visitors"
              /></Field>
              <div class="grid gap-1.5">
                <label :for="`${editorId}-type-${index}`" class="text-[13px] font-medium">Action</label
                ><Select
                  :id="`${editorId}-type-${index}`"
                  :model-value="rule.type"
                  :options="[
                    { value: 'conditional', label: 'Redirect' },
                    { value: 'split_test', label: 'Split test' },
                  ]"
                  @update:model-value="
                    rule.type = $event as RoutingRuleDraft['type'];
                    onRuleTypeChange(rule);
                  "
                />
              </div>
            </div>

            <div class="sentence">
              <!-- When -->
              <template v-if="rule.conditions.length">
                <template v-for="(condition, conditionIndex) in rule.conditions" :key="conditionIndex">
                  <span class="sentence-word">{{
                    conditionIndex === 0 ? 'If' : rule.match_mode === 'all' ? 'and' : 'or'
                  }}</span>
                  <div class="condition-row" :class="condition.operator === 'between' && 'condition-row--range'">
                    <Select
                      class="[grid-area:type]"
                      :model-value="condition.type"
                      :options="schema.conditionTypes"
                      :aria-label="`Condition ${conditionIndex + 1} field`"
                      @update:model-value="
                        condition.type = $event;
                        onConditionTypeChange(condition);
                      "
                    />
                    <Select
                      class="[grid-area:op]"
                      :model-value="condition.operator"
                      :options="operatorsFor(condition)"
                      :aria-label="`Condition ${conditionIndex + 1} operator`"
                      @update:model-value="onOperatorChange(condition, $event)"
                    />
                    <template v-if="hasValueInput(condition)">
                      <div
                        v-if="condition.operator === 'between'"
                        class="grid min-w-0 grid-cols-2 gap-2 [grid-area:value]"
                      >
                        <input
                          v-model="rangeValue(condition).from"
                          class="h-9 min-w-0"
                          :type="condition.type === 'date_time' ? 'datetime-local' : 'time'"
                          :aria-label="`Condition ${conditionIndex + 1} start`"
                        />
                        <input
                          v-model="rangeValue(condition).to"
                          class="h-9 min-w-0"
                          :type="condition.type === 'date_time' ? 'datetime-local' : 'time'"
                          :aria-label="`Condition ${conditionIndex + 1} end`"
                        />
                      </div>
                      <Select
                        v-else-if="valueOptions(condition)"
                        class="[grid-area:value]"
                        :model-value="scalarValue(condition)"
                        :options="valueOptions(condition)!"
                        :aria-label="`Condition ${conditionIndex + 1} value`"
                        @update:model-value="condition.value = $event"
                      />
                      <input
                        v-else
                        :value="scalarValue(condition)"
                        class="h-9 min-w-0 [grid-area:value]"
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
                        @input="condition.value = ($event.target as HTMLInputElement).value"
                      />
                    </template>
                    <span v-else class="self-center px-1 text-xs text-faint [grid-area:value]">No value needed</span>
                    <Button
                      type="button"
                      variant="ghost"
                      size="sm"
                      class="h-9 w-8 px-0 text-faint [grid-area:remove] hover:text-danger"
                      :aria-label="`Remove condition ${conditionIndex + 1}`"
                      @click="rule.conditions.splice(conditionIndex, 1)"
                      ><X class="h-3.5 w-3.5"
                    /></Button>
                    <label
                      v-if="['date_time', 'day_of_week', 'time_of_day'].includes(condition.type)"
                      class="condition-extra flex min-w-0 items-center gap-2 text-xs text-muted"
                      ><CalendarClock class="h-3.5 w-3.5 shrink-0 text-faint" />Timezone<input
                        v-model="condition.timezone"
                        class="h-8 min-w-0 flex-1 text-xs"
                        placeholder="Europe/Paris"
                    /></label>
                  </div>
                </template>
              </template>
              <template v-else>
                <span class="sentence-word">If</span>
                <p class="flex min-h-9 items-center text-xs text-muted">
                  Any visitor. Add a condition to narrow the audience.
                </p>
              </template>

              <span />
              <div class="flex flex-wrap items-center gap-x-4 gap-y-2">
                <Button
                  type="button"
                  variant="ghost"
                  size="sm"
                  class="-ms-2"
                  @click="rule.conditions.push(newCondition())"
                  ><Plus class="h-3.5 w-3.5" />Add condition</Button
                >
                <label v-if="rule.conditions.length > 1" class="flex items-center gap-2 text-xs text-muted"
                  >Match<Select
                    v-model="rule.match_mode"
                    :options="[
                      { value: 'all', label: 'all conditions' },
                      { value: 'any', label: 'any condition' },
                    ]"
                    aria-label="Condition matching mode"
                    class="h-8 w-auto min-w-36"
                /></label>
              </div>

              <!-- Then -->
              <template v-if="rule.type === 'conditional'">
                <label :for="`${editorId}-destination-${index}`" class="sentence-word mt-3">Then</label>
                <input
                  :id="`${editorId}-destination-${index}`"
                  v-model="rule.destination_url"
                  type="url"
                  class="mt-3 h-9"
                  placeholder="Redirect to https://example.com/landing"
                />
              </template>
              <template v-else>
                <span class="sentence-word mt-3">Then</span>
                <div class="mt-3 grid min-w-0 gap-2">
                  <p class="flex min-h-9 items-center gap-2 text-xs text-muted">
                    <Shuffle class="h-3.5 w-3.5 text-faint" />Split matched traffic by weight
                  </p>
                  <div class="variant-row text-[11px] font-medium text-faint" aria-hidden="true">
                    <span class="[grid-area:name]">Variant</span>
                    <span class="variant-url-head [grid-area:url]">Destination</span>
                    <span class="[grid-area:weight]">Weight</span>
                    <span class="text-end [grid-area:share]">Share</span>
                  </div>
                  <div
                    v-for="(variant, variantIndex) in rule.variants"
                    :key="variant.id ?? variant.client_id ?? variantIndex"
                    class="variant-row"
                    :class="!variant.is_enabled && 'opacity-60'"
                  >
                    <input
                      v-model="variant.name"
                      class="h-9 min-w-0 [grid-area:name]"
                      :aria-label="`Variant ${variantIndex + 1} name`"
                    />
                    <input
                      v-model="variant.destination_url"
                      type="url"
                      class="h-9 min-w-0 [grid-area:url]"
                      placeholder="https://example.com/variant"
                      :aria-label="`Variant ${variant.name} destination URL`"
                    />
                    <input
                      v-model="variant.weight"
                      class="h-9 min-w-0 [grid-area:weight]"
                      type="number"
                      min="1"
                      step="1"
                      :aria-label="`Variant ${variant.name} weight`"
                    />
                    <span class="self-center text-end font-mono text-xs tabular-nums text-muted [grid-area:share]"
                      >{{ variantShare(rule, variant) }}%</span
                    >
                    <Switch
                      v-model="variant.is_enabled"
                      class="self-center [grid-area:toggle]"
                      :aria-label="`Enable variant ${variant.name}`"
                    />
                    <Button
                      type="button"
                      variant="ghost"
                      size="sm"
                      class="h-9 w-8 px-0 text-faint [grid-area:remove] hover:text-danger"
                      :aria-label="`Remove variant ${variant.name}`"
                      @click="rule.variants.splice(variantIndex, 1)"
                      ><X class="h-3.5 w-3.5"
                    /></Button>
                  </div>
                  <Button
                    type="button"
                    variant="ghost"
                    size="sm"
                    class="-ms-2 justify-self-start"
                    @click="rule.variants.push(newVariant(String.fromCharCode(65 + rule.variants.length)))"
                    ><Plus class="h-3.5 w-3.5" />Add variant</Button
                  >
                </div>
              </template>
            </div>

            <div v-if="errorsFor(index).length" role="alert" class="grid gap-1 rounded-lg bg-danger/10 px-3 py-2">
              <p v-for="[key, error] in errorsFor(index)" :key="key" class="text-xs text-danger">{{ error }}</p>
            </div>
            <p class="flex items-center gap-2 text-xs text-faint">
              <ArrowDown class="h-3.5 w-3.5 shrink-0" />Otherwise, continue to
              {{ index < rules.length - 1 ? 'the next rule.' : 'the default destination.' }}
            </p>
          </div>
          <p v-else-if="errorsFor(index).length" role="alert" class="border-t px-4 py-2 text-xs text-danger">
            This routing rule needs attention. Expand it to review the errors.
          </p>
        </li>
      </ol>
    </template>

    <!-- Default destination closes the flow, at the same level as the rules -->
    <div class="flex min-w-0 items-center gap-3 rounded-xl border border-dashed px-3 py-2.5">
      <CornerDownRight class="h-4 w-4 shrink-0 text-faint" />
      <span class="min-w-0 flex-1"
        ><span class="block text-[13px] font-medium">{{ rules.length ? 'Otherwise' : 'Everyone' }}</span
        ><span class="block truncate text-xs" :class="defaultDestination ? 'text-muted' : 'text-faint'">{{
          defaultDestination || 'Add a destination URL in the Link tab.'
        }}</span></span
      >
      <span class="shrink-0 text-[11px] text-faint">Default</span>
    </div>
  </div>
</template>

<style scoped>
.routing-builder {
  container-type: inline-size;
}
/* Leading word column (If / and / Then) + content column, shared by every row of a rule. */
.sentence {
  display: grid;
  grid-template-columns: 2.25rem minmax(0, 1fr);
  column-gap: 0.75rem;
  row-gap: 0.75rem;
  align-items: start;
}
.sentence-word {
  display: flex;
  min-height: 2.25rem;
  align-items: center;
  font-size: 0.75rem;
  font-weight: 500;
  color: hsl(var(--muted));
}
.condition-row {
  display: grid;
  grid-template-columns: minmax(0, 1fr) minmax(0, 1fr) 2rem;
  grid-template-areas:
    'type op remove'
    'value value .';
  gap: 0.5rem;
  min-width: 0;
}
/* Optional rows (time range, timezone) only take space when present. */
.condition-extra {
  grid-column: 1 / -2;
}
.variant-row {
  display: grid;
  grid-template-columns: minmax(0, 1fr) 4.5rem 2.75rem 2.25rem 2rem;
  grid-template-areas:
    'name weight share toggle remove'
    'url url url url .';
  column-gap: 0.5rem;
  row-gap: 0.375rem;
  min-width: 0;
}
.variant-url-head {
  display: none;
}
@container (min-width: 520px) {
  .sentence {
    row-gap: 0.5rem;
  }
  .condition-row {
    grid-template-columns: minmax(0, 1fr) minmax(0, 1fr) minmax(0, 1.25fr) 2rem;
    grid-template-areas: 'type op value remove';
  }
  /* Two time pickers don't fit the value column: give the range its own full row. */
  .condition-row--range {
    grid-template-areas:
      'type op . remove'
      'value value value .';
  }
  .variant-row {
    grid-template-columns: 5.5rem minmax(0, 1fr) 4.5rem 2.75rem 2.25rem 2rem;
    grid-template-areas: 'name url weight share toggle remove';
  }
  .variant-url-head {
    display: block;
  }
}
:global(.routing-menu) {
  @apply z-[70] min-w-44 max-w-[calc(100vw-2rem)] rounded-lg border border-border-strong bg-overlay p-1 shadow-drawer;
}
:global(.routing-menu-item) {
  @apply flex cursor-default select-none items-center gap-2 rounded-md px-2.5 py-2 text-[13px] outline-none;
}
:global(.routing-menu-item[data-highlighted]) {
  @apply bg-elevated;
}
:global(.routing-menu-item[data-disabled]) {
  @apply pointer-events-none opacity-40;
}
</style>
