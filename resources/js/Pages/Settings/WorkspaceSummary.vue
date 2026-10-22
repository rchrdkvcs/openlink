<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

import Checkbox from '@/Components/ui/Checkbox.vue';
import SaveBar from '@/Components/ui/SaveBar.vue';
import Select from '@/Components/ui/Select.vue';
import SettingsGroup from '@/Components/ui/SettingsGroup.vue';
import SettingsRow from '@/Components/ui/SettingsRow.vue';
import Switch from '@/Components/ui/Switch.vue';
import SettingsLayout from '@/Layouts/SettingsLayout.vue';
import type { SelectOption } from '@/lib/controls';
import { toast } from '@/lib/toast';
import { initialTimezone, statusLines, type SummaryStatus } from '@/lib/workspaceSummary';

type Member = { id: number; name: string; email: string; role: string };

const props = defineProps<{
  schedule: { is_enabled: boolean; timezone: string | null; recipients: number[] };
  members: Member[];
  status: SummaryStatus;
  timezones: string[];
}>();

const form = useForm({
  is_enabled: props.schedule.is_enabled,
  timezone: initialTimezone(props.schedule.timezone, props.timezones, Intl.DateTimeFormat().resolvedOptions().timeZone),
  recipients: [...props.schedule.recipients],
});

const timezoneOptions = computed<SelectOption<string>[]>(() =>
  props.timezones.map((timezone) => ({ value: timezone, label: timezone.replaceAll('_', ' ') })),
);

const lines = computed(() => statusLines(props.status, props.schedule.is_enabled));

const recipientError = computed(
  () => form.errors.recipients ?? Object.entries(form.errors).find(([key]) => key.startsWith('recipients.'))?.[1],
);

function toggleRecipient(id: number, checked: boolean) {
  form.recipients = checked ? [...form.recipients, id] : form.recipients.filter((recipient) => recipient !== id);
}

function save() {
  form.put(route('workspace-summary.update'), {
    preserveScroll: true,
    onSuccess: () => {
      form.defaults();
      toast({ title: 'Weekly summary updated', tone: 'success' });
    },
  });
}

function discard() {
  form.reset();
  form.clearErrors();
}
</script>

<template>
  <Head title="Weekly summary" />

  <SettingsLayout
    title="Weekly summary"
    description="Email selected members a summary of last week's visits, scans and top links every Monday at 08:00."
  >
    <form class="space-y-8" @submit.prevent="save">
      <SettingsGroup title="Schedule">
        <SettingsRow
          label="Send weekly summary"
          description="Covers Monday to Sunday. Turning this off stops future sends."
          :error="form.errors.is_enabled"
        >
          <div class="flex sm:justify-end">
            <Switch v-model="form.is_enabled" aria-label="Send weekly summary" />
          </div>
        </SettingsRow>
        <SettingsRow
          label="Timezone"
          description="Decides when each week starts and when the email is sent."
          :error="form.errors.timezone"
        >
          <Select v-model="form.timezone" :options="timezoneOptions" aria-label="Timezone" />
        </SettingsRow>
      </SettingsGroup>

      <SettingsGroup title="Recipients">
        <template #footer>
          Only current members can receive the summary. Members who leave the workspace stop receiving it. Opening the
          report from the email requires signing in.
        </template>
        <p v-if="recipientError" class="px-4 pt-3 text-xs text-danger sm:px-5">{{ recipientError }}</p>
        <label
          v-for="member in members"
          :key="member.id"
          class="flex cursor-pointer items-center gap-3 px-4 py-3 sm:px-5"
        >
          <Checkbox
            :model-value="form.recipients.includes(member.id)"
            @update:model-value="toggleRecipient(member.id, $event)"
          />
          <span class="min-w-0 flex-1">
            <span class="block truncate text-sm font-medium text-foreground">{{ member.name }}</span>
            <span class="block truncate text-[13px] text-muted">{{ member.email }}</span>
          </span>
          <span class="text-xs capitalize text-faint">{{ member.role }}</span>
        </label>
      </SettingsGroup>

      <SaveBar
        :dirty="form.isDirty"
        :processing="form.processing"
        :has-errors="form.hasErrors"
        @discard="discard"
        @save="save"
      />
    </form>

    <SettingsGroup title="Delivery status" class="mt-8">
      <template #footer> Summaries need a running queue worker, the scheduler and working mail settings. </template>
      <p
        v-for="line in lines"
        :key="line.text"
        class="px-4 py-3 text-[13px] sm:px-5"
        :class="{
          'text-muted': line.tone === 'neutral',
          'text-foreground': line.tone === 'success',
          'text-danger': line.tone === 'danger',
        }"
        :role="line.tone === 'danger' ? 'status' : undefined"
      >
        {{ line.text }}
      </p>
    </SettingsGroup>
  </SettingsLayout>
</template>
