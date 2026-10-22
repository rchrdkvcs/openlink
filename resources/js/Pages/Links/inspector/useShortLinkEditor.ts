import { useForm } from '@inertiajs/vue3';
import { computed, ref, shallowRef, watch } from 'vue';

import { isLikelyUrl, normalizeUrl } from '@/lib/links';
import {
  closedSections,
  needsMultipart,
  sectionsWithErrors,
  type ShortLinkForm,
  toForm,
  toPayload,
} from '@/lib/shortLinks/shortLinkForm';
import { toast } from '@/lib/toast';
import type { ShortLink } from '@/types/shortLinks';

import { changedFields, type RoutingSaveReceipt } from '../routing/inspectorDraft';

export function useShortLinkEditor(link: () => ShortLink, canEdit: () => boolean) {
  const base = shallowRef(link());
  const form = useForm<ShortLinkForm>(toForm(link()));
  const sections = ref(closedSections());

  function load(target: ShortLink, resetSections: boolean) {
    base.value = target;
    form.defaults(toForm(target));
    form.reset();
    form.clearErrors();
    if (resetSections) sections.value = closedSections();
  }

  watch(link, (current, previous) => {
    if (current.id !== previous?.id) load(current, true);
    else if (!form.isDirty && !form.processing) load(current, false);
  });

  function acceptRoutingSave(receipt: RoutingSaveReceipt) {
    const current = link();
    if (
      receipt.link_id !== base.value.id ||
      receipt.link_id !== current.id ||
      receipt.expected_revision !== base.value.revision ||
      receipt.revision !== current.revision
    )
      return;
    const changes = changedFields(form.data(), toForm(base.value));
    load(current, false);
    Object.assign(form, changes);
  }

  function save() {
    if (!canEdit() || !form.isDirty || form.processing) return;

    const target = base.value;
    const options = {
      preserveScroll: true,
      preserveState: true,
      onSuccess: () => {
        load(link(), false);
        toast({ title: 'Changes saved', tone: 'success', duration: 2000 });
      },
      onError: (errors: Record<string, string>) => {
        if (errors.expected_revision) {
          toast({
            title: 'This link changed',
            description: errors.expected_revision,
            tone: 'danger',
            duration: 6000,
          });
        }
        sectionsWithErrors(form.errors).forEach((section) => (sections.value[section] = true));
      },
    };
    const request = form.transform((data) => ({
      ...toPayload(data, { hasPassword: target.has_password }),
      expected_revision: target.revision,
    }));
    const url = route('short-links.update', target.id);

    if (needsMultipart(form)) request.post(url, options);
    else request.patch(url, options);
  }

  return {
    form,
    sections,
    save,
    acceptRoutingSave,
    stale: computed(() => base.value.revision !== link().revision),
    discard: () => load(link(), false),
    destinationValid: computed(() => isLikelyUrl(normalizeUrl(form.destination_url))),
    hasErrors: computed(() => Object.keys(form.errors).length > 0),
  };
}
