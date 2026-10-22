<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { ChevronRight, Route } from '@lucide/vue';
import { computed } from 'vue';

import { confirmAction } from '@/lib/confirm';
import type { RoutingRuleDraft } from '@/types/shortLinks';

const props = defineProps<{
  rules: RoutingRuleDraft[];
  linkId: number;
  dirty: boolean;
}>();

const editorUrl = computed(() => route('links.routing', props.linkId));

async function openEditor(event: MouseEvent) {
  if (event.metaKey || event.ctrlKey || event.shiftKey || event.altKey || event.button !== 0) return;

  event.preventDefault();
  const leave =
    !props.dirty ||
    (await confirmAction({
      title: 'Leave without saving?',
      message: 'Your changes to this link are not saved yet.',
      confirmLabel: 'Leave',
      destructive: true,
    }));
  if (leave) router.visit(editorUrl.value);
}

const enabledRules = computed(() => props.rules.filter((rule) => rule.is_enabled).length);

const status = computed(() => {
  if (!enabledRules.value) return 'Off';
  return `${enabledRules.value} rule${enabledRules.value === 1 ? '' : 's'}`;
});
</script>

<template>
  <a
    :href="editorUrl"
    class="flex min-h-11 w-full items-center gap-2.5 px-3.5 py-2.5 text-start transition-colors hover:bg-elevated/40 focus-visible:bg-elevated/40 focus-visible:outline-none"
    @click="openEditor"
  >
    <Route class="h-4 w-4 shrink-0" :class="enabledRules > 0 ? 'text-accent' : 'text-faint'" />
    <span class="flex-1 text-[13px] font-medium text-foreground">Smart routing</span>
    <span class="text-[13px]" :class="enabledRules > 0 ? 'text-muted' : 'text-faint'">{{ status }}</span>
    <ChevronRight class="h-3.5 w-3.5 shrink-0 text-faint" />
  </a>
</template>
