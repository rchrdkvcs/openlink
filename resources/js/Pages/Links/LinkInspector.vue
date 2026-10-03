<script setup lang="ts">
import { Archive, Trash2 } from '@lucide/vue';
import { computed, onMounted, onUnmounted } from 'vue';

import ShortUrlComposer from '@/Components/Links/ShortUrlComposer.vue';
import Button from '@/Components/ui/Button.vue';
import Field from '@/Components/ui/Field.vue';
import Input from '@/Components/ui/Input.vue';
import Select from '@/Components/ui/Select.vue';
import Switch from '@/Components/ui/Switch.vue';
import TagInput from '@/Components/ui/TagInput.vue';
import { archiveLink, deleteLink } from '@/lib/linkActions';
import { hasOpenFloatingLayer } from '@/lib/overlays';
import { useShell } from '@/lib/shell';
import type { Domain, Folder, Tag } from '@/types/payloads';
import type { RoutingSchema, ShortLink } from '@/types/shortLinks';

import InspectorHeader from './inspector/InspectorHeader.vue';
import InspectorOptions from './inspector/InspectorOptions.vue';
import InspectorRouting from './inspector/InspectorRouting.vue';
import InspectorSaveBar from './inspector/InspectorSaveBar.vue';
import { useShortLinkEditor } from './inspector/useShortLinkEditor';

const props = defineProps<{
  link: ShortLink;
  domains: Domain[];
  folders: Folder[];
  knownTags: Tag[];
  routingSchema: RoutingSchema;
}>();

const emit = defineEmits<{ close: [] }>();

const { canEdit } = useShell();

const { form, sections, save, discard, destinationValid, routingHasErrors, hasErrors } = useShortLinkEditor(
  () => props.link,
  () => canEdit.value,
);

const folderOptions = computed(() => [
  { value: '', label: 'No folder' },
  ...props.folders.map((folder) => ({
    value: String(folder.id),
    label: folder.name,
  })),
]);

const shortUrlChanged = computed(
  () => form.slug !== props.link.slug || Number(form.domain_id) !== props.link.domain.id,
);

function onKeydown(event: KeyboardEvent) {
  if ((event.metaKey || event.ctrlKey) && event.key.toLowerCase() === 's') {
    event.preventDefault();
    save();
    return;
  }

  const target = event.target as HTMLElement | null;
  if (
    event.key === 'Escape' &&
    !hasOpenFloatingLayer() &&
    !document.querySelector('[role="dialog"], [role="alertdialog"]') &&
    !target?.closest('input, textarea')
  ) {
    emit('close');
  }
}

onMounted(() => document.addEventListener('keydown', onKeydown));
onUnmounted(() => document.removeEventListener('keydown', onKeydown));
</script>

<template>
  <aside class="flex h-full flex-col overflow-hidden bg-canvas" aria-label="Link details">
    <InspectorHeader :link="link" :preview-url="form.destination_url" @close="emit('close')" />

    <form class="min-h-0 flex-1 overflow-y-auto overscroll-contain" @submit.prevent="save">
      <fieldset :disabled="!canEdit" class="contents">
        <div class="grid grid-cols-3 divide-x border-b">
          <div class="px-5 py-3">
            <p class="text-xs text-faint">Visits</p>
            <p class="mt-0.5 text-[15px] font-semibold tabular-nums">
              {{ link.visits.toLocaleString() }}
            </p>
          </div>
          <div class="px-5 py-3">
            <p class="text-xs text-faint">Scans</p>
            <p class="mt-0.5 text-[15px] font-semibold tabular-nums">
              {{ link.scans.toLocaleString() }}
            </p>
          </div>
          <label class="flex cursor-pointer flex-col px-5 py-3">
            <span class="text-xs text-faint">{{ form.is_enabled ? 'Enabled' : 'Disabled' }}</span>
            <span class="mt-1.5 flex items-center gap-2"><Switch v-model="form.is_enabled" /></span>
          </label>
        </div>

        <div class="space-y-4 px-5 py-4">
          <Field label="Destination" :error="form.errors.destination_url">
            <Input v-model="form.destination_url" spellcheck="false" placeholder="https://example.com/page" />
          </Field>

          <div class="grid gap-1.5">
            <span class="text-[13px] font-medium text-foreground">Short link</span>
            <ShortUrlComposer
              v-model:domain-id="form.domain_id"
              v-model:slug="form.slug"
              :domains="domains"
              :disabled="!canEdit"
              slug-placeholder="slug"
            />
            <p v-if="form.errors.slug || form.errors.domain_id" class="text-xs text-danger">
              {{ form.errors.slug ?? form.errors.domain_id }}
            </p>
            <p v-else-if="shortUrlChanged" class="text-xs text-warning">
              Links already shared will stop working. QR codes keep working.
            </p>
          </div>

          <div class="grid gap-4">
            <Field label="Folder" :error="form.errors.folder_id">
              <Select v-model="form.folder_id" :options="folderOptions" :disabled="!canEdit" />
            </Field>
            <Field label="Tags" :error="form.errors.tags">
              <TagInput v-model="form.tags" :suggestions="knownTags" />
            </Field>
          </div>
        </div>

        <div class="mx-5 mb-4 divide-y overflow-hidden rounded-xl border bg-surface">
          <InspectorOptions v-model:sections="sections" :form="form" :successful-visits="link.successful_visits" />
          <InspectorRouting
            v-model="form.routing_rules"
            v-model:open="sections.routing"
            :schema="routingSchema"
            :errors="form.errors"
            :has-errors="routingHasErrors"
            :default-destination="form.destination_url"
          />
        </div>

        <div v-if="canEdit" class="flex items-center gap-1 px-3 pb-6">
          <Button
            v-if="link.status !== 'archived'"
            variant="ghost"
            size="sm"
            type="button"
            @click="archiveLink(link, () => emit('close'))"
          >
            <Archive class="h-3.5 w-3.5" /> Archive
          </Button>
          <Button
            variant="ghost"
            size="sm"
            type="button"
            class="hover:bg-danger/10 hover:text-danger"
            @click="deleteLink(link, () => emit('close'))"
          >
            <Trash2 class="h-3.5 w-3.5" /> Delete
          </Button>
        </div>
      </fieldset>
    </form>

    <InspectorSaveBar
      :visible="canEdit && form.isDirty"
      :has-errors="hasErrors"
      :processing="form.processing"
      :can-save="destinationValid"
      @discard="discard"
      @save="save"
    />
  </aside>
</template>
