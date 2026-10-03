<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { FileText, Link2 } from '@lucide/vue';
import { computed } from 'vue';

import Button from '@/Components/ui/Button.vue';
import Dialog from '@/Components/ui/Dialog.vue';
import Field from '@/Components/ui/Field.vue';
import Input from '@/Components/ui/Input.vue';
import SegmentedControl from '@/Components/ui/SegmentedControl.vue';
import Select from '@/Components/ui/Select.vue';
import type { SelectOption } from '@/lib/controls';

import PayloadFields from './PayloadFields.vue';
import ShortLinkPicker from './ShortLinkPicker.vue';
import type { PayloadDescriptors, ShortLinkOption } from './types';
import { payloadDefaults, payloadHint } from './types';
import { useShortLinkSearch } from './useShortLinkSearch';

type TargetType = 'short_link' | 'direct';

const props = defineProps<{
  payloadTypes: Record<string, string>;
  payloadDescriptors: PayloadDescriptors;
  shortLinks: ShortLinkOption[];
}>();

const open = defineModel<boolean>('open', { required: true });

const form = useForm({
  name: '',
  target_type: 'short_link' as TargetType,
  short_link_id: '' as string | number,
  payload_type: 'url',
  payload: payloadDefaults('url', props.payloadDescriptors),
});

const { search, links } = useShortLinkSearch(
  props.shortLinks,
  computed(() => form.short_link_id),
);

const targetOptions: { value: TargetType; label: string; icon: unknown }[] = [
  { value: 'short_link', label: 'Short link', icon: Link2 },
  { value: 'direct', label: 'Content', icon: FileText },
];

const typeOptions = computed<SelectOption[]>(() =>
  Object.entries(props.payloadTypes).map(([value, label]) => ({ value, label })),
);

const targetType = computed<TargetType>({
  get: () => form.target_type,
  set: (value) => {
    form.target_type = value;
    form.clearErrors();
  },
});

const shortLinkId = computed({
  get: () => form.short_link_id,
  set: (value: string | number) => {
    form.short_link_id = value;
    form.clearErrors('short_link_id');
  },
});

const hint = computed(() =>
  form.target_type === 'short_link'
    ? 'Scans follow the short link’s routing and count toward its analytics.'
    : payloadHint(form.payload_type, props.payloadDescriptors),
);

function setPayloadType(type: string | number | null) {
  const next = String(type ?? 'url');
  if (next === form.payload_type) return;
  form.payload_type = next;
  form.payload = payloadDefaults(next, props.payloadDescriptors);
  form.clearErrors();
}

function resetForm() {
  form.reset();
  form.clearErrors();
  form.payload = payloadDefaults('url', props.payloadDescriptors);
  search.value = '';
}

function close() {
  open.value = false;
  setTimeout(resetForm, 200);
}

function onOpenChange(value: boolean) {
  if (value) open.value = true;
  else close();
}

function submit() {
  form
    .transform((data) =>
      data.target_type === 'short_link'
        ? { name: data.name, short_link_id: data.short_link_id }
        : { name: data.name, payload_type: data.payload_type, payload: data.payload },
    )
    .post(route('qr-codes.store'), {
      preserveScroll: true,
      onSuccess: () => close(),
    });
}
</script>

<template>
  <Dialog
    :open="open"
    size="lg"
    title="New QR code"
    description="Choose what the code opens. You can style it in the next step."
    @update:open="onOpenChange"
  >
    <form @submit.prevent="submit">
      <div class="grid max-h-[min(60vh,560px)] gap-5 overflow-y-auto px-5 pb-5 pt-4">
        <div class="grid gap-2">
          <SegmentedControl v-model="targetType" :options="targetOptions" label="QR code target" class="w-full" />
          <p class="text-xs leading-relaxed text-faint">{{ hint }}</p>
        </div>

        <ShortLinkPicker
          v-if="form.target_type === 'short_link'"
          v-model="shortLinkId"
          v-model:search="search"
          :links="links"
          :error="form.errors.short_link_id"
        />

        <template v-else>
          <Field label="Type" :error="form.errors.payload_type">
            <Select :model-value="form.payload_type" :options="typeOptions" @update:model-value="setPayloadType" />
          </Field>
          <PayloadFields
            v-model="form.payload"
            :type="form.payload_type"
            :descriptors="payloadDescriptors"
            :errors="form.errors"
          />
        </template>

        <Field label="Name" :error="form.errors.name">
          <Input v-model="form.name" placeholder="Lobby Wi-Fi, business card, event poster" />
        </Field>
      </div>

      <footer class="flex items-center justify-end gap-2 border-t px-5 py-3.5">
        <Button variant="ghost" type="button" @click="close">Cancel</Button>
        <Button :loading="form.processing">Create QR code</Button>
      </footer>
    </form>
  </Dialog>
</template>
