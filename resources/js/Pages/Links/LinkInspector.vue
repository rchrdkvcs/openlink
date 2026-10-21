<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref } from 'vue';

import ShortUrlComposer from '@/Components/Links/ShortUrlComposer.vue';
import Field from '@/Components/ui/Field.vue';
import Input from '@/Components/ui/Input.vue';
import Select from '@/Components/ui/Select.vue';
import Switch from '@/Components/ui/Switch.vue';
import TagInput from '@/Components/ui/TagInput.vue';
import type { DestinationHealth } from '@/lib/destinationHealth';
import { hasOpenFloatingLayer } from '@/lib/overlays';
import { useShell } from '@/lib/shell';
import type { UtmTemplate } from '@/lib/utm';
import type { Domain, Folder, Tag } from '@/types/payloads';
import type { RoutingSchema, ShortLink } from '@/types/shortLinks';

import InspectorHeader from './inspector/InspectorHeader.vue';
import InspectorHealth from './inspector/InspectorHealth.vue';
import InspectorHistory from './inspector/InspectorHistory.vue';
import InspectorOptions from './inspector/InspectorOptions.vue';
import InspectorPreview from './inspector/InspectorPreview.vue';
import InspectorRouting from './inspector/InspectorRouting.vue';
import InspectorSaveBar from './inspector/InspectorSaveBar.vue';
import InspectorUtm from './inspector/InspectorUtm.vue';
import { useShortLinkEditor } from './inspector/useShortLinkEditor';

const props = defineProps<{
  link: ShortLink;
  domains: Domain[];
  folders: Folder[];
  knownTags: Tag[];
  routingSchema: RoutingSchema;
  utmTemplates: UtmTemplate[];
  health: Map<string, DestinationHealth>;
}>();

const emit = defineEmits<{ close: [] }>();

const { canEdit, canManage } = useShell();

const { form, sections, save, discard, destinationValid, hasErrors } = useShortLinkEditor(
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

const healthOpen = ref(false);

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
          <InspectorUtm
            v-model="form.destination_url"
            v-model:open="sections.utm"
            :templates="utmTemplates"
            :can-manage-templates="canManage"
            :disabled="!canEdit"
          />
          <InspectorOptions v-model:sections="sections" :form="form" :successful-visits="link.successful_visits" />
          <InspectorPreview v-model:open="sections.preview" :form="form" :link="link" />
          <InspectorRouting
            :rules="link.routing_rules ?? []"
            :schema="routingSchema"
            :link-id="link.id"
            :dirty="canEdit && form.isDirty"
          />
          <InspectorHealth
            v-model:open="healthOpen"
            :link="link"
            :health="health"
            :can-check="canEdit"
            :unsaved="form.isDirty"
          />
        </div>
      </fieldset>

      <InspectorHistory :link="link" :has-unsaved-changes="canEdit && form.isDirty" />
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
