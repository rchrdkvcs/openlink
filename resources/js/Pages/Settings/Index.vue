<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { CircleAlert, Lock, Mail, UserPlus } from '@lucide/vue';
import { computed, onMounted, onUnmounted, ref } from 'vue';

import Button from '@/Components/ui/Button.vue';
import EmptyState from '@/Components/ui/EmptyState.vue';
import Input from '@/Components/ui/Input.vue';
import SaveBar from '@/Components/ui/SaveBar.vue';
import SegmentedControl from '@/Components/ui/SegmentedControl.vue';
import SettingsGroup from '@/Components/ui/SettingsGroup.vue';
import SettingsRow from '@/Components/ui/SettingsRow.vue';
import StepperInput from '@/Components/ui/StepperInput.vue';
import Switch from '@/Components/ui/Switch.vue';
import Textarea from '@/Components/ui/Textarea.vue';
import SettingsLayout from '@/Layouts/SettingsLayout.vue';
import { toast } from '@/lib/toast';

type RegistrationMode = 'closed' | 'invite_only' | 'open';

const props = defineProps<{
  settings: Record<string, any>;
  updateStatus: {
    current: string;
    latest: { version: string; url: string } | null;
    available: boolean;
    canUpdate: boolean;
    state: 'pending' | 'running' | 'succeeded' | 'failed' | null;
  } | null;
}>();

const requestingUpdate = ref(false);
let updatePoll: ReturnType<typeof setInterval> | undefined;

onMounted(() => {
  updatePoll = setInterval(() => {
    if (props.updateStatus?.state === 'pending' || props.updateStatus?.state === 'running') {
      router.reload({ only: ['updateStatus'] });
    }
  }, 5000);
});

onUnmounted(() => {
  if (updatePoll) clearInterval(updatePoll);
});

function requestUpdate() {
  requestingUpdate.value = true;
  router.post(
    route('instance-update.store'),
    {},
    {
      preserveScroll: true,
      onFinish: () => {
        requestingUpdate.value = false;
      },
    },
  );
}

const updateInProgress = computed(
  () => props.updateStatus?.state === 'pending' || props.updateStatus?.state === 'running',
);

const updateFailed = computed(() => props.updateStatus?.state === 'failed');

const updateMessage = computed(() => {
  const status = props.updateStatus;
  if (status?.state === 'pending') return 'Update requested…';
  if (status?.state === 'running') return 'Updating containers…';
  if (status?.state === 'failed') return 'Update failed. Check the updater logs and retry.';
  if (status?.available && !status.canUpdate)
    return 'A new release is available. Update through your deployment platform.';
  if (status?.available) return 'A new release is available.';
  if (!status || status.current === 'dev') return 'Development builds have no release version.';
  if (status.latest) return 'Up to date.';
  return 'Release information is temporarily unavailable.';
});

const isInstanceAdmin = computed(() => Object.keys(props.settings).length > 0);

const settingsForm = useForm({
  registration_mode: (props.settings.registration_mode ?? 'invite_only') as RegistrationMode,
  require_email_verification: Boolean(props.settings.require_email_verification ?? false),
  default_domain: props.settings.default_domain ?? 'localhost',
  dns_target: props.settings.dns_target ?? '',
  slug_length: String(props.settings.slug_length ?? 6),
  analytics_retention_days: String(props.settings.analytics_retention_days ?? 365),
  reserved_slugs: (props.settings.reserved_slugs ?? []).join('\n'),
  reserved_prefixes: (props.settings.reserved_prefixes ?? []).join('\n'),
  public_unavailable_title: props.settings.public_unavailable_title ?? 'This link is unavailable',
  public_unavailable_message: props.settings.public_unavailable_message ?? 'The link cannot be opened right now.',
});

const registrationModes: { value: RegistrationMode; label: string; description: string; icon: unknown }[] = [
  {
    value: 'closed',
    label: 'Closed',
    description: 'No one can create an account. Existing users keep access.',
    icon: Lock,
  },
  {
    value: 'invite_only',
    label: 'Invite-only',
    description: 'People join only through invite links shared by members.',
    icon: Mail,
  },
  {
    value: 'open',
    label: 'Open',
    description: 'Anyone who reaches the sign-up page can create an account.',
    icon: UserPlus,
  },
];

