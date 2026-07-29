<script setup lang="ts">
import { useForm, usePage } from '@inertiajs/vue3';
import { Link2, Plus, UserPlus } from '@lucide/vue';
import { ref } from 'vue';

import Modal from '@/Components/Modal.vue';
import Button from '@/Components/ui/Button.vue';
import CopyCheckIcon from '@/Components/ui/CopyCheckIcon.vue';
import Field from '@/Components/ui/Field.vue';
import IconButton from '@/Components/ui/IconButton.vue';
import WorkspaceColorPicker from '@/Components/Workspaces/WorkspaceColorPicker.vue';
import WorkspaceIconPicker from '@/Components/Workspaces/WorkspaceIconPicker.vue';
import { fetchJson, HttpError } from '@/lib/http';

defineProps<{ show: boolean }>();

const emit = defineEmits<{ close: [] }>();

const page = usePage();
const step = ref<'details' | 'invite'>('details');
const createdWorkspace = ref<{ id: number; name: string } | null>(null);

const form = useForm({ name: '', icon: '', color: '' });

const inviteRole = ref('editor');
const inviteUrl = ref<string | null>(null);
const inviteError = ref<string | null>(null);
const generating = ref(false);
const copied = ref(false);

function submit() {
  form
    .transform((data) => ({
      name: data.name,
      icon: data.icon || null,
      color: data.color || null,
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
        ? 'Invalid role selected.'
        : 'Could not generate an invite link. You can do it later from the Members page.';
  } finally {
    generating.value = false;
  }
}

async function copyInviteUrl() {
  if (!inviteUrl.value) return;

  await navigator.clipboard.writeText(inviteUrl.value);
  copied.value = true;
  setTimeout(() => (copied.value = false), 2000);
}

function close() {
  emit('close');

  // Reset after the closing transition so the content doesn't flicker.
  setTimeout(() => {
    step.value = 'details';
    createdWorkspace.value = null;
    form.reset();
    form.clearErrors();
    inviteRole.value = 'editor';
    inviteUrl.value = null;
    inviteError.value = null;
    copied.value = false;
  }, 250);
}
</script>

<template>
  <Modal :show="show" max-width="lg" @close="close">
    <div class="p-6">
      <template v-if="step === 'details'">
        <div class="flex items-start gap-3">
          <WorkspaceIconPicker v-model:icon="form.icon" :name="form.name" :color="form.color" />
          <div>
            <h2 class="text-base font-semibold text-foreground">Create workspace</h2>
            <p class="mt-0.5 text-sm text-muted">
              Use a workspace when links, members, domains, or folders should be isolated. Click the icon to change it.
            </p>
          </div>
        </div>

        <form class="mt-6 grid gap-5" @submit.prevent="submit">
          <Field label="Name" :error="form.errors.name">
            <input v-model="form.name" class="h-9" placeholder="Acme Events" autofocus />
          </Field>

          <WorkspaceColorPicker v-model:color="form.color" :name="form.name" />
          <p v-if="form.errors.icon || form.errors.color" class="text-xs text-danger">
            {{ form.errors.icon ?? form.errors.color }}
          </p>

          <div class="flex justify-end gap-3">
            <Button variant="secondary" type="button" @click="close">Cancel</Button>
            <Button :loading="form.processing"> <Plus class="h-4 w-4" /> Create workspace </Button>
          </div>
        </form>
      </template>

      <template v-else>
        <div class="flex items-start gap-3">
          <span class="grid h-9 w-9 shrink-0 place-items-center rounded-lg bg-accent/15 text-accent">
            <UserPlus class="h-4.5 w-4.5" />
          </span>
          <div class="min-w-0">
            <h2 class="truncate text-base font-semibold text-foreground">
              Invite members to {{ createdWorkspace?.name }}
            </h2>
            <p class="mt-0.5 text-sm text-muted">
              Anyone with the link joins this workspace with the link's role. You can skip this and invite later.
            </p>
          </div>
        </div>

        <div class="mt-5 rounded-lg border bg-elevated/30 p-4">
          <div class="flex items-end gap-3">
            <Field label="Role" class="flex-1">
              <select v-model="inviteRole" class="h-9">
                <option value="admin">Admin</option>
                <option value="editor">Editor</option>
                <option value="viewer">Viewer</option>
              </select>
            </Field>
            <Button type="button" :loading="generating" @click="generateInvite">
              <Link2 class="h-4 w-4" /> Generate invite link
            </Button>
          </div>

          <p v-if="inviteError" class="mt-3 text-xs text-danger">{{ inviteError }}</p>

          <div v-if="inviteUrl" class="mt-3 flex items-center gap-2 rounded-md border bg-surface px-3 py-2">
            <code class="block min-w-0 flex-1 truncate text-xs text-muted">{{ inviteUrl }}</code>
            <IconButton :title="copied ? 'Copied' : 'Copy link'" @click="copyInviteUrl">
              <CopyCheckIcon :copied="copied" />
            </IconButton>
          </div>
        </div>

        <div class="mt-6 flex justify-end">
          <Button type="button" @click="close">{{ inviteUrl ? 'Done' : 'Skip for now' }}</Button>
        </div>
      </template>
    </div>
  </Modal>
</template>
