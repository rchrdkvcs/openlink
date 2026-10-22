<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

import Button from '@/Components/ui/Button.vue';
import Dialog from '@/Components/ui/Dialog.vue';
import Field from '@/Components/ui/Field.vue';
import Input from '@/Components/ui/Input.vue';

import { normalizePath } from './linkPageItems';

const props = defineProps<{ pathPrefix: string }>();

const open = defineModel<boolean>('open', { required: true });

const form = useForm({ name: '', path: '' });
const pathEdited = ref(false);

function onName(value: string | number | null | undefined) {
  form.name = String(value ?? '');
  if (!pathEdited.value) form.path = normalizePath(form.name).replace(/-+$/, '');
}

function onPath(value: string | number | null | undefined) {
  pathEdited.value = true;
  form.path = normalizePath(String(value ?? ''));
}

function close() {
  open.value = false;
  setTimeout(() => {
    form.reset();
    form.clearErrors();
    pathEdited.value = false;
  }, 200);
}

function onOpenChange(value: boolean) {
  if (value) open.value = true;
  else close();
}

function submit() {
  form.post(route('link-pages.store'), { preserveScroll: true, onSuccess: () => close() });
}
</script>

<template>
  <Dialog
    :open="open"
    title="New link page"
    description="Start as a draft. Nothing is public until you publish it."
    @update:open="onOpenChange"
  >
    <form @submit.prevent="submit">
      <div class="grid gap-5 px-5 pb-5 pt-4">
        <Field label="Name" :error="form.errors.name">
          <Input :model-value="form.name" placeholder="Spring conference" autofocus @update:model-value="onName" />
        </Field>
        <Field label="Path" :error="form.errors.path" :hint="`${props.pathPrefix}${form.path || 'your-path'}`">
          <Input
            :model-value="form.path"
            placeholder="spring-conference"
            spellcheck="false"
            autocapitalize="off"
            @update:model-value="onPath"
          />
        </Field>
      </div>

      <footer class="flex items-center justify-end gap-2 border-t px-5 py-3.5">
        <Button variant="ghost" type="button" @click="close">Cancel</Button>
        <Button :loading="form.processing">Create link page</Button>
      </footer>
    </form>
  </Dialog>
</template>
