<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

import Button from '@/Components/ui/Button.vue';
import Dialog from '@/Components/ui/Dialog.vue';
import Field from '@/Components/ui/Field.vue';
import Input from '@/Components/ui/Input.vue';
import SettingsGroup from '@/Components/ui/SettingsGroup.vue';
import SettingsRow from '@/Components/ui/SettingsRow.vue';

const open = ref(false);
const passwordInput = ref<InstanceType<typeof Input> | null>(null);

const form = useForm({
  password: '',
});

function openDialog() {
  form.reset();
  form.clearErrors();
  open.value = true;
}

function setOpen(value: boolean) {
  open.value = value;
  if (!value) {
    form.reset();
    form.clearErrors();
  }
}

function deleteUser() {
  form.delete(route('profile.destroy'), {
    preserveScroll: true,
    onSuccess: () => setOpen(false),
    onError: () => passwordInput.value?.focus(),
    onFinish: () => form.reset(),
  });
}
</script>

<template>
  <SettingsGroup title="Danger zone">
    <SettingsRow
      label="Delete account"
      description="Permanently removes your account, sign-in methods and API tokens. This cannot be undone."
    >
      <div class="flex sm:justify-end">
        <Button variant="danger" type="button" @click="openDialog">Delete account</Button>
      </div>
    </SettingsRow>
  </SettingsGroup>

  <Dialog
    :open="open"
    size="sm"
    role="alertdialog"
    title="Delete your account?"
    description="Your account and its data are deleted permanently. Enter your password to confirm."
    @update:open="setOpen"
  >
    <form class="px-5 pb-5 pt-4" @submit.prevent="deleteUser">
      <Field label="Password" :error="form.errors.password">
        <Input
          id="password"
          ref="passwordInput"
          v-model="form.password"
          type="password"
          autocomplete="current-password"
          autofocus
        />
      </Field>
      <div class="mt-5 flex justify-end gap-2">
        <Button variant="secondary" type="button" @click="setOpen(false)">Cancel</Button>
        <Button variant="danger" :loading="form.processing" :disabled="!form.password">Delete account</Button>
      </div>
    </form>
  </Dialog>
</template>
