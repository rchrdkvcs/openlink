<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Megaphone } from '@lucide/vue';

import Button from '@/Components/ui/Button.vue';
import Field from '@/Components/ui/Field.vue';
import Input from '@/Components/ui/Input.vue';
import Select from '@/Components/ui/Select.vue';
import { UTM_FIELDS, type UtmTemplate } from '@/lib/utm';

import InspectorRow from './InspectorRow.vue';
import { useUtmBuilder } from './useUtmBuilder';

const props = defineProps<{ templates: UtmTemplate[]; canManageTemplates: boolean; disabled?: boolean }>();

const destination = defineModel<string>({ required: true });
const open = defineModel<boolean>('open', { default: false });

const {
  values,
  filled,
  summary,
  hasDestination,
  fieldsDisabled,
  selectedId,
  selected,
  changes,
  applicable,
  canApply,
  templateOptions,
  showCreateTemplateLink,
  finalUrl,
  update,
  apply,
} = useUtmBuilder({
  destination,
  templates: () => props.templates,
  disabled: () => Boolean(props.disabled),
  canManageTemplates: () => props.canManageTemplates,
});
</script>

<template>
  <InspectorRow
    v-model:open="open"
    :icon="Megaphone"
    label="UTM parameters"
    :summary="summary"
    :active="filled.length > 0"
  >
    <div v-if="templates.length" class="space-y-2">
      <div class="flex items-end gap-2">
        <Field label="Template" class="flex-1">
          <Select v-model="selectedId" :options="templateOptions" :disabled="disabled" aria-label="UTM template" />
        </Field>
        <Button type="button" variant="secondary" :disabled="!canApply" @click="apply"> Apply </Button>
      </div>
      <div v-if="selected" class="rounded-lg border bg-canvas px-3 py-2 text-xs" aria-live="polite">
        <p v-if="!hasDestination" class="text-faint">Add a destination before applying a template.</p>
        <p v-else-if="!applicable" class="text-faint">The destination already uses these values.</p>
        <p v-else-if="changes.length === 0" class="text-faint">Removes repeated UTM parameters.</p>
        <template v-else>
          <p class="mb-1 font-medium text-muted">Applying changes</p>
          <ul class="space-y-0.5">
            <li v-for="change in changes" :key="change.key" class="flex gap-1.5 break-all">
              <span class="shrink-0 font-mono text-faint">{{ change.key }}</span>
              <span v-if="change.from" class="text-faint line-through">{{ change.from }}</span>
              <span class="text-foreground">{{ change.to }}</span>
            </li>
          </ul>
        </template>
      </div>
    </div>
    <p v-else class="text-xs text-faint">
      No UTM templates yet.
      <Link v-if="showCreateTemplateLink" :href="route('settings.workspace')" class="text-accent hover:underline">
        Create one in workspace settings.
      </Link>
    </p>

    <div class="grid gap-3">
      <Field v-for="field in UTM_FIELDS" :key="field.key" :label="field.label">
        <Input
          :model-value="values[field.key]"
          :placeholder="field.placeholder"
          spellcheck="false"
          :disabled="fieldsDisabled"
          @update:model-value="update(field.key, String($event ?? ''))"
        />
      </Field>
    </div>

    <div class="grid gap-1">
      <span class="text-xs font-medium text-muted">Final URL</span>
      <p class="break-all rounded-lg border bg-canvas px-3 py-2 font-mono text-xs text-foreground">
        {{ finalUrl || 'Add a destination first.' }}
      </p>
      <p class="text-xs leading-relaxed text-faint">
        Values are kept exactly as typed. Use consistent lowercase names, like newsletter or email, so campaigns group
        together in analytics. Clear a field to remove it from the URL.
      </p>
    </div>
  </InspectorRow>
</template>
