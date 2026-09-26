<script setup lang="ts">
import {
  ArrowDown,
  ArrowDownRight,
  ArrowRight,
  Check,
  Ellipsis,
  GitBranch,
  Link2,
  CalendarClock,
  ChevronDown,
  ChevronRight,
  Copy,
  Globe2,
  Megaphone,
  MonitorSmartphone,
  Plus,
  Shuffle,
  Trash2,
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

import Badge from '@/Components/ui/Badge.vue';
import Button from '@/Components/ui/Button.vue';
import Field from '@/Components/ui/Field.vue';
import Input from '@/Components/ui/Input.vue';
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
  <div class="routing-builder grid min-w-0 gap-5">
    <div class="flex items-start gap-3">
      <span
        class="grid h-10 w-10 shrink-0 place-items-center rounded-xl border border-accent/20 bg-accent/10 text-accent"
        ><GitBranch class="h-5 w-5"
      /></span>
      <div>
        <h3 class="text-sm font-semibold">Smart Routing</h3>
        <p class="mt-1 text-xs leading-5 text-muted">
          Send each visitor to the right destination. The first matching enabled rule wins.
        </p>
      </div>
    </div>

    <p
      v-if="props.errors?.routing_rules"
      role="alert"
      class="rounded-lg border border-danger/30 bg-danger/10 px-3 py-2 text-xs text-danger"
    >
      {{ props.errors.routing_rules }}
    </p>

    <div v-if="rules.length === 0" class="rounded-xl border bg-surface p-4 sm:p-5">
      <p class="text-sm font-medium">Start with a routing rule</p>
      <p class="mt-1 text-xs leading-5 text-muted">
        Choose a starting point, then tailor the conditions to your visitors.
      </p>
      <div class="mt-4 grid gap-2 min-[420px]:grid-cols-2">
        <button
          v-for="preset in schema.presets"
          :key="preset.kind"
          type="button"
          class="group flex items-start gap-3 rounded-lg border bg-elevated/30 p-3 text-start outline-none transition-colors hover:border-accent/40 hover:bg-accent/5 focus-visible:ring-2 focus-visible:ring-accent/40"
          @click="addRule(preset.kind)"
        >
          <component :is="presetIcons[preset.kind] ?? Plus" class="mt-0.5 h-4 w-4 shrink-0 text-accent" />
          <span
            ><span class="block text-[13px] font-medium">{{ preset.label }}</span
            ><span class="mt-1 block text-xs leading-5 text-muted">{{ preset.description }}</span></span
          >
        </button>
      </div>
    </div>

    <div v-if="rules.length" class="grid gap-3">
      <p class="text-xs text-muted">
        {{ rules.length }} routing rule{{ rules.length > 1 ? 's' : '' }}
        <span class="mx-1 text-faint">·</span> Evaluated from top to bottom
      </p>
      <article
        v-for="(rule, index) in rules"
        :key="rule.id ?? rule.client_id ?? index"
        class="min-w-0 rounded-xl border bg-surface"
        :class="
          errorsFor(index).length ? 'border-danger/50' : openIndex === index ? 'border-border-strong' : 'border-border'
        "
      >
        <div class="flex items-center gap-2 p-3 sm:gap-3 sm:p-4">
          <button
            type="button"
            class="flex min-w-0 flex-1 items-center gap-3 rounded-md text-start outline-none focus-visible:ring-2 focus-visible:ring-accent/40"
            :aria-expanded="openIndex === index"
            :aria-controls="`${editorId}-rule-${index}`"
            @click="toggle(index)"
          >
            <span
              class="grid h-7 w-7 shrink-0 place-items-center rounded-md border bg-elevated/50 font-mono text-xs text-muted"
              >{{ String(index + 1).padStart(2, '0') }}</span
            >
            <span class="min-w-0 flex-1"
              ><span class="block truncate text-[13px] font-medium">{{ rule.name || 'Untitled routing rule' }}</span
              ><span class="mt-0.5 block truncate text-xs text-muted">{{
                rule.is_enabled ? ruleSummary(rule) : 'Disabled · skipped when routing visitors'
              }}</span></span
            >
            <ChevronRight
              class="h-4 w-4 shrink-0 text-faint transition-transform duration-150"
              :class="openIndex === index && 'rotate-90'"
            />
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

        <div v-if="openIndex === index" :id="`${editorId}-rule-${index}`" class="grid gap-6 border-t p-3 sm:p-5">
          <div class="grid gap-3 min-[480px]:grid-cols-[1fr_170px]">
            <Field label="Rule name"><Input v-model="rule.name" placeholder="e.g. French visitors" /></Field>
            <div class="grid gap-1.5">
              <label :for="`${editorId}-type-${index}`" class="text-[13px] font-medium">Routing type</label
              ><Select
                :id="`${editorId}-type-${index}`"
                :model-value="rule.type"
                :options="[
                  { value: 'conditional', label: 'Destination' },
                  { value: 'split_test', label: 'Split test' },
                ]"
                @update:model-value="
                  rule.type = $event as RoutingRuleDraft['type'];
                  onRuleTypeChange(rule);
                "
              />
            </div>
          </div>

          <section class="grid gap-3" :aria-labelledby="`${editorId}-when-${index}`">
            <div class="flex flex-wrap items-center justify-between gap-2">
              <h4
                :id="`${editorId}-when-${index}`"
                class="text-[11px] font-semibold uppercase tracking-[0.12em] text-muted"
              >
                When
              </h4>
              <span class="text-xs text-faint"
                >{{ rule.conditions.length }} condition{{ rule.conditions.length === 1 ? '' : 's' }}</span
              >
            </div>
            <div class="rounded-xl border p-3 sm:p-4">
              <div class="mb-4 flex flex-wrap items-center gap-2 text-xs text-muted">
                <GitBranch class="h-3.5 w-3.5 text-faint" />
                <span>Match</span>
                <Select
                  v-model="rule.match_mode"
                  :options="[
                    { value: 'all', label: 'All conditions' },
                    { value: 'any', label: 'Any condition' },
                  ]"
                  aria-label="Condition matching mode"
                  size="sm"
                  class="w-auto min-w-36"
                />
                <span>in this group</span>
              </div>

              <div
                v-if="!rule.conditions.length"
                class="mb-3 flex items-center gap-2 rounded-lg bg-elevated/50 px-3 py-3 text-xs text-muted"
              >
                <Globe2 class="h-4 w-4 shrink-0" />Applies to every visitor. Add a condition to narrow the audience.
              </div>
              <div class="grid" :class="rule.conditions.length > 1 ? 'condition-group' : 'gap-3'">
                <template v-for="(condition, conditionIndex) in rule.conditions" :key="conditionIndex">
                  <div v-if="conditionIndex > 0" class="condition-join" aria-hidden="true">
                    <span class="rounded-full border bg-surface px-1.5 py-0.5 text-[10px] text-muted">{{
                      rule.match_mode === 'all' ? 'And' : 'Or'
                    }}</span>
                  </div>
                  <div class="condition-row relative rounded-lg border bg-elevated/40 p-2">
                    <div class="condition-fields">
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
                        <div v-if="condition.operator === 'between'" class="condition-value grid min-w-0 gap-2">
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
                          :model-value="scalarValue(condition)"
                          :options="valueOptions(condition)!"
                          class="condition-value"
                          :aria-label="`Condition ${conditionIndex + 1} value`"
                          @update:model-value="condition.value = $event"
                        />
                        <Input
                          v-else
                          :model-value="scalarValue(condition)"
                          class="condition-value"
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
                      <span v-else class="condition-value self-center px-1 text-xs text-faint">No value needed</span>
                    </div>
                    <Button
                      type="button"
                      variant="ghost"
                      size="sm"
                      class="h-9 w-8 px-0 hover:text-danger"
                      :aria-label="`Remove condition ${conditionIndex + 1}`"
                      @click="rule.conditions.splice(conditionIndex, 1)"
                      ><Trash2 class="h-3.5 w-3.5"
                    /></Button>
                    <label
                      v-if="['date_time', 'day_of_week', 'time_of_day'].includes(condition.type)"
                      class="col-span-2 flex flex-wrap items-center gap-2 border-t pt-2 text-xs text-muted"
                      ><CalendarClock class="h-3.5 w-3.5" />Timezone<Input
                        v-model="condition.timezone"
                        size="sm"
                        class="flex-1 basis-36 text-xs"
                        placeholder="Europe/Paris"
                    /></label>
                  </div>
                </template>
              </div>
              <Button
                type="button"
                variant="secondary"
                size="sm"
                class="mt-3"
                @click="rule.conditions.push(newCondition())"
                ><Plus class="h-3.5 w-3.5" />Add condition</Button
              >
            </div>
          </section>

          <section class="grid gap-3" :aria-labelledby="`${editorId}-then-${index}`">
            <div class="flex items-center gap-2">
              <h4
                :id="`${editorId}-then-${index}`"
                class="text-[11px] font-semibold uppercase tracking-[0.12em] text-muted"
              >
                Then
              </h4>
              <Badge variant="success" class="text-[10px] normal-case"><Check class="h-3 w-3" />If matched</Badge>
            </div>
            <div v-if="rule.type === 'conditional'" class="rounded-xl border bg-elevated/40 p-3 sm:p-4">
              <label
                :for="`${editorId}-destination-${index}`"
                class="mb-2 flex items-center gap-2 text-[13px] font-medium"
                ><ArrowDownRight class="h-4 w-4 text-accent" />Redirect to</label
              >
              <Input
                :id="`${editorId}-destination-${index}`"
                v-model="rule.destination_url"
                type="url"
                size="lg"
                placeholder="https://example.com/landing"
              />
            </div>
            <div v-else class="grid gap-3 rounded-xl border p-3 sm:p-4">
              <div>
                <p class="flex items-center gap-2 text-[13px] font-medium">
                  <Shuffle class="h-4 w-4 text-accent" />Split traffic between destinations
                </p>
                <p class="mt-1 text-xs leading-5 text-muted">
                  Traffic shares are calculated from the weights of enabled variants.
                </p>
              </div>
              <div
                v-for="(variant, variantIndex) in rule.variants"
                :key="variant.id ?? variant.client_id ?? variantIndex"
                class="grid gap-3 rounded-lg border bg-elevated/40 p-3"
              >
                <div class="flex items-center gap-2">
                  <Badge variant="outline">{{ variantShare(rule, variant) }}%</Badge
                  ><span class="flex-1 text-xs text-muted">{{
                    variant.is_enabled ? 'of matched traffic' : 'Disabled'
                  }}</span
                  ><Switch v-model="variant.is_enabled" :aria-label="`Enable variant ${variant.name}`" /><Button
                    type="button"
                    variant="ghost"
                    size="sm"
                    class="w-8 px-0 hover:text-danger"
                    :aria-label="`Remove variant ${variant.name}`"
                    @click="rule.variants.splice(variantIndex, 1)"
                    ><Trash2 class="h-3.5 w-3.5"
                  /></Button>
                </div>
                <div class="grid grid-cols-[minmax(0,1fr)_88px] gap-2">
                  <Field label="Variant name"><Input v-model="variant.name" /></Field
                  ><Field label="Weight"><Input v-model="variant.weight" type="number" min="1" step="1" /></Field>
                </div>
                <Field label="Destination URL"
                  ><Input v-model="variant.destination_url" type="url" placeholder="https://example.com/variant"
                /></Field>
              </div>
              <Button
                type="button"
                variant="secondary"
                size="sm"
                class="justify-self-start"
                @click="rule.variants.push(newVariant(String.fromCharCode(65 + rule.variants.length)))"
                ><Plus class="h-3.5 w-3.5" />Add variant</Button
              >
            </div>
          </section>
          <div
            v-if="errorsFor(index).length"
            role="alert"
            class="grid gap-1 rounded-lg border border-danger/30 bg-danger/10 p-3"
          >
            <p v-for="[key, error] in errorsFor(index)" :key="key" class="text-xs text-danger">{{ error }}</p>
          </div>
          <p class="flex items-start gap-2 text-xs leading-5 text-muted">
            <ArrowDown class="mt-0.5 h-3.5 w-3.5 shrink-0 text-faint" />If not matched, continue to
            {{ index < rules.length - 1 ? 'the next enabled routing rule.' : 'the default destination.' }}
          </p>
        </div>
        <p v-else-if="errorsFor(index).length" role="alert" class="border-t px-4 py-2 text-xs text-danger">
          This routing rule needs attention. Expand it to review the errors.
        </p>
      </article>

      <DropdownMenuRoot>
        <DropdownMenuTrigger as-child
          ><Button type="button" variant="secondary" size="sm" class="justify-self-start"
            ><Plus class="h-3.5 w-3.5" />Add routing rule<ChevronDown class="h-3 w-3 text-faint" /></Button
        ></DropdownMenuTrigger>
        <DropdownMenuPortal
          ><DropdownMenuContent align="start" :side-offset="6" class="routing-menu w-72" @escape-key-down.stop>
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

    <div class="rounded-xl border border-dashed p-4">
      <div class="flex items-center gap-2 text-[13px] font-medium">
        <ArrowRight class="h-4 w-4 text-muted" />Default destination
      </div>
      <p class="mt-1 text-xs leading-5 text-muted">
        {{
          rules.length
            ? 'Used when no enabled routing rule matches.'
            : 'All visitors go here until you add a routing rule.'
        }}
      </p>
      <div class="mt-3 flex min-w-0 items-start gap-2 text-xs">
        <Link2 class="mt-0.5 h-3.5 w-3.5 shrink-0 text-faint" /><span
          class="min-w-0 break-all"
          :class="defaultDestination ? 'text-foreground' : 'text-faint'"
          >{{ defaultDestination || 'Add a destination URL in the Link tab.' }}</span
        >
      </div>
    </div>
  </div>
