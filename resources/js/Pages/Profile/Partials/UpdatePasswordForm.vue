<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

import Button from '@/Components/ui/Button.vue';
import Input from '@/Components/ui/Input.vue';
import SettingsGroup from '@/Components/ui/SettingsGroup.vue';
import SettingsRow from '@/Components/ui/SettingsRow.vue';
import { toast } from '@/lib/toast';

const passwordInput = ref<InstanceType<typeof Input> | null>(null);
const currentPasswordInput = ref<InstanceType<typeof Input> | null>(null);

const form = useForm({
  current_password: '',
  password: '',
  password_confirmation: '',
});

function updatePassword() {
  form.put(route('password.update'), {
    preserveScroll: true,
    onSuccess: () => {
      form.reset();
      toast({ title: 'Password updated', tone: 'success' });
    },
    onError: () => {
      if (form.errors.password) {
        form.reset('password', 'password_confirmation');
        passwordInput.value?.focus();
      }
      if (form.errors.current_password) {
        form.reset('current_password');
        currentPasswordInput.value?.focus();
      }
    },
  });
}
</script>

<template>
  <form @submit.prevent="updatePassword">
    <SettingsGroup title="Password" description="Use a long, unique password you don't use anywhere else.">
      <SettingsRow label="Current password" for="current_password" :error="form.errors.current_password">
        <Input
          id="current_password"
          ref="currentPasswordInput"
          v-model="form.current_password"
          type="password"
          autocomplete="current-password"
        />
      </SettingsRow>

      <SettingsRow label="New password" for="password" :error="form.errors.password">
        <Input id="password" ref="passwordInput" v-model="form.password" type="password" autocomplete="new-password" />
      </SettingsRow>

      <SettingsRow label="Confirm new password" for="password_confirmation" :error="form.errors.password_confirmation">
        <Input
          id="password_confirmation"
          v-model="form.password_confirmation"
          type="password"
          autocomplete="new-password"
        />
      </SettingsRow>

      <div class="flex justify-end px-4 py-3 sm:px-5">
        <Button size="sm" :loading="form.processing" :disabled="!form.isDirty">Update password</Button>
      </div>
    </SettingsGroup>
  </form>
</template>
