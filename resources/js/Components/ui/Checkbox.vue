<script setup lang="ts">
import { cn } from '@/lib/utils';

defineOptions({ inheritAttrs: false });

const model = defineModel<boolean>({ default: false });
</script>

<template>
  <input
    v-model="model"
    v-bind="$attrs"
    type="checkbox"
    :class="
      cn(
        'form-checkbox h-4 w-4 shrink-0 cursor-pointer rounded border-border-strong bg-surface p-0 text-accent shadow-none transition-colors focus:ring-2 focus:ring-accent/25 focus:ring-offset-0 disabled:cursor-not-allowed disabled:opacity-50',
        $attrs.class as string,
      )
    "
  />
</template>
