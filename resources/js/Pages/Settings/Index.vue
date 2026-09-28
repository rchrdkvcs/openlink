<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { BarChart3, CheckCircle2, CircleAlert, Download, Globe2, Link2, Lock, Mail, UserPlus } from '@lucide/vue';
import { computed, onMounted, onUnmounted, ref } from 'vue';

import Button from '@/Components/ui/Button.vue';
import Checkbox from '@/Components/ui/Checkbox.vue';
import EmptyState from '@/Components/ui/EmptyState.vue';
import Field from '@/Components/ui/Field.vue';
import Input from '@/Components/ui/Input.vue';
import SectionCard from '@/Components/ui/SectionCard.vue';
import StepperInput from '@/Components/ui/StepperInput.vue';
import Textarea from '@/Components/ui/Textarea.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';

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

const isInstanceAdmin = computed(() => Object.keys(props.settings).length > 0);

const settingsForm = useForm({
  registration_mode: props.settings.registration_mode ?? 'invite_only',
  require_email_verification: props.settings.require_email_verification ?? false,
  default_domain: props.settings.default_domain ?? 'localhost',
  dns_target: props.settings.dns_target ?? '',
  slug_length: String(props.settings.slug_length ?? 6),
  analytics_retention_days: String(props.settings.analytics_retention_days ?? 365),
  reserved_slugs: (props.settings.reserved_slugs ?? []).join('\n'),
  reserved_prefixes: (props.settings.reserved_prefixes ?? []).join('\n'),
  public_unavailable_title: props.settings.public_unavailable_title ?? 'This link is unavailable',
  public_unavailable_message: props.settings.public_unavailable_message ?? 'The link cannot be opened right now.',
});

