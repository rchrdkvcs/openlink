<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Megaphone, Pencil, Plus, Trash2 } from '@lucide/vue';
import { ref } from 'vue';

import Button from '@/Components/ui/Button.vue';
import EmptyState from '@/Components/ui/EmptyState.vue';
import IconButton from '@/Components/ui/IconButton.vue';
import PageHeader from '@/Components/ui/PageHeader.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
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
  <Head title="UTM templates" />

  <AuthenticatedLayout>
    <div class="mx-auto w-full max-w-5xl px-4 py-6 sm:px-6 lg:px-8 lg:py-8">
      <PageHeader
        title="UTM templates"
        description="Shared campaign values you can copy into a link’s destination URL."
      >
        <template #actions>
          <Button size="sm" type="button" @click="openDialog(null)"><Plus /> New template</Button>
        </template>
      </PageHeader>

      <div v-if="templates.length === 0" class="mt-6 rounded-xl border border-dashed">
        <EmptyState
          title="No UTM templates yet"
          description="Save reusable UTM parameter values, then apply them from the link editor."
        >
          <template #icon><Megaphone class="h-5 w-5" /></template>
          <template #action>
            <Button size="sm" type="button" @click="openDialog(null)"><Plus /> Create template</Button>
          </template>
        </EmptyState>
      </div>

      <ul v-else class="mt-6 divide-y rounded-xl border bg-surface">
        <li v-for="template in templates" :key="template.id" class="flex items-center gap-3 px-4 py-3 sm:px-5">
          <span class="grid h-8 w-8 shrink-0 place-items-center rounded-lg border bg-elevated text-faint">
            <Megaphone class="h-4 w-4" />
          </span>
          <div class="min-w-0 flex-1">
            <p class="truncate text-[13px] font-medium text-foreground">{{ template.name }}</p>
            <p class="truncate font-mono text-xs text-faint" :title="templateSummary(template)">
              {{ templateSummary(template) }}
            </p>
          </div>
          <IconButton :title="`Edit ${template.name}`" @click="openDialog(template)"><Pencil /></IconButton>
          <IconButton variant="danger" :title="`Delete ${template.name}`" @click="destroy(template)"
            ><Trash2
          /></IconButton>
        </li>
      </ul>
      <p class="mt-3 text-xs text-faint">
        Applying a template copies its values. Editing or deleting a template leaves existing links unchanged.
      </p>
    </div>

    <UtmTemplateDialog v-model:open="dialogOpen" :template="editing" />
  </AuthenticatedLayout>
</template>
