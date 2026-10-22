<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { computed, watch } from 'vue';

import Button from '@/Components/ui/Button.vue';
import Dialog from '@/Components/ui/Dialog.vue';
import Field from '@/Components/ui/Field.vue';
import Input from '@/Components/ui/Input.vue';
import { toast } from '@/lib/toast';
import { templateForm, UTM_FIELDS, type UtmTemplate } from '@/lib/utm';

const props = defineProps<{ template: UtmTemplate | null }>();
const open = defineModel<boolean>('open', { required: true });

const form = useForm(templateForm(null));
const parametersError = computed(() => (form.errors as Partial<Record<string, string>>).parameters);

watch(open, (value) => {
  if (!value) return;
  form.defaults(templateForm(props.template));
  form.reset();
  form.clearErrors();
});

function submit() {
  const options = {
    preserveScroll: true,
    onSuccess: () => {
      open.value = false;
      toast({ title: props.template ? 'Template updated' : 'Template created', tone: 'success' });
    },
  };

  if (props.template) form.patch(route('utm-templates.update', props.template.id), options);
  else form.post(route('utm-templates.store'), options);
}
</script>

<template>
  <Dialog
    v-model:open="open"
    size="lg"
    :title="template ? `Edit ${template.name}` : 'New UTM template'"
    description="Members apply templates to a link's destination. Changing a template later does not update links that already use its values."
  >
    <form class="space-y-4 px-5 pb-5 pt-4" @submit.prevent="submit">
      <Field label="Name" :error="form.errors.name">
        <Input v-model="form.name" placeholder="Weekly newsletter" />
      </Field>

      <div class="grid gap-3 sm:grid-cols-2">
        <Field v-for="field in UTM_FIELDS" :key="field.key" :label="field.label" :error="form.errors[field.key]">
          <Input v-model="form[field.key]" :placeholder="field.placeholder" spellcheck="false" />
        </Field>
      </div>

      <p class="text-xs" :class="parametersError ? 'text-danger' : 'text-faint'">
        {{
          parametersError ??
          'Leave a field empty to keep the link’s own value. Values are kept exactly as typed, so use consistent lowercase names.'
        }}
      </p>

      <div class="flex justify-end gap-2 pt-1">
        <Button variant="ghost" type="button" @click="open = false">Cancel</Button>
        <Button :loading="form.processing">{{ template ? 'Save template' : 'Create template' }}</Button>
      </div>
    </form>
  </Dialog>
</template>
