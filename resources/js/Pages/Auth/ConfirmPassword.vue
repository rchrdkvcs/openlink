<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';

import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
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
    <Head title="Confirm Password" />

    <div class="mb-6">
      <h1 class="text-lg font-semibold text-foreground">Confirm your password</h1>
      <p class="mt-1 text-sm text-muted">
        This is a secure area of the application. Please confirm your password before continuing.
      </p>
    </div>

    <form class="space-y-4" @submit.prevent="submit">
      <div>
        <InputLabel for="password" value="Password" />
        <TextInput
          id="password"
          type="password"
          class="mt-1.5 block w-full"
          v-model="form.password"
          required
          autocomplete="current-password"
          autofocus
        />
        <InputError class="mt-2" :message="form.errors.password" />
      </div>

      <PrimaryButton class="w-full" :disabled="form.processing">Confirm</PrimaryButton>
    </form>
  </GuestLayout>
</template>
