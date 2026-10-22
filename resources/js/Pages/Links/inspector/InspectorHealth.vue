<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Activity, RefreshCw } from '@lucide/vue';
import { computed, ref } from 'vue';

import Badge from '@/Components/ui/Badge.vue';
import Button from '@/Components/ui/Button.vue';
import { relativeTime } from '@/lib/datetime';
import {
  type DestinationHealth,
  healthLabel,
  healthNote,
  healthVariant,
  linkDestinations,
} from '@/lib/destinationHealth';
import { displayUrl } from '@/lib/links';
import type { ShortLink } from '@/types/shortLinks';

import InspectorRow from './InspectorRow.vue';

const props = defineProps<{
  link: ShortLink;
  health: Map<string, DestinationHealth>;
  canCheck: boolean;
  unsaved: boolean;
}>();

const open = defineModel<boolean>('open', { default: false });
const requesting = ref(false);

const rows = computed(() =>
  linkDestinations(props.link).map((destination, index) => ({
    ...destination,
    key: `${destination.role}-${index}`,
    health: props.health.get(destination.url),
  })),
);

const failing = computed(
  () => new Set(rows.value.filter((row) => row.health?.has_incident).map((row) => row.url)).size,
);
const checking = computed(() => rows.value.some((row) => row.health?.pending));

const summary = computed(() => {
  if (failing.value) return `${failing.value} failing`;
  if (checking.value) return 'Checking…';
  if (rows.value.every((row) => row.health?.status === 'reachable')) return 'Reachable';
  if (rows.value.every((row) => !row.health || row.health.status === 'unchecked')) return 'Not checked';
  return 'See details';
});

function check() {
  requesting.value = true;
  router.post(
    route('short-links.destination-checks.store', props.link.id),
    {},
    {
      preserveScroll: true,
      preserveState: true,
      only: ['destinationHealth', 'navigation'],
      onFinish: () => (requesting.value = false),
    },
  );
}
</script>

<template>
  <InspectorRow
    v-model:open="open"
    :icon="Activity"
    label="Destination health"
    :summary="summary"
    :active="failing > 0"
  >
    <ul class="divide-y overflow-hidden rounded-lg border">
      <li v-for="row in rows" :key="row.key" class="flex items-start gap-3 px-3 py-2.5">
        <div class="min-w-0 flex-1">
          <p class="text-xs text-faint">{{ row.label }}</p>
          <p class="truncate text-[13px] text-foreground" :title="row.url">{{ displayUrl(row.url) }}</p>
          <p class="mt-0.5 text-xs text-muted">{{ healthNote(row.health, relativeTime) }}</p>
          <p v-if="row.health?.detail && row.health.status !== 'reachable'" class="mt-0.5 text-xs text-faint">
            {{ row.health.detail }}
          </p>
        </div>
        <Badge :variant="healthVariant(row.health)" dot class="mt-0.5 shrink-0">{{ healthLabel(row.health) }}</Badge>
      </li>
    </ul>
    <div v-if="canCheck" class="flex items-center justify-between gap-3">
      <p class="text-xs text-faint">
        {{ unsaved ? 'Checks use the saved destinations.' : 'Checks never affect redirects.' }}
      </p>
      <Button variant="secondary" size="sm" type="button" :loading="requesting" :disabled="checking" @click="check">
        <RefreshCw /> Check now
      </Button>
    </div>
  </InspectorRow>
</template>
