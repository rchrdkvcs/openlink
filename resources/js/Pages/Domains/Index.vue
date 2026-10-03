<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ArrowRightLeft, Ban, Globe, MoreHorizontal, Plus, RefreshCw, Settings2, Trash2 } from '@lucide/vue';
import { computed, ref } from 'vue';

import Badge from '@/Components/ui/Badge.vue';
import Button from '@/Components/ui/Button.vue';
import Dialog from '@/Components/ui/Dialog.vue';
import EmptyState from '@/Components/ui/EmptyState.vue';
import Field from '@/Components/ui/Field.vue';
import Input from '@/Components/ui/Input.vue';
import Menu from '@/Components/ui/Menu.vue';
import MenuItem from '@/Components/ui/MenuItem.vue';
import MenuSeparator from '@/Components/ui/MenuSeparator.vue';
import Select from '@/Components/ui/Select.vue';
import SettingsLayout from '@/Layouts/SettingsLayout.vue';
import { confirmAction } from '@/lib/confirm';
import { toast } from '@/lib/toast';

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

const props = defineProps<{
  currentWorkspace: Workspace;
  workspaces: Workspace[];
  canManageWorkspace: boolean;
  domains: Domain[];
}>();

const addOpen = ref(false);
const addForm = useForm({ hostname: '' });

function openAdd() {
  addForm.reset();
  addForm.clearErrors();
  addOpen.value = true;
}

function submitAdd() {
  addForm.post(route('domains.store'), {
    onSuccess: () => {
      addOpen.value = false;
    },
  });
}

const transferDomainTarget = ref<Domain | null>(null);
const transferForm = useForm({ workspace_id: '' as number | '' });

const transferOpen = computed({
  get: () => transferDomainTarget.value !== null,
  set: (value: boolean) => {
    if (!value) transferDomainTarget.value = null;
  },
});

function openTransfer(domain: Domain) {
  transferForm.reset();
  transferForm.clearErrors();
  transferDomainTarget.value = domain;
}

function submitTransfer() {
  const domain = transferDomainTarget.value;
  if (!domain) return;
  const target = targetWorkspaceOptions.value.find((option) => option.value === transferForm.workspace_id);

  transferForm.post(route('domains.transfer', domain.id), {
    preserveScroll: true,
    onSuccess: () => {
      transferDomainTarget.value = null;
      transferForm.reset();
      toast({ title: `${domain.hostname} transferred`, description: target?.label, tone: 'success' });
    },
  });
}

function verifyDomain(domain: Domain) {
  router.post(route('domains.verify', domain.id), {}, { preserveScroll: true });
}

async function disableDomain(domain: Domain) {
  const confirmed = await confirmAction({
    title: `Disable ${domain.hostname}?`,
    message: 'Short links on this domain stop redirecting.',
    confirmLabel: 'Disable domain',
    destructive: true,
  });

  if (!confirmed) return;

  router.post(
    route('domains.disable', domain.id),
    {},
    {
      preserveScroll: true,
      onSuccess: () => toast({ title: `${domain.hostname} disabled` }),
    },
  );
}

async function deleteDomain(domain: Domain) {
  const confirmed = await confirmAction({
    title: `Delete ${domain.hostname}?`,
    message: 'Links using this domain are deleted too. This cannot be undone.',
    confirmLabel: 'Delete domain',
    destructive: true,
  });

  if (!confirmed) return;

  router.delete(route('domains.destroy', domain.id), {
    preserveScroll: true,
    onSuccess: () => toast({ title: `${domain.hostname} deleted` }),
  });
}

function statusVariant(domain: Domain) {
  if (domain.is_default) return 'outline';
  if (domain.status === 'active') return 'success';
  if (domain.status === 'failed_verification') return 'danger';
  if (domain.status === 'disabled') return 'default';
  return 'warning';
}

function statusLabel(domain: Domain) {
  if (domain.is_default) return 'Default';
  return (
    {
      active: 'Active',
      ownership_verified: 'Almost ready',
      pending_verification: 'Setup needed',
      failed_verification: 'Setup needed',
      disabled: 'Disabled',
    }[domain.status] ?? domain.status
  );
}

function subtitle(domain: Domain) {
  if (domain.is_default) return 'Instance default, available to every workspace';
  if (domain.status === 'active') return 'DNS configured';
  if (domain.status === 'disabled') return 'Not serving links';
  if (domain.status === 'ownership_verified') return 'Ownership verified, waiting for DNS to point here';
  return 'Waiting for DNS records';
}

function needsSetup(domain: Domain) {
  return !domain.is_default && domain.status !== 'active' && domain.status !== 'disabled';
}

function canAct(domain: Domain) {
  return props.canManageWorkspace && !domain.is_default;
}

const targetWorkspaceOptions = computed(() =>
  props.workspaces
    .filter((workspace) => workspace.id !== props.currentWorkspace.id)
    .map((workspace) => ({ value: workspace.id, label: workspace.name })),
);
</script>

