<script setup lang="ts">
import { Check, CornerDownRight, FlaskConical, Lock, Shuffle, TriangleAlert, X } from '@lucide/vue';
import { computed, ref } from 'vue';

import Badge from '@/Components/ui/Badge.vue';
import Button from '@/Components/ui/Button.vue';
import Field from '@/Components/ui/Field.vue';
import Input from '@/Components/ui/Input.vue';
import Select from '@/Components/ui/Select.vue';
import { fetchJson, HttpError } from '@/lib/http';
import { createRouting, formatConditionValue } from '@/lib/routing';
import {
  availabilityReasons,
  newPreviewContext,
  type PreviewDraft,
  previewErrors,
  previewRequest,
  randomSeed,
  type RoutingPreview,
  ruleStatus,
  skippedReason,
} from '@/lib/routing/preview';
import type { RoutingSchema } from '@/types/shortLinks';

import { linkStatusLabel } from '../linkStatus';

const props = defineProps<{
  linkId: number;
  schema: RoutingSchema;
  draft: PreviewDraft | null;
  unsaved: boolean;
}>();

const routing = computed(() => createRouting(props.schema));
const context = ref(newPreviewContext(Intl.DateTimeFormat().resolvedOptions().timeZone || 'UTC'));
const result = ref<RoutingPreview | null>(null);
const errors = ref<string[]>([]);
const running = ref(false);

const deviceOptions = computed(() => [
  { value: '', label: 'Unknown' },
  ...(props.schema.valueOptions.device_type ?? []),
]);

const textFields = [
  { key: 'country', label: 'Country', placeholder: 'FR' },
  { key: 'language', label: 'Language', placeholder: 'fr' },
  { key: 'browser', label: 'Browser', placeholder: 'Chrome' },
  { key: 'operating_system', label: 'OS', placeholder: 'iOS' },
] as const;

const utmFields = [
  { key: 'utm_source', label: 'UTM source' },
  { key: 'utm_medium', label: 'UTM medium' },
  { key: 'utm_campaign', label: 'UTM campaign' },
  { key: 'utm_term', label: 'UTM term' },
  { key: 'utm_content', label: 'UTM content' },
] as const;

const reasons = computed(() => (result.value ? availabilityReasons(result.value.availability) : []));

const localTime = computed(() =>
  result.value
    ? new Date(result.value.context.occurred_at).toLocaleString('en-US', {
        timeZone: result.value.context.timezone,
        dateStyle: 'medium',
        timeStyle: 'short',
      })
    : '',
);

function conditionText(condition: RoutingPreview['rules'][number]['conditions'][number]): string {
  const value = formatConditionValue(props.schema, {
    type: condition.type,
    operator: condition.operator,
    value: condition.value,
  });

  return `${routing.value.label(condition.type)} ${condition.operator.replaceAll('_', ' ')} ${value}`.trim();
}

async function run() {
  running.value = true;
  errors.value = [];

  try {
    result.value = await fetchJson<RoutingPreview>(route('short-links.routing-preview', props.linkId), {
      method: 'POST',
      body: JSON.stringify(previewRequest(context.value, props.draft)),
    });
  } catch (error) {
    result.value = null;
    errors.value =
      error instanceof HttpError && error.status === 422
        ? previewErrors(error.body)
        : ['Couldn’t test routing. Try again.'];
  } finally {
    running.value = false;
  }
}
</script>

