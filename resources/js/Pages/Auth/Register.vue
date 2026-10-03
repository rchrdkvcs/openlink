<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';

import OAuthButtons from '@/Components/Auth/OAuthButtons.vue';
import Button from '@/Components/ui/Button.vue';
import Field from '@/Components/ui/Field.vue';
import Input from '@/Components/ui/Input.vue';
import GuestLayout from '@/Layouts/GuestLayout.vue';

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
  <GuestLayout title="Create your account" description="Shorten, share and measure links in minutes.">
    <Head title="Create an account" />

    <p
      v-if="invite"
      class="mb-5 rounded-lg border border-accent/25 bg-accent/10 px-3 py-2.5 text-[13px] leading-relaxed text-foreground"
    >
      You’re joining <span class="font-semibold">{{ invite.workspace }}</span> as
      <span class="font-semibold capitalize">{{ invite.role }}</span
      >.
    </p>

    <form class="grid gap-4" @submit.prevent="submit">
      <Field label="Name" :error="form.errors.name">
        <Input id="name" v-model="form.name" type="text" required autofocus autocomplete="name" />
      </Field>

      <Field label="Email" :error="form.errors.email">
        <Input id="email" v-model="form.email" type="email" required autocomplete="username" />
      </Field>

      <Field label="Password" :error="form.errors.password">
        <Input id="password" v-model="form.password" type="password" required autocomplete="new-password" />
      </Field>

      <Field label="Confirm password" :error="form.errors.password_confirmation">
        <Input
          id="password_confirmation"
          v-model="form.password_confirmation"
          type="password"
          required
          autocomplete="new-password"
        />
      </Field>

      <Button class="mt-1 w-full" :loading="form.processing">Create account</Button>
    </form>

    <OAuthButtons class="mt-5" :providers="oauthProviders" intent="register" :invite="invite?.token" />

    <template #footer>
      <p class="mt-6 text-center text-[13px] text-muted">
        Already have an account?
        <Link :href="route('login')" class="font-medium text-foreground hover:underline hover:underline-offset-4"
          >Sign in</Link
        >
      </p>
    </template>
  </GuestLayout>
</template>
