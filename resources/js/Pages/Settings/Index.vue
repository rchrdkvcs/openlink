<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { Lock } from '@lucide/vue';
import { computed } from 'vue';

import EmptyState from '@/Components/ui/EmptyState.vue';
import Input from '@/Components/ui/Input.vue';
import SaveBar from '@/Components/ui/SaveBar.vue';
import SegmentedControl from '@/Components/ui/SegmentedControl.vue';
import SettingsGroup from '@/Components/ui/SettingsGroup.vue';
import SettingsRow from '@/Components/ui/SettingsRow.vue';
import StepperInput from '@/Components/ui/StepperInput.vue';
import Switch from '@/Components/ui/Switch.vue';
import SettingsLayout from '@/Layouts/SettingsLayout.vue';
import { toast } from '@/lib/toast';

import { instanceSettingsFields, registrationDescription, registrationModes, retentionHint } from './instanceSettings';
import type { UpdateStatus } from './instanceUpdates';
import ShortLinkRulesGroup from './ShortLinkRulesGroup.vue';
import UnavailablePageGroup from './UnavailablePageGroup.vue';
import UpdatesGroup from './UpdatesGroup.vue';

const props = defineProps<{
  settings: Record<string, any>;
  updateStatus: UpdateStatus | null;
}>();

const isInstanceAdmin = computed(() => Object.keys(props.settings).length > 0);

const settingsForm = useForm(instanceSettingsFields(props.settings));

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
      <UpdatesGroup :update-status="updateStatus" />

      <form class="space-y-8" @submit.prevent="updateSettings">
        <SettingsGroup title="Access">
          <SettingsRow
            label="Registration"
            :description="registrationDescription(settingsForm.registration_mode)"
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

        <ShortLinkRulesGroup :form="settingsForm" />

        <SettingsGroup title="Analytics">
          <SettingsRow
            label="Retention in days"
            :description="retentionHint(settingsForm.analytics_retention_days)"
            :error="settingsForm.errors.analytics_retention_days"
          >
            <div class="sm:ml-auto sm:w-40">
              <StepperInput v-model="settingsForm.analytics_retention_days" :step="30" :min="30" />
            </div>
          </SettingsRow>
        </SettingsGroup>

        <UnavailablePageGroup :form="settingsForm" />

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
