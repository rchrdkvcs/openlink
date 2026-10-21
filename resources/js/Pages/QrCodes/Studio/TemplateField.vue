<script setup lang="ts">
import { Bookmark, MoreHorizontal, Settings2 } from '@lucide/vue';
import { computed, ref } from 'vue';

import Button from '@/Components/ui/Button.vue';
import Menu from '@/Components/ui/Menu.vue';
import MenuItem from '@/Components/ui/MenuItem.vue';
import Select from '@/Components/ui/Select.vue';

import { templateOptions } from '../qrTemplates';
import type { QrCodeTemplate } from '../types';
import ManageTemplatesDialog from './ManageTemplatesDialog.vue';
import TemplateNameDialog from './TemplateNameDialog.vue';

const props = defineProps<{
  templates: QrCodeTemplate[];
  applied: QrCodeTemplate | null;
  canManage: boolean;
  hasUnsavedChanges: boolean;
  qrToken: string;
}>();

const emit = defineEmits<{ apply: [template: QrCodeTemplate] }>();

const naming = ref(false);
const managing = ref(false);
const renaming = ref<QrCodeTemplate | null>(null);

const options = computed(() => templateOptions(props.templates));

function apply(id: number | null) {
  const template = props.templates.find((candidate) => candidate.id === id);
  if (template) emit('apply', template);
}

function saveAsTemplate() {
  renaming.value = null;
  naming.value = true;
}

function rename(template: QrCodeTemplate) {
  managing.value = false;
  renaming.value = template;
  naming.value = true;
}
</script>

<template>
  <div v-if="templates.length || canManage" class="grid gap-1.5">
    <span class="text-[13px] font-medium text-foreground">Template</span>
    <div class="flex items-center gap-2">
      <Select
        v-if="templates.length"
        :model-value="applied?.id ?? null"
        :options="options"
        placeholder="Apply a template"
        aria-label="Apply a template"
        class="flex-1"
        @update:model-value="apply"
      />
      <p v-else class="flex-1 text-xs text-faint">Save this design to reuse it on other QR codes.</p>
      <Menu v-if="canManage" width="w-60">
        <template #trigger>
          <Button variant="secondary" type="button" class="w-8 px-0" aria-label="Template actions">
            <MoreHorizontal />
          </Button>
        </template>
        <MenuItem :icon="Bookmark" :disabled="hasUnsavedChanges" @select="saveAsTemplate">
          {{ hasUnsavedChanges ? 'Save changes to create a template' : 'Save as template' }}
        </MenuItem>
        <MenuItem :icon="Settings2" @select="managing = true">Manage templates</MenuItem>
      </Menu>
    </div>
    <p v-if="applied" class="text-xs leading-relaxed text-faint">
      Previewing “{{ applied.name }}”. Save to apply it, or discard to keep the current design.
    </p>

    <template v-if="canManage">
      <TemplateNameDialog v-model:open="naming" :qr-token="qrToken" :template="renaming" />
      <ManageTemplatesDialog
        v-model:open="managing"
        :templates="templates"
        :qr-token="qrToken"
        :has-unsaved-changes="hasUnsavedChanges"
        @rename="rename"
      />
    </template>
  </div>
</template>
