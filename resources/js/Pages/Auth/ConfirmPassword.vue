<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';

import PrimaryButton from '@/Components/PrimaryButton.vue';
import Field from '@/Components/ui/Field.vue';
import Input from '@/Components/ui/Input.vue';
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
  <GuestLayout>
    <Head title="Confirm your password" />

    <div class="mb-6">
      <h1 class="text-lg font-semibold text-foreground">Confirm your password</h1>
      <p class="mt-1 text-sm text-muted">
        This is a secure area of the application. Please confirm your password before continuing.
      </p>
    </div>

    <form class="space-y-4" @submit.prevent="submit">
      <Field label="Password" :error="form.errors.password">
        <Input
          id="password"
          type="password"
          v-model="form.password"
          required
          autocomplete="current-password"
          autofocus
        />
      </Field>

      <PrimaryButton class="w-full" :disabled="form.processing">Confirm</PrimaryButton>
    </form>
  </GuestLayout>
</template>
