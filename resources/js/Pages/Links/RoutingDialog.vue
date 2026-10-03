<script setup lang="ts">
import Button from '@/Components/ui/Button.vue';
import Dialog from '@/Components/ui/Dialog.vue';

import RoutingRulesEditor from './RoutingRulesEditor.vue';
import type { RoutingRuleDraft, RoutingSchema } from './types';

defineProps<{
  errors?: Record<string, string | undefined>;
  schema: RoutingSchema;
  defaultDestination?: string;
}>();

const open = defineModel<boolean>('open', { required: true });
const rules = defineModel<RoutingRuleDraft[]>({ required: true });
</script>

<template>
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
