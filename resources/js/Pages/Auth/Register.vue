<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { ArrowRight } from '@lucide/vue';

import AuthLink from '@/Components/Auth/AuthLink.vue';
import AuthNotice from '@/Components/Auth/AuthNotice.vue';
import OAuthButtons from '@/Components/Auth/OAuthButtons.vue';
import Button from '@/Components/ui/Button.vue';
import Field from '@/Components/ui/Field.vue';
import Input from '@/Components/ui/Input.vue';
import PasswordInput from '@/Components/ui/PasswordInput.vue';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import { roleLabel } from '@/lib/permissions';

const props = defineProps<{
  invite?: {
    token: string;
    workspace: string;
    role: string;
  } | null;
  oauthProviders: Record<string, boolean>;
}>();

const form = useForm({
  name: '',
  email: '',
  password: '',
  password_confirmation: '',
  invite_token: props.invite?.token ?? '',
});

const submit = () => {
  form.post(route('register'), {
    onFinish: () => {
      form.reset('password', 'password_confirmation');
    },
  });
};
</script>

<template>
  <GuestLayout
    :title="invite ? `Join ${invite.workspace}` : 'Create your account'"
    description="Shorten, share and measure links in minutes."
  >
    <Head title="Create an account" />

    <AuthNotice v-if="invite" tone="accent" class="mb-6">
      You’re joining <span class="font-semibold">{{ invite.workspace }}</span> as
      <span class="font-semibold">{{ roleLabel(invite.role) }}</span
      >.
    </AuthNotice>

    <OAuthButtons class="mb-6" :providers="oauthProviders" intent="register" :invite="invite?.token" />

    <form class="grid gap-5" @submit.prevent="submit">
      <Field label="Name" :error="form.errors.name">
        <Input
          id="name"
          v-model="form.name"
          size="lg"
          type="text"
          placeholder="Ada Lovelace"
          required
          autofocus
          autocomplete="name"
          :aria-invalid="Boolean(form.errors.name)"
        />
      </Field>

      <Field label="Email" :error="form.errors.email">
        <Input
          id="email"
          v-model="form.email"
          size="lg"
          type="email"
          placeholder="you@company.com"
          required
          autocomplete="username"
          :aria-invalid="Boolean(form.errors.email)"
        />
      </Field>

      <Field label="Password" hint="At least 8 characters." :error="form.errors.password">
        <PasswordInput
          id="password"
          v-model="form.password"
          size="lg"
          required
          :aria-invalid="Boolean(form.errors.password)"
        />
      </Field>

      <Field label="Confirm password" :error="form.errors.password_confirmation">
        <PasswordInput
          id="password_confirmation"
          v-model="form.password_confirmation"
          size="lg"
          required
          :aria-invalid="Boolean(form.errors.password_confirmation)"
        />
      </Field>

      <Button class="mt-1 w-full" size="lg" :loading="form.processing">
        Create account
        <ArrowRight v-if="!form.processing" />
      </Button>
    </form>

    <template #footer> Already have an account? <AuthLink :href="route('login')">Sign in</AuthLink> </template>
  </GuestLayout>
</template>
