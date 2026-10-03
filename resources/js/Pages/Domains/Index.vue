<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { Globe, Plus } from '@lucide/vue';
import { computed, ref } from 'vue';

import Button from '@/Components/ui/Button.vue';
import EmptyState from '@/Components/ui/EmptyState.vue';
import SettingsLayout from '@/Layouts/SettingsLayout.vue';
import { transferTargetOptions } from '@/lib/domains';
import { useShell } from '@/lib/shell';
import type { Domain } from '@/types/payloads';

import AddDomainDialog from './AddDomainDialog.vue';
import DomainRow from './DomainRow.vue';
import TransferDomainDialog from './TransferDomainDialog.vue';

defineProps<{ domains: Domain[] }>();

const { workspace, workspaces, canManage } = useShell();

const addOpen = ref(false);
const transferTarget = ref<Domain | null>(null);

const targetWorkspaceOptions = computed(() => transferTargetOptions(workspaces.value, workspace.value?.id ?? 0));
</script>

<template>
  <Head title="Domains" />

  <SettingsLayout title="Domains" description="Hostnames your short links can use, and their DNS status.">
    <template v-if="canManage" #actions>
      <Button type="button" @click="addOpen = true"><Plus class="h-4 w-4" /> Add domain</Button>
    </template>

    <section class="overflow-hidden rounded-xl border bg-surface">
      <ul v-if="domains.length" class="divide-y divide-border">
        <DomainRow
          v-for="domain in domains"
          :key="domain.id"
          :domain="domain"
          :can-transfer="targetWorkspaceOptions.length > 0"
          @transfer="transferTarget = $event"
        />
      </ul>

      <EmptyState v-else title="No domains yet" description="Add a domain you own to use branded short URLs.">
        <template #icon><Globe class="h-5 w-5" /></template>
        <template v-if="canManage" #action>
          <Button type="button" size="sm" @click="addOpen = true"><Plus class="h-4 w-4" /> Add domain</Button>
        </template>
      </EmptyState>
    </section>

    <AddDomainDialog v-model:open="addOpen" />

    <TransferDomainDialog v-model:domain="transferTarget" :options="targetWorkspaceOptions" />
  </SettingsLayout>
</template>
