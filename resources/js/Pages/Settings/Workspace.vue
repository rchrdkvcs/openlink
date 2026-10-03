<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

import Button from '@/Components/ui/Button.vue';
import Input from '@/Components/ui/Input.vue';
import SaveBar from '@/Components/ui/SaveBar.vue';
import Select from '@/Components/ui/Select.vue';
import SettingsGroup from '@/Components/ui/SettingsGroup.vue';
import SettingsRow from '@/Components/ui/SettingsRow.vue';
import WorkspaceIconPicker from '@/Components/Workspaces/WorkspaceIconPicker.vue';
import SettingsLayout from '@/Layouts/SettingsLayout.vue';
import { confirmAction } from '@/lib/confirm';
import type { SelectOption } from '@/lib/controls';
import { toast } from '@/lib/toast';

type Workspace = {
  id: number;
  name: string;
  slug: string;
  icon?: string | null;
  preferred_domain_id?: number | null;
};

const props = defineProps<{
  currentWorkspace: Workspace;
  role: string;
  domains: { id: number; hostname: string; is_default: boolean }[];
  canDelete: boolean;
}>();

const form = useForm({
  name: props.currentWorkspace.name,
  icon: props.currentWorkspace.icon ?? '',
  preferred_domain_id: (props.currentWorkspace.preferred_domain_id ?? '') as number | '',
});

const domainOptions = computed<SelectOption<number | ''>[]>(() => [
  { value: '', label: 'Automatic' },
  ...props.domains.map((domain) => ({
    value: domain.id,
    label: domain.is_default ? `${domain.hostname} (default)` : domain.hostname,
  })),
]);

function save() {
  form
    .transform((data) => ({
      name: data.name,
      icon: data.icon || null,
      preferred_domain_id: data.preferred_domain_id || null,
    }))
    .patch(route('workspaces.update', props.currentWorkspace.id), {
      preserveScroll: true,
      onSuccess: () => {
        form.defaults();
        toast({ title: 'Workspace updated', tone: 'success' });
      },
    });
}

function discard() {
  form.reset();
  form.clearErrors();
}

async function destroy() {
  const confirmed = await confirmAction({
    title: `Delete ${props.currentWorkspace.name}?`,
    message:
      'Links, domains, folders, QR codes, members and analytics in this workspace are deleted permanently. This cannot be undone.',
    confirmLabel: 'Delete workspace',
    destructive: true,
  });

  if (confirmed) router.delete(route('workspaces.destroy', props.currentWorkspace.id));
}
</script>

<template>
  <Head title="Workspace settings" />

  <SettingsLayout title="General" description="How this workspace looks and behaves for everyone in it.">
    <form class="space-y-8" @submit.prevent="save">
      <SettingsGroup title="Identity">
        <SettingsRow label="Icon" description="Shown in the sidebar and workspace switcher. Click to change.">
          <div class="flex sm:justify-end">
            <WorkspaceIconPicker v-model:icon="form.icon" :name="form.name" align="end" />
          </div>
        </SettingsRow>
        <SettingsRow label="Name" for="workspace-name" :error="form.errors.name ?? form.errors.icon">
          <Input id="workspace-name" v-model="form.name" placeholder="Acme Events" />
        </SettingsRow>
      </SettingsGroup>

      <SettingsGroup title="Short links">
        <SettingsRow
          label="Preferred domain"
          description="Pre-selected when anyone in this workspace creates a link."
          :error="form.errors.preferred_domain_id"
        >
          <Select v-model="form.preferred_domain_id" :options="domainOptions" aria-label="Preferred domain" />
        </SettingsRow>
      </SettingsGroup>

      <SaveBar
        :dirty="form.isDirty"
        :processing="form.processing"
        :has-errors="form.hasErrors"
        @discard="discard"
        @save="save"
      />
    </form>

    <SettingsGroup v-if="role === 'owner'" title="Danger zone">
      <SettingsRow
        label="Delete workspace"
        :description="
          canDelete
            ? 'Permanently removes this workspace and everything inside it.'
            : 'Create or join another workspace before deleting this one.'
        "
      >
        <div class="flex sm:justify-end">
          <Button variant="danger" type="button" :disabled="!canDelete" @click="destroy">Delete workspace</Button>
        </div>
      </SettingsRow>
    </SettingsGroup>
  </SettingsLayout>
</template>
