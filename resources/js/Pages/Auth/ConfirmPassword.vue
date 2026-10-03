<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';

import Button from '@/Components/ui/Button.vue';
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
  <GuestLayout title="Confirm your password" description="This is a secure area. Confirm your password to continue.">
    <Head title="Confirm your password" />

    <form class="grid gap-4" @submit.prevent="submit">
      <Field label="Password" :error="form.errors.password">
        <Input
          id="password"
          v-model="form.password"
          type="password"
          required
          autocomplete="current-password"
          autofocus
        />
      </Field>

      <Button class="mt-1 w-full" :loading="form.processing">Confirm</Button>
    </form>
  </GuestLayout>
</template>
