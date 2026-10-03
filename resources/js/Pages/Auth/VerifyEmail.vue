<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { MailCheck } from '@lucide/vue';
import { computed } from 'vue';

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
  <GuestLayout>
    <Head title="Verify your email" />

    <div class="mb-6 text-center">
      <span class="mx-auto mb-4 grid h-11 w-11 place-items-center rounded-xl border bg-elevated text-muted">
        <MailCheck class="h-5 w-5" />
      </span>
      <h1 class="text-[22px] font-semibold tracking-[-0.015em] text-foreground">Check your inbox</h1>
      <p class="mt-1.5 text-sm leading-relaxed text-muted">
        We sent you a verification link. Open it to activate your account.
      </p>
    </div>

    <p
      v-if="verificationLinkSent"
      role="status"
      class="mb-5 rounded-lg border border-success/25 bg-success/10 px-3 py-2 text-[13px] text-success"
    >
      A new verification link is on its way.
    </p>

    <form class="grid gap-3" @submit.prevent="submit">
      <p v-if="form.errors.email" class="text-xs text-danger">{{ form.errors.email }}</p>
      <Button variant="secondary" class="w-full" :loading="form.processing">Resend email</Button>
    </form>

    <template #footer>
      <p class="mt-6 text-center text-[13px] text-muted">
        <Link
          :href="route('logout')"
          method="post"
          as="button"
          class="font-medium text-foreground hover:underline hover:underline-offset-4"
        >
          Sign out
        </Link>
      </p>
    </template>
  </GuestLayout>
</template>
