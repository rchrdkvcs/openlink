<script setup lang="ts">
import { ChevronRight, Dices, History } from '@lucide/vue';
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue';

import Field from '@/Components/ui/Field.vue';
import IconButton from '@/Components/ui/IconButton.vue';
import Input from '@/Components/ui/Input.vue';
import Select from '@/Components/ui/Select.vue';
import Switch from '@/Components/ui/Switch.vue';
import TagInput from '@/Components/ui/TagInput.vue';
import type { DestinationHealth } from '@/lib/destinationHealth';
import { randomSlug } from '@/lib/links';
import { hasOpenFloatingLayer } from '@/lib/overlays';
import { useShell } from '@/lib/shell';
import { toForm, type ShortLinkForm } from '@/lib/shortLinks/shortLinkForm';
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
import { changedFields } from './routing/inspectorDraft';
import RoutingEditorModal from './routing/RoutingEditorModal.vue';

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

const routingOpen = ref(false);
let inspectorChanges: Partial<ShortLinkForm> = {};

function openRouting() {
  inspectorChanges = changedFields(form.data(), toForm(props.link));
  routingOpen.value = true;
}

async function routingSettled() {
  await nextTick();
  Object.assign(form, inspectorChanges);
}

const healthOpen = ref(false);
const historyOpen = ref(false);
const historyButton = ref<HTMLButtonElement | null>(null);
const details = ref<HTMLFormElement | null>(null);
const detailsScroll = ref(0);
const domainOptions = computed(() => props.domains.map((domain) => ({ value: domain.id, label: domain.hostname })));

function openHistory() {
  detailsScroll.value = details.value?.scrollTop ?? 0;
  historyOpen.value = true;
}

async function closeHistory() {
  historyOpen.value = false;
  await nextTick();
  if (details.value) details.value.scrollTop = detailsScroll.value;
  historyButton.value?.focus({ preventScroll: true });
}

watch(
  () => props.link.id,
  () => (historyOpen.value = false),
);

const shortUrlChanged = computed(
  () => form.slug !== props.link.slug || Number(form.domain_id) !== props.link.domain.id,
);

function onKeydown(event: KeyboardEvent) {
  if (routingOpen.value) return;
  if ((event.metaKey || event.ctrlKey) && event.key.toLowerCase() === 's') {
    event.preventDefault();
    if (!historyOpen.value) save();
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

    <InspectorHistory
      v-if="historyOpen"
      :link="link"
      :has-unsaved-changes="canEdit && form.isDirty"
      @back="closeHistory"
    />

    <form
      v-show="!historyOpen"
      ref="details"
      class="min-h-0 flex-1 overflow-y-auto overscroll-contain"
      @submit.prevent="save"
    >
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

          <Field label="Domain" :error="form.errors.domain_id">
            <Select v-model="form.domain_id" :options="domainOptions" :disabled="!canEdit" />
          </Field>
          <div class="grid gap-1.5">
            <label for="inspector-slug" class="text-[13px] font-medium text-foreground">Slug</label>
            <div class="flex items-center gap-2">
              <Input
                id="inspector-slug"
                v-model="form.slug"
                spellcheck="false"
                placeholder="slug"
                class="min-w-0 flex-1"
              />
              <IconButton v-if="canEdit" title="Random slug" @click="form.slug = randomSlug()"><Dices /></IconButton>
            </div>
            <p v-if="form.errors.slug" class="text-xs text-danger">{{ form.errors.slug }}</p>
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
          <InspectorRouting :rules="link.routing_rules ?? []" :link-id="link.id" @open="openRouting" />
          <InspectorHealth
            v-model:open="healthOpen"
            :link="link"
            :health="health"
            :can-check="canEdit"
            :unsaved="form.isDirty"
          />
        </div>
      </fieldset>

      <div class="mx-5 mb-4 overflow-hidden rounded-xl border bg-surface">
        <button
          ref="historyButton"
          type="button"
          class="flex min-h-11 w-full items-center gap-2.5 px-3.5 py-2.5 text-start transition-colors hover:bg-elevated/40 focus-visible:bg-elevated/40 focus-visible:outline-none"
          @click="openHistory"
        >
          <History class="h-4 w-4 shrink-0 text-faint" />
          <span class="flex-1 text-[13px] font-medium">History</span>
          <span class="text-[13px] text-faint">{{
            link.revision ? `Revision ${link.revision}` : 'No changes recorded'
          }}</span>
          <ChevronRight class="h-3.5 w-3.5 shrink-0 text-faint" />
        </button>
      </div>
    </form>

    <RoutingEditorModal
      v-if="routingOpen"
      :link="link"
      :routing-schema="routingSchema"
      :inspector-dirty="canEdit && form.isDirty"
      @close="routingOpen = false"
      @settled="routingSettled"
    />

    <InspectorSaveBar
      :visible="!historyOpen && canEdit && form.isDirty"
      :has-errors="hasErrors"
      :processing="form.processing"
      :can-save="destinationValid"
      @discard="discard"
      @save="save"
    />
  </aside>
</template>
