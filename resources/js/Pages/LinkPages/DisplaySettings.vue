<script setup lang="ts">
import { computed } from 'vue';

import type { LinkPageDisplay } from '@/Components/LinkPages/types';
import Checkbox from '@/Components/ui/Checkbox.vue';

const display = defineModel<LinkPageDisplay>({ required: true });
defineProps<{ error?: string }>();

const iconsOnly = computed(() => display.value.show_icons && !display.value.show_labels && !display.value.show_urls);
const settings = [
  { key: 'show_icons', label: 'Show icons' },
  { key: 'show_labels', label: 'Show labels' },
  { key: 'show_urls', label: 'Show short URLs' },
] as const;

function isLastVisible(key: keyof LinkPageDisplay): boolean {
  return display.value[key] && Object.values(display.value).filter(Boolean).length === 1;
}

function setIconsOnly(value: boolean) {
  display.value = { show_icons: true, show_labels: !value, show_urls: false };
}
</script>

<template>
  <div class="grid gap-5 p-4 sm:p-5">
    <label class="flex min-h-11 cursor-pointer items-center gap-3 text-sm font-medium">
      <Checkbox :model-value="iconsOnly" @update:model-value="setIconsOnly" />
      Icons only
    </label>
    <div class="grid gap-1 sm:grid-cols-3">
      <label v-for="setting in settings" :key="setting.key" class="flex min-h-11 items-center gap-2.5 text-[13px]">
        <Checkbox v-model="display[setting.key]" :disabled="isLastVisible(setting.key)" />
        {{ setting.label }}
      </label>
    </div>
    <p v-if="error" class="text-xs text-danger" role="alert">{{ error }}</p>
    <p class="text-xs leading-relaxed text-muted">
      URLs show the short address. Known sites use bundled icons; protected links and other sites use a generic icon.
      Icons-only links keep their labels for assistive technology and on hover.
    </p>
  </div>
</template>
