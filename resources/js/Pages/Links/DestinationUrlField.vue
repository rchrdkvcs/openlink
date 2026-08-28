<script setup lang="ts">
import { Globe } from '@lucide/vue';
import { computed, ref, watch } from 'vue';

import { isLikelyUrl, originOf } from '@/lib/links';

const props = defineProps<{
  modelValue: string;
  error?: string;
  autofocus?: boolean;
}>();

const emit = defineEmits<{ 'update:modelValue': [value: string] }>();

const faviconFailed = ref(false);
watch(
  () => props.modelValue,
  () => (faviconFailed.value = false),
);

const faviconSrc = computed(() => {
  const origin = originOf(props.modelValue);

  return isLikelyUrl(props.modelValue) && origin && !faviconFailed.value
    ? route('favicons.show', { url: origin })
    : null;
});
</script>

<template>
  <div>
    <div
      class="flex items-center gap-3 rounded-xl border bg-surface px-4 py-1 transition-colors focus-within:border-accent/60 focus-within:ring-2 focus-within:ring-accent/25 hover:border-border-strong"
    >
      <img
        v-if="faviconSrc"
        :src="faviconSrc"
        alt=""
        class="h-5 w-5 shrink-0 rounded bg-elevated"
        @error="faviconFailed = true"
      />
      <Globe v-else class="h-5 w-5 shrink-0 text-faint" />
      <input
        :value="modelValue"
        class="h-12 flex-1 !border-0 !bg-transparent !p-0 !text-[15px] !shadow-none !ring-0"
        placeholder="Paste the destination URL…"
        :autofocus="autofocus"
        @input="emit('update:modelValue', ($event.target as HTMLInputElement).value)"
      />
    </div>
    <p v-if="error" class="mt-1.5 text-xs text-danger">{{ error }}</p>
  </div>
</template>
