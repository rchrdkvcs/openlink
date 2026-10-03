<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft } from '@lucide/vue';
import { computed, ref } from 'vue';

import PageHeader from '@/Components/ui/PageHeader.vue';
import SegmentedControl from '@/Components/ui/SegmentedControl.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { useShell } from '@/lib/shell';

import { studioDescription, targetLabel } from './qrLabels';
import { PANELS } from './qrPanels';
import AdvancedPanel from './Studio/AdvancedPanel.vue';
import ContentPanel from './Studio/ContentPanel.vue';
import QrCodeActions from './Studio/QrCodeActions.vue';
import QrCodePreview from './Studio/QrCodePreview.vue';
import StudioSaveBar from './Studio/StudioSaveBar.vue';
import StylePanel from './Studio/StylePanel.vue';
import { useQrCodeEditor } from './Studio/useQrCodeEditor';
import { useSaveShortcut } from './Studio/useSaveShortcut';
import type { PayloadDescriptors, QrCodeRecord, ShortLinkOption } from './types';

const props = defineProps<{
  qr: QrCodeRecord;
  payloadTypes: Record<string, string>;
  payloadDescriptors: PayloadDescriptors;
  shortLinks: ShortLinkOption[];
}>();

const { canEdit } = useShell();

const { form, panel, isDirty, previewUrl, save, discard, pickLogo, removeLogo } = useQrCodeEditor(
  props.qr,
  props.payloadDescriptors,
  () => canEdit.value,
);

const exportSize = ref(props.qr.size);

const description = computed(() =>
  studioDescription(
    targetLabel(form.target_type, form.payload_type, props.payloadTypes),
    props.qr.is_direct,
    props.qr.scans,
  ),
);

useSaveShortcut(() => {
  if (isDirty.value) save();
});
</script>

<template>
  <Head :title="qr.name" />

  <AuthenticatedLayout>
    <div class="flex flex-col xl:h-full xl:flex-row">
      <div
        class="flex min-w-0 flex-1 flex-col px-4 py-6 [scrollbar-gutter:stable] sm:px-6 lg:px-8 lg:py-8 xl:overflow-y-auto xl:overscroll-contain"
      >
        <PageHeader :title="qr.name" :description="description">
          <template #eyebrow>
            <Link
              :href="route('qr-codes.index')"
              class="mb-1 inline-flex items-center gap-1 text-xs font-medium text-faint transition-colors hover:text-foreground"
            >
              <ArrowLeft class="h-3.5 w-3.5" /> QR codes
            </Link>
          </template>
          <template #actions>
            <QrCodeActions :qr="qr" :export-size="exportSize" />
          </template>
        </PageHeader>

        <div class="flex flex-1 items-center justify-center py-8 xl:py-10">
          <QrCodePreview
            v-model:export-size="exportSize"
            :qr="qr"
            :src="previewUrl"
            :target-type="form.target_type"
            :background-color="form.background_color"
            :transparent="form.background_transparent"
            :pending-logo="form.logo !== null"
          />
        </div>
      </div>

      <aside class="flex flex-col border-t bg-canvas xl:h-full xl:w-[420px] xl:shrink-0 xl:border-l xl:border-t-0">
        <div class="px-5 pb-4 pt-5">
          <SegmentedControl v-model="panel" :options="PANELS" label="Settings section" class="w-full" />
        </div>

        <form class="min-h-0 flex-1 overflow-y-auto overscroll-contain px-5 pb-6" @submit.prevent="save">
          <fieldset :disabled="!canEdit" class="contents">
            <ContentPanel
              v-show="panel === 'content'"
              :form="form"
              :qr="qr"
              :payload-types="payloadTypes"
              :payload-descriptors="payloadDescriptors"
              :short-links="shortLinks"
            />
            <StylePanel
              v-show="panel === 'style'"
              :form="form"
              :has-saved-logo="qr.has_logo"
              @pick-logo="pickLogo"
              @remove-logo="removeLogo"
            />
            <AdvancedPanel v-show="panel === 'advanced'" :form="form" />
          </fieldset>
        </form>

        <StudioSaveBar
          :visible="canEdit && isDirty"
          :processing="form.processing"
          :has-errors="form.hasErrors"
          @save="save"
          @discard="discard"
        />
      </aside>
    </div>
  </AuthenticatedLayout>
</template>
