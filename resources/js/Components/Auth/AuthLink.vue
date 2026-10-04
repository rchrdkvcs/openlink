<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
</script>

<template>
  <Link
    class="rounded-sm font-medium text-foreground underline-offset-4 hover:underline focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent/25"
  >
    <slot />
  </Link>
</template>
