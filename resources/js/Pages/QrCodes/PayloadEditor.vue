<script setup lang="ts">
import { computed } from 'vue';

import Field from '@/Components/ui/Field.vue';
import Select from '@/Components/ui/Select.vue';

import PayloadFields from './PayloadFields.vue';
import { payloadTypeOptions } from './qrPayload';
import { setPayloadType, type TargetForm } from './qrTarget';
import type { PayloadDescriptors } from './types';

const props = defineProps<{
  form: TargetForm;
  payloadTypes: Record<string, string>;
  payloadDescriptors: PayloadDescriptors;
}>();

const typeOptions = computed(() => payloadTypeOptions(props.payloadTypes));

function onTypeChange(type: unknown) {
  setPayloadType(props.form, type, props.payloadDescriptors);
}
</script>

<template>
  <Field label="Type" :error="form.errors.payload_type">
    <Select :model-value="form.payload_type" :options="typeOptions" @update:model-value="onTypeChange" />
  </Field>
  <PayloadFields
    v-model="form.payload"
    :type="form.payload_type"
    :descriptors="payloadDescriptors"
    :errors="form.errors"
  />
</template>
