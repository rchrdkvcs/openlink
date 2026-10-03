<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { computed, watch } from 'vue';

import Button from '@/Components/ui/Button.vue';
import Dialog from '@/Components/ui/Dialog.vue';
import Field from '@/Components/ui/Field.vue';
import Select from '@/Components/ui/Select.vue';
import type { WorkspaceOption } from '@/lib/domains';
import { toast } from '@/lib/toast';
import type { Domain } from '@/types/payloads';

const props = defineProps<{ options: WorkspaceOption[] }>();

const domain = defineModel<Domain | null>('domain', { default: null });

const form = useForm({ workspace_id: '' as number | '' });

const open = computed({
  get: () => domain.value !== null,
  set: (value: boolean) => {
    if (!value) domain.value = null;
  },
});

watch(domain, (value) => {
  if (!value) return;
  form.reset();
  form.clearErrors();
});

function submit() {
  const target = domain.value;
  if (!target) return;
  const workspace = props.options.find((option) => option.value === form.workspace_id);

  form.post(route('domains.transfer', target.id), {
    preserveScroll: true,
    onSuccess: () => {
      domain.value = null;
      form.reset();
      toast({ title: `${target.hostname} transferred`, description: workspace?.label, tone: 'success' });
    },
  });
}
</script>

<template>
  <Dialog
    v-model:open="open"
    size="sm"
    :title="domain ? `Transfer ${domain.hostname}` : 'Transfer domain'"
    description="Only domains without links can be moved to another workspace you manage."
  >
    <form class="px-5 pb-5 pt-4" @submit.prevent="submit">
      <Field label="Workspace" :error="form.errors.workspace_id">
        <Select
          v-model="form.workspace_id"
          :options="options"
          placeholder="Choose a workspace"
          aria-label="Workspace"
        />
      </Field>
      <div class="mt-5 flex justify-end gap-2">
        <Button variant="secondary" type="button" @click="open = false">Cancel</Button>
        <Button :loading="form.processing" :disabled="!form.workspace_id">Transfer</Button>
      </div>
    </form>
  </Dialog>
</template>
