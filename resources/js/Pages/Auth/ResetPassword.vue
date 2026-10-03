<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';

import Button from '@/Components/ui/Button.vue';
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
  <GuestLayout title="Choose a new password" description="Use a long, unique password you don’t use anywhere else.">
    <Head title="Reset your password" />

    <form class="grid gap-4" @submit.prevent="submit">
      <Field label="Email" :error="form.errors.email">
        <Input id="email" v-model="form.email" type="email" required autofocus autocomplete="username" />
      </Field>

      <Field label="New password" :error="form.errors.password">
        <Input id="password" v-model="form.password" type="password" required autocomplete="new-password" />
      </Field>

      <Field label="Confirm password" :error="form.errors.password_confirmation">
        <Input
          id="password_confirmation"
          v-model="form.password_confirmation"
          type="password"
          required
          autocomplete="new-password"
        />
      </Field>

      <Button class="mt-1 w-full" :loading="form.processing">Reset password</Button>
    </form>
  </GuestLayout>
</template>
