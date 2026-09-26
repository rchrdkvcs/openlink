<script setup lang="ts">
import { computed, provide, useId } from 'vue';

import { fieldContextKey } from '@/lib/select';

const props = defineProps<{
  label?: string;
  hint?: string;
  error?: string;
}>();
const id = useId();
provide(
  fieldContextKey,
  computed(() => ({
    labelId: props.label ? `${id}-label` : undefined,
    descriptionId: props.error || props.hint ? `${id}-description` : undefined,
    invalid: Boolean(props.error),
  })),
);
</script>

<template>
  <label class="grid min-w-0 content-start gap-1.5">
    <span :id="`${id}-label`" v-if="label" class="text-[13px] font-medium text-foreground">{{ label }}</span>
    <slot />
    <span :id="`${id}-description`" role="alert" v-if="error" class="text-xs text-danger">{{ error }}</span>
    <span :id="`${id}-description`" v-else-if="hint" class="text-xs text-faint">{{ hint }}</span>
  </label>
</template>
