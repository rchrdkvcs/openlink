<script setup lang="ts">
import { router, useForm } from '@inertiajs/vue3';
import { Copy, ExternalLink } from '@lucide/vue';
import { ref } from 'vue';

import Badge from '@/Components/ui/Badge.vue';
import Button from '@/Components/ui/Button.vue';
import Dialog from '@/Components/ui/Dialog.vue';
import Field from '@/Components/ui/Field.vue';
import Input from '@/Components/ui/Input.vue';
import SettingsGroup from '@/Components/ui/SettingsGroup.vue';
import SettingsRow from '@/Components/ui/SettingsRow.vue';
import { copyToClipboard, toast } from '@/lib/toast';

defineProps<{
  twoFactor: {
    enabled: boolean;
    pendingSecret?: string | null;
    otpauthUrl?: string | null;
  };
}>();

const prepareForm = useForm({});
const confirmForm = useForm({ code: '' });
const disableForm = useForm({ password: '' });
const disableOpen = ref(false);

function returnToSecurity() {
  router.replace({ url: route('profile.edit', { tab: 'security' }), preserveScroll: true, preserveState: true });
}

function prepare() {
  prepareForm.post(route('profile.two-factor.prepare'), {
    preserveScroll: true,
    preserveState: true,
    onSuccess: returnToSecurity,
  });
}

function confirm() {
  confirmForm.post(route('profile.two-factor.confirm'), {
    preserveScroll: true,
    preserveState: true,
    onSuccess: () => {
      confirmForm.reset();
      returnToSecurity();
      toast({ title: 'Two-factor authentication enabled', tone: 'success' });
    },
  });
}

function openDisable() {
  disableForm.reset();
  disableForm.clearErrors();
  disableOpen.value = true;
}

function disable() {
  disableForm.delete(route('profile.two-factor.disable'), {
    preserveScroll: true,
    preserveState: true,
    onSuccess: () => {
      disableForm.reset();
      disableOpen.value = false;
      returnToSecurity();
      toast({ title: 'Two-factor authentication turned off', tone: 'success' });
    },
  });
}
</script>

<template>
  <SettingsGroup title="Two-factor authentication">
    <SettingsRow
      label="Authenticator app"
      :description="
        twoFactor.enabled
          ? 'A code from your authenticator app is required when you sign in.'
          : 'Require a one-time code from an authenticator app when you sign in.'
      "
    >
      <div class="flex items-center gap-3 sm:justify-end">
        <template v-if="twoFactor.enabled">
          <Badge variant="success" dot>On</Badge>
          <Button variant="secondary" size="sm" type="button" @click="openDisable">Turn off</Button>
        </template>
        <Badge v-else-if="twoFactor.pendingSecret" variant="warning" dot>Setup in progress</Badge>
        <Button v-else variant="secondary" size="sm" type="button" :loading="prepareForm.processing" @click="prepare">
          Set up
        </Button>
      </div>
    </SettingsRow>

    <div v-if="!twoFactor.enabled && twoFactor.pendingSecret" class="space-y-5 px-4 py-4 sm:px-5">
      <div>
        <p class="text-sm font-medium text-foreground">1. Add Openlink to your authenticator app</p>
        <p class="mt-0.5 text-[13px] text-muted">Enter this setup key manually, or open the link on this device.</p>
        <div class="mt-3 flex items-center gap-2">
          <code
            class="min-w-0 flex-1 truncate rounded-lg border bg-background px-3 py-2 font-mono text-[13px] tracking-wider text-foreground"
            >{{ twoFactor.pendingSecret }}</code
          >
          <Button
            variant="secondary"
            type="button"
            aria-label="Copy setup key"
            @click="copyToClipboard(twoFactor.pendingSecret, 'Setup key copied')"
          >
            <Copy class="h-4 w-4" /> Copy
          </Button>
        </div>
        <a
          v-if="twoFactor.otpauthUrl"
          :href="twoFactor.otpauthUrl"
          class="mt-2 inline-flex items-center gap-1.5 rounded-md text-[13px] font-medium text-accent underline-offset-4 hover:underline focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent/40"
        >
          <ExternalLink class="h-3.5 w-3.5" /> Open in authenticator app
        </a>
      </div>

      <form @submit.prevent="confirm">
        <p class="text-sm font-medium text-foreground">2. Enter the 6-digit code</p>
        <p class="mt-0.5 text-[13px] text-muted">Confirm the app is set up correctly.</p>
        <div class="mt-3 flex items-start gap-2">
          <div class="w-40">
            <Input
              id="two_factor_code"
              v-model="confirmForm.code"
              inputmode="numeric"
              autocomplete="one-time-code"
              placeholder="123456"
              aria-label="Authentication code"
              class="font-mono tracking-widest"
            />
          </div>
          <Button :loading="confirmForm.processing" :disabled="!confirmForm.code.trim()">Turn on</Button>
        </div>
        <p v-if="confirmForm.errors.code" class="mt-1.5 text-xs text-danger">{{ confirmForm.errors.code }}</p>
      </form>
    </div>
  </SettingsGroup>

  <Dialog
    v-model:open="disableOpen"
    size="sm"
    title="Turn off two-factor authentication?"
    description="Your account will be protected by your password only. Enter your password to continue."
  >
    <form class="px-5 pb-5 pt-4" @submit.prevent="disable">
      <Field label="Password" :error="disableForm.errors.password">
        <Input
          id="disable_two_factor_password"
          v-model="disableForm.password"
          type="password"
          autocomplete="current-password"
          autofocus
        />
      </Field>
      <div class="mt-5 flex justify-end gap-2">
        <Button variant="secondary" type="button" @click="disableOpen = false">Cancel</Button>
        <Button variant="danger" :loading="disableForm.processing" :disabled="!disableForm.password">Turn off</Button>
      </div>
    </form>
  </Dialog>
</template>
