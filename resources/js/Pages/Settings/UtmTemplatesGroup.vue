<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { Megaphone, Pencil, Plus, Trash2 } from '@lucide/vue';
import { ref } from 'vue';

import Button from '@/Components/ui/Button.vue';
import IconButton from '@/Components/ui/IconButton.vue';
import SettingsGroup from '@/Components/ui/SettingsGroup.vue';
import { confirmAction } from '@/lib/confirm';
import { toast } from '@/lib/toast';
import { templateSummary, type UtmTemplate } from '@/lib/utm';

import UtmTemplateDialog from './UtmTemplateDialog.vue';

defineProps<{ templates: UtmTemplate[] }>();

const dialogOpen = ref(false);
const editing = ref<UtmTemplate | null>(null);

function openDialog(template: UtmTemplate | null) {
  editing.value = template;
  dialogOpen.value = true;
}

async function destroy(template: UtmTemplate) {
  const confirmed = await confirmAction({
    title: `Delete ${template.name}?`,
    message: 'Links that already use its values keep their destination URL.',
    confirmLabel: 'Delete template',
    destructive: true,
  });

  if (!confirmed) return;

  router.delete(route('utm-templates.destroy', template.id), {
    preserveScroll: true,
    onSuccess: () => toast({ title: 'Template deleted' }),
  });
}
</script>

<template>
  <SettingsGroup title="UTM templates" description="Shared campaign values members can apply to a link's destination.">
    <template #actions>
      <Button v-if="templates.length" variant="secondary" size="sm" type="button" @click="openDialog(null)">
        <Plus /> New template
      </Button>
    </template>

    <div v-for="template in templates" :key="template.id" class="flex items-center gap-3 px-4 py-3 sm:px-5">
      <span class="grid h-8 w-8 shrink-0 place-items-center rounded-lg border bg-elevated text-faint">
        <Megaphone class="h-4 w-4" />
      </span>
      <div class="min-w-0 flex-1">
        <p class="truncate text-[13px] font-medium text-foreground">{{ template.name }}</p>
        <p class="truncate font-mono text-xs text-faint" :title="templateSummary(template)">
          {{ templateSummary(template) }}
        </p>
      </div>
      <IconButton :title="`Edit ${template.name}`" @click="openDialog(template)">
        <Pencil class="h-3.5 w-3.5" />
      </IconButton>
      <IconButton variant="danger" :title="`Delete ${template.name}`" @click="destroy(template)">
        <Trash2 class="h-3.5 w-3.5" />
      </IconButton>
    </div>

    <div v-if="templates.length === 0" class="flex items-center justify-between gap-4 px-4 py-3.5 sm:px-5">
      <p class="text-[13px] text-muted">No UTM templates yet.</p>
      <Button variant="secondary" size="sm" type="button" @click="openDialog(null)">Create template</Button>
    </div>
  </SettingsGroup>

  <UtmTemplateDialog v-model:open="dialogOpen" :template="editing" />
</template>
