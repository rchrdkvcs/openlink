import { computed, type Ref, ref, watch } from 'vue';

import { normalizeUrl } from '@/lib/links';
import {
  applyTemplate,
  emptyUtm,
  hasUrlBase,
  presentUtm,
  readUtm,
  templateChanges,
  type UtmKey,
  type UtmTemplate,
  writeUtm,
} from '@/lib/utm';

export type UtmBuilderOptions = {
  destination: Ref<string>;
  templates: () => UtmTemplate[];
  disabled: () => boolean;
  canManageTemplates: () => boolean;
};

export function useUtmBuilder({ destination, templates, disabled, canManageTemplates }: UtmBuilderOptions) {
  const values = computed(() => readUtm(destination.value));
  const filled = computed(() => presentUtm(destination.value));
  const summary = computed(() =>
    filled.value.length
      ? filled.value
          .map((key) => values.value[key])
          .filter(Boolean)
          .join(' · ') || 'Empty parameters'
      : 'None',
  );
  const hasDestination = computed(() => hasUrlBase(destination.value));
  const fieldsDisabled = computed(() => disabled() || !hasDestination.value);

  const selectedId = ref('');
  const selected = computed(() => templates().find((template) => String(template.id) === selectedId.value));
  const changes = computed(() => (selected.value ? templateChanges(destination.value, selected.value) : []));
  const applied = computed(() =>
    selected.value ? applyTemplate(destination.value, selected.value) : destination.value,
  );
  const applicable = computed(() => applied.value !== destination.value);
  const canApply = computed(() => !fieldsDisabled.value && applicable.value);

  const templateOptions = computed(() => [
    { value: '', label: 'Choose a template' },
    ...templates().map((template) => ({ value: String(template.id), label: template.name })),
  ]);

  const showCreateTemplateLink = computed(() => templates().length === 0 && canManageTemplates());

  watch(templates, () => {
    if (selectedId.value && !selected.value) selectedId.value = '';
  });

  function update(key: UtmKey, value: string) {
    if (fieldsDisabled.value) return;
    destination.value = writeUtm(destination.value, { [key]: value });
  }

  function removeAll() {
    if (fieldsDisabled.value) return;
    destination.value = writeUtm(destination.value, emptyUtm());
    selectedId.value = '';
  }

  function apply() {
    if (!canApply.value) return;
    destination.value = applied.value;
    selectedId.value = '';
  }

  return {
    values,
    filled,
    summary,
    hasDestination,
    fieldsDisabled,
    selectedId,
    selected,
    changes,
    applicable,
    canApply,
    templateOptions,
    showCreateTemplateLink,
    finalUrl: computed(() => normalizeUrl(destination.value)),
    update,
    removeAll,
    apply,
  };
}
