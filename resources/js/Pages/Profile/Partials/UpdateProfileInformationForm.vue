<script setup lang="ts">
import { Link, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

import Button from '@/Components/ui/Button.vue';
import Input from '@/Components/ui/Input.vue';
import SettingsGroup from '@/Components/ui/SettingsGroup.vue';
import SettingsRow from '@/Components/ui/SettingsRow.vue';
import { toast } from '@/lib/toast';
import type { PageProps } from '@/types';

defineProps<{
  mustVerifyEmail?: boolean;
  status?: string;
}>();

const page = usePage<PageProps>();
const user = computed(() => page.props.auth.user);

const form = useForm({
  name: user.value.name,
  email: user.value.email,
});

function save() {
  form.patch(route('profile.update'), {
    preserveScroll: true,
    onSuccess: () => {
      form.defaults();
      toast({ title: 'Profile updated', tone: 'success' });
    },
  });
}
</script>

<template>
  <form @submit.prevent="save">
    <SettingsGroup title="Personal information">
      <SettingsRow label="Name" for="name" :error="form.errors.name">
        <Input id="name" v-model="form.name" type="text" required autocomplete="name" />
      </SettingsRow>

      <SettingsRow
        label="Email"
        for="email"
        description="Used to sign in and for account notifications."
        :error="form.errors.email"
      >
        <Input id="email" v-model="form.email" type="email" required autocomplete="username" />
      </SettingsRow>

      <div
        v-if="mustVerifyEmail && user.email_verified_at === null"
        class="flex flex-col gap-1 px-4 py-3 text-[13px] sm:flex-row sm:items-center sm:justify-between sm:px-5"
      >
        <p v-if="status === 'verification-link-sent'" class="text-success">
          A new verification link has been sent to your email.
        </p>
        <p v-else class="text-warning">Your email address is not verified.</p>
        <Link
          :href="route('verification.send')"
          method="post"
          as="button"
          class="rounded-md text-left font-medium text-accent underline-offset-4 hover:underline focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent/40"
        >
          Resend verification email
        </Link>
      </div>

      <div class="flex justify-end px-4 py-3 sm:px-5">
        <Button size="sm" :loading="form.processing" :disabled="!form.isDirty">Save</Button>
      </div>
    </SettingsGroup>
  </form>
</template>
