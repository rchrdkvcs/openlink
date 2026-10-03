<script setup lang="ts">
import { ImageOff, Upload } from '@lucide/vue';
import { ref, watch } from 'vue';

import Button from '@/Components/ui/Button.vue';
import Field from '@/Components/ui/Field.vue';

const props = defineProps<{
  label: string;
  file: File | null;
  removable: boolean;
  error?: string;
}>();

const emit = defineEmits<{ pick: [file: File | null]; remove: [] }>();

const input = ref<HTMLInputElement | null>(null);

watch(
  () => props.file,
  (file) => {
    if (!file && input.value) input.value.value = '';
  },
);

function onChange(event: Event) {
  emit('pick', (event.target as HTMLInputElement).files?.[0] ?? null);
}
</script>

<template>
  <Field label="Logo" hint="PNG, JPG or WebP up to 2 MB. Error correction is raised automatically." :error="error">
    <div class="flex items-center gap-2">
      <label
        class="inline-flex h-8 min-w-0 flex-1 cursor-pointer items-center justify-center gap-1.5 rounded-lg bg-elevated px-3 text-[13px] font-medium text-foreground transition-colors focus-within:ring-2 focus-within:ring-accent/40 hover:bg-border-strong"
      >
        <Upload class="h-3.5 w-3.5 shrink-0 text-muted" />
        <span class="truncate">{{ label }}</span>
        <input ref="input" type="file" accept="image/png,image/jpeg,image/webp" class="sr-only" @change="onChange" />
      </label>
      <Button v-if="removable" variant="ghost" type="button" @click="emit('remove')"><ImageOff /> Remove</Button>
    </div>
  </Field>
</template>
