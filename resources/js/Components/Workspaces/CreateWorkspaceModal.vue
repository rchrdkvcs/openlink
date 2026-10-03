<script setup lang="ts">
import { useForm, usePage } from '@inertiajs/vue3';
import { Copy, Link2 } from '@lucide/vue';
import { computed, ref } from 'vue';

import Button from '@/Components/ui/Button.vue';
import Dialog from '@/Components/ui/Dialog.vue';
import Field from '@/Components/ui/Field.vue';
import IconButton from '@/Components/ui/IconButton.vue';
import Input from '@/Components/ui/Input.vue';
import Select from '@/Components/ui/Select.vue';
import WorkspaceIconPicker from '@/Components/Workspaces/WorkspaceIconPicker.vue';
import { fetchJson, HttpError } from '@/lib/http';
import { copyToClipboard } from '@/lib/toast';

defineProps<{ show: boolean }>();

const emit = defineEmits<{ close: [] }>();

const page = usePage();
const step = ref<'details' | 'invite'>('details');
const createdWorkspace = ref<{ id: number; name: string } | null>(null);

const form = useForm({ name: '', icon: '' });

const roleOptions = [
  { value: 'admin', label: 'Admin' },
  { value: 'editor', label: 'Editor' },
  { value: 'viewer', label: 'Viewer' },
];

const inviteRole = ref('editor');
const inviteUrl = ref<string | null>(null);
const inviteError = ref<string | null>(null);
const generating = ref(false);

const title = computed(() =>
  step.value === 'details' ? 'New workspace' : `Invite people to ${createdWorkspace.value?.name ?? 'your workspace'}`,
);

const description = computed(() =>
  step.value === 'details'
    ? 'Keep links, domains, folders and members separate.'
    : 'Anyone with the link joins with the role you choose. You can also do this later.',
);

function submit() {
  form
    .transform((data) => ({
      name: data.name,
      icon: data.icon || null,
    }))
    .post(route('workspaces.store'), {
      preserveScroll: true,
      onSuccess: () => {
        createdWorkspace.value = (page.props.currentWorkspace as { id: number; name: string } | undefined) ?? null;
        step.value = 'invite';
      },
    });
}

async function generateInvite() {
  if (!createdWorkspace.value) return;

  generating.value = true;
  inviteError.value = null;

  try {
    const payload = await fetchJson<{ url: string }>(route('invite-links.store'), {
      method: 'POST',
      headers: { 'X-Workspace-Id': String(createdWorkspace.value.id) },
      body: JSON.stringify({ role: inviteRole.value }),
    });
    inviteUrl.value = payload.url;
  } catch (error) {
    inviteError.value =
      error instanceof HttpError && error.status === 422
        ? 'Choose a valid role.'
        : 'Couldn’t create an invite link. You can create one later in Settings.';
  } finally {
    generating.value = false;
  }
}

function copyInviteUrl() {
  if (inviteUrl.value) copyToClipboard(inviteUrl.value, 'Invite link copied');
}

function close() {
  emit('close');

  setTimeout(() => {
    step.value = 'details';
    createdWorkspace.value = null;
    form.reset();
    form.clearErrors();
    inviteRole.value = 'editor';
    inviteUrl.value = null;
    inviteError.value = null;
  }, 250);
}

function onOpenChange(value: boolean) {
  if (!value) close();
}
</script>

<template>
  <Dialog :open="show" :title="title" :description="description" @update:open="onOpenChange">
    <form v-if="step === 'details'" @submit.prevent="submit">
      <div class="grid gap-5 px-5 pb-5 pt-4">
        <div class="flex items-end gap-3">
          <WorkspaceIconPicker v-model:icon="form.icon" :name="form.name" />
          <Field label="Name" :error="form.errors.name" class="min-w-0 flex-1">
            <Input v-model="form.name" placeholder="Acme Events" autofocus />
          </Field>
        </div>
        <p v-if="form.errors.icon" class="-mt-3 text-xs text-danger">{{ form.errors.icon }}</p>
      </div>

      <footer class="flex items-center justify-end gap-2 border-t px-5 py-3.5">
        <Button variant="ghost" type="button" @click="close">Cancel</Button>
        <Button :loading="form.processing">Create workspace</Button>
      </footer>
    </form>

    <template v-else>
      <div class="grid gap-3 px-5 pb-5 pt-4">
        <div class="flex items-end gap-2">
          <Field label="Role" class="min-w-0 flex-1">
            <Select v-model="inviteRole" :options="roleOptions" />
          </Field>
          <Button variant="secondary" type="button" :loading="generating" @click="generateInvite">
            <Link2 class="h-4 w-4" /> {{ inviteUrl ? 'New link' : 'Create link' }}
          </Button>
        </div>

        <p v-if="inviteError" class="text-xs text-danger">{{ inviteError }}</p>

        <div
          v-if="inviteUrl"
          class="flex items-center gap-2 rounded-lg border border-transparent bg-elevated/70 py-1 pl-3 pr-1"
        >
          <code class="block min-w-0 flex-1 truncate font-mono text-xs text-muted">{{ inviteUrl }}</code>
          <IconButton title="Copy invite link" @click="copyInviteUrl">
            <Copy class="h-4 w-4" />
          </IconButton>
        </div>
      </div>

      <footer class="flex items-center justify-end gap-2 border-t px-5 py-3.5">
        <Button type="button" :variant="inviteUrl ? 'primary' : 'ghost'" @click="close">
          {{ inviteUrl ? 'Done' : 'Skip for now' }}
        </Button>
      </footer>
    </template>
  </Dialog>
</template>
