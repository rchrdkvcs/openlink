<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { ArrowRightLeft, Ban, Globe, MoreHorizontal, RefreshCw, Settings2, Trash2 } from '@lucide/vue';
import { computed } from 'vue';

import Badge from '@/Components/ui/Badge.vue';
import Menu from '@/Components/ui/Menu.vue';
import MenuItem from '@/Components/ui/MenuItem.vue';
import MenuSeparator from '@/Components/ui/MenuSeparator.vue';
import { confirmAction } from '@/lib/confirm';
import { domainNeedsSetup, domainStatusLabel, domainStatusVariant, domainSubtitle } from '@/lib/domains';
import { useShell } from '@/lib/shell';
import { toast } from '@/lib/toast';
import type { Domain } from '@/types/payloads';

const props = defineProps<{ domain: Domain; canTransfer: boolean }>();

const { canManage } = useShell();

const emit = defineEmits<{ transfer: [domain: Domain] }>();

const needsSetup = computed(() => domainNeedsSetup(props.domain));
const canAct = computed(() => canManage.value && !props.domain.is_default);

function verify() {
  router.post(route('domains.verify', props.domain.id), {}, { preserveScroll: true });
}

async function disable() {
  const { hostname, id } = props.domain;
  const confirmed = await confirmAction({
    title: `Disable ${hostname}?`,
    message: 'Short links on this domain stop redirecting.',
    confirmLabel: 'Disable domain',
    destructive: true,
  });

  if (!confirmed) return;

  router.post(
    route('domains.disable', id),
    {},
    {
      preserveScroll: true,
      onSuccess: () => toast({ title: `${hostname} disabled` }),
    },
  );
}

async function destroy() {
  const { hostname, id } = props.domain;
  const confirmed = await confirmAction({
    title: `Delete ${hostname}?`,
    message: 'Links using this domain are deleted too. This cannot be undone.',
    confirmLabel: 'Delete domain',
    destructive: true,
  });

  if (!confirmed) return;

  router.delete(route('domains.destroy', id), {
    preserveScroll: true,
    onSuccess: () => toast({ title: `${hostname} deleted` }),
  });
}
</script>

<template>
  <li class="flex items-center gap-4 px-4 py-3.5 sm:px-5">
    <span class="grid h-8 w-8 shrink-0 place-items-center rounded-lg border bg-elevated text-faint">
      <Globe class="h-4 w-4" />
    </span>
    <div class="min-w-0 flex-1">
      <p class="truncate text-sm font-medium text-foreground">
        {{ domain.hostname }}
      </p>
      <p class="mt-0.5 truncate text-[13px] text-muted">
        {{ domainSubtitle(domain) }}
      </p>
      <p v-if="domain.failure_reason" class="mt-1 text-xs text-danger">
        {{ domain.failure_reason }}
      </p>
    </div>
    <Link
      v-if="canManage && needsSetup"
      :href="route('domains.setup', domain.id)"
      class="hidden h-8 items-center rounded-lg border bg-elevated/60 px-3 text-[13px] font-medium text-foreground transition-colors hover:border-border-strong hover:bg-elevated sm:inline-flex"
    >
      Continue setup
    </Link>
    <Badge :variant="domainStatusVariant(domain)" dot class="shrink-0">{{ domainStatusLabel(domain) }}</Badge>
    <Menu v-if="canAct" width="w-56">
      <template #trigger>
        <button
          type="button"
          class="grid h-8 w-8 shrink-0 place-items-center rounded-lg text-muted transition-colors duration-150 hover:bg-elevated hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent/25 data-[state=open]:bg-elevated data-[state=open]:text-foreground"
          :aria-label="`Actions for ${domain.hostname}`"
        >
          <MoreHorizontal class="h-4 w-4" />
        </button>
      </template>
      <MenuItem :icon="RefreshCw" @select="verify">Check DNS now</MenuItem>
      <MenuItem v-if="needsSetup" :icon="Settings2" @select="router.visit(route('domains.setup', domain.id))">
        Continue setup
      </MenuItem>
      <MenuItem :icon="ArrowRightLeft" :disabled="!canTransfer" @select="emit('transfer', domain)"
        >Transfer to…</MenuItem
      >
      <MenuSeparator />
      <MenuItem v-if="domain.status !== 'disabled'" :icon="Ban" destructive @select="disable">Disable</MenuItem>
      <MenuItem :icon="Trash2" destructive @select="destroy">Delete</MenuItem>
    </Menu>
    <span v-else-if="canManage" class="w-8 shrink-0" />
  </li>
</template>
