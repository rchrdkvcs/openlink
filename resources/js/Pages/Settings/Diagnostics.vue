<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

import Badge from '@/Components/ui/Badge.vue';
import Button from '@/Components/ui/Button.vue';
import SettingsGroup from '@/Components/ui/SettingsGroup.vue';
import SettingsLayout from '@/Layouts/SettingsLayout.vue';
import { relativeTime, shortDate } from '@/lib/datetime';
import { toast } from '@/lib/toast';

import {
  attentionSummary,
  type DiagnosticCheck,
  heartbeatFootnote,
  type MailTest,
  stateLabel,
  stateVariant,
} from './diagnostics';

const props = defineProps<{
  checks: Record<string, DiagnosticCheck>;
  mailTest: MailTest | null;
  heartbeat: { cadenceMinutes: number; staleAfterMinutes: number };
  canSendTestMail: boolean;
}>();

const page = usePage();
const rechecking = ref(false);
const sendingMail = ref(false);

const services = computed(() => Object.entries(props.checks).filter(([key]) => key !== 'mail'));
const mailCheck = computed(() => props.checks.mail);
const summary = computed(() => attentionSummary(Object.values(props.checks)));
const mailError = computed(() => (page.props.errors as Record<string, string> | undefined)?.mail);
const adminEmail = computed(() => (page.props.auth as { user: { email: string } }).user.email);

function failureMessage(errors: Record<string, string>, fallback: string): string {
  return Object.values(errors)[0] ?? fallback;
}

function recheck() {
  rechecking.value = true;
  router.post(
    route('instance-diagnostics.recheck'),
    {},
    {
      preserveScroll: true,
      onSuccess: () => toast({ title: 'Checks finished', tone: 'success' }),
      onError: (errors) => toast({ title: failureMessage(errors, 'Checks could not run'), tone: 'danger' }),
      onFinish: () => {
        rechecking.value = false;
      },
    },
  );
}

function sendTestMail() {
  sendingMail.value = true;
  router.post(
    route('instance-diagnostics.test-mail'),
    {},
    {
      preserveScroll: true,
      onSuccess: () =>
        toast({
          title: 'Test email accepted',
          description: `Check the inbox of ${adminEmail.value}.`,
          tone: 'success',
        }),
      onFinish: () => {
        sendingMail.value = false;
      },
    },
  );
}

function timeLabel(value: string | null): string {
  return value ? relativeTime(value) : 'Never';
}
</script>

<template>
  <Head title="Diagnostics" />

  <SettingsLayout title="Diagnostics" :description="summary">
    <template #actions>
      <Button type="button" variant="secondary" :loading="rechecking" @click="recheck">Recheck</Button>
    </template>

    <SettingsGroup title="Services">
      <div v-for="[key, check] in services" :key="key" class="px-4 py-3.5 sm:px-5">
        <div class="flex flex-wrap items-center justify-between gap-x-4 gap-y-1">
          <h3 class="text-sm font-medium text-foreground">{{ check.label }}</h3>
          <Badge :variant="stateVariant(check.state)" dot>{{ stateLabel(check.state) }}</Badge>
        </div>
        <p class="mt-1 text-pretty text-[13px] leading-relaxed text-muted">{{ check.summary }}</p>
        <p v-if="check.hint" class="mt-1.5 text-pretty text-[13px] leading-relaxed text-foreground">
          {{ check.hint }}
        </p>
        <dl class="mt-2 flex flex-wrap gap-x-5 gap-y-1 text-xs text-faint">
          <div class="flex gap-1">
            <dt>Last healthy</dt>
            <dd class="tabular-nums" :title="shortDate(check.observedAt)">{{ timeLabel(check.observedAt) }}</dd>
          </div>
          <div class="flex gap-1">
            <dt>Checked</dt>
            <dd class="tabular-nums" :title="shortDate(check.checkedAt)">{{ timeLabel(check.checkedAt) }}</dd>
          </div>
        </dl>
      </div>
      <template #footer>{{ heartbeatFootnote(heartbeat.cadenceMinutes, heartbeat.staleAfterMinutes) }}</template>
    </SettingsGroup>

    <SettingsGroup title="Email">
      <div class="px-4 py-3.5 sm:px-5">
        <div class="flex flex-wrap items-center justify-between gap-x-4 gap-y-1">
          <h3 class="text-sm font-medium text-foreground">{{ mailCheck.label }}</h3>
          <Badge :variant="stateVariant(mailCheck.state)" dot>{{ stateLabel(mailCheck.state) }}</Badge>
        </div>
        <p class="mt-1 text-pretty text-[13px] leading-relaxed text-muted">{{ mailCheck.summary }}</p>
        <p v-if="mailCheck.hint" class="mt-1.5 text-pretty text-[13px] leading-relaxed text-foreground">
          {{ mailCheck.hint }}
        </p>
      </div>
      <div class="flex flex-col gap-3 px-4 py-3.5 sm:flex-row sm:items-center sm:justify-between sm:px-5">
        <div class="min-w-0">
          <h3 class="text-sm font-medium text-foreground">Test email</h3>
          <p class="mt-0.5 text-pretty text-[13px] leading-relaxed text-muted">
            <template v-if="mailTest">{{ mailTest.summary }} Sent {{ timeLabel(mailTest.checkedAt) }}.</template>
            <template v-else>Send a message to {{ adminEmail }} to check that the mail server accepts it.</template>
          </p>
          <p v-if="mailError" class="mt-1 text-xs text-danger" role="alert">{{ mailError }}</p>
        </div>
        <Button
          type="button"
          size="sm"
          variant="secondary"
          class="shrink-0"
          :disabled="!canSendTestMail"
          :loading="sendingMail"
          @click="sendTestMail"
        >
          Send test email
        </Button>
      </div>
    </SettingsGroup>
  </SettingsLayout>
</template>
