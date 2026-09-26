<script setup lang="ts">
import { controlVariants } from '@/lib/controls';
import { cn } from '@/lib/utils';

defineOptions({ inheritAttrs: false });

const model = defineModel<string | null>();
</script>

<template>
  <textarea
    v-model="model"
    v-bind="$attrs"
    :class="cn(controlVariants(), 'h-auto min-h-20 py-2 leading-relaxed', $attrs.class as string)"
  />
</template>