const registrationModes = [
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

const hasErrors = computed(() => Object.keys(settingsForm.errors).length > 0);
const showSaveBar = computed(() => settingsForm.isDirty || settingsForm.processing || settingsForm.recentlySuccessful);

function updateSettings() {
  settingsForm.patch(route('instance-settings.update'), {
    preserveScroll: true,
    onSuccess: () => settingsForm.defaults(),
  });
}

function discardChanges() {
  settingsForm.reset();
  settingsForm.clearErrors();
}
</script>

<template>
  <Head title="Settings" />

  <AuthenticatedLayout>
    <div class="w-full px-4 py-8 sm:px-6 lg:px-8">
      <div class="mx-auto w-full max-w-4xl">
        <div class="mb-6">
          <h1 class="text-xl font-semibold tracking-tight">Settings</h1>
          <p class="mt-1 text-sm text-muted">Instance-level behaviour for this Openlink installation.</p>
        </div>

        <SectionCard v-if="!isInstanceAdmin">
          <EmptyState
            title="Reserved for instance administrators"
            description="Only an instance administrator can view and change these settings. Workspace options — name, appearance, and preferred domain — live in the workspace switcher."
          >
            <template #icon><Lock class="h-5 w-5" /></template>
          </EmptyState>
        </SectionCard>

        <SectionCard
          v-else
          title="Application updates"
          description="Version installed on this Openlink instance."
          class="mb-4"
        >
          <template #icon><Download class="h-4 w-4 text-faint" /></template>
          <div class="flex flex-wrap items-center justify-between gap-4 p-5">
            <div class="text-sm">
              <p>
                Installed: <span class="font-medium">{{ updateStatus?.current ?? 'dev' }}</span>
              </p>
              <p v-if="updateStatus?.latest" class="mt-1 text-muted">
                Latest stable release:
                <a
                  :href="updateStatus.latest.url"
                  target="_blank"
                  rel="noopener noreferrer"
                  class="text-accent underline"
                >
                  {{ updateStatus.latest.version }}
                </a>
              </p>
              <p v-else class="mt-1 text-muted">Release information is temporarily unavailable.</p>
              <p v-if="updateStatus?.state === 'pending'" class="mt-2 text-muted">Update requested…</p>
              <p v-else-if="updateStatus?.state === 'running'" class="mt-2 text-muted">Updating containers…</p>
              <p v-else-if="updateStatus?.state === 'failed'" class="mt-2 text-danger">
                Update failed. Check the updater logs and retry.
              </p>
              <p v-else-if="updateStatus?.available && !updateStatus.canUpdate" class="mt-2 text-muted">
                A new release is available. Update this installation through your deployment platform.
              </p>
              <p v-else-if="updateStatus?.current === 'dev'" class="mt-2 text-muted">
                This development build has no release version.
              </p>
              <p v-else-if="!updateStatus?.available && updateStatus?.latest" class="mt-2 text-muted">Up to date.</p>
            </div>
            <Button
              v-if="updateStatus?.available && updateStatus.canUpdate"
              type="button"
              :loading="requestingUpdate"
              :disabled="updateStatus.state === 'pending' || updateStatus.state === 'running'"
              @click="requestUpdate"
            >
              Update now
            </Button>
          </div>
        </SectionCard>

        <form v-if="isInstanceAdmin" class="space-y-4" @submit.prevent="updateSettings">
          <SectionCard title="Access" description="Who can create an account on this instance.">
            <template #icon><UserPlus class="h-4 w-4 text-faint" /></template>

            <div class="p-5">
              <fieldset class="grid gap-2 sm:grid-cols-3">
                <label
                  v-for="mode in registrationModes"
                  :key="mode.value"
                  class="flex cursor-pointer flex-col gap-1.5 rounded-md border p-3 transition-colors duration-150 has-[:focus-visible]:ring-2 has-[:focus-visible]:ring-accent/40"
                  :class="
                    settingsForm.registration_mode === mode.value
                      ? 'border-accent/60 bg-accent/5'
                      : 'border-border hover:border-border-strong hover:bg-elevated/40'
                  "
                >
                  <input
                    v-model="settingsForm.registration_mode"
                    type="radio"
                    name="registration_mode"
                    :value="mode.value"
                    class="sr-only"
                  />
                  <span class="flex items-center gap-2">
                    <component
                      :is="mode.icon"
                      class="h-4 w-4 shrink-0"
                      :class="settingsForm.registration_mode === mode.value ? 'text-accent' : 'text-faint'"
                    />
                    <span class="text-[13px] font-medium text-foreground">{{ mode.label }}</span>
                  </span>
                  <span class="text-xs leading-relaxed text-muted">{{ mode.description }}</span>
                </label>
              </fieldset>
              <p v-if="settingsForm.errors.registration_mode" class="mt-2 text-xs text-danger">
                {{ settingsForm.errors.registration_mode }}
              </p>
            </div>
          </SectionCard>

          <SectionCard
            title="Email verification"
            description="Control whether people must confirm their email address before using Openlink."
          >
            <template #icon><Mail class="h-4 w-4 text-faint" /></template>
            <label class="flex cursor-pointer items-start gap-3 p-5">
              <Checkbox v-model="settingsForm.require_email_verification" class="mt-0.5" />
              <span>
                <span class="block text-sm font-medium text-foreground">Require email verification</span>
                <span class="mt-1 block text-xs text-muted"
                  >When enabled, unverified users need a working mail server to access the dashboard and API. Disabled
                  by default.</span
                >
              </span>
            </label>
            <p v-if="settingsForm.errors.require_email_verification" class="px-5 pb-4 text-xs text-danger">
              {{ settingsForm.errors.require_email_verification }}
            </p>
          </SectionCard>

          <SectionCard title="Domains &amp; DNS" description="Hostnames used to publish and serve short URLs.">
            <template #icon><Globe2 class="h-4 w-4 text-faint" /></template>

            <div class="grid gap-5 p-5 sm:grid-cols-2">
              <Field
                label="Default domain"
                hint="Available to every workspace for short URLs, without DNS setup."
                :error="settingsForm.errors.default_domain"
              >
                <Input v-model="settingsForm.default_domain" placeholder="localhost" />
              </Field>
              <Field
                label="DNS target"
                hint="Where workspace domains should point. Leave empty to use the default domain."
                :error="settingsForm.errors.dns_target"
              >
                <Input v-model="settingsForm.dns_target" placeholder="203.0.113.10 or app.example.com" />
              </Field>
            </div>
          </SectionCard>

          <SectionCard title="Short links" description="Slug generation and the reserved namespace.">
            <template #icon><Link2 class="h-4 w-4 text-faint" /></template>

            <div class="grid gap-5 p-5 sm:grid-cols-2">
              <Field
                label="Generated slug length"
                hint="Between 4 and 32 characters."
                class="sm:max-w-52"
                :error="settingsForm.errors.slug_length"
              >
                <StepperInput v-model="settingsForm.slug_length" :min="4" />
              </Field>
              <div class="hidden sm:block" />
              <Field
                label="Reserved slugs"
                hint="One per line. These can never be claimed by a short link."
                :error="settingsForm.errors.reserved_slugs"
              >
                <Textarea
                  v-model="settingsForm.reserved_slugs"
                  class="font-mono text-[13px]"
                  rows="6"
                  placeholder="admin&#10;login&#10;settings"
                />
              </Field>
              <Field
                label="Reserved prefixes"
                hint="One per line. Slugs starting with these are rejected."
                :error="settingsForm.errors.reserved_prefixes"
              >
                <Textarea
                  v-model="settingsForm.reserved_prefixes"
                  class="font-mono text-[13px]"
                  rows="6"
                  placeholder="api/&#10;qr/"
                />
              </Field>
            </div>
          </SectionCard>

          <SectionCard title="Analytics" description="How long visit data is kept.">
            <template #icon><BarChart3 class="h-4 w-4 text-faint" /></template>

            <div class="p-5">
              <Field
                label="Retention (days)"
                :hint="retentionHint"
                class="sm:max-w-52"
                :error="settingsForm.errors.analytics_retention_days"
              >
                <StepperInput v-model="settingsForm.analytics_retention_days" :step="30" :min="30" />
              </Field>
            </div>
          </SectionCard>

          <SectionCard
            title="Unavailable page"
            description="Shown when a short link is expired, disabled, or scheduled."
          >
            <template #icon><CircleAlert class="h-4 w-4 text-faint" /></template>

            <div class="grid gap-5 p-5 lg:grid-cols-2">
              <div class="grid content-start gap-5">
                <Field label="Title" :error="settingsForm.errors.public_unavailable_title">
                  <Input v-model="settingsForm.public_unavailable_title" placeholder="This link is unavailable" />
                </Field>
                <Field label="Message" :error="settingsForm.errors.public_unavailable_message">
                  <Textarea
                    v-model="settingsForm.public_unavailable_message"
                    rows="3"
                    placeholder="The link cannot be opened right now."
                  />
                </Field>
              </div>

              <div class="relative overflow-hidden rounded-lg border bg-background">
                <div
                  class="pointer-events-none absolute inset-x-0 top-0 h-32 bg-[radial-gradient(ellipse_at_top,hsl(var(--warning)/0.08),transparent_65%)]"
                />
                <p class="absolute left-3 top-2.5 text-[11px] font-medium uppercase tracking-wide text-faint">
                  Preview
                </p>
                <div class="grid min-h-full place-items-center px-6 py-10">
                  <div
                    class="card-sheen relative w-full max-w-xs rounded-xl border bg-surface p-5 text-center shadow-2xl shadow-black/30"
                  >
                    <div
                      class="mx-auto mb-3 grid h-9 w-9 place-items-center rounded-lg border bg-elevated text-warning"
                    >
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
            </div>
          </SectionCard>

          <div class="pointer-events-none sticky bottom-4 z-20">
            <Transition
              enter-active-class="transition duration-200 ease-emphasized-out"
              enter-from-class="translate-y-2 opacity-0"
              enter-to-class="translate-y-0 opacity-100"
              leave-active-class="transition duration-150 ease-in-out"
              leave-to-class="translate-y-2 opacity-0"
            >
              <div
                v-if="showSaveBar"
                class="pointer-events-auto mx-auto flex w-full max-w-xl items-center justify-between gap-3 rounded-lg border bg-overlay/95 py-2 pl-4 pr-2 shadow-2xl shadow-black/40 backdrop-blur-md"
              >
                <p v-if="hasErrors" class="truncate text-[13px] text-danger">Some fields need attention.</p>
                <p v-else-if="settingsForm.isDirty || settingsForm.processing" class="truncate text-[13px] text-muted">
                  You have unsaved changes.
                </p>
                <p v-else class="inline-flex items-center gap-1.5 truncate text-[13px] text-success">
                  <CheckCircle2 class="h-4 w-4 shrink-0" /> Instance settings saved.
                </p>

                <div v-if="settingsForm.isDirty || settingsForm.processing" class="flex shrink-0 items-center gap-2">
                  <Button
                    variant="ghost"
                    size="sm"
                    type="button"
                    :disabled="settingsForm.processing"
                    @click="discardChanges"
                  >
                    Discard
                  </Button>
                  <Button size="sm" :loading="settingsForm.processing">Save changes</Button>
                </div>
              </div>
            </Transition>
          </div>
        </form>
      </div>
    </div>
  </AuthenticatedLayout>
</template>
