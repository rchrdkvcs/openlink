<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { ArrowRight } from '@lucide/vue';

import PublicStateLayout from '@/Components/PublicState/PublicStateLayout.vue';
import StateArt from '@/Components/PublicState/StateArt.vue';
import Button from '@/Components/ui/Button.vue';
import PasswordInput from '@/Components/ui/PasswordInput.vue';

const props = defineProps<{
  shortLinkId: number;
  qrCodeId?: number | null;
  passwordUrl: string;
  error?: string | null;
}>();

const form = useForm({
  password: '',
  qr_code_id: props.qrCodeId ?? '',
});

function submit() {
  form.post(props.passwordUrl);
}
</script>

<template>
  <Head title="Protected link" />

  <PublicStateLayout
    title="Protected link"
    description="This link is password protected. Enter the visitor password to continue."
    :tone="error ? 'danger' : 'accent'"
  >
    <template #art>
      <StateArt scene="locked" :tone="error ? 'danger' : 'accent'" :alert="Boolean(error)" />
    </template>

    <form class="grid gap-3 text-left" @submit.prevent="submit">
      <label for="visitor-password" class="sr-only">Password</label>
      <PasswordInput
        id="visitor-password"
        v-model="form.password"
        size="lg"
        autofocus
        autocomplete="current-password"
        placeholder="Password"
        :aria-invalid="Boolean(error)"
        :aria-describedby="error ? 'visitor-password-error' : undefined"
      />
      <p v-if="error" id="visitor-password-error" role="alert" class="text-xs text-danger">{{ error }}</p>
      <Button class="mt-1 w-full" size="lg" :loading="form.processing">
        Continue
        <ArrowRight v-if="!form.processing" />
      </Button>
    </form>
  </PublicStateLayout>
</template>
