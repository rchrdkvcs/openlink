<script setup lang="ts">
import { onMounted, ref } from 'vue';

import { controlVariants, type ControlSize } from '@/lib/controls';
import { cn } from '@/lib/utils';

defineOptions({ inheritAttrs: false });
withDefaults(defineProps<{ size?: ControlSize }>(), { size: 'md' });

// Native v-model on the inner element keeps Vue's number casting for type="number".
const model = defineModel<string | number | null>();

const input = ref<HTMLInputElement | null>(null);

// `autofocus` alone is ignored inside modals and drawers mounted after page load.
onMounted(() => {
  if (input.value?.hasAttribute('autofocus')) {
    input.value.focus();
  }
});

defineExpose({ focus: () => input.value?.focus(), el: input });
</script>

<template>
  <input ref="input" v-model="model" v-bind="$attrs" :class="cn(controlVariants({ size }), $attrs.class as string)" />
</template>
