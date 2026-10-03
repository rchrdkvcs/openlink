<script setup lang="ts">
import { ChevronRight, Route } from '@lucide/vue';
import { computed } from 'vue';

import Button from '@/Components/ui/Button.vue';
import Dialog from '@/Components/ui/Dialog.vue';
import { createRouting, type FormErrors, ruleName } from '@/lib/routing';
import type { RoutingRuleDraft, RoutingSchema } from '@/types/shortLinks';

import RoutingRulesEditor from '../routing/RoutingRulesEditor.vue';

const props = defineProps<{
  schema: RoutingSchema;
  errors: FormErrors;
  hasErrors: boolean;
  defaultDestination: string;
}>();

const rules = defineModel<RoutingRuleDraft[]>({ required: true });
const open = defineModel<boolean>('open', { required: true });

const PREVIEW_LIMIT = 3;

const enabledRules = computed(() => rules.value.filter((rule) => rule.is_enabled).length);

const status = computed(() => {
  if (props.hasErrors) return 'Needs attention';
  if (!enabledRules.value) return 'Off';
  return `${enabledRules.value} rule${enabledRules.value === 1 ? '' : 's'}`;
});

const preview = computed(() => {
  const routing = createRouting(props.schema);

  return rules.value.slice(0, PREVIEW_LIMIT).map((rule, index) => ({
    key: rule.id ?? rule.client_id ?? index,
    name: ruleName(rule),
    enabled: rule.is_enabled,
    summary: rule.is_enabled ? routing.summary(rule) : 'Disabled',
  }));
});
</script>

<template>
  <div>
    <button
      type="button"
      class="flex h-10 w-full items-center gap-2.5 px-3.5 text-left transition-colors hover:bg-elevated/40 focus-visible:bg-elevated/40 focus-visible:outline-none"
      aria-haspopup="dialog"
      @click="open = true"
    >
      <Route class="h-4 w-4 shrink-0" :class="enabledRules > 0 ? 'text-accent' : 'text-faint'" />
      <span class="flex-1 text-[13px] font-medium text-foreground">Smart routing</span>
      <span
        class="max-w-[55%] truncate text-[13px]"
        :class="hasErrors ? 'text-danger' : enabledRules > 0 ? 'text-muted' : 'text-faint'"
        >{{ status }}</span
      >
      <ChevronRight class="h-3.5 w-3.5 shrink-0 text-faint" />
    </button>
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
  </div>

  <Dialog
    v-model:open="open"
    size="xl"
    class="top-[8vh]"
    title="Smart routing"
    description="Send visitors to different destinations based on country, device, campaign, time, or split traffic between variants. Rules are checked top to bottom; the first match wins."
  >
    <div class="max-h-[min(640px,70vh)] overflow-y-auto overscroll-contain px-5 pb-5 pt-4">
      <RoutingRulesEditor v-model="rules" :errors="errors" :schema="schema" :default-destination="defaultDestination" />
    </div>
    <footer class="flex items-center justify-end gap-2 border-t px-5 py-3.5">
      <Button type="button" @click="open = false">Done</Button>
    </footer>
  </Dialog>
</template>