<template>
  <Head title="Domains" />

  <SettingsLayout title="Domains" description="Hostnames your short links can use, and their DNS status.">
    <template v-if="canManageWorkspace" #actions>
      <Button type="button" @click="openAdd"><Plus class="h-4 w-4" /> Add domain</Button>
    </template>

    <section class="overflow-hidden rounded-xl border bg-surface">
      <ul v-if="domains.length" class="divide-y divide-border">
        <li v-for="domain in domains" :key="domain.id" class="flex items-center gap-4 px-4 py-3.5 sm:px-5">
          <span class="grid h-8 w-8 shrink-0 place-items-center rounded-lg border bg-elevated text-faint">
            <Globe class="h-4 w-4" />
          </span>
          <div class="min-w-0 flex-1">
            <p class="truncate text-sm font-medium text-foreground">{{ domain.hostname }}</p>
            <p class="mt-0.5 truncate text-[13px] text-muted">{{ subtitle(domain) }}</p>
            <p v-if="domain.failure_reason" class="mt-1 text-xs text-danger">{{ domain.failure_reason }}</p>
          </div>
          <Link
            v-if="canManageWorkspace && needsSetup(domain)"
            :href="route('domains.setup', domain.id)"
            class="hidden h-8 items-center rounded-lg border bg-elevated/60 px-3 text-[13px] font-medium text-foreground transition-colors hover:border-border-strong hover:bg-elevated sm:inline-flex"
          >
            Continue setup
          </Link>
          <Badge :variant="statusVariant(domain)" dot class="shrink-0">{{ statusLabel(domain) }}</Badge>
          <Menu v-if="canAct(domain)" width="w-56">
            <template #trigger>
              <button
                type="button"
                class="grid h-8 w-8 shrink-0 place-items-center rounded-lg text-muted transition-colors duration-150 hover:bg-elevated hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent/25 data-[state=open]:bg-elevated data-[state=open]:text-foreground"
                :aria-label="`Actions for ${domain.hostname}`"
              >
                <MoreHorizontal class="h-4 w-4" />
              </button>
            </template>
            <MenuItem :icon="RefreshCw" @select="verifyDomain(domain)">Check DNS now</MenuItem>
            <MenuItem
              v-if="needsSetup(domain)"
              :icon="Settings2"
              @select="router.visit(route('domains.setup', domain.id))"
            >
              Continue setup
            </MenuItem>
            <MenuItem
              :icon="ArrowRightLeft"
              :disabled="targetWorkspaceOptions.length === 0"
              @select="openTransfer(domain)"
            >
              Transfer to…
            </MenuItem>
            <MenuSeparator />
            <MenuItem v-if="domain.status !== 'disabled'" :icon="Ban" destructive @select="disableDomain(domain)">
              Disable
            </MenuItem>
            <MenuItem :icon="Trash2" destructive @select="deleteDomain(domain)">Delete</MenuItem>
          </Menu>
          <span v-else-if="canManageWorkspace" class="w-8 shrink-0" />
        </li>
      </ul>

      <EmptyState v-else title="No domains yet" description="Add a domain you own to use branded short URLs.">
        <template #icon><Globe class="h-5 w-5" /></template>
        <template v-if="canManageWorkspace" #action>
          <Button type="button" size="sm" @click="openAdd"><Plus class="h-4 w-4" /> Add domain</Button>
        </template>
      </EmptyState>
    </section>

    <Dialog
      v-model:open="addOpen"
      title="Add a domain"
      description="Use a domain or subdomain you own, like go.yourcompany.com. You will add DNS records next."
    >
      <form class="px-5 pb-5 pt-4" @submit.prevent="submitAdd">
        <Field label="Hostname" :error="addForm.errors.hostname">
          <Input
            v-model="addForm.hostname"
            placeholder="go.example.com"
            autocomplete="off"
            spellcheck="false"
            autofocus
            required
          />
        </Field>
        <div class="mt-5 flex justify-end gap-2">
          <Button variant="secondary" type="button" @click="addOpen = false">Cancel</Button>
          <Button :loading="addForm.processing" :disabled="!addForm.hostname.trim()">Continue</Button>
        </div>
      </form>
    </Dialog>

    <Dialog
      v-model:open="transferOpen"
      size="sm"
      :title="transferDomainTarget ? `Transfer ${transferDomainTarget.hostname}` : 'Transfer domain'"
      description="Only domains without links can be moved to another workspace you manage."
    >
      <form class="px-5 pb-5 pt-4" @submit.prevent="submitTransfer">
        <Field label="Workspace" :error="transferForm.errors.workspace_id">
          <Select
            v-model="transferForm.workspace_id"
            :options="targetWorkspaceOptions"
            placeholder="Choose a workspace"
            aria-label="Workspace"
          />
        </Field>
        <div class="mt-5 flex justify-end gap-2">
          <Button variant="secondary" type="button" @click="transferOpen = false">Cancel</Button>
          <Button :loading="transferForm.processing" :disabled="!transferForm.workspace_id">Transfer</Button>
        </div>
      </form>
    </Dialog>
  </SettingsLayout>
</template>
