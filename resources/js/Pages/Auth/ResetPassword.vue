<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';

import Button from '@/Components/ui/Button.vue';
import Field from '@/Components/ui/Field.vue';
import Input from '@/Components/ui/Input.vue';
import PasswordInput from '@/Components/ui/PasswordInput.vue';
import GuestLayout from '@/Layouts/GuestLayout.vue';

const props = defineProps<{
  email: string;
  token: string;
}>();

const form = useForm({
  token: props.token,
  email: props.email,
  password: '',
  password_confirmation: '',
});

const submit = () => {
  form.post(route('password.store'), {
    onFinish: () => {
      form.reset('password', 'password_confirmation');
    },
  });
};
</script>

<template>
  <GuestLayout title="Choose a new password" description="Use a long, unique password you don’t use anywhere else.">
    <Head title="Reset your password" />

    <form class="grid gap-5" @submit.prevent="submit">
      <Field label="Email" :error="form.errors.email">
        <Input
          id="email"
          v-model="form.email"
          size="lg"
          type="email"
          required
          autocomplete="username"
          :aria-invalid="Boolean(form.errors.email)"
        />
      </Field>

      <Field label="New password" hint="At least 8 characters." :error="form.errors.password">
        <PasswordInput
          id="password"
          v-model="form.password"
          size="lg"
          required
          autofocus
          :aria-invalid="Boolean(form.errors.password)"
        />
      </Field>

      <Field label="Confirm password" :error="form.errors.password_confirmation">
        <PasswordInput
          id="password_confirmation"
          v-model="form.password_confirmation"
          size="lg"
          required
          :aria-invalid="Boolean(form.errors.password_confirmation)"
        />
      </Field>

      <Button class="mt-1 w-full" size="lg" :loading="form.processing">Reset password</Button>
    </form>
  </GuestLayout>
</template>
