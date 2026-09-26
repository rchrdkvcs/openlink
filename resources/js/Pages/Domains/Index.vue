<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowRightLeft, Ban, Globe, Plus, RefreshCw, Settings2, Trash2 } from '@lucide/vue';
import { ref } from 'vue';

import Badge from '@/Components/ui/Badge.vue';
import Button from '@/Components/ui/Button.vue';
import ConfirmDialog from '@/Components/ui/ConfirmDialog.vue';
import EmptyState from '@/Components/ui/EmptyState.vue';
import Field from '@/Components/ui/Field.vue';
import IconButton from '@/Components/ui/IconButton.vue';
import Popover from '@/Components/ui/Popover.vue';
import Select from '@/Components/ui/Select.vue';
import SelectOption from '@/Components/ui/SelectOption.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { useConfirmation } from '@/lib/useConfirmation';

type Workspace = { id: number; name: string; slug: string };
type Domain = {
  id: number;
  hostname: string;
  status: string;
  is_default: boolean;
  expected_txt?: string;
  failure_reason?: string | null;
  dns_check_error?: string | null;
};

const { confirmation, requestConfirmation } = useConfirmation();

const props = defineProps<{
  currentWorkspace: Workspace;
  workspaces: Workspace[];
  canManageWorkspace: boolean;
  domains: Domain[];
}>();

const transferMenuFor = ref<number | null>(null);
const transferForm = useForm({ workspace_id: '' });

function verifyDomain(domain: Domain) {
  useForm({}).post(route('domains.verify', domain.id), { preserveScroll: true });
}

function disableDomain(domain: Domain) {
  useForm({}).post(route('domains.disable', domain.id), { preserveScroll: true });
}

function openTransfer(domain: Domain) {
  transferForm.clearErrors();
  transferForm.workspace_id = '';
  transferMenuFor.value = transferMenuFor.value === domain.id ? null : domain.id;
}

function transferDomain(domain: Domain) {
  transferForm.post(route('domains.transfer', domain.id), {
    preserveScroll: true,
    onSuccess: () => {
      transferMenuFor.value = null;
      transferForm.reset();
    },
  });
}

function deleteDomain(domain: Domain) {
  requestConfirmation({
    title: `Delete ${domain.hostname}?`,
    description: 'Links using this domain will also be permanently deleted. This cannot be undone.',
    action: () => useForm({}).delete(route('domains.destroy', domain.id), { preserveScroll: true }),
  });
}

function statusVariant(domain: Domain) {
  if (domain.is_default) return 'outline';
  if (domain.status === 'active') return 'success';
  if (domain.status === 'failed_verification') return 'danger';
  return 'warning';
}

function statusLabel(domain: Domain) {
  if (domain.is_default) return 'default';
  return (
    {
      active: 'active',
      ownership_verified: 'almost ready',
      pending_verification: 'setup needed',
      failed_verification: 'setup needed',
      disabled: 'disabled',
    }[domain.status] ?? domain.status
  );
}

function needsSetup(domain: Domain) {
  return !domain.is_default && domain.status !== 'active' && domain.status !== 'disabled';
}

function targetWorkspaces() {
  return props.workspaces.filter((workspace) => workspace.id !== props.currentWorkspace.id);
}
</script>

<template>
  <Head title="Domains & DNS" />

  <AuthenticatedLayout>
    <div class="ui-page">
      <div class="mb-6 flex flex-wrap items-end justify-between gap-3">
        <div>
          <h1 class="text-xl font-semibold tracking-tight">Domains</h1>
          <p class="mt-1 text-sm text-muted">Manage hostnames and DNS verification for this workspace.</p>
        </div>
        <Link v-if="canManageWorkspace" :href="route('domains.create')">
          <Button class="shrink-0"><Plus class="h-4 w-4" /> Add domain</Button>
        </Link>
      </div>

      <section class="ui-panel p-2">
        <div class="ui-list-header hidden gap-3 xl:grid xl:grid-cols-[minmax(0,1.4fr)_120px_minmax(0,1fr)_144px]">
          <span>Hostname</span>
          <span>Status</span>
          <span>DNS record</span>
          <span class="text-right">Actions</span>
        </div>

        <div class="space-y-1 pt-1">
          <article
            v-for="domain in domains"
            :key="domain.id"
            class="ui-list-row grid gap-3 xl:grid-cols-[minmax(0,1.4fr)_120px_minmax(0,1fr)_144px] xl:items-center"
          >
            <div class="min-w-0">
              <p class="break-words text-sm font-medium text-foreground">{{ domain.hostname }}</p>
              <p class="mt-0.5 text-xs text-faint">
                {{ domain.is_default ? 'Application default' : 'Workspace domain' }}
              </p>
            </div>
            <div>
              <Badge :variant="statusVariant(domain)" dot>{{ statusLabel(domain) }}</Badge>
            </div>
            <div class="min-w-0">
              <Link v-if="canManageWorkspace && needsSetup(domain)" :href="route('domains.setup', domain.id)">
                <Button variant="secondary" size="sm"><Settings2 class="h-3.5 w-3.5" /> Continue setup</Button>
              </Link>
              <p v-else-if="!domain.is_default && domain.status === 'active'" class="text-xs text-muted">
                DNS configured
              </p>
              <p v-if="domain.failure_reason" class="mt-1.5 text-xs text-danger">
                {{ domain.failure_reason }}
              </p>
            </div>
            <div class="flex gap-0.5 xl:justify-end">
              <IconButton
                v-if="canManageWorkspace && !domain.is_default"
                title="Verify DNS"
                @click="verifyDomain(domain)"
              >
                <RefreshCw class="h-4 w-4" />
              </IconButton>
              <Popover
                v-if="canManageWorkspace && !domain.is_default"
                :open="transferMenuFor === domain.id"
                align="end"
                class="ui-popover-form w-64 p-3"
                aria-label="Transfer domain"
                @update:open="$event ? openTransfer(domain) : (transferMenuFor = null)"
              >
                <template #trigger
                  ><IconButton title="Transfer domain"><ArrowRightLeft class="h-4 w-4" /></IconButton
                ></template>
                <form class="grid gap-3" @submit.prevent="transferDomain(domain)">
                  <Field label="Transfer to" :error="transferForm.errors.workspace_id">
                    <Select v-model="transferForm.workspace_id" class="h-9">
                      <SelectOption value="">Choose workspace</SelectOption>
                      <SelectOption v-for="workspace in targetWorkspaces()" :key="workspace.id" :value="workspace.id">
                        {{ workspace.name }}
                      </SelectOption>
                    </Select>
                  </Field>
                  <Button size="sm" :loading="transferForm.processing" :disabled="!transferForm.workspace_id"
                    >Transfer</Button
                  >
                </form>
              </Popover>
              <IconButton
                v-if="canManageWorkspace && !domain.is_default"
                variant="danger"
                title="Disable domain"
                @click="disableDomain(domain)"
              >
                <Ban class="h-4 w-4" />
              </IconButton>
              <IconButton
                v-if="canManageWorkspace && !domain.is_default"
                variant="danger"
                title="Delete domain"
                @click="deleteDomain(domain)"
              >
                <Trash2 class="h-4 w-4" />
              </IconButton>
            </div>
          </article>
        </div>

        <EmptyState
          v-if="domains.length === 0"
          title="No domains configured"
          description="Add a domain to start using branded short URLs."
        >
          <template #icon><Globe class="h-5 w-5" /></template>
        </EmptyState>
      </section>
    </div>
    <ConfirmDialog :confirmation="confirmation" @close="confirmation = null" />
  </AuthenticatedLayout>
</template>
