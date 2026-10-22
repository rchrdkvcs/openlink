<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { computed, watch } from 'vue';

import Button from '@/Components/ui/Button.vue';
import Dialog from '@/Components/ui/Dialog.vue';
import Field from '@/Components/ui/Field.vue';
import Input from '@/Components/ui/Input.vue';
import { toast } from '@/lib/toast';

import type { QrCodeTemplate } from '../types';

const props = defineProps<{
  qrToken: string;
  template: QrCodeTemplate | null;
}>();

const open = defineModel<boolean>('open', { required: true });

const form = useForm({ name: '', qr_code: '' });

const renaming = computed(() => props.template !== null);

watch(open, (value) => {
  if (!value) return;
  form.name = props.template?.name ?? '';
  form.qr_code = props.qrToken;
  form.clearErrors();
});

function submit() {
  const options = {
    preserveScroll: true,
    preserveState: true,
    onSuccess: () => {
      open.value = false;
      toast({
        title: renaming.value ? 'Template renamed' : 'Template saved',
        tone: 'success',
      });
    },
  };

  if (props.template) {
    form.transform(({ name }) => ({ name })).patch(route('qr-code-templates.update', props.template.id), options);
  } else {
    form.transform((data) => data).post(route('qr-code-templates.store'), options);
  }
}
</script>

<template>
  <Dialog
    v-model:open="open"
    size="sm"
    :title="renaming ? 'Rename template' : 'Save as template'"
    :description="
      renaming
        ? undefined
        : 'Saves this QR code’s colors, styles, margin, error correction and logo. Content and name stay with the QR code.'
    "
  >
    <form @submit.prevent="submit">
      <div class="px-5 pb-5 pt-4">
        <Field label="Template name" :error="form.errors.name || form.errors.qr_code">
          <Input v-model="form.name" maxlength="80" placeholder="Brand, event print, dark mode" />
        </Field>
      </div>
      <footer class="flex items-center justify-end gap-2 border-t px-5 py-3.5">
        <Button variant="ghost" type="button" @click="open = false">Cancel</Button>
        <Button :loading="form.processing">{{ renaming ? 'Rename' : 'Save template' }}</Button>
      </footer>
    </form>
  </Dialog>
</template>
