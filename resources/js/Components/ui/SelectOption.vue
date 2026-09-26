<script setup lang="ts">
import { Check } from '@lucide/vue';
import { SelectItem, SelectItemIndicator, SelectItemText } from 'radix-vue';

import { encodeSelectValue, type SelectValue } from '@/lib/select';

defineProps<{ value: SelectValue; disabled?: boolean }>();
</script>

<template>
  <SelectItem
    :value="encodeSelectValue(value)"
    :disabled="disabled"
    class="ui-menu-item relative flex min-h-8 select-none items-center gap-2 py-1.5 pe-8 ps-2.5 text-[13px] outline-none data-[disabled]:pointer-events-none data-[highlighted]:bg-elevated data-[highlighted]:text-foreground data-[disabled]:opacity-40"
  >
    <SelectItemText class="min-w-0 break-words [overflow-wrap:anywhere]"><slot /></SelectItemText>
    <SelectItemIndicator class="absolute end-2.5 flex items-center"><Check class="h-3.5 w-3.5" /></SelectItemIndicator>
  </SelectItem>
</template>
