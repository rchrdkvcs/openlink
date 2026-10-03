<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';

import OAuthButtons from '@/Components/Auth/OAuthButtons.vue';
import Button from '@/Components/ui/Button.vue';
import Checkbox from '@/Components/ui/Checkbox.vue';
import Field from '@/Components/ui/Field.vue';
import Input from '@/Components/ui/Input.vue';
import GuestLayout from '@/Layouts/GuestLayout.vue';

defineProps<{
  canResetPassword?: boolean;
  oauthProviders: Record<string, boolean>;
  status?: string;
}>();

const form = useForm({
  email: '',
  password: '',
  remember: false,
});

const submit = () => {
  form.post(route('login'), {
    onFinish: () => {
      form.reset('password');
    },
  });
};
</script>

<template>
  <GuestLayout title="Sign in to Openlink" description="Welcome back. Enter your details to continue.">
    <Head title="Sign in" />

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

      <div class="grid gap-1.5">
        <div class="flex items-center justify-between">
          <label for="password" class="text-[13px] font-medium text-foreground">Password</label>
          <Link
            v-if="canResetPassword"
            :href="route('password.request')"
            class="text-xs text-muted transition-colors hover:text-foreground"
          >
            Forgot password?
          </Link>
        </div>
        <Input id="password" v-model="form.password" type="password" required autocomplete="current-password" />
        <p v-if="form.errors.password" class="text-xs text-danger">{{ form.errors.password }}</p>
      </div>

      <label class="flex w-fit cursor-pointer items-center gap-2">
        <Checkbox v-model="form.remember" name="remember" />
        <span class="text-[13px] text-muted">Keep me signed in</span>
      </label>

      <Button class="mt-1 w-full" :loading="form.processing">Sign in</Button>
    </form>

    <OAuthButtons class="mt-5" :providers="oauthProviders" intent="login" />

    <template #footer>
      <p class="mt-6 text-center text-[13px] text-muted">
        New to Openlink?
        <Link :href="route('register')" class="font-medium text-foreground hover:underline hover:underline-offset-4"
          >Create an account</Link
        >
      </p>
    </template>
  </GuestLayout>
</template>
