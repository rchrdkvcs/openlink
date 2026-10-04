<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { KeyRound } from '@lucide/vue';

import AuthIcon from '@/Components/Auth/AuthIcon.vue';
import AuthLink from '@/Components/Auth/AuthLink.vue';
import AuthNotice from '@/Components/Auth/AuthNotice.vue';
import Button from '@/Components/ui/Button.vue';
import Field from '@/Components/ui/Field.vue';
import Input from '@/Components/ui/Input.vue';
import GuestLayout from '@/Layouts/GuestLayout.vue';

defineProps<{
  status?: string;
}>();

const form = useForm({
  email: '',
});

const submit = () => {
  form.post(route('password.email'));
};
</script>

<template>
  <GuestLayout
    title="Reset your password"
    description="Enter your email and we’ll send you a link to choose a new one."
  >
    <Head title="Reset your password" />

    <template #icon>
      <AuthIcon><KeyRound /></AuthIcon>
    </template>

    <AuthNotice v-if="status" class="mb-6">{{ status }}</AuthNotice>

    <form class="grid gap-5" @submit.prevent="submit">
      <Field label="Email" :error="form.errors.email">
        <Input
          id="email"
          v-model="form.email"
          size="lg"
          type="email"
          placeholder="you@company.com"
          required
          autofocus
          autocomplete="username"
          :aria-invalid="Boolean(form.errors.email)"
        />
      </Field>

      <Button class="mt-1 w-full" size="lg" :loading="form.processing">Send reset link</Button>
    </form>

    <template #footer> Remembered it? <AuthLink :href="route('login')">Back to sign in</AuthLink> </template>
  </GuestLayout>
</template>
