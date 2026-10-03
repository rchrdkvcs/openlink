<script setup lang="ts">
import Field from '@/Components/ui/Field.vue';
import Input from '@/Components/ui/Input.vue';
import Select from '@/Components/ui/Select.vue';

import { ERROR_CORRECTION_OPTIONS } from '../qrOptions';
import type { QrEditorForm } from './useQrCodeEditor';

defineProps<{ form: QrEditorForm }>();
</script>

<template>
  <div class="grid gap-5">
    <Field label="Margin" hint="Quiet zone around the code, in modules." :error="form.errors.margin">
      <Input v-model="form.margin" type="number" min="0" max="16" />
    </Field>
    <Field
      label="Error correction"
      hint="Higher levels survive damage and logos, at the cost of density."
      :error="form.errors.error_correction"
    >
      <Select v-model="form.error_correction" :options="ERROR_CORRECTION_OPTIONS" />
    </Field>
    <Field label="Default size" hint="Export size in pixels." :error="form.errors.size">
      <Input v-model="form.size" type="number" min="128" max="4096" />
    </Field>
  </div>
</template>
