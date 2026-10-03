<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';

import Button from '@/Components/ui/Button.vue';
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
  <GuestLayout title="Two-factor authentication" description="Enter the code from your authenticator app.">
    <Head title="Two-factor authentication" />

    <form class="grid gap-4" @submit.prevent="submit">
      <Field label="Authentication code" :error="form.errors.one_time_password">
        <Input
          id="one_time_password"
          v-model="form.one_time_password"
          type="text"
          inputmode="numeric"
          class="text-center font-mono tracking-[0.3em]"
          placeholder="000000"
          required
          autofocus
          autocomplete="one-time-code"
        />
      </Field>

      <Button class="mt-1 w-full" :loading="form.processing">Continue</Button>
    </form>

    <template #footer>
      <p class="mt-6 text-center text-[13px] text-muted">
        Not your account?
        <Link :href="route('login')" class="font-medium text-foreground hover:underline hover:underline-offset-4"
          >Back to sign in</Link
        >
      </p>
    </template>
  </GuestLayout>
</template>
