import { useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

import { isLikelyUrl, normalizeUrl } from '@/lib/links';
import {
  closedSections,
  sectionsWithErrors,
  type ShortLinkForm,
  toForm,
  toPayload,
} from '@/lib/shortLinks/shortLinkForm';
import { toast } from '@/lib/toast';
import type { ShortLink } from '@/types/shortLinks';

export function useShortLinkEditor(link: () => ShortLink, canEdit: () => boolean) {
  const form = useForm<ShortLinkForm>(toForm(link()));
  const sections = ref(closedSections());

  function load(target: ShortLink, resetSections: boolean) {
    form.defaults(toForm(target));
    form.reset();
    form.clearErrors();
    if (resetSections) sections.value = closedSections();
  }

  watch(link, (current, previous) => load(current, current.id !== previous?.id));

  function save() {
    if (!canEdit() || !form.isDirty || form.processing) return;

    const target = link();
    form
      .transform((data) => toPayload(data, { hasPassword: target.has_password }))
      .patch(route('short-links.update', target.id), {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => toast({ title: 'Changes saved', tone: 'success', duration: 2000 }),
        onError: () => sectionsWithErrors(form.errors).forEach((section) => (sections.value[section] = true)),
      });
  }

  const errorSections = computed(() => sectionsWithErrors(form.errors));

  return {
    form,
    sections,
    save,
    discard: () => load(link(), false),
    destinationValid: computed(() => isLikelyUrl(normalizeUrl(form.destination_url))),
    routingHasErrors: computed(() => errorSections.value.includes('routing')),
    hasErrors: computed(() => Object.keys(form.errors).length > 0),
  };
}