const registrationDescription = computed(
  () => registrationModes.find((mode) => mode.value === settingsForm.registration_mode)?.description,
);

const retentionHint = computed(() => {
  const days = Number(settingsForm.analytics_retention_days);
  if (!Number.isFinite(days) || days < 30) return 'Visit events older than this are pruned. Minimum 30 days.';
  if (days >= 365) {
    const years = days / 365;
    const rounded = Number.isInteger(years) ? years : Math.round(years * 10) / 10;
    return `Visit events are kept for about ${rounded} ${rounded === 1 ? 'year' : 'years'}, then pruned.`;
  }
  return `Visit events are kept for about ${Math.round(days / 30)} months, then pruned.`;
});

function updateSettings() {
  settingsForm.patch(route('instance-settings.update'), {
    preserveScroll: true,
    onSuccess: () => {
      settingsForm.defaults();
      toast({ title: 'Instance settings saved', tone: 'success' });
    },
  });
}

function discardChanges() {
  settingsForm.reset();
  settingsForm.clearErrors();
}
</script>

<template>
  <Head title="Instance settings" />

  <SettingsLayout title="Instance settings" description="Behaviour for everyone on this Openlink installation.">
    <SettingsGroup v-if="!isInstanceAdmin">
      <EmptyState
        title="Reserved for instance administrators"
        description="Only an instance administrator can view and change these settings. Workspace options live in workspace settings."
      >
        <template #icon><Lock class="h-5 w-5" /></template>
      </EmptyState>
    </SettingsGroup>

    <template v-else>
      <SettingsGroup title="Updates">
        <SettingsRow
          label="Installed version"
          :description="updateFailed ? undefined : updateMessage"
          :error="updateFailed ? updateMessage : undefined"
        >
          <div class="flex items-center gap-3 sm:justify-end">
            <span class="font-mono text-[13px] text-foreground">{{ updateStatus?.current ?? 'dev' }}</span>
            <Button
              v-if="updateStatus?.available && updateStatus.canUpdate"
              type="button"
              size="sm"
              :loading="requestingUpdate || updateInProgress"
              @click="requestUpdate"
            >
              Update now
            </Button>
          </div>
        </SettingsRow>
        <SettingsRow label="Latest stable release">
          <div class="flex sm:justify-end">
            <a
              v-if="updateStatus?.latest"
              :href="updateStatus.latest.url"
              target="_blank"
              rel="noopener noreferrer"
              class="font-mono text-[13px] text-accent hover:underline"
            >
              {{ updateStatus.latest.version }}
            </a>
            <span v-else class="text-[13px] text-faint">Unavailable</span>
          </div>
        </SettingsRow>
      </SettingsGroup>

      <form class="space-y-8" @submit.prevent="updateSettings">
        <SettingsGroup title="Access">
          <SettingsRow
            label="Registration"
            :description="registrationDescription"
            :error="settingsForm.errors.registration_mode"
            stacked
          >
            <SegmentedControl
              v-model="settingsForm.registration_mode"
              :options="registrationModes"
              label="Registration"
              class="w-full sm:w-auto"
            />
          </SettingsRow>
          <SettingsRow
            label="Require email verification"
            description="Unverified users cannot use the dashboard or API. Needs a working mail server."
            :error="settingsForm.errors.require_email_verification"
          >
            <div class="flex sm:justify-end">
              <Switch v-model="settingsForm.require_email_verification" aria-label="Require email verification" />
            </div>
          </SettingsRow>
        </SettingsGroup>

        <SettingsGroup title="Domains and DNS">
          <SettingsRow
            label="Default domain"
            description="Available to every workspace without DNS setup."
            for="default-domain"
            :error="settingsForm.errors.default_domain"
          >
            <Input id="default-domain" v-model="settingsForm.default_domain" placeholder="localhost" />
          </SettingsRow>
          <SettingsRow
            label="DNS target"
            description="Where workspace domains should point. Empty uses the default domain."
            for="dns-target"
            :error="settingsForm.errors.dns_target"
          >
            <Input id="dns-target" v-model="settingsForm.dns_target" placeholder="203.0.113.10 or app.example.com" />
          </SettingsRow>
        </SettingsGroup>

        <SettingsGroup title="Short links">
          <SettingsRow
            label="Generated slug length"
            description="Between 4 and 32 characters."
            :error="settingsForm.errors.slug_length"
          >
            <div class="sm:ml-auto sm:w-40">
              <StepperInput v-model="settingsForm.slug_length" :min="4" />
            </div>
          </SettingsRow>
          <SettingsRow
            label="Reserved slugs"
            description="One per line. These can never be claimed by a short link."
            for="reserved-slugs"
            :error="settingsForm.errors.reserved_slugs"
            stacked
          >
            <Textarea
              id="reserved-slugs"
              v-model="settingsForm.reserved_slugs"
              class="font-mono text-[13px]"
              rows="5"
              placeholder="admin&#10;login&#10;settings"
            />
          </SettingsRow>
          <SettingsRow
            label="Reserved prefixes"
            description="One per line. Slugs starting with these are rejected."
            for="reserved-prefixes"
            :error="settingsForm.errors.reserved_prefixes"
            stacked
          >
            <Textarea
              id="reserved-prefixes"
              v-model="settingsForm.reserved_prefixes"
              class="font-mono text-[13px]"
              rows="4"
              placeholder="api/&#10;qr/"
            />
          </SettingsRow>
        </SettingsGroup>

        <SettingsGroup title="Analytics">
          <SettingsRow
            label="Retention in days"
            :description="retentionHint"
            :error="settingsForm.errors.analytics_retention_days"
          >
            <div class="sm:ml-auto sm:w-40">
              <StepperInput v-model="settingsForm.analytics_retention_days" :step="30" :min="30" />
            </div>
          </SettingsRow>
        </SettingsGroup>

        <SettingsGroup
          title="Unavailable page"
          description="Shown when a short link is expired, disabled or not yet scheduled."
        >
          <SettingsRow label="Title" for="unavailable-title" :error="settingsForm.errors.public_unavailable_title">
            <Input
              id="unavailable-title"
              v-model="settingsForm.public_unavailable_title"
              placeholder="This link is unavailable"
            />
          </SettingsRow>
          <SettingsRow
            label="Message"
            for="unavailable-message"
            :error="settingsForm.errors.public_unavailable_message"
            stacked
          >
            <Textarea
              id="unavailable-message"
              v-model="settingsForm.public_unavailable_message"
              rows="3"
              placeholder="The link cannot be opened right now."
            />
          </SettingsRow>
          <SettingsRow label="Preview" stacked>
            <div class="relative overflow-hidden rounded-lg border bg-background">
              <div
                class="pointer-events-none absolute inset-x-0 top-0 h-32 bg-[radial-gradient(ellipse_at_top,hsl(var(--warning)/0.08),transparent_65%)]"
              />
              <div class="grid place-items-center px-6 py-10">
                <div class="relative w-full max-w-xs rounded-xl border bg-surface p-5 text-center">
                  <div class="mx-auto mb-3 grid h-9 w-9 place-items-center rounded-lg border bg-elevated text-warning">
                    <CircleAlert class="h-4 w-4" />
                  </div>
                  <p class="break-words text-sm font-semibold text-foreground">
                    {{ settingsForm.public_unavailable_title || 'This link is unavailable' }}
                  </p>
                  <p class="mt-1.5 break-words text-[13px] text-muted">
                    {{ settingsForm.public_unavailable_message || 'The link cannot be opened right now.' }}
                  </p>
                </div>
              </div>
            </div>
          </SettingsRow>
        </SettingsGroup>

        <SaveBar
          :dirty="settingsForm.isDirty"
          :processing="settingsForm.processing"
          :has-errors="settingsForm.hasErrors"
          @discard="discardChanges"
          @save="updateSettings"
        />
      </form>
    </template>
  </SettingsLayout>
</template>
