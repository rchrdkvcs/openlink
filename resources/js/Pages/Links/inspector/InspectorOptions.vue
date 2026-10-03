<script setup lang="ts">
import type { InertiaForm } from '@inertiajs/vue3';
import { CalendarClock, Gauge, LifeBuoy, Lock } from '@lucide/vue';
import { computed } from 'vue';

import DateTimeField from '@/Components/ui/DateTimeField.vue';
import Field from '@/Components/ui/Field.vue';
import Input from '@/Components/ui/Input.vue';
import PasswordInput from '@/Components/ui/PasswordInput.vue';
import StepperInput from '@/Components/ui/StepperInput.vue';
import { humanize } from '@/lib/datetime';
import { displayUrl } from '@/lib/links';
import type { InspectorSection, ShortLinkForm } from '@/lib/shortLinks/shortLinkForm';

import InspectorRow from './InspectorRow.vue';

const props = defineProps<{ form: InertiaForm<ShortLinkForm>; successfulVisits: number }>();

const sections = defineModel<Record<InspectorSection, boolean>>('sections', { required: true });

const scheduleSummary = computed(() => {
  const { activates_at: starts, expires_at: ends } = props.form;
  if (starts && ends) return `${humanize(starts).split(' ·')[0]} → ${humanize(ends).split(' ·')[0]}`;
  if (starts) return `Starts ${humanize(starts)}`;
  if (ends) return `Ends ${humanize(ends)}`;
  return 'Always on';
});
</script>

<template>
  <InspectorRow
    v-model:open="sections.schedule"
    :icon="CalendarClock"
    label="Schedule"
    :summary="scheduleSummary"
    :active="Boolean(form.activates_at || form.expires_at)"
  >
    <Field label="Starts" hint="Leave empty to activate immediately." :error="form.errors.activates_at">
      <DateTimeField v-model="form.activates_at" />
    </Field>
    <Field label="Ends" hint="The link stops resolving after this date." :error="form.errors.expires_at">
      <DateTimeField v-model="form.expires_at" />
    </Field>
  </InspectorRow>

  <InspectorRow
    v-model:open="sections.limit"
    :icon="Gauge"
    label="Visit limit"
    :summary="form.visit_limit ? `${successfulVisits} / ${form.visit_limit}` : 'Unlimited'"
    :active="Boolean(form.visit_limit)"
  >
    <StepperInput v-model="form.visit_limit" :step="100" placeholder="Unlimited" />
    <p class="text-xs" :class="form.errors.visit_limit ? 'text-danger' : 'text-faint'">
      {{ form.errors.visit_limit ?? 'The link stops resolving after this many visits. Clear to remove.' }}
    </p>
  </InspectorRow>

  <InspectorRow
    v-model:open="sections.password"
    :icon="Lock"
    label="Password"
    :summary="form.password ? 'Protected' : 'None'"
    :active="Boolean(form.password)"
  >
    <PasswordInput v-model="form.password" placeholder="Visitors must enter this to continue" />
    <p class="text-xs" :class="form.errors.password ? 'text-danger' : 'text-faint'">
      {{ form.errors.password ?? 'Clear the field to remove protection.' }}
    </p>
  </InspectorRow>

  <InspectorRow
    v-model:open="sections.fallback"
    :icon="LifeBuoy"
    label="Fallback"
    :summary="form.fallback_url ? displayUrl(form.fallback_url) : 'Unavailable page'"
    :active="Boolean(form.fallback_url)"
  >
    <Input v-model="form.fallback_url" placeholder="https://example.com/expired" />
    <p class="text-xs" :class="form.errors.fallback_url ? 'text-danger' : 'text-faint'">
      {{ form.errors.fallback_url ?? 'Where visitors go when the link is expired, scheduled or disabled.' }}
    </p>
  </InspectorRow>
</template>
