<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { Loader2, Trash2 } from '@lucide/vue';
import { ref, watch } from 'vue';

import Modal from '@/Components/Modal.vue';
import Button from '@/Components/ui/Button.vue';
import Field from '@/Components/ui/Field.vue';
import WorkspaceAvatar from '@/Components/WorkspaceAvatar.vue';
import WorkspaceColorPicker from '@/Components/Workspaces/WorkspaceColorPicker.vue';
import WorkspaceIconPicker from '@/Components/Workspaces/WorkspaceIconPicker.vue';
import { fetchJson } from '@/lib/http';

type ManagePayload = {
  id: number;
  name: string;
  icon: string | null;
  color: string | null;
  preferred_domain_id: number | null;
  role: string;
  can_delete: boolean;
  domains: { id: number; hostname: string }[];
};

const props = defineProps<{
  show: boolean;
  workspaceId: number | null;
}>();

const emit = defineEmits<{ close: [] }>();

const loading = ref(false);
const loadError = ref(false);
const manage = ref<ManagePayload | null>(null);
const confirmingDelete = ref(false);

const form = useForm({
  name: '',
  icon: '',
  color: '',
  preferred_domain_id: '' as number | '',
});
const deleteForm = useForm({});

watch(
  () => [props.show, props.workspaceId] as const,
  async ([show, workspaceId]) => {
    if (!show || !workspaceId) {
      return;
    }

    manage.value = null;
    loadError.value = false;
    confirmingDelete.value = false;
    form.clearErrors();
    loading.value = true;

    try {
      const payload = await fetchJson<ManagePayload>(route('workspaces.manage', workspaceId));
      manage.value = payload;
      form.name = payload.name;
      form.icon = payload.icon ?? '';
      form.color = payload.color ?? '';
      form.preferred_domain_id = payload.preferred_domain_id ?? '';
    } catch {
      loadError.value = true;
    } finally {
      loading.value = false;
    }
  },
);

function submit() {
  if (!props.workspaceId) return;

  form
    .transform((data) => ({
      name: data.name,
      icon: data.icon || null,
      color: data.color || null,
      preferred_domain_id: data.preferred_domain_id || null,
    }))
    .patch(route('workspaces.update', props.workspaceId), { preserveScroll: true });
}

function destroy() {
  if (!props.workspaceId) return;

  deleteForm.delete(route('workspaces.destroy', props.workspaceId), {
    preserveScroll: true,
    onSuccess: () => emit('close'),
  });
}
</script>

<template>
  <Modal :show="show" max-width="lg" @close="emit('close')">
    <div class="p-6">
      <div class="flex items-start gap-3">
        <WorkspaceIconPicker
          v-if="manage"
          v-model:icon="form.icon"
          :name="form.name || manage.name"
          :color="form.color"
        />
        <WorkspaceAvatar v-else size="lg" />
        <div class="min-w-0">
          <h2 class="truncate text-base font-semibold text-foreground">Workspace settings</h2>
          <p class="mt-0.5 text-sm text-muted">
            Name, appearance, and defaults for {{ manage?.name ?? 'this workspace' }}. Click the icon to change it.
          </p>
        </div>
      </div>

      <div v-if="loading" class="grid place-items-center py-12 text-muted">
        <Loader2 class="h-5 w-5 animate-spin" />
      </div>

      <p
        v-else-if="loadError"
        class="mt-6 rounded-lg border border-danger/40 bg-danger/10 px-4 py-3 text-sm text-danger"
      >
        Could not load workspace settings. Close and try again.
      </p>

      <template v-else-if="manage">
        <form class="mt-6 grid gap-5" @submit.prevent="submit">
          <Field label="Name" :error="form.errors.name">
            <input v-model="form.name" class="h-9" placeholder="Acme Events" />
          </Field>

          <WorkspaceColorPicker v-model:color="form.color" :name="form.name" />
          <p v-if="form.errors.icon || form.errors.color" class="text-xs text-danger">
            {{ form.errors.icon ?? form.errors.color }}
          </p>

          <Field
            label="Preferred domain"
            hint="Used as the default domain when creating new short links."
            :error="form.errors.preferred_domain_id"
          >
            <select v-model="form.preferred_domain_id" class="h-9">
              <option value="">No preferred domain</option>
              <option v-for="domain in manage.domains" :key="domain.id" :value="domain.id">
                {{ domain.hostname }}
              </option>
            </select>
          </Field>

          <div class="flex items-center gap-3">
            <Button :loading="form.processing">Save changes</Button>
            <Transition
              enter-active-class="transition ease-in-out"
              enter-from-class="opacity-0"
              leave-active-class="transition ease-in-out"
              leave-to-class="opacity-0"
            >
              <p v-if="form.recentlySuccessful" class="text-[13px] text-success">Saved.</p>
            </Transition>
          </div>
        </form>

        <div v-if="manage.role === 'owner'" class="mt-6 border-t pt-5">
          <template v-if="!confirmingDelete">
            <div class="flex flex-wrap items-center justify-between gap-3">
              <div>
                <p class="text-sm font-medium text-foreground">Delete workspace</p>
                <p class="mt-0.5 text-[13px] text-muted">
                  {{
                    manage.can_delete
                      ? 'Permanently removes this workspace and everything inside it.'
                      : 'You must have another workspace before deleting this one.'
                  }}
                </p>
              </div>
              <Button variant="danger" type="button" :disabled="!manage.can_delete" @click="confirmingDelete = true">
                <Trash2 class="h-4 w-4" /> Delete
              </Button>
            </div>
          </template>
          <template v-else>
            <p class="text-sm font-medium text-foreground">Delete {{ manage.name }}?</p>
            <p class="mt-1 text-[13px] text-muted">
              Links, domains, folders, members, and analytics in this workspace will be permanently deleted. This cannot
              be undone.
            </p>
            <div class="mt-3 flex justify-end gap-3">
              <Button variant="secondary" type="button" @click="confirmingDelete = false">Cancel</Button>
              <Button variant="danger" type="button" :loading="deleteForm.processing" @click="destroy">
                <Trash2 class="h-4 w-4" /> Delete workspace
              </Button>
            </div>
          </template>
        </div>
      </template>
    </div>
  </Modal>
</template>
