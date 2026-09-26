<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';

import PrimaryButton from '@/Components/PrimaryButton.vue';
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
  <GuestLayout>
    <Head title="Forgot your password" />

    <div class="mb-6">
      <h1 class="text-lg font-semibold text-foreground">Reset your password</h1>
      <p class="mt-1 text-sm text-muted">Enter your email address and we will send you a password reset link.</p>
    </div>

    <div v-if="status" class="mb-4 rounded-md border border-success/25 bg-success/10 px-3 py-2 text-sm text-success">
      {{ status }}
    </div>

    <form class="space-y-4" @submit.prevent="submit">
      <Field label="Email" :error="form.errors.email">
        <Input id="email" type="email" v-model="form.email" required autofocus autocomplete="username" />
      </Field>

      <PrimaryButton class="w-full" :disabled="form.processing">Email password reset link</PrimaryButton>
    </form>

    <template #footer>
      <p class="mt-6 text-center text-sm text-muted">
        Remembered it?
        <Link :href="route('login')" class="font-medium text-foreground underline-offset-4 hover:underline"
          >Back to log in</Link
        >
      </p>
    </template>
  </GuestLayout>
</template>
