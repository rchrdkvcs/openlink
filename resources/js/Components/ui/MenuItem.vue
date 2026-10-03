<script setup lang="ts">
import { Check } from '@lucide/vue';
import { DropdownMenuItem } from 'radix-vue';

withDefaults(
  defineProps<{
    icon?: unknown;
    destructive?: boolean;
    shortcut?: string;
    disabled?: boolean;
    checked?: boolean;
  }>(),
  { destructive: false, disabled: false, checked: false },
);

const emit = defineEmits<{ select: [event: Event] }>();
</script>

<template>
  <DropdownMenuItem
    :disabled="disabled"
    class="flex h-8 cursor-default select-none items-center gap-2.5 rounded-lg px-2.5 text-[13px] outline-none transition-colors duration-75 data-[disabled]:pointer-events-none data-[disabled]:opacity-40"
    :class="
      destructive ? 'text-danger data-[highlighted]:bg-danger/15' : 'text-foreground data-[highlighted]:bg-elevated'
    "
    @select="emit('select', $event)"
  >
    <component :is="icon" v-if="icon" class="h-3.5 w-3.5 shrink-0" :class="destructive ? '' : 'text-muted'" />
    <span class="min-w-0 flex-1 truncate"><slot /></span>
    <span v-if="shortcut" class="text-xs text-faint">{{ shortcut }}</span>
    <Check v-if="checked" class="h-3.5 w-3.5 shrink-0 text-accent" />
  </DropdownMenuItem>
</template>
