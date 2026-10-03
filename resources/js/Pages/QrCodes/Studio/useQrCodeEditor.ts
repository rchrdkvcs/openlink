import { useForm, type InertiaForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

import { toast } from '@/lib/toast';

import { hasPendingLogoChange, initialEditorFields, toUpdatePayload, type QrEditorFields } from '../qrForm';
import { panelForErrors, type Panel } from '../qrPanels';
import { previewUrl as buildPreviewUrl } from '../qrUrls';
import type { PayloadDescriptors, QrCodeRecord } from '../types';

export type QrEditorForm = InertiaForm<QrEditorFields>;

export function useQrCodeEditor(qr: QrCodeRecord, descriptors: PayloadDescriptors, canEdit: () => boolean) {
  const form: QrEditorForm = useForm(initialEditorFields(qr, descriptors));
  const panel = ref<Panel>('content');
  const previewVersion = ref(0);

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

    form.transform(toUpdatePayload).post(route('qr-codes.update', qr.token), {
      preserveScroll: true,
      onSuccess: () => {
        clearLogoChange();
        previewVersion.value += 1;
        form.defaults({ ...form.data(), logo: null, remove_logo: false });
        toast({ title: 'QR code saved', tone: 'success' });
      },
      onError: revealErrors,
    });
  }

  function discard() {
    form.reset();
    form.clearErrors();
    clearLogoChange();
  }

  function pickLogo(file: File | null) {
    form.logo = file;
    if (file) form.remove_logo = false;
  }

  function removeLogo() {
    form.logo = null;
    form.remove_logo = true;
  }

  return { form, panel, isDirty, previewUrl, save, discard, pickLogo, removeLogo };
}
