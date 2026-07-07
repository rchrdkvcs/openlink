<script setup lang="ts">
import Button from '@/Components/ui/Button.vue';
import Field from '@/Components/ui/Field.vue';
import Switch from '@/Components/ui/Switch.vue';
import {
    Activity,
    CalendarClock,
    ChevronDown,
    ChevronUp,
    Copy,
    Globe2,
    Megaphone,
    MonitorSmartphone,
    Plus,
    Route,
    Shuffle,
    Trash2,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import type { RoutingCondition, RoutingRuleDraft, RoutingVariantDraft } from './types';

const rules = defineModel<RoutingRuleDraft[]>({ required: true });

const props = defineProps<{
    errors?: Record<string, string | undefined>;
}>();

const editingIndex = ref<number | null>(null);

const timezone = Intl.DateTimeFormat().resolvedOptions().timeZone || 'UTC';

const conditionTypes = [
    { value: 'country', label: 'Country' },
    { value: 'language', label: 'Language' },
    { value: 'device_type', label: 'Device' },
    { value: 'browser', label: 'Browser' },
    { value: 'operating_system', label: 'OS' },
    { value: 'referrer_host', label: 'Referrer host' },
    { value: 'referrer_channel', label: 'Referrer channel' },
    { value: 'utm_source', label: 'UTM source' },
    { value: 'utm_medium', label: 'UTM medium' },
    { value: 'utm_campaign', label: 'UTM campaign' },
    { value: 'utm_term', label: 'UTM term' },
    { value: 'utm_content', label: 'UTM content' },
    { value: 'date_time', label: 'Date/time' },
    { value: 'day_of_week', label: 'Day' },
    { value: 'time_of_day', label: 'Time' },
];

const scalarOperators = [
    { value: 'is', label: 'is' },
    { value: 'is_not', label: 'is not' },
    { value: 'contains', label: 'contains' },
    { value: 'does_not_contain', label: 'does not contain' },
    { value: 'starts_with', label: 'starts with' },
    { value: 'ends_with', label: 'ends with' },
    { value: 'is_empty', label: 'is empty' },
    { value: 'is_not_empty', label: 'is not empty' },
];

const timeOperators = [
    { value: 'before', label: 'before' },
    { value: 'after', label: 'after' },
    { value: 'between', label: 'between' },
];

const dayOptions = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
const deviceOptions = ['mobile', 'desktop', 'tablet', 'bot'];
const channelOptions = ['direct', 'search', 'social', 'video', 'email', 'messaging', 'ai', 'referral'];

const editingRule = computed(() => (editingIndex.value === null ? null : rules.value[editingIndex.value] ?? null));

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

    return { type, operator: 'is', value: defaultValue(type) };
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

function addRule(kind: string) {
    const type = kind === 'split' ? 'split_test' : 'conditional';
    const conditionType =
        kind === 'device' ? 'device_type' :
        kind === 'campaign' ? 'utm_campaign' :
        kind === 'time' ? 'time_of_day' :
        kind === 'custom' || kind === 'split' ? 'custom' :
        'country';

    rules.value = [...rules.value, newRule(type, conditionType)];
    editingIndex.value = rules.value.length - 1;
}

function duplicateRule(index: number) {
    const copy = JSON.parse(JSON.stringify(rules.value[index])) as RoutingRuleDraft;
    copy.id = undefined;
    copy.client_id = uid();
    copy.name = `${copy.name} copy`;
    copy.variants = copy.variants.map((variant) => ({ ...variant, id: undefined, client_id: uid() }));
    rules.value = [...rules.value.slice(0, index + 1), copy, ...rules.value.slice(index + 1)];
    editingIndex.value = index + 1;
}

function removeRule(index: number) {
    rules.value = rules.value.filter((_, current) => current !== index);
    editingIndex.value = null;
}

function moveRule(index: number, direction: -1 | 1) {
    const next = index + direction;
    if (next < 0 || next >= rules.value.length) return;
    const clone = [...rules.value];
    [clone[index], clone[next]] = [clone[next], clone[index]];
    rules.value = clone;
    editingIndex.value = next;
}

function addCondition(rule: RoutingRuleDraft) {
    rule.conditions.push(newCondition());
}

function removeCondition(rule: RoutingRuleDraft, index: number) {
    rule.conditions.splice(index, 1);
}

function addVariant(rule: RoutingRuleDraft) {
    rule.variants.push(newVariant(String.fromCharCode(65 + rule.variants.length)));
}

function removeVariant(rule: RoutingRuleDraft, index: number) {
    rule.variants.splice(index, 1);
}

function onConditionTypeChange(condition: RoutingCondition) {
    const replacement = newCondition(condition.type);
    condition.operator = replacement.operator;
    condition.value = replacement.value;
    condition.timezone = replacement.timezone;
}

function onRuleTypeChange(rule: RoutingRuleDraft) {
    if (rule.type === 'split_test' && rule.variants.length === 0) {
        rule.variants = [newVariant('A'), newVariant('B')];
    }
}

function operatorsFor(condition: RoutingCondition) {
    return ['date_time', 'time_of_day'].includes(condition.type) ? timeOperators : scalarOperators;
}

function defaultValue(type: string) {
    if (type === 'device_type') return 'mobile';
    if (type === 'referrer_channel') return 'social';
    if (type === 'country') return 'FR';
    if (type === 'language') return 'fr';
    return '';
}

function labelFor(type: string) {
    return conditionTypes.find((option) => option.value === type)?.label ?? type;
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
        ? rule.conditions.map((condition) => `${labelFor(condition.type)} ${condition.operator.replaceAll('_', ' ')} ${formatValue(condition)}`.trim()).join(rule.match_mode === 'all' ? ' + ' : ' / ')
        : 'All visitors';

    if (rule.type === 'split_test') {
        const variants = rule.variants.filter((variant) => variant.is_enabled).map((variant) => `${variant.weight}% ${variant.name}`).join(', ');
        return `${conditionSummary} -> ${variants || 'No active variants'}`;
    }

    return `${conditionSummary} -> ${rule.destination_url || 'No destination yet'}`;
}

function hasValueInput(condition: RoutingCondition) {
    return condition.operator !== 'is_empty' && condition.operator !== 'is_not_empty';
}

function valueOptions(condition: RoutingCondition) {
    if (condition.type === 'device_type') return deviceOptions;
    if (condition.type === 'referrer_channel') return channelOptions;
    if (condition.type === 'day_of_week') return dayOptions;
    return null;
}

function rangeValue(condition: RoutingCondition): { from?: string; to?: string } {
    if (typeof condition.value !== 'object' || condition.value === null || Array.isArray(condition.value)) {
        condition.value = { from: '', to: '' };
    }

    return condition.value;
}
</script>

<template>
    <div class="grid gap-4">
        <div v-if="props.errors?.routing_rules" class="rounded-md border border-danger/30 bg-danger/10 px-3 py-2 text-xs text-danger">
            {{ props.errors.routing_rules }}
        </div>

        <div v-if="rules.length === 0" class="rounded-lg border border-dashed bg-surface p-4">
            <div class="flex items-start gap-3">
                <span class="grid h-9 w-9 shrink-0 place-items-center rounded-lg border border-accent/30 bg-accent/10">
                    <Route class="h-4 w-4 text-accent" />
                </span>
                <div class="min-w-0">
                    <p class="text-sm font-medium text-foreground">Smart Routing</p>
                    <p class="mt-1 text-xs leading-5 text-faint">Choose destinations by country, device, campaign, time, or split traffic between variants.</p>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-2 gap-2 sm:grid-cols-3">
            <Button type="button" variant="secondary" size="sm" @click="addRule('country')"><Globe2 class="h-3.5 w-3.5" />Country</Button>
            <Button type="button" variant="secondary" size="sm" @click="addRule('device')"><MonitorSmartphone class="h-3.5 w-3.5" />Device</Button>
            <Button type="button" variant="secondary" size="sm" @click="addRule('campaign')"><Megaphone class="h-3.5 w-3.5" />Campaign</Button>
            <Button type="button" variant="secondary" size="sm" @click="addRule('time')"><CalendarClock class="h-3.5 w-3.5" />Time</Button>
            <Button type="button" variant="secondary" size="sm" @click="addRule('split')"><Shuffle class="h-3.5 w-3.5" />Split test</Button>
            <Button type="button" variant="secondary" size="sm" @click="addRule('custom')"><Plus class="h-3.5 w-3.5" />Custom</Button>
        </div>

        <div v-if="rules.length" class="grid gap-2">
            <article
                v-for="(rule, index) in rules"
                :key="rule.id ?? rule.client_id ?? index"
                class="rounded-lg border bg-surface p-3 transition-colors"
                :class="editingIndex === index ? 'border-accent/50' : 'hover:border-border-strong'"
            >
                <div class="flex items-start gap-3">
                    <div class="grid h-7 w-7 shrink-0 place-items-center rounded-md bg-elevated text-xs font-semibold text-muted">{{ index + 1 }}</div>
                    <button type="button" class="min-w-0 flex-1 text-left" @click="editingIndex = editingIndex === index ? null : index">
                        <span class="block truncate text-[13px] font-medium text-foreground">{{ rule.name }}</span>
                        <span class="mt-0.5 block truncate text-xs text-faint">{{ ruleSummary(rule) }}</span>
                    </button>
                    <Switch v-model="rule.is_enabled" />
                </div>
                <div class="mt-3 flex flex-wrap justify-end gap-1.5">
                    <Button type="button" variant="ghost" size="sm" title="Move up" :disabled="index === 0" @click="moveRule(index, -1)"><ChevronUp class="h-3.5 w-3.5" /></Button>
                    <Button type="button" variant="ghost" size="sm" title="Move down" :disabled="index === rules.length - 1" @click="moveRule(index, 1)"><ChevronDown class="h-3.5 w-3.5" /></Button>
                    <Button type="button" variant="ghost" size="sm" title="Duplicate" @click="duplicateRule(index)"><Copy class="h-3.5 w-3.5" /></Button>
                    <Button type="button" variant="danger" size="sm" title="Delete" @click="removeRule(index)"><Trash2 class="h-3.5 w-3.5" /></Button>
                </div>
            </article>
        </div>

        <section v-if="editingRule" class="grid gap-4 rounded-lg border bg-elevated/40 p-4">
            <div class="grid gap-3 sm:grid-cols-[1fr_140px]">
                <Field label="Rule name">
                    <input v-model="editingRule.name" class="h-9" />
                </Field>
                <Field label="Type">
                    <select v-model="editingRule.type" class="h-9" @change="onRuleTypeChange(editingRule)">
                        <option value="conditional">Destination</option>
                        <option value="split_test">Split test</option>
                    </select>
                </Field>
            </div>

            <div class="flex items-center justify-between gap-3 rounded-md border bg-surface px-3 py-2">
                <span class="text-[13px] text-muted">Conditions match</span>
                <select v-model="editingRule.match_mode" class="h-8 w-28">
                    <option value="all">All</option>
                    <option value="any">Any</option>
                </select>
            </div>

            <div class="grid gap-2">
                <div
                    v-for="(condition, conditionIndex) in editingRule.conditions"
                    :key="conditionIndex"
                    class="grid gap-2 rounded-md border bg-surface p-2 sm:grid-cols-[140px_140px_1fr_auto]"
                >
                    <select v-model="condition.type" class="h-9" @change="onConditionTypeChange(condition)">
                        <option v-for="option in conditionTypes" :key="option.value" :value="option.value">{{ option.label }}</option>
                    </select>
                    <select v-model="condition.operator" class="h-9">
                        <option v-for="option in operatorsFor(condition)" :key="option.value" :value="option.value">{{ option.label }}</option>
                    </select>

                    <template v-if="hasValueInput(condition)">
                        <div v-if="condition.operator === 'between'" class="grid grid-cols-2 gap-2">
                            <input v-model="rangeValue(condition).from" class="h-9" :type="condition.type === 'date_time' ? 'datetime-local' : 'time'" />
                            <input v-model="rangeValue(condition).to" class="h-9" :type="condition.type === 'date_time' ? 'datetime-local' : 'time'" />
                        </div>
                        <select v-else-if="valueOptions(condition)" v-model="condition.value" class="h-9">
                            <option v-for="option in valueOptions(condition)" :key="option" :value="option">{{ option }}</option>
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

                    <Button type="button" variant="ghost" size="sm" title="Remove condition" @click="removeCondition(editingRule, conditionIndex)">
                        <Trash2 class="h-3.5 w-3.5" />
                    </Button>

                    <input
                        v-if="['date_time', 'day_of_week', 'time_of_day'].includes(condition.type)"
                        v-model="condition.timezone"
                        class="h-8 sm:col-span-4"
                        placeholder="Timezone"
                    />
                </div>
                <Button type="button" variant="secondary" size="sm" class="justify-self-start" @click="addCondition(editingRule)">
                    <Plus class="h-3.5 w-3.5" />Condition
                </Button>
            </div>

            <Field v-if="editingRule.type === 'conditional'" label="Destination URL" :error="props.errors?.[`routing_rules.${editingIndex}.destination_url`]">
                <input v-model="editingRule.destination_url" class="h-9" placeholder="https://example.com/landing" />
            </Field>

            <div v-else class="grid gap-2">
                <div class="flex items-center justify-between gap-3">
                    <p class="text-[13px] font-medium text-foreground">Variants</p>
                    <Button type="button" variant="secondary" size="sm" @click="addVariant(editingRule)"><Plus class="h-3.5 w-3.5" />Variant</Button>
                </div>
                <div
                    v-for="(variant, variantIndex) in editingRule.variants"
                    :key="variant.id ?? variant.client_id ?? variantIndex"
                    class="grid gap-2 rounded-md border bg-surface p-2 sm:grid-cols-[92px_1fr_80px_auto]"
                >
                    <input v-model="variant.name" class="h-9" />
                    <input v-model="variant.destination_url" class="h-9" placeholder="https://example.com/variant" />
                    <input v-model="variant.weight" class="h-9" type="number" min="1" />
                    <div class="flex items-center gap-2">
                        <Switch v-model="variant.is_enabled" />
                        <Button type="button" variant="ghost" size="sm" title="Remove variant" @click="removeVariant(editingRule, variantIndex)">
                            <Trash2 class="h-3.5 w-3.5" />
                        </Button>
                    </div>
                </div>
                <p v-if="props.errors?.[`routing_rules.${editingIndex}.variants`]" class="text-xs text-danger">
                    {{ props.errors[`routing_rules.${editingIndex}.variants`] }}
                </p>
            </div>

            <div class="flex justify-end">
                <Button type="button" variant="secondary" @click="editingIndex = null"><Activity class="h-3.5 w-3.5" />Done</Button>
            </div>
        </section>
    </div>
</template>
