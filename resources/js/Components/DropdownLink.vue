<script setup lang="ts">
import { Link } from '@inertiajs/vue3';

defineProps<{
  href: string;
}>();
</script>

<template>
  <Link
    :href="href"
    class="ui-menu-item block w-full px-2.5 py-1.5 text-start text-[13px] text-muted transition-colors duration-100 hover:bg-elevated hover:text-foreground focus:bg-elevated focus:text-foreground focus:outline-none"
  >
    <slot />
  </Link>
</template>
