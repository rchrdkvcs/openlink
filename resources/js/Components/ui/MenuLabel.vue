<script setup lang="ts">
import { DropdownMenuLabel } from 'radix-vue';
</script>

<template>
  <DropdownMenuLabel class="px-2.5 pb-1 pt-2 text-xs font-medium text-faint">
    <slot />
  </DropdownMenuLabel>
</template>
