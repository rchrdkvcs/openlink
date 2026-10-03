<script setup lang="ts">
import { computed } from 'vue';

import Checkbox from '@/Components/ui/Checkbox.vue';
import Field from '@/Components/ui/Field.vue';
import Input from '@/Components/ui/Input.vue';
import Select from '@/Components/ui/Select.vue';
import Textarea from '@/Components/ui/Textarea.vue';

import { flagValue, inputValue, isFieldDisabled, payloadFields, textValue } from './qrPayload';
import type { PayloadDescriptors, PayloadValue, PayloadValues } from './types';

const props = defineProps<{
  type: string;
  descriptors: PayloadDescriptors;
  errors?: Partial<Record<string, string>>;
}>();

const payload = defineModel<PayloadValues>({ required: true });

const fields = computed(() => payloadFields(props.type, props.descriptors));

function update(key: string, value: PayloadValue | undefined) {
  payload.value[key] = value ?? null;
}

function error(key: string) {
  return props.errors?.[`payload.${key}`];
}
</script>

<template>
  <div class="grid gap-4">
    <template v-for="field in fields" :key="field.key">
      <label
        v-if="field.control === 'checkbox'"
        class="flex h-8 cursor-pointer items-center justify-between gap-3 rounded-lg border bg-background/30 px-3"
      >
        <span class="text-[13px] font-medium text-foreground">{{ field.label }}</span>
        <Checkbox :model-value="flagValue(payload[field.key])" @update:model-value="update(field.key, $event)" />
      </label>

      <Field v-else :label="field.label" :error="error(field.key)">
        <Select
          v-if="field.control === 'select'"
          :model-value="inputValue(payload[field.key])"
          @update:model-value="update(field.key, $event)"
          :options="field.options ?? []"
        />

        <Textarea
          v-else-if="field.control === 'textarea'"
          :model-value="textValue(payload[field.key])"
          @update:model-value="update(field.key, $event)"
          :rows="field.rows ?? 4"
          :class="field.class"
          :placeholder="field.placeholder"
        />

        <Input
          v-else
          :model-value="inputValue(payload[field.key])"
          @update:model-value="update(field.key, $event)"
          :type="field.control"
          :step="field.step"
          :disabled="isFieldDisabled(field, payload)"
          :placeholder="field.placeholder"
        />
      </Field>
    </template>
  </div>
</template>
