<script setup lang="ts">
import { Check, Copy } from '@lucide/vue';

defineProps<{
    copied: boolean;
}>();
</script>

<template>
    <!-- Both icons stacked in one grid cell; cross-fade with a slight scale so
         the swap reads as one morph instead of two objects trading places. -->
    <span class="grid h-3.5 w-3.5 shrink-0">
        <Copy
            class="col-start-1 row-start-1 h-3.5 w-3.5 transition-[transform,opacity] duration-150 ease-out"
            :class="copied ? 'scale-50 opacity-0' : 'scale-100 opacity-100'"
        />
        <Check
            class="col-start-1 row-start-1 h-3.5 w-3.5 text-success transition-[transform,opacity] duration-150 ease-out"
            :class="copied ? 'scale-100 opacity-100' : 'scale-50 opacity-0'"
        />
    </span>
</template>
