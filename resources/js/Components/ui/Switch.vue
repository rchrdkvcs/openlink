<script setup lang="ts">
import { SwitchRoot, SwitchThumb } from 'radix-vue';

defineProps<{ modelValue: boolean; disabled?: boolean }>();
const emit = defineEmits<{ 'update:modelValue': [value: boolean] }>();
</script>

<template>
  <SwitchRoot
    :checked="modelValue"
    :disabled="disabled"
    :aria-label="($attrs['aria-label'] as string) ?? 'Enabled'"
    class="relative inline-flex h-5 w-9 shrink-0 items-center rounded-full bg-border-strong transition-colors duration-150 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent disabled:cursor-not-allowed disabled:opacity-50 data-[state=checked]:bg-accent"
    @update:checked="emit('update:modelValue', $event)"
  >
    <SwitchThumb
      class="ease-emphasized-out pointer-events-none block h-4 w-4 translate-x-0.5 rounded-full bg-white shadow-sm transition-transform duration-150 data-[state=checked]:translate-x-[18px]"
    />
  </SwitchRoot>
</template>
