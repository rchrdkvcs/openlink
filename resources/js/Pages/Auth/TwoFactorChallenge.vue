<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';

import PrimaryButton from '@/Components/PrimaryButton.vue';
import Field from '@/Components/ui/Field.vue';
import Input from '@/Components/ui/Input.vue';
import GuestLayout from '@/Layouts/GuestLayout.vue';

const form = useForm({
  one_time_password: '',
});

const submit = () => {
  form.post(route('login.two-factor'), {
    onFinish: () => form.reset('one_time_password'),
  });
};
</script>

<template>
  <GuestLayout>
    <Head title="Two-factor authentication" />

    <div class="mb-6">
      <h1 class="text-lg font-semibold text-foreground">Two-factor authentication</h1>
      <p class="mt-1 text-sm text-muted">Enter the code from your authenticator app to finish signing in.</p>
    </div>

    <form class="space-y-4" @submit.prevent="submit">
      <Field label="Authentication code" :error="form.errors.one_time_password">
        <Input
          id="one_time_password"
          type="text"
          inputmode="numeric"
          v-model="form.one_time_password"
          required
          autofocus
          autocomplete="one-time-code"
        />
      </Field>

      <PrimaryButton class="w-full" :disabled="form.processing">Continue</PrimaryButton>
    </form>

    <template #footer>
      <p class="mt-6 text-center text-sm text-muted">
        Not your account?
        <Link :href="route('login')" class="font-medium text-foreground underline-offset-4 hover:underline"
          >Back to login</Link
        >
      </p>
    </template>
  </GuestLayout>
</template>
