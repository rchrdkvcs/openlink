<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

import PrimaryButton from '@/Components/PrimaryButton.vue';
import Field from '@/Components/ui/Field.vue';
import Input from '@/Components/ui/Input.vue';

const passwordInput = ref<InstanceType<typeof Input> | null>(null);
const currentPasswordInput = ref<InstanceType<typeof Input> | null>(null);

const form = useForm({
  current_password: '',
  password: '',
  password_confirmation: '',
});

const updatePassword = () => {
  form.put(route('password.update'), {
    preserveScroll: true,
    onSuccess: () => {
      form.reset();
    },
    onError: () => {
      if (form.errors.password) {
        form.reset('password', 'password_confirmation');
        passwordInput.value?.focus();
      }
      if (form.errors.current_password) {
        form.reset('current_password');
        currentPasswordInput.value?.focus();
      }
    },
  });
};
</script>

<template>
  <section>
    <header>
      <h2 class="text-base font-semibold text-foreground">Update Password</h2>

      <p class="mt-1 text-sm text-muted">Ensure your account is using a long, random password to stay secure.</p>
    </header>

    <form @submit.prevent="updatePassword" class="mt-6 space-y-5">
      <Field label="Current Password" :error="form.errors.current_password">
        <Input
          id="current_password"
          ref="currentPasswordInput"
          v-model="form.current_password"
          type="password"
          autocomplete="current-password"
        />
      </Field>

      <Field label="New Password" :error="form.errors.password">
        <Input id="password" ref="passwordInput" v-model="form.password" type="password" autocomplete="new-password" />
      </Field>

      <Field label="Confirm Password" :error="form.errors.password_confirmation">
        <Input
          id="password_confirmation"
          v-model="form.password_confirmation"
          type="password"
          autocomplete="new-password"
        />
      </Field>

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
