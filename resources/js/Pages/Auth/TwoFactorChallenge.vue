<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { ShieldCheck } from '@lucide/vue';

import AuthIcon from '@/Components/Auth/AuthIcon.vue';
import AuthLink from '@/Components/Auth/AuthLink.vue';
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
  <GuestLayout title="Two-factor authentication" description="Enter the 6-digit code from your authenticator app.">
    <Head title="Two-factor authentication" />

    <template #icon>
      <AuthIcon tone="accent"><ShieldCheck /></AuthIcon>
    </template>

    <form class="grid gap-5" @submit.prevent="submit">
      <Field label="Authentication code" :error="form.errors.one_time_password">
        <Input
          id="one_time_password"
          v-model="form.one_time_password"
          size="lg"
          type="text"
          inputmode="numeric"
          class="h-12 text-center font-mono text-lg tracking-[0.4em]"
          placeholder="000000"
          maxlength="6"
          required
          autofocus
          autocomplete="one-time-code"
          :aria-invalid="Boolean(form.errors.one_time_password)"
        />
      </Field>

      <Button class="mt-1 w-full" size="lg" :loading="form.processing">Verify</Button>
    </form>

    <template #footer> Not your account? <AuthLink :href="route('login')">Back to sign in</AuthLink> </template>
  </GuestLayout>
</template>