<template>
  <div class="@container grid min-w-0 gap-5">
    <p class="text-xs leading-relaxed text-muted">
      Simulate a visitor to see which rule wins. Testing
      <strong class="font-medium text-foreground">{{ draft && unsaved ? 'unsaved changes' : 'saved routing' }}</strong
      >. Nothing is saved, no visit or analytics event is recorded, and destinations are not contacted.
    </p>

    <form class="grid gap-4" @submit.prevent="run">
      <div class="@xl:grid-cols-3 grid grid-cols-2 gap-3">
        <Field v-for="field in textFields" :key="field.key" :label="field.label">
          <Input v-model="context[field.key]" size="sm" :placeholder="field.placeholder" spellcheck="false" />
        </Field>
        <Field label="Device">
          <Select v-model="context.device_type" size="sm" :options="deviceOptions" />
        </Field>
        <Field label="Referrer URL" class="@xl:col-span-1 col-span-2">
          <Input v-model="context.referrer" size="sm" placeholder="https://www.instagram.com/" spellcheck="false" />
        </Field>
        <Field v-for="field in utmFields" :key="field.key" :label="field.label">
          <Input v-model="context[field.key]" size="sm" spellcheck="false" />
        </Field>
      </div>

      <div class="@xl:grid-cols-3 grid grid-cols-2 gap-3">
        <Field label="Date and time">
          <Input v-model="context.occurred_at" size="sm" type="datetime-local" />
        </Field>
        <Field label="In timezone">
          <Input v-model="context.timezone" size="sm" placeholder="Europe/Paris" spellcheck="false" />
        </Field>
        <Field label="Visitor seed" class="@xl:col-span-1 col-span-2" hint="Stands in for the daily visitor hash.">
          <span class="flex gap-1.5">
            <Input v-model="context.seed" size="sm" class="min-w-0 flex-1" spellcheck="false" />
            <Button
              type="button"
              variant="secondary"
              size="sm"
              aria-label="New visitor seed"
              @click="context.seed = randomSeed()"
              ><Shuffle
            /></Button>
          </span>
        </Field>
      </div>

      <Button type="submit" size="sm" class="justify-self-start" :disabled="running"
        ><FlaskConical />{{ running ? 'Testing…' : 'Test routing' }}</Button
      >
    </form>

    <ul v-if="errors.length" role="alert" class="grid gap-1 rounded-lg bg-danger/10 px-3 py-2 text-xs text-danger">
      <li v-for="error in errors" :key="error">{{ error }}</li>
    </ul>

    <section v-if="result" class="grid gap-3" aria-live="polite" aria-label="Routing test result">
      <div class="grid gap-1 rounded-xl border bg-surface px-3.5 py-3">
        <span class="text-xs text-faint">Simulated destination · {{ localTime }}</span>
        <span class="break-all text-[13px] font-medium text-foreground">{{ result.decision.destination_url }}</span>
        <span class="text-xs text-muted">
          <template v-if="result.decision.is_default">No rule matched. Default destination.</template>
          <template v-else
            >From rule {{ (result.decision.rule_index ?? 0) + 1 }}, {{ result.decision.rule_name
            }}<template v-if="result.decision.variant_name">, variant {{ result.decision.variant_name }}</template
            >.</template
          >
        </span>
      </div>

      <div
        class="grid gap-1 rounded-xl px-3.5 py-3 text-xs"
        :class="result.availability.available ? 'bg-elevated/40 text-muted' : 'bg-warning/10 text-warning'"
      >
        <span class="flex items-center gap-1.5 font-medium">
          <TriangleAlert v-if="!result.availability.available" class="h-3.5 w-3.5 shrink-0" />
          Public link: {{ linkStatusLabel(result.availability.status) }}
          <template v-if="result.availability.domain"> on {{ result.availability.domain }}</template>
        </span>
        <span v-if="!result.availability.available">
          Real visitors won’t reach this destination. {{ reasons.join(' ') }}
        </span>
        <span v-else>This test only simulates routing; real visitors still go through the link’s checks.</span>
        <span v-if="result.availability.password_protected" class="flex items-center gap-1.5">
          <Lock class="h-3.5 w-3.5 shrink-0" />Visitors must enter the password before routing applies.
        </span>
      </div>

      <ol class="grid gap-2" aria-label="Rule results">
        <li v-for="rule in result.rules" :key="rule.index" class="grid gap-2 rounded-xl border px-3.5 py-2.5">
          <div class="flex min-w-0 items-center gap-2">
            <span class="text-xs tabular-nums text-faint">{{ rule.index + 1 }}</span>
            <span class="min-w-0 flex-1 truncate text-[13px] font-medium text-foreground">{{ rule.name }}</span>
            <Badge :variant="ruleStatus(rule.status).tone">{{ ruleStatus(rule.status).label }}</Badge>
          </div>
          <p v-if="rule.status === 'skipped'" class="text-xs text-warning">{{ skippedReason(rule) }}</p>
          <ul v-if="rule.conditions.length" class="grid gap-1">
            <li
              v-for="(condition, index) in rule.conditions"
              :key="index"
              class="flex min-w-0 items-center gap-1.5 text-xs"
              :class="condition.matched ? 'text-success' : 'text-muted'"
            >
              <Check v-if="condition.matched" class="h-3.5 w-3.5 shrink-0" aria-label="Matched" />
              <X v-else class="h-3.5 w-3.5 shrink-0" aria-label="Not matched" />
              <span class="truncate">{{ conditionText(condition) }}</span>
            </li>
            <li v-if="rule.conditions.length > 1" class="text-xs text-faint">
              Needs {{ rule.match_mode === 'all' ? 'all' : 'any' }} of these.
            </li>
          </ul>
          <template v-if="rule.type === 'split_test'">
            <ul class="grid gap-1">
              <li
                v-for="(variant, index) in rule.variants"
                :key="variant.id ?? index"
                class="flex min-w-0 items-center gap-2 text-xs"
                :class="
                  variant.selected ? 'font-medium text-foreground' : variant.eligible ? 'text-muted' : 'text-faint'
                "
              >
                <CornerDownRight v-if="variant.selected" class="h-3.5 w-3.5 shrink-0 text-accent" />
                <span class="min-w-0 flex-1 truncate">{{ variant.name }}</span>
                <span class="tabular-nums">weight {{ variant.weight }}</span>
                <span class="w-12 text-end tabular-nums">{{
                  variant.eligible ? `${variant.share}%` : variant.is_enabled ? 'No weight' : 'Off'
                }}</span>
              </li>
            </ul>
            <p v-if="rule.slot !== null" class="text-xs text-faint">
              Seed lands on slot {{ rule.slot }} of {{ rule.total_weight }}. Real visitors keep the same variant for a
              day, then may switch.
            </p>
          </template>
        </li>
      </ol>
    </section>
  </div>
</template>
