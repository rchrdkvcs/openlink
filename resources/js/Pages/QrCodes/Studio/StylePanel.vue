<script setup lang="ts">
import SegmentedControl from '@/Components/ui/SegmentedControl.vue';
import Switch from '@/Components/ui/Switch.vue';

import { logoLabel, showsLogo } from '../qrForm';
import { EYE_STYLE_OPTIONS, STYLE_OPTIONS } from '../qrOptions';
import ColorField from './ColorField.vue';
import LogoField from './LogoField.vue';
import type { QrEditorForm } from './useQrCodeEditor';

defineProps<{
  form: QrEditorForm;
  hasSavedLogo: boolean;
}>();

const emit = defineEmits<{ pickLogo: [file: File | null]; removeLogo: [] }>();
</script>

<template>
  <div class="grid gap-5">
    <div class="grid gap-1.5">
      <span class="text-[13px] font-medium text-foreground">Modules</span>
      <SegmentedControl v-model="form.style" :options="STYLE_OPTIONS" label="Module style" class="w-full" />
      <span v-if="form.errors.style" class="text-xs text-danger">{{ form.errors.style }}</span>
    </div>

    <div class="grid gap-1.5">
      <span class="text-[13px] font-medium text-foreground">Corners</span>
      <SegmentedControl v-model="form.eye_style" :options="EYE_STYLE_OPTIONS" label="Eye style" class="w-full" />
      <span v-if="form.errors.eye_style" class="text-xs text-danger">{{ form.errors.eye_style }}</span>
    </div>

    <div class="grid grid-cols-2 gap-3">
      <ColorField v-model="form.foreground_color" label="Foreground" :error="form.errors.foreground_color" />
      <ColorField
        v-model="form.background_color"
        label="Background"
        empty-label="None"
        :disabled="form.background_transparent"
        :error="form.errors.background_color"
      />
    </div>

    <label class="flex cursor-pointer items-center justify-between gap-4">
      <span class="min-w-0">
        <span class="block text-[13px] font-medium text-foreground">Transparent background</span>
        <span class="mt-0.5 block text-xs text-faint">PNG and SVG exports stay see-through.</span>
      </span>
      <Switch v-model="form.background_transparent" aria-label="Transparent background" />
    </label>

    <LogoField
      :label="logoLabel(hasSavedLogo, form)"
      :file="form.logo"
      :removable="showsLogo(hasSavedLogo, form)"
      :error="form.errors.logo"
      @pick="emit('pickLogo', $event)"
      @remove="emit('removeLogo')"
    />
  </div>
</template>
