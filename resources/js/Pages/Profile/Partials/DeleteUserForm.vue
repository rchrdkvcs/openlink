<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { nextTick, ref } from 'vue';

import DangerButton from '@/Components/DangerButton.vue';
import Modal from '@/Components/Modal.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import Input from '@/Components/ui/Input.vue';

const confirmingUserDeletion = ref(false);
const passwordInput = ref<InstanceType<typeof Input> | null>(null);

const form = useForm({
  password: '',
});

const confirmUserDeletion = () => {
  confirmingUserDeletion.value = true;

  nextTick(() => passwordInput.value?.focus());
};

const deleteUser = () => {
  form.delete(route('profile.destroy'), {
    preserveScroll: true,
    onSuccess: () => closeModal(),
    onError: () => passwordInput.value?.focus(),
    onFinish: () => {
      form.reset();
    },
  });
};

const closeModal = () => {
  confirmingUserDeletion.value = false;

  form.clearErrors();
  form.reset();
};
</script>

<template>
  <section class="space-y-6">
    <header>
      <h2 class="text-base font-semibold text-danger">Delete Account</h2>

      <p class="mt-1 text-sm text-muted">
        Once your account is deleted, all of its resources and data will be permanently deleted. Before deleting your
        account, please download any data or information that you wish to retain.
      </p>
    </header>

    <DangerButton @click="confirmUserDeletion">Delete Account</DangerButton>

    <Modal :show="confirmingUserDeletion" @close="closeModal">
      <div class="p-6">
        <h2 class="text-base font-semibold text-foreground">Are you sure you want to delete your account?</h2>

        <p class="mt-1 text-sm text-muted">
          Once your account is deleted, all of its resources and data will be permanently deleted. Please enter your
          password to confirm you would like to permanently delete your account.
        </p>

        <div class="mt-6 grid gap-1.5">
          <label for="password" class="sr-only">Password</label>

          <Input
            id="password"
            ref="passwordInput"
            v-model="form.password"
            type="password"
            class="w-3/4"
            placeholder="Password"
            @keyup.enter="deleteUser"
          />

          <p v-if="form.errors.password" class="text-xs text-danger">{{ form.errors.password }}</p>
        </div>

        <div class="mt-6 flex justify-end gap-3">
          <SecondaryButton @click="closeModal">Cancel</SecondaryButton>

          <DangerButton :disabled="form.processing" @click="deleteUser">Delete Account</DangerButton>
        </div>
      </div>
    </Modal>
  </section>
</template>
