import { useForm, type InertiaForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

import { toast } from '@/lib/toast';

import { hasPendingLogoChange, initialEditorFields, toUpdatePayload, type QrEditorFields } from '../qrForm';
import { panelForErrors, type Panel } from '../qrPanels';
import { templateFields, withTemplateDesign } from '../qrTemplates';
import { previewUrl as buildPreviewUrl } from '../qrUrls';
import type { PayloadDescriptors, QrCodeRecord, QrCodeTemplate } from '../types';

export type QrEditorForm = InertiaForm<QrEditorFields>;

export function useQrCodeEditor(qr: QrCodeRecord, descriptors: PayloadDescriptors, canEdit: () => boolean) {
  const form: QrEditorForm = useForm(initialEditorFields(qr, descriptors));
  const panel = ref<Panel>('content');
  const previewVersion = ref(0);
  const appliedTemplate = ref<QrCodeTemplate | null>(null);

  const isDirty = computed(() => form.isDirty || hasPendingLogoChange(form));
  const previewUrl = computed(() => buildPreviewUrl(qr.token, form, previewVersion.value));

  function clearLogoChange() {
    form.logo = null;
    form.remove_logo = false;
  }

  function revealErrors() {
    panel.value = panelForErrors(Object.keys(form.errors)) ?? panel.value;
  }

  function save() {
    if (!canEdit() || form.processing) return;

    form
      .transform((data) => withTemplateDesign(toUpdatePayload(data), appliedTemplate.value))
      .post(route('qr-codes.update', qr.token), {
        preserveScroll: true,
        onSuccess: () => {
          clearLogoChange();
          form.template_id = null;
          appliedTemplate.value = null;
          previewVersion.value += 1;
          form.defaults({ ...form.data(), logo: null, remove_logo: false, template_id: null });
          toast({ title: 'QR code saved', tone: 'success' });
        },
        onError: revealErrors,
      });
  }

  function discard() {
    form.reset();
    form.clearErrors();
    clearLogoChange();
    appliedTemplate.value = null;
  }

  function applyTemplate(template: QrCodeTemplate) {
    if (!canEdit()) return;

    Object.assign(form, templateFields(template));
    form.clearErrors();
    appliedTemplate.value = template;
  }

  function pickLogo(file: File | null) {
    form.logo = file;
    if (file) form.remove_logo = false;
  }

  function removeLogo() {
    form.logo = null;
    form.remove_logo = true;
  }

  return {
    form,
    panel,
    isDirty,
    previewUrl,
    previewVersion,
    appliedTemplate,
    save,
    discard,
    applyTemplate,
    pickLogo,
    removeLogo,
  };
}
