<script setup lang="ts">
import { Link, useForm, usePage } from '@inertiajs/vue3';

import PrimaryButton from '@/Components/PrimaryButton.vue';
import Field from '@/Components/ui/Field.vue';
import Input from '@/Components/ui/Input.vue';
import type { PageProps } from '@/types';

defineProps<{
  mustVerifyEmail?: boolean;
  status?: string;
}>();

const user = usePage<PageProps>().props.auth.user;

const form = useForm({
  name: user.name,
  email: user.email,
});
</script>

<template>
  <section>
    <header>
      <h2 class="text-base font-semibold text-foreground">Profile Information</h2>

      <p class="mt-1 text-sm text-muted">Update your account's profile information and email address.</p>
    </header>

    <form @submit.prevent="form.patch(route('profile.update'))" class="mt-6 space-y-5">
      <Field label="Name" :error="form.errors.name">
        <Input id="name" type="text" v-model="form.name" required autofocus autocomplete="name" />
      </Field>

      <Field label="Email" :error="form.errors.email">
        <Input id="email" type="email" v-model="form.email" required autocomplete="username" />
      </Field>

      <div v-if="mustVerifyEmail && user.email_verified_at === null">
        <p class="mt-2 text-sm text-muted">
          Your email address is unverified.
          <Link
            :href="route('verification.send')"
            method="post"
            as="button"
            class="rounded-md text-sm text-accent underline-offset-4 hover:underline focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent/40"
          >
            Click here to re-send the verification email.
          </Link>
        </p>

        <div v-show="status === 'verification-link-sent'" class="mt-2 text-sm font-medium text-success">
          A new verification link has been sent to your email address.
        </div>
      </div>

      <div class="flex items-center gap-4">
        <PrimaryButton :disabled="form.processing">Save</PrimaryButton>

        <Transition
          enter-active-class="transition ease-in-out"
          enter-from-class="opacity-0"
          leave-active-class="transition ease-in-out"
          leave-to-class="opacity-0"
        >
          <p v-if="form.recentlySuccessful" class="text-sm text-success">Saved.</p>
        </Transition>
      </div>
    </form>
  </section>
</template>
