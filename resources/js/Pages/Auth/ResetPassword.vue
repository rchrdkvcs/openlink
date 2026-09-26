<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';

import PrimaryButton from '@/Components/PrimaryButton.vue';
import Field from '@/Components/ui/Field.vue';
import Input from '@/Components/ui/Input.vue';
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
  <GuestLayout>
    <Head title="Reset your password" />

    <div class="mb-6">
      <h1 class="text-lg font-semibold text-foreground">Choose a new password</h1>
      <p class="mt-1 text-sm text-muted">Pick a long, unique password for your account.</p>
    </div>

    <form class="space-y-4" @submit.prevent="submit">
      <Field label="Email" :error="form.errors.email">
        <Input id="email" type="email" v-model="form.email" required autofocus autocomplete="username" />
      </Field>

      <Field label="Password" :error="form.errors.password">
        <Input id="password" type="password" v-model="form.password" required autocomplete="new-password" />
      </Field>

      <Field label="Confirm Password" :error="form.errors.password_confirmation">
        <Input
          id="password_confirmation"
          type="password"
          v-model="form.password_confirmation"
          required
          autocomplete="new-password"
        />
      </Field>

      <PrimaryButton class="w-full" :disabled="form.processing">Reset password</PrimaryButton>
    </form>
  </GuestLayout>
</template>
