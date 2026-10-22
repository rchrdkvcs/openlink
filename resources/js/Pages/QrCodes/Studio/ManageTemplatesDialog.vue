<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { MoreHorizontal, Pencil, RefreshCw, Trash2 } from '@lucide/vue';

import Badge from '@/Components/ui/Badge.vue';
import Button from '@/Components/ui/Button.vue';
import Dialog from '@/Components/ui/Dialog.vue';
import Menu from '@/Components/ui/Menu.vue';
import MenuItem from '@/Components/ui/MenuItem.vue';
import MenuSeparator from '@/Components/ui/MenuSeparator.vue';
import { confirmAction } from '@/lib/confirm';
import { toast } from '@/lib/toast';

import type { QrCodeTemplate } from '../types';

const props = defineProps<{
  templates: QrCodeTemplate[];
  qrToken: string;
  hasUnsavedChanges: boolean;
}>();

const open = defineModel<boolean>('open', { required: true });

const emit = defineEmits<{ rename: [template: QrCodeTemplate] }>();

async function replace(template: QrCodeTemplate) {
  const confirmed = await confirmAction({
    title: `Replace “${template.name}”?`,
    message: 'The template takes this QR code’s saved design. QR codes that already use it keep their current design.',
    confirmLabel: 'Replace design',
  });
  if (!confirmed) return;

  router.patch(
    route('qr-code-templates.update', template.id),
    { qr_code: props.qrToken },
    {
      preserveScroll: true,
      onSuccess: () => toast({ title: 'Template updated', tone: 'success' }),
    },
  );
}

async function destroy(template: QrCodeTemplate) {
  const confirmed = await confirmAction({
    title: `Delete “${template.name}”?`,
    message: 'QR codes that already use this template keep their design and logo.',
    confirmLabel: 'Delete template',
    destructive: true,
  });
  if (!confirmed) return;

  router.delete(route('qr-code-templates.destroy', template.id), {
    preserveScroll: true,
  });
}
</script>

<template>
  <Dialog
    v-model:open="open"
    size="md"
    title="Templates"
    description="Workspace designs anyone who edits QR codes can apply."
  >
    <ul v-if="templates.length" class="grid max-h-[min(50vh,420px)] gap-0.5 overflow-y-auto px-3 pb-3 pt-3">
      <li v-for="template in templates" :key="template.id" class="flex items-center gap-3 rounded-lg px-2 py-1.5">
        <span
          class="h-5 w-5 shrink-0 rounded-md border"
          :style="{
            backgroundColor: template.background_transparent ? 'transparent' : template.background_color,
          }"
        >
          <span class="m-1 block h-3 w-3 rounded-sm" :style="{ backgroundColor: template.foreground_color }" />
        </span>
        <span class="min-w-0 flex-1 truncate text-[13px] text-foreground">{{ template.name }}</span>
        <Badge v-if="template.has_logo">Logo</Badge>
        <Menu width="w-60">
          <template #trigger>
            <Button
              variant="ghost"
              size="sm"
              type="button"
              class="w-7 px-0"
              :aria-label="`Actions for ${template.name}`"
            >
              <MoreHorizontal />
            </Button>
          </template>
          <MenuItem :icon="Pencil" @select="emit('rename', template)">Rename</MenuItem>
          <MenuItem :icon="RefreshCw" :disabled="hasUnsavedChanges" @select="replace(template)">
            {{ hasUnsavedChanges ? 'Save changes to replace' : 'Replace with this design' }}
          </MenuItem>
          <MenuSeparator />
          <MenuItem :icon="Trash2" destructive @select="destroy(template)">Delete</MenuItem>
        </Menu>
      </li>
    </ul>
    <p v-else class="px-5 pb-5 pt-3 text-[13px] text-muted">No templates yet.</p>
  </Dialog>
</template>
