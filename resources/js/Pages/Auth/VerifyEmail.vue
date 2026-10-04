<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { MailCheck } from '@lucide/vue';
import { computed } from 'vue';

import AuthIcon from '@/Components/Auth/AuthIcon.vue';
import AuthNotice from '@/Components/Auth/AuthNotice.vue';
import Button from '@/Components/ui/Button.vue';
import GuestLayout from '@/Layouts/GuestLayout.vue';

const props = defineProps<{
  status?: string;
}>();

const form = useForm<{ email?: string }>({});

const submit = () => {
  form.post(route('verification.send'));
};

const verificationLinkSent = computed(() => props.status === 'verification-link-sent');
</script>

<template>
  <GuestLayout
    title="Check your inbox"
    description="We sent you a verification link. Open it to activate your account."
  >
    <Head title="Verify your email" />

    <template #icon>
      <AuthIcon tone="accent"><MailCheck /></AuthIcon>
    </template>

    <AuthNotice v-if="verificationLinkSent" class="mb-6">A new verification link is on its way.</AuthNotice>

    <form class="grid gap-3" @submit.prevent="submit">
      <p v-if="form.errors.email" class="text-xs text-danger">{{ form.errors.email }}</p>
      <Button variant="secondary" size="lg" class="w-full" :loading="form.processing">Resend email</Button>
    </form>

    <template #footer>
      Wrong account?
      <Link
        :href="route('logout')"
        method="post"
        as="button"
        class="rounded-sm font-medium text-foreground underline-offset-4 hover:underline focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent/25"
      >
        Sign out
      </Link>
    </template>
  </GuestLayout>
</template>
