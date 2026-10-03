<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';

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

    <p
      v-if="status"
      role="status"
      class="mb-5 rounded-lg border border-success/25 bg-success/10 px-3 py-2 text-[13px] text-success"
    >
      {{ status }}
    </p>

    <form class="grid gap-4" @submit.prevent="submit">
      <Field label="Email" :error="form.errors.email">
        <Input id="email" v-model="form.email" type="email" required autofocus autocomplete="username" />
      </Field>

      <Button class="mt-1 w-full" :loading="form.processing">Send reset link</Button>
    </form>

    <template #footer>
      <p class="mt-6 text-center text-[13px] text-muted">
        Remembered it?
        <Link :href="route('login')" class="font-medium text-foreground hover:underline hover:underline-offset-4"
          >Back to sign in</Link
        >
      </p>
    </template>
  </GuestLayout>
</template>
