<script setup lang="ts">
import Checkbox from '@/Components/ui/Checkbox.vue';
import Field from '@/Components/ui/Field.vue';
import Input from '@/Components/ui/Input.vue';
import Select from '@/Components/ui/Select.vue';
import Textarea from '@/Components/ui/Textarea.vue';

import type { PayloadDescriptors, PayloadField } from './types';

const props = defineProps<{
  type: string;
  descriptors: PayloadDescriptors;
  errors?: Record<string, string>;
}>();

const payload = defineModel<Record<string, any>>({ required: true });

const fields = () => props.descriptors[props.type]?.fields ?? props.descriptors.raw?.fields ?? [];

function error(key: string) {
  return props.errors?.[`payload.${key}`];
}

function disabled(field: PayloadField) {
  return field.disabledWhen ? payload.value[field.disabledWhen.key] === field.disabledWhen.value : false;
}
</script>

<template>
  <div class="grid gap-4">
    <template v-for="field in fields()" :key="field.key">
      <label
        v-if="field.control === 'checkbox'"
        class="flex items-center justify-between gap-3 rounded-md border bg-elevated/40 px-3 py-2.5"
      >
        <span class="text-[13px] font-medium text-foreground">{{ field.label }}</span>
        <Checkbox v-model="payload[field.key]" />
      </label>

      <Field v-else :label="field.label" :error="error(field.key)">
        <Select v-if="field.control === 'select'" v-model="payload[field.key]" :options="field.options ?? []" />

        <Textarea
          v-else-if="field.control === 'textarea'"
          v-model="payload[field.key]"
          :rows="field.rows ?? 4"
          :class="field.class"
          :placeholder="field.placeholder"
        />

        <Input
          v-else
          v-model="payload[field.key]"
          :type="field.control"
          :step="field.step"
          :disabled="disabled(field)"
          :placeholder="field.placeholder"
        />
      </Field>
    </template>
  </div>
</template>
