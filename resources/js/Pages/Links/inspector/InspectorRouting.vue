<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { ChevronRight, Route } from '@lucide/vue';
import { computed } from 'vue';

import { confirmAction } from '@/lib/confirm';
import { createRouting, ruleName } from '@/lib/routing';
import type { RoutingRuleDraft, RoutingSchema } from '@/types/shortLinks';

const props = defineProps<{
  rules: RoutingRuleDraft[];
  schema: RoutingSchema;
  linkId: number;
  dirty: boolean;
}>();

const PREVIEW_LIMIT = 3;

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

const preview = computed(() => {
  const routing = createRouting(props.schema);

  return props.rules.slice(0, PREVIEW_LIMIT).map((rule, index) => ({
    key: rule.id ?? rule.client_id ?? index,
    name: ruleName(rule),
    enabled: rule.is_enabled,
    summary: rule.is_enabled ? routing.summary(rule) : 'Disabled',
  }));
});
</script>

<template>
  <div>
    <div class="flex h-10 w-full items-center gap-2.5 px-3.5">
      <Route class="h-4 w-4 shrink-0" :class="enabledRules > 0 ? 'text-accent' : 'text-faint'" />
      <span class="flex-1 text-[13px] font-medium text-foreground">Smart routing</span>
      <span class="max-w-[45%] truncate text-[13px]" :class="enabledRules > 0 ? 'text-muted' : 'text-faint'">{{
        status
      }}</span>
    </div>
    <ul v-if="preview.length" class="grid gap-2 pb-3 pe-3.5 ps-[1.625rem]">
      <li v-for="rule in preview" :key="rule.key" class="flex min-w-0 items-start gap-2">
        <span
          class="mt-[5px] h-1.5 w-1.5 shrink-0 rounded-full"
          :class="rule.enabled ? 'bg-accent' : 'bg-border-strong'"
          aria-hidden="true"
        />
        <span class="min-w-0 flex-1">
          <span class="block truncate text-xs font-medium text-foreground">{{ rule.name }}</span>
          <span class="block truncate text-xs text-faint">{{ rule.summary }}</span>
        </span>
      </li>
      <li v-if="rules.length > PREVIEW_LIMIT" class="ps-3.5 text-xs text-faint">
        {{ rules.length - PREVIEW_LIMIT }} more
      </li>
    </ul>
    <div class="px-3.5 pb-3 ps-[1.625rem]">
      <a
        :href="editorUrl"
        @click="openEditor"
        class="inline-flex h-7 items-center gap-1.5 rounded-md text-xs font-medium text-accent transition-colors hover:text-foreground"
        >Open routing editor<ChevronRight class="h-3.5 w-3.5"
      /></a>
    </div>
  </div>
</template>