</template>

<style scoped>
.routing-builder {
  container-type: inline-size;
}
.condition-row {
  display: grid;
  grid-template-columns: minmax(0, 1fr) 2rem;
  gap: 0.5rem;
  align-items: start;
}
.condition-fields {
  display: grid;
  grid-template-columns: minmax(0, 1fr);
  gap: 0.5rem;
}
.condition-group {
  position: relative;
  padding-inline-start: 1.75rem;
}
.condition-group::before {
  content: '';
  position: absolute;
  inset-inline-start: 0.75rem;
  top: 1.625rem;
  bottom: 1.625rem;
  border-inline-start: 1px solid hsl(var(--border-strong));
}
.condition-group > .condition-row::before {
  content: '';
  position: absolute;
  inset-inline-start: -1rem;
  top: 1.625rem;
  width: 1rem;
  border-top: 1px solid hsl(var(--border-strong));
}
.condition-join {
  position: relative;
  display: grid;
  width: 1.75rem;
  height: 1.5rem;
  margin-inline-start: -1.75rem;
  place-items: center;
}
.condition-join > span {
  position: relative;
  z-index: 1;
}
@container (min-width: 390px) {
  .condition-fields {
    grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
  }
  .condition-value {
    grid-column: 1 / -1;
  }
}
@container (min-width: 600px) {
  .condition-fields {
    grid-template-columns: minmax(0, 1fr) minmax(0, 1fr) minmax(0, 1.25fr);
  }
  .condition-value {
    grid-column: auto;
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
