<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { LockKeyhole } from '@lucide/vue';

import AuthIcon from '@/Components/Auth/AuthIcon.vue';
import Button from '@/Components/ui/Button.vue';
import Field from '@/Components/ui/Field.vue';
import PasswordInput from '@/Components/ui/PasswordInput.vue';
import GuestLayout from '@/Layouts/GuestLayout.vue';

const form = useForm({
  password: '',
});

const submit = () => {
  form.post(route('password.confirm'), {
    onFinish: () => {
      form.reset();
    },
  });
};
</script>

<template>
  <GuestLayout title="Confirm your password" description="This is a secure area. Confirm your password to continue.">
    <Head title="Confirm your password" />

    <template #icon>
      <AuthIcon><LockKeyhole /></AuthIcon>
    </template>

    <form class="grid gap-5" @submit.prevent="submit">
      <Field label="Password" :error="form.errors.password">
        <PasswordInput
          id="password"
          v-model="form.password"
          size="lg"
          required
          autofocus
          autocomplete="current-password"
          :aria-invalid="Boolean(form.errors.password)"
        />
      </Field>

      <Button class="mt-1 w-full" size="lg" :loading="form.processing">Confirm</Button>
    </form>
  </GuestLayout>
</template>
