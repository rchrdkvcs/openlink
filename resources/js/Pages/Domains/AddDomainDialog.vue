<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { watch } from 'vue';

import Button from '@/Components/ui/Button.vue';
import Dialog from '@/Components/ui/Dialog.vue';
import Field from '@/Components/ui/Field.vue';
import Input from '@/Components/ui/Input.vue';

const open = defineModel<boolean>('open', { default: false });

const form = useForm({ hostname: '' });

watch(open, (value) => {
  if (!value) return;
  form.reset();
  form.clearErrors();
});

function submit() {
  form.post(route('domains.store'), {
    onSuccess: () => {
      open.value = false;
    },
  });
}
</script>

<template>
  <Dialog
    v-model:open="open"
    title="Add a domain"
    description="Use a domain or subdomain you own, like go.yourcompany.com. You will add DNS records next."
  >
    <form class="px-5 pb-5 pt-4" @submit.prevent="submit">
      <Field label="Hostname" :error="form.errors.hostname">
        <Input
          v-model="form.hostname"
          placeholder="go.example.com"
          autocomplete="off"
          spellcheck="false"
          autofocus
          required
        />
      </Field>
      <div class="mt-5 flex justify-end gap-2">
        <Button variant="secondary" type="button" @click="open = false">Cancel</Button>
        <Button :loading="form.processing" :disabled="!form.hostname.trim()">Continue</Button>
      </div>
    </form>
  </Dialog>
</template>
