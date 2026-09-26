<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';

import OAuthButtons from '@/Components/Auth/OAuthButtons.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
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
  <GuestLayout>
    <Head title="Create an account" />

    <div class="mb-6">
      <h1 class="text-lg font-semibold text-foreground">Create your account</h1>
      <p class="mt-1 text-sm text-muted">Start managing short links in minutes.</p>
    </div>

    <div v-if="invite" class="mb-5 rounded-md border border-accent/25 bg-accent/10 px-3 py-2.5 text-sm text-foreground">
      You are joining <strong>{{ invite.workspace }}</strong> as <strong class="capitalize">{{ invite.role }}</strong
      >.
    </div>

    <form class="space-y-4" @submit.prevent="submit">
      <Field label="Name" :error="form.errors.name">
        <Input id="name" type="text" v-model="form.name" required autofocus autocomplete="name" />
      </Field>

      <Field label="Email" :error="form.errors.email">
        <Input id="email" type="email" v-model="form.email" required autocomplete="username" />
      </Field>

      <Field label="Password" :error="form.errors.password">
        <Input id="password" type="password" v-model="form.password" required autocomplete="new-password" />
      </Field>

      <Field label="Confirm Password" :error="form.errors.password_confirmation">
        <Input
          id="password_confirmation"
          type="password"
          v-model="form.password_confirmation"
          required
          autocomplete="new-password"
        />
      </Field>

      <PrimaryButton class="w-full" :disabled="form.processing">Register</PrimaryButton>
    </form>

    <OAuthButtons class="mt-5" :providers="oauthProviders" intent="register" :invite="invite?.token" />

    <template #footer>
      <p class="mt-6 text-center text-sm text-muted">
        Already registered?
        <Link :href="route('login')" class="font-medium text-foreground underline-offset-4 hover:underline"
          >Log in</Link
        >
      </p>
    </template>
  </GuestLayout>
</template>
