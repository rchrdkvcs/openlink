<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowRight } from '@lucide/vue';

import AuthLink from '@/Components/Auth/AuthLink.vue';
import AuthNotice from '@/Components/Auth/AuthNotice.vue';
import OAuthButtons from '@/Components/Auth/OAuthButtons.vue';
import Button from '@/Components/ui/Button.vue';
import Checkbox from '@/Components/ui/Checkbox.vue';
import Field from '@/Components/ui/Field.vue';
import Input from '@/Components/ui/Input.vue';
import PasswordInput from '@/Components/ui/PasswordInput.vue';
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
  <GuestLayout title="Welcome back" description="Sign in to your Openlink workspace.">
    <Head title="Sign in" />

    <AuthNotice v-if="status" class="mb-6">{{ status }}</AuthNotice>

    <OAuthButtons class="mb-6" :providers="oauthProviders" intent="login" />

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

      <div class="grid gap-1.5">
        <div class="flex items-center justify-between">
          <label for="password" class="text-[13px] font-medium text-foreground">Password</label>
          <Link
            v-if="canResetPassword"
            :href="route('password.request')"
            class="rounded-sm text-xs text-muted transition-colors hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent/25"
          >
            Forgot password?
          </Link>
        </div>
        <PasswordInput
          id="password"
          v-model="form.password"
          size="lg"
          required
          autocomplete="current-password"
          :aria-invalid="Boolean(form.errors.password)"
        />
        <p v-if="form.errors.password" class="text-xs text-danger">{{ form.errors.password }}</p>
      </div>

      <label class="flex w-fit cursor-pointer items-center gap-2">
        <Checkbox v-model="form.remember" name="remember" />
        <span class="text-[13px] text-muted">Keep me signed in</span>
      </label>

      <Button class="mt-1 w-full" size="lg" :loading="form.processing">
        Sign in
        <ArrowRight v-if="!form.processing" />
      </Button>
    </form>

    <template #footer> New to Openlink? <AuthLink :href="route('register')">Create an account</AuthLink> </template>
  </GuestLayout>
</template>
