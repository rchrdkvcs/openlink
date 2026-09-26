<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';

import OAuthButtons from '@/Components/Auth/OAuthButtons.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
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
  <GuestLayout>
    <Head title="Log in" />

    <div class="mb-6">
      <h1 class="text-lg font-semibold text-foreground">Welcome back</h1>
      <p class="mt-1 text-sm text-muted">Sign in to your account to continue.</p>
    </div>

    <div v-if="status" class="mb-4 rounded-md border border-success/25 bg-success/10 px-3 py-2 text-sm text-success">
      {{ status }}
    </div>

    <form class="space-y-4" @submit.prevent="submit">
      <Field label="Email" :error="form.errors.email">
        <Input id="email" type="email" v-model="form.email" required autofocus autocomplete="username" />
      </Field>

      <div class="grid gap-1.5">
        <div class="flex items-center justify-between">
          <label for="password" class="text-[13px] font-medium text-foreground">Password</label>
          <Link
            v-if="canResetPassword"
            :href="route('password.request')"
            class="text-xs text-muted underline-offset-4 transition-colors hover:text-foreground hover:underline"
          >
            Forgot password?
          </Link>
        </div>

        <Input id="password" type="password" v-model="form.password" required autocomplete="current-password" />

        <p v-if="form.errors.password" class="text-xs text-danger">{{ form.errors.password }}</p>
      </div>

      <label class="flex items-center gap-2">
        <Checkbox name="remember" v-model="form.remember" />
        <span class="text-sm text-muted">Remember me</span>
      </label>

      <PrimaryButton class="w-full" :disabled="form.processing">Log in</PrimaryButton>
    </form>

    <OAuthButtons class="mt-5" :providers="oauthProviders" intent="login" />

    <template #footer>
      <p class="mt-6 text-center text-sm text-muted">
        No account?
        <Link :href="route('register')" class="font-medium text-foreground underline-offset-4 hover:underline"
          >Register</Link
        >
      </p>
    </template>
  </GuestLayout>
</template>
